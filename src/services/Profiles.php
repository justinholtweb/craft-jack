<?php

namespace justinholtweb\jack\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use justinholtweb\jack\models\Profile;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\records\ProfileRecord;

/**
 * The facts about the site: one global set, plus per-site overrides.
 *
 * {@see self::factMap()} is what the rest of the plugin actually uses. It returns the merged facts
 * *plus the derived ones* — `controller.display`, `site.name`, `withdrawal.address` — so that a
 * clause can write `{{ controller.display }}` without every clause having to reimplement "the
 * controller is usually but not always the company named above".
 *
 * Deriving in one place is what stops the imprint and the privacy policy disagreeing about the
 * company's address, which is the classic failure of hand-written legal pages.
 */
class Profiles extends Component
{
    private ?Profile $global = null;

    /** @var array<int, Profile|false> */
    private array $overrides = [];

    public function getGlobal(): Profile
    {
        if ($this->global !== null) {
            return $this->global;
        }

        /** @var ProfileRecord|null $record */
        $record = ProfileRecord::find()->where(['siteId' => null])->orderBy(['id' => SORT_ASC])->one();

        if (!$record) {
            $record = new ProfileRecord(['siteId' => null, 'facts' => '{}']);
            $record->save();
        }

        return $this->global = $this->toModel($record);
    }

    public function getOverride(int $siteId): ?Profile
    {
        if (array_key_exists($siteId, $this->overrides)) {
            return $this->overrides[$siteId] ?: null;
        }

        /** @var ProfileRecord|null $record */
        $record = ProfileRecord::find()->where(['siteId' => $siteId])->one();
        $profile = $record ? $this->toModel($record) : false;
        $this->overrides[$siteId] = $profile;

        return $profile ?: null;
    }

    /**
     * The raw merged facts for a site: global, with any per-site overrides laid on top.
     *
     * An override key with an empty value is *not* an override. Blanking a field on a site
     * override screen means "no answer here", which has to fall back to the global answer rather
     * than erase it — otherwise saving an English override with three fields filled in would wipe
     * the company address out of the English privacy policy.
     *
     * @return array<string, mixed>
     */
    public function factsForSite(?int $siteId = null): array
    {
        $facts = $this->getGlobal()->facts;

        if ($siteId !== null && ($override = $this->getOverride($siteId))) {
            foreach ($override->facts as $key => $value) {
                if ($value !== null && $value !== '') {
                    $facts[$key] = $value;
                }
            }
        }

        return $facts;
    }

    /**
     * Facts as the compiler wants them: merged, defaulted from the site, and with the derived
     * keys added.
     *
     * @return array<string, string>
     */
    public function factMap(?int $siteId = null, string $language = 'de'): array
    {
        $facts = $this->factsForSite($siteId);
        $site = $siteId !== null
            ? Craft::$app->getSites()->getSiteById($siteId)
            : Craft::$app->getSites()->getPrimarySite();

        // Defaults from Craft. A site that has not told Jack its name has told Craft.
        if (($facts['site.name'] ?? '') === '' && $site) {
            $facts['site.name'] = $site->getName();
        }

        if (($facts['site.url'] ?? '') === '' && $site) {
            $facts['site.url'] = rtrim((string)$site->getBaseUrl(), '/');
        }

        foreach ($this->defaults() as $key => $value) {
            if (($facts[$key] ?? '') === '') {
                $facts[$key] = $value;
            }
        }

        return $facts + $this->derived($facts, $language);
    }

