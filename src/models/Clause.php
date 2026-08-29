<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * One clause of the library, as loaded from `src/library/clauses/*.php`.
 *
 * A clause is a *rule for producing a section*, not the section itself: it knows which document
 * types it belongs to, when it applies, which facts it needs and what it says in each language.
 * {@see justinholtweb\jack\services\Compiler} turns clauses into {@see Section}s; nothing else
 * ever renders one.
 */
class Clause extends Model
{
    public string $key = '';

    /** @var string[] */
    public array $documentTypes = [];

    /**
     * Jurisdictions this clause is written for. Empty means all of them.
     *
     * @var string[]
     */
    public array $jurisdictions = [];

    public int $order = 500;

    /** Semantic version of *this clause's text*, bumped whenever the wording changes. */
    public string $version = '1.0.0';

    /** ISO date the wording last changed. */
    public string $updated = '';

    /** Statute or case the wording comes from, shown to editors and never to visitors. */
    public ?string $citation = null;

    /**
     * Conditions that must all hold for the clause to appear.
     *
     * @var array<string, mixed>
     */
    public array $when = [];

    /**
     * Conditions that suppress the clause if any of them holds.
     *
     * @var array<string, mixed>
     */
    public array $unless = [];

    /**
     * Fact keys the clause needs. A missing one is a warning on the document, not an exception.
     *
     * @var string[]
     */
    public array $requires = [];

    /** Name of a Compiler generator that builds the body, for clauses that are not written out. */
    public ?string $generator = null;

    /** @var array<string, string> language => heading */
    public array $heading = [];

    /** @var array<string, string> language => HTML */
    public array $body = [];

    /**
     * Editor-facing note explaining something non-obvious about the clause.
     *
     * @var array<string, string>
     */
    public array $note = [];

    /** Set for clauses that came from the library rather than from a person. */
    public bool $bundled = true;

    public static function fromDefinition(array $definition): self
    {
        $clause = new self();
        $clause->key = (string)($definition['key'] ?? '');
        $clause->documentTypes = $definition['documentTypes'] ?? [];
        $clause->jurisdictions = $definition['jurisdictions'] ?? [];
        $clause->order = (int)($definition['order'] ?? 500);
        $clause->version = (string)($definition['version'] ?? '1.0.0');
        $clause->updated = (string)($definition['updated'] ?? '');
        $clause->citation = $definition['citation'] ?? null;
        $clause->when = $definition['when'] ?? [];
        $clause->unless = $definition['unless'] ?? [];
        $clause->requires = $definition['requires'] ?? [];
        $clause->generator = $definition['generator'] ?? null;
        $clause->heading = $definition['heading'] ?? [];
        $clause->body = $definition['body'] ?? [];
        $clause->note = $definition['note'] ?? [];

        return $clause;
    }

    /** Heading in the requested language, falling back to English and then to any language. */
    public function heading(string $language): string
    {
        return $this->pick($this->heading, $language);
    }

    public function body(string $language): string
    {
        return $this->pick($this->body, $language);
    }

    public function note(string $language): ?string
    {
        return $this->pick($this->note, $language) ?: null;
    }

    public function appliesToType(string $documentType): bool
    {
        return in_array($documentType, $this->documentTypes, true);
    }

    public function appliesToJurisdiction(string $jurisdiction): bool
    {
        return $this->jurisdictions === [] || in_array($jurisdiction, $this->jurisdictions, true);
    }

    /**
     * @param array<string, string> $values
     */
    private function pick(array $values, string $language): string
    {
        if (isset($values[$language])) {
            return $values[$language];
        }

        // A two-letter fallback covers `de-CH` reaching `de`, which is the common multi-site shape.
        $short = substr($language, 0, 2);

        return $values[$short] ?? $values['en'] ?? (reset($values) ?: '');
    }
}
