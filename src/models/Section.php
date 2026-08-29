<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * One rendered section of a compiled document: a heading, some HTML, and where it came from.
 *
 * Sections keep their provenance — `clauseKey`, `clauseVersion`, `source` — after rendering,
 * which is what makes the staleness check and the override screen possible. A document that
 * remembered only its HTML could tell you it had changed but never what to do about it.
 */
class Section extends Model
{
    public const SOURCE_LIBRARY = 'library';
    public const SOURCE_GENERATED = 'generated';
    public const SOURCE_OVERRIDE = 'override';
    public const SOURCE_CUSTOM = 'custom';

    public string $clauseKey = '';
    public string $clauseVersion = '';
    public string $heading = '';
    public string $html = '';
    public int $order = 500;
    public string $source = self::SOURCE_LIBRARY;
    public ?string $citation = null;
    public ?string $note = null;

    /**
     * Fact keys the clause wanted and the profile did not have.
     *
     * @var string[]
     */
    public array $missingFacts = [];

    /**
     * Stable anchor id, used for the table of contents and for deep links from a cookie banner.
     *
     * Lower-cased and hyphenated rather than a straight copy of the clause key: these end up in
     * URLs that people paste into emails and tickets, and a fragment that is only sometimes
     * capitalised is one somebody will eventually get wrong.
     */
    public function anchor(): string
    {
        $key = str_replace(['.', ':', '_'], '-', $this->clauseKey);
        $key = preg_replace('~(?<!^)[A-Z]~', '-$0', $key) ?? $key;

        return 'jack-' . strtolower(preg_replace('~-+~', '-', $key) ?? $key);
    }

    public function isOverridden(): bool
    {
        return $this->source === self::SOURCE_OVERRIDE;
    }
}
