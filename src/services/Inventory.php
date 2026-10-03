<?php

namespace justinholtweb\jack\services;

use craft\base\Component;
use craft\helpers\Json;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\records\ServiceRecord;

/**
 * What this site hands data to.
 *
 * The inventory is the single answer to "who are the recipients", and everything downstream is a
 * projection of it: the privacy policy's service sections, the cookie table, the transfer
 * paragraph, and what the detector compares its findings against. Nothing else in Jack keeps its
 * own idea of which third parties are in play.
 *
 * Entries are global rather than per-site. The processors a business uses are a property of the
 * business, and a German and an English site are usually one business — the *language* differs,
 * not the recipients. A genuinely different brand on the same install overrides the profile facts
 * and, if it must, disables entries it does not use.
 */
class Inventory extends Component
{
    /** @var ServiceEntry[]|null keyed by service key */
    private ?array $entries = null;

    /**
     * @return ServiceEntry[]
     */
    public function all(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $entries = [];

        /** @var ServiceRecord[] $records */
        $records = ServiceRecord::find()->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC])->all();

        foreach ($records as $record) {
            $entry = $this->toModel($record);
            $entries[$entry->serviceKey] = $entry;
        }

        return $this->entries = $entries;
    }

    /**
     * Entries that are switched on, in the order they should appear in a document: first-party
     * things first (the site's own logs and forms), then everyone else grouped by category.
     *
     * @return ServiceEntry[]
     */
    public function enabled(): array
    {
        $entries = array_filter($this->all(), static fn(ServiceEntry $entry) => $entry->enabled);

        uasort($entries, static function(ServiceEntry $a, ServiceEntry $b) {
            return [!$a->isFirstParty(), $a->category(), $a->sortOrder, $a->label()]
                <=> [!$b->isFirstParty(), $b->category(), $b->sortOrder, $b->label()];
        });

        return $entries;
    }

    /**
     * Enabled entries grouped by category, ready for the compiler.
     *
     * @return array<string, ServiceEntry[]>
     */
    public function enabledByCategory(): array
    {
        $grouped = [];

        foreach ($this->enabled() as $entry) {
            $grouped[$entry->category()][] = $entry;
        }

        return $grouped;
    }

    public function get(string $serviceKey): ?ServiceEntry
    {
        return $this->all()[$serviceKey] ?? null;
    }

    public function has(string $serviceKey): bool
    {
        return isset($this->all()[$serviceKey]);
    }

    public function isEnabled(string $serviceKey): bool
    {
        return $this->all()[$serviceKey]->enabled ?? false;
    }

    /**
     * Whether any enabled entry sends data out of the EEA.
     *
     * The transfer clause turns on this and nothing else: a site that uses only European
     * processors should not carry two paragraphs about American surveillance law.
     */
    public function hasTransfers(): bool
    {
        foreach ($this->enabled() as $entry) {
            if ($entry->transfer() !== ServiceEntry::TRANSFER_NONE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any enabled entry is in one of these categories.
     *
     * @param string[] $categories
     */
    public function hasCategory(array $categories): bool
    {
        foreach ($this->enabled() as $entry) {
            if (in_array($entry->category(), $categories, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cookies declared by enabled entries, flattened for the cookie table.
     *
     * @return array<int, array{service: string, category: string, essential: bool, name: string, duration: string}>
     */
    public function cookies(?string $language = null): array
    {
        $rows = [];

        foreach ($this->enabled() as $entry) {
            foreach ($entry->cookiesFor($language) as $cookie) {
                $rows[] = [
                    'service' => $entry->label($language),
                    'category' => $entry->category(),
                    'essential' => $entry->isEssential(),
                    'name' => $cookie['name'],
                    'duration' => $cookie['duration'],
                ];
            }
        }

        return $rows;
    }

    public function save(ServiceEntry $entry): bool
    {
        if (!$entry->validate()) {
            return false;
        }

        $record = $entry->id ? ServiceRecord::findOne($entry->id) : ServiceRecord::findOne(['serviceKey' => $entry->serviceKey]);
        $record ??= new ServiceRecord();

        $record->serviceKey = $entry->serviceKey;
        $record->enabled = $entry->enabled;
        $record->custom = $entry->custom;
        $record->sortOrder = $entry->sortOrder;
        $record->overrides = Json::encode($this->cleanOverrides($entry->overrides));

        if (!$record->save()) {
            $entry->addErrors($record->getErrors());

            return false;
        }

        $entry->id = $record->id;
        $entry->uid = $record->uid;
        $this->invalidate();

        return true;
    }

    /**
     * Adds a registry service to the inventory, or switches it back on if it is already there.
     */
    public function add(string $serviceKey): ServiceEntry|false
    {
        $existing = $this->get($serviceKey);

        if ($existing) {
            $existing->enabled = true;

            return $this->save($existing) ? $existing : false;
        }

        $definition = Plugin::getInstance()->library->service($serviceKey);

        $entry = new ServiceEntry([
            'serviceKey' => $serviceKey,
            'enabled' => true,
            'custom' => $definition === null,
            'sortOrder' => count($this->all()),
            'definition' => $definition ?? [],
        ]);

        return $this->save($entry) ? $entry : false;
    }

    public function delete(string $serviceKey): bool
    {
        $entry = $this->get($serviceKey);

        if (!$entry) {
            return true;
        }

        // Session cookies and server log files happen whether or not anybody wants them in the
        // document, so they cannot be removed — only a lie would follow.
        if ($entry->isAlwaysOn()) {
            return false;
        }

        ServiceRecord::deleteAll(['serviceKey' => $serviceKey]);
        $this->invalidate();

        return true;
    }

    /**
     * Registry entries that are not yet in the inventory, grouped by category — the "add a
     * service" picker.
     *
     * @return array<string, array<string, string>>
     */
    public function available(): array
    {
        $inInventory = array_keys($this->all());
        $available = [];

        foreach (Plugin::getInstance()->library->services() as $key => $definition) {
            if (in_array($key, $inInventory, true)) {
                continue;
            }

            $available[$definition['category'] ?? 'functional'][$key] = Plugin::getInstance()->library->serviceLabel($key);
        }

        ksort($available);

        foreach ($available as &$services) {
            asort($services);
        }

        return $available;
    }

    /**
     * Human labels for the categories, in the order they should read in a document.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'functional' => 'Site functions',
            'infrastructure' => 'Infrastructure',
            'security' => 'Security',
            'payment' => 'Payments',
            'email' => 'Email and newsletters',
            'support' => 'Support and chat',
            'booking' => 'Bookings',
            'media' => 'Embedded media',
            'fonts' => 'Fonts and icons',
            'maps' => 'Maps',
            'analytics' => 'Analytics',
            'advertising' => 'Advertising',
            'social' => 'Social media',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return self::categories()[$category] ?? ucfirst($category);
    }

    private function toModel(ServiceRecord $record): ServiceEntry
    {
        $overrides = $record->overrides ? (Json::decodeIfJson($record->overrides) ?: []) : [];

        return new ServiceEntry([
            'id' => $record->id,
            'serviceKey' => $record->serviceKey,
            'enabled' => (bool)$record->enabled,
            'custom' => (bool)$record->custom,
            'sortOrder' => (int)$record->sortOrder,
            'overrides' => is_array($overrides) ? $overrides : [],
            'definition' => Plugin::getInstance()->library->service($record->serviceKey) ?? [],
            'uid' => $record->uid,
        ]);
    }

    /**
     * Drops override keys that match the library, so that "overridden" keeps meaning something.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function cleanOverrides(array $overrides): array
    {
        return array_filter(
            $overrides,
            static fn($value) => $value !== null && $value !== '' && $value !== [],
        );
    }

    private function invalidate(): void
    {
        $this->entries = null;
        Plugin::getInstance()->documents->onFactsChanged();
    }
}