    /**
     * Facts computed from other facts.
     *
     * @param array<string, mixed> $facts
     * @return array<string, string>
     */
    private function derived(array $facts, string $language): array
    {
        $german = str_starts_with($language, 'de');

        $addressLines = array_values(array_filter([
            $facts['company.name'] ?? '',
            $facts['company.addressExtra'] ?? '',
            $facts['company.street'] ?? '',
            trim(($facts['company.postalCode'] ?? '') . ' ' . ($facts['company.city'] ?? '')),
            $facts['company.country'] ?? '',
        ], static fn($line) => trim((string)$line) !== ''));

        $companyBlock = implode("<br>\n", array_map(
            static fn($line) => htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8'),
            $addressLines,
        ));

        // The controller is the company unless somebody said otherwise, which is the case that
        // every clause would otherwise have to spell out for itself.
        $sameAsCompany = !isset($facts['controller.sameAsCompany']) || $facts['controller.sameAsCompany'];
        $controller = $sameAsCompany
            ? $companyBlock
            : trim(nl2br(htmlspecialchars(
                trim(($facts['controller.name'] ?? '') . "\n" . ($facts['controller.address'] ?? '')),
                ENT_QUOTES,
                'UTF-8',
            )));

        $withdrawalAddress = trim((string)($facts['terms.returnsAddress'] ?? ''));
        $withdrawalBlock = $withdrawalAddress !== ''
            ? nl2br(htmlspecialchars($withdrawalAddress, ENT_QUOTES, 'UTF-8'))
            : $companyBlock;

        if (($facts['contact.email'] ?? '') !== '') {
            $email = htmlspecialchars((string)$facts['contact.email'], ENT_QUOTES, 'UTF-8');
            $withdrawalBlock .= "<br>\n" . ($german ? 'E-Mail: ' : 'Email: ') . $email;
        }

        $statusText = match ($facts['accessibility.status'] ?? 'partial') {
            'full' => $german ? 'vollständig konform' : 'fully conformant',
            'none' => $german ? 'nicht konform' : 'not conformant',
            default => $german ? 'teilweise konform' : 'partially conformant',
        };

        return [
            'company.address' => $companyBlock,
            'controller.display' => $controller !== '' ? $controller : $companyBlock,
            'withdrawal.address' => $withdrawalBlock,
            'accessibility.statusText' => $statusText,
            'accessibility.contactEmail' => (string)($facts['accessibility.feedbackEmail'] ?? $facts['contact.email'] ?? ''),
            'document.updated' => Craft::$app->getFormatter()->asDate('now', 'long'),
            'library.version' => Library::VERSION,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        $defaults = [];

        foreach (Plugin::getInstance()->library->facts() as $key => $definition) {
            if (isset($definition['default'])) {
                $defaults[$key] = $definition['default'];
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $facts
     */
    public function saveGlobal(array $facts): bool
    {
        $profile = $this->getGlobal();
        $record = ProfileRecord::findOne($profile->id) ?? new ProfileRecord(['siteId' => null]);
        $record->facts = Json::encode($this->clean($facts));

        if (!$record->save()) {
            return false;
        }

        $this->global = null;
        $this->invalidate();

        return true;
    }

    /**
     * @param array<string, mixed> $facts
     */
    public function saveOverride(int $siteId, array $facts): bool
    {
        $facts = $this->clean($facts);
        $record = ProfileRecord::findOne(['siteId' => $siteId]);

        if ($facts === []) {
            // An override map with nothing in it is not an override; keeping the row would make
            // "does this site differ" a question you cannot answer by looking.
            $record?->delete();
            unset($this->overrides[$siteId]);
            $this->invalidate();

            return true;
        }

        $record ??= new ProfileRecord(['siteId' => $siteId]);
        $record->facts = Json::encode($facts);

        if (!$record->save()) {
            return false;
        }

        unset($this->overrides[$siteId]);
        $this->invalidate();

        return true;
    }

    /**
     * How much of the profile is filled in, as a fraction, counting only facts some clause in the
     * library could actually ask for.
     */
    public function completeness(?int $siteId = null): float
    {
        $needed = [];

        foreach (Plugin::getInstance()->library->clauses() as $clause) {
            foreach ($clause->requires as $key) {
                $needed[$key] = true;
            }
        }

        if ($needed === []) {
            return 1.0;
        }

        $facts = $this->factsForSite($siteId);
        $filled = 0;

        foreach (array_keys($needed) as $key) {
            if (trim((string)($facts[$key] ?? '')) !== '') {
                $filled++;
            }
        }

        return $filled / count($needed);
    }

    /**
     * @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    private function clean(array $facts): array
    {
        $known = Plugin::getInstance()->library->facts();
        $clean = [];

        foreach ($facts as $key => $value) {
            if (!isset($known[$key])) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            // Every fact is one line or one paragraph of text; an array from a tampered post would
            // only break the next compile.
            if ($value === '' || $value === null || !is_scalar($value)) {
                continue;
            }

            // Links end up in an href. Only http(s) is kept, so a profile editor cannot put a
            // `javascript:` link on every public page.
            if (($known[$key]['type'] ?? '') === 'url' && !Compiler::isWebUrl((string)$value)) {
                continue;
            }

            $clean[$key] = ($known[$key]['type'] ?? '') === 'bool' ? (bool)$value : $value;
        }

        return $clean;
    }

    private function toModel(ProfileRecord $record): Profile
    {
        return new Profile([
            'id' => $record->id,
            'siteId' => $record->siteId,
            'facts' => $record->facts ? (Json::decodeIfJson($record->facts) ?: []) : [],
            'uid' => $record->uid,
        ]);
    }

    /** Facts changed, so every compiled document that used them is now a lie waiting to be found. */
    private function invalidate(): void
    {
        Plugin::getInstance()->documents->onFactsChanged();
    }
}
