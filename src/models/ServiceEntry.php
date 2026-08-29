<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * One entry in the inventory: a third party this site actually hands data to.
 *
 * An entry is a *registry key plus the site's answers*. Everything factual — who the provider is,
 * where they are, what the data is for — comes from `src/library/services.php` and stays there, so
 * that when Meta moves its Irish entity, one file changes and every site's privacy policy is
 * right again. What lives on the entry is only what differs per site: whether it is switched on,
 * and the handful of judgements a lawyer might make differently (legal basis, whether it is
 * strictly necessary, an extra sentence).
 *
 * Entries with no registry definition are *custom* — a service Jack has never heard of, described
 * by hand. They carry the same fields, filled in by a person.
 */
class ServiceEntry extends Model
{
    public const BASIS_CONSENT = 'consent';
    public const BASIS_CONTRACT = 'contract';
    public const BASIS_INTEREST = 'interest';
    public const BASIS_OBLIGATION = 'obligation';

    public const TRANSFER_NONE = 'none';
    public const TRANSFER_ADEQUACY = 'adequacy';
    public const TRANSFER_DPF = 'dpf';
    public const TRANSFER_SCC = 'scc';

    public ?int $id = null;
    public string $serviceKey = '';
    public bool $enabled = true;
    public bool $custom = false;
    public int $sortOrder = 0;

    /**
     * Fields overriding the registry definition, or holding it outright for a custom entry.
     *
     * Only keys present here override; a null or missing key means "use the library's answer".
     *
     * @var array<string, mixed>
     */
    public array $overrides = [];

    /**
     * The merged registry definition. Set by {@see justinholtweb\jack\services\Inventory}.
     *
     * @var array<string, mixed>
     */
    public array $definition = [];

    public ?string $uid = null;

    public function defineRules(): array
    {
        return [
            [['serviceKey'], 'required'],
            [['serviceKey'], 'string', 'max' => 128],
            [['serviceKey'], 'match', 'pattern' => '/^[a-z0-9_]+$/', 'message' => 'Service keys may contain lowercase letters, numbers and underscores.'],
            [['enabled', 'custom'], 'boolean'],
            [['sortOrder'], 'integer'],
            [['overrides'], 'safe'],
        ];
    }

    /** A merged field: the site's override if it set one, otherwise the library's answer. */
    public function get(string $field, mixed $default = null): mixed
    {
        if (array_key_exists($field, $this->overrides) && $this->overrides[$field] !== null && $this->overrides[$field] !== '') {
            return $this->overrides[$field];
        }

        return $this->definition[$field] ?? $default;
    }

    /**
     * A merged field that is language-keyed, resolved down to a string.
     */
    public function text(string $field, string $language): string
    {
        $value = $this->get($field);

        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            return '';
        }

        return $value[$language] ?? $value[substr($language, 0, 2)] ?? $value['en'] ?? (reset($value) ?: '');
    }

    /**
     * The service's name in a language.
     *
     * A brand is a brand — "Stripe" is "Stripe" in every language, so most registry labels are a
     * plain string. The generic first-party entries are not brands, and "Server log files" in the
     * middle of a German privacy policy is the sort of detail that tells a reader the document
     * was generated and not read.
     */
    public function label(?string $language = null): string
    {
        $label = $this->get('label');

        if (is_array($label)) {
            return $this->text('label', $language ?? 'en') ?: $this->serviceKey;
        }

        return (string)($label ?: $this->serviceKey);
    }

    public function category(): string
    {
        return (string)$this->get('category', 'functional');
    }

    public function legalBasis(): string
    {
        return (string)$this->get('legalBasis', self::BASIS_CONSENT);
    }

    public function transfer(): string
    {
        return (string)$this->get('transfer', self::TRANSFER_NONE);
    }

    public function isEssential(): bool
    {
        return (bool)$this->get('essential', false);
    }

    public function isFirstParty(): bool
    {
        return (bool)$this->get('firstParty', false);
    }

    /**
     * Whether the entry can be switched off at all.
     *
     * Session cookies and server logs are not a choice — the site cannot run without them — so
     * they stay in the inventory permanently rather than being something an editor can quietly
     * remove from the privacy policy while it keeps happening.
     */
    public function isAlwaysOn(): bool
    {
        return (bool)$this->get('alwaysOn', false);
    }

    /**
     * The cookies this service sets, as declared.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cookies(): array
    {
        $cookies = $this->get('cookies', []);

        return is_array($cookies) ? $cookies : [];
    }

    /**
     * The same list with every value resolved down to a string in one language.
     *
     * Durations are language maps because "Sitzung" and "session" are not the same word, and a
     * German cookie table reading "Sitzung / session" is the tell that nobody read the output.
     *
     * @return array<int, array{name: string, duration: string}>
     */
    public function cookiesFor(?string $language = null): array
    {
        $language ??= 'en';

        return array_map(static function(array $cookie) use ($language): array {
            $duration = $cookie['duration'] ?? '';

            if (is_array($duration)) {
                $duration = $duration[$language]
                    ?? $duration[substr($language, 0, 2)]
                    ?? $duration['en']
                    ?? (reset($duration) ?: '');
            }

            return [
                'name' => (string)($cookie['name'] ?? ''),
                'duration' => (string)$duration,
            ];
        }, $this->cookies());
    }

    public function isKnown(): bool
    {
        return $this->definition !== [];
    }
}
