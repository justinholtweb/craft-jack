<?php

namespace justinholtweb\jack\helpers;

/**
 * A cookie name with a wildcard in it — `_ga_*` — on its way into a consent manager's config.
 *
 * Klaro and CookieConsent both match a cookie to delete by exact name when given a string, and by
 * pattern only when given a JavaScript `RegExp`. JSON has no RegExp, so the value has two shapes:
 * in a JSON download it is the pattern's source as a string (`^_ga_.*$`), and in the `<script>`
 * {@see \justinholtweb\jack\services\ConsentExport::script()} writes it is a real regex literal.
 *
 * Only names made of the characters cookie names are actually made of become patterns, which is
 * what makes the literal safe to write into a script: there is no `/` to close it early.
 */
final class CookiePattern implements \JsonSerializable
{
    /** What a cookie name may contain to be turned into a pattern. */
    public const NAME_PATTERN = '/^[A-Za-z0-9_.\-*]+$/';

    private function __construct(public readonly string $source)
    {
    }

    /**
     * A pattern for a wildcard name, or the name unchanged when it has no wildcard (or contains
     * something a pattern should not be built from).
     */
    public static function fromName(string $name): self|string
    {
        if (!str_contains($name, '*') || preg_match(self::NAME_PATTERN, $name) !== 1) {
            return $name;
        }

        return new self('^' . str_replace('\*', '.*', preg_quote($name, '/')) . '$');
    }

    public function jsonSerialize(): string
    {
        return $this->source;
    }

    /** The pattern as a JavaScript regex literal. */
    public function toJs(): string
    {
        return '/' . $this->source . '/';
    }
}
