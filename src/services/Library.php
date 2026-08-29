<?php

namespace justinholtweb\jack\services;

use craft\base\Component;
use justinholtweb\jack\models\Clause;

/**
 * The clause library and the processor registry, loaded from `src/library`.
 *
 * Read-only and versioned. Nothing writes here — the whole point of shipping legal text as files
 * inside the plugin is that a `composer update` is how a site's texts get better, and a stamped
 * version is how a site finds out that they did.
 *
 * {@see self::VERSION} is a date rather than a semantic version because that is what the question
 * actually is: not "is this a breaking change" but "how old is the law in this document".
 */
class Library extends Component
{
    /** Date the bundled texts were last reviewed. */
    public const VERSION = '2026-08-18';

    /** @var Clause[]|null */
    private ?array $clauses = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $services = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $facts = null;

    /** @var array<string, array<string, array<string, string>>>|null */
    private ?array $templates = null;

    /**
     * Every clause in the library, keyed by clause key and ordered by `order`.
     *
     * @return Clause[]
     */
    public function clauses(): array
    {
        if ($this->clauses !== null) {
            return $this->clauses;
        }

        $clauses = [];

        foreach (glob($this->path('clauses') . '/*.php') ?: [] as $file) {
            foreach (require $file as $definition) {
                $clause = Clause::fromDefinition($definition);

                if ($clause->key === '') {
                    continue;
                }

                $clauses[$clause->key] = $clause;
            }
        }

        uasort($clauses, static fn(Clause $a, Clause $b) => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $this->clauses = $clauses;
    }

    public function clause(string $key): ?Clause
    {
        return $this->clauses()[$key] ?? null;
    }

    /**
     * Clauses that could appear in this document type under this jurisdiction.
     *
     * "Could" — conditions on the profile and the inventory are the compiler's business, not the
     * library's, because they need facts the library has never heard of.
     *
     * @return Clause[]
     */
    public function clausesFor(string $documentType, string $jurisdiction = 'de'): array
    {
        return array_filter(
            $this->clauses(),
            static fn(Clause $clause) => $clause->appliesToType($documentType)
                && $clause->appliesToJurisdiction($jurisdiction),
        );
    }

    /**
     * The processor registry.
     *
     * @return array<string, array<string, mixed>>
     */
    public function services(): array
    {
        return $this->services ??= require $this->path('services.php');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function service(string $key): ?array
    {
        return $this->services()[$key] ?? null;
    }

    /**
     * A registry entry's name in one language.
     *
     * Most labels are a plain string — a brand is a brand — but the generic first-party entries
     * are language maps, so nothing may read `$definition['label']` directly.
     */
    public function serviceLabel(string $key, string $language = 'en'): string
    {
        $label = $this->service($key)['label'] ?? null;

        if (is_array($label)) {
            return $label[$language] ?? $label[substr($language, 0, 2)] ?? $label['en'] ?? (reset($label) ?: $key);
        }

        return (string)($label ?: $key);
    }

    /**
     * Registry keys grouped by category, for the inventory picker.
     *
     * @return array<string, array<string, string>>
     */
    public function servicesByCategory(): array
    {
        $grouped = [];

        foreach ($this->services() as $key => $definition) {
            $grouped[$definition['category'] ?? 'functional'][$key] = $this->serviceLabel($key);
        }

        ksort($grouped);

        foreach ($grouped as &$services) {
            asort($services);
        }

        return $grouped;
    }

    /**
     * The fact schema.
     *
     * @return array<string, array<string, mixed>>
     */
    public function facts(): array
    {
        return $this->facts ??= require $this->path('facts.php');
    }

    /**
     * Sentence templates for the generated sections.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function templates(): array
    {
        return $this->templates ??= require $this->path('templates.php');
    }

    /**
     * One template string in one language, or an empty string.
     */
    public function template(string $group, string $key, string $language): string
    {
        $value = $this->templates()[$group][$key] ?? null;

        if (!is_array($value)) {
            return '';
        }

        return $value[$language] ?? $value[substr($language, 0, 2)] ?? $value['en'] ?? (reset($value) ?: '');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fact(string $key): ?array
    {
        return $this->facts()[$key] ?? null;
    }

    /**
     * Fact keys grouped by the tab they belong on, in declaration order.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function factsByGroup(): array
    {
        $grouped = [];

        foreach ($this->facts() as $key => $definition) {
            $grouped[$definition['group'] ?? 'company'][$key] = $definition;
        }

        return $grouped;
    }

    /**
     * The version of every clause that could appear in a document type — the shape a compiled
     * document's stamp is compared against.
     *
     * @return array<string, string>
     */
    public function stampFor(string $documentType, string $jurisdiction = 'de'): array
    {
        $stamp = [];

        foreach ($this->clausesFor($documentType, $jurisdiction) as $clause) {
            $stamp[$clause->key] = $clause->version;
        }

        return $stamp;
    }

    private function path(string $suffix): string
    {
        return dirname(__DIR__) . '/library/' . $suffix;
    }
}
