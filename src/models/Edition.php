<?php

namespace justinholtweb\jack\models;

use justinholtweb\jack\elements\Document;

/**
 * What each edition allows.
 *
 * Static and pure, taking `$isPro` rather than reaching for the plugin, so that "what does Pro
 * buy" has one answer written down in one place and can be tested without an application.
 *
 * The split follows one rule: **Lite gets you legal, Pro gets you maintained.** The two documents
 * German law actually requires of a website — an imprint and a privacy policy — compile fully on
 * Lite, in one language, from the whole clause library and the whole processor registry. What Pro
 * adds is everything that keeps them true over time and across sites: the other document types,
 * every site and language, editing the text, watching for services nobody declared, and the diff
 * that says what changed when the library moves.
 *
 * Documents already compiled do not stop rendering when a licence lapses. A privacy policy that
 * 404s because of a billing problem is a worse outcome than any it could prevent.
 */
class Edition
{
    /** Document types Lite can create. */
    public const LITE_DOCUMENT_TYPES = ['imprint', 'privacy'];

    /**
     * @return string[]
     */
    public static function documentTypes(bool $isPro): array
    {
        return $isPro ? Document::TYPES : self::LITE_DOCUMENT_TYPES;
    }

    public static function allowsDocumentType(string $type, bool $isPro): bool
    {
        return in_array($type, self::documentTypes($isPro), true);
    }

    /** Compiling a document for anything but the primary site. */
    public static function allowsMultiSite(bool $isPro): bool
    {
        return $isPro;
    }

    /** Rewriting a bundled clause's heading or body for one document. */
    public static function allowsOverrides(bool $isPro): bool
    {
        return $isPro;
    }

    /** Adding a section the library does not have. */
    public static function allowsCustomSections(bool $isPro): bool
    {
        return $isPro;
    }

    /** Scanning templates, plugins and the environment for undeclared services. */
    public static function allowsDetection(bool $isPro): bool
    {
        return $isPro;
    }

    /** Version history and the diff between two published versions. */
    public static function allowsHistory(bool $isPro): bool
    {
        return $isPro;
    }

    /**
     * Seeing *what* changed when the clause library moves.
     *
     * Lite is still told a document is out of date — being kept in the dark about that would make
     * the free edition worse than useless, since the whole risk this plugin addresses is a legal
     * text nobody noticed had aged.
     */
    public static function allowsChangeDetail(bool $isPro): bool
    {
        return $isPro;
    }

    /** The console commands, and therefore the CI check. */
    public static function allowsConsole(bool $isPro): bool
    {
        return $isPro;
    }
}
