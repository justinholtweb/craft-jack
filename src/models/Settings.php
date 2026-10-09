<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * Plugin settings.
 *
 * Nothing here is `required`. A settings model that refuses to validate cannot be saved during
 * installation, and the install then fails for a reason that looks nothing like the cause.
 */
class Settings extends Model
{
    /**
     * Template used to render a document at its own URL.
     *
     * Empty means Jack renders the document itself, in a plain, unstyled page. That is a real
     * answer rather than a placeholder: a legal text needs to be readable and printable, and a
     * site that has not got round to styling one should still be able to publish it.
     */
    public string $documentTemplate = '';

    /**
     * Recompile documents whenever the profile or the inventory changes.
     *
     * Off, documents keep the text they were compiled with and go stale visibly. This is the
     * setting a cautious legal department turns off, so it is a setting.
     */
    public bool $autoCompile = true;

    /** Print a "last updated" line at the foot of every rendered document. */
    public bool $showLastUpdated = true;

    /**
     * Restrict overrides and custom sections to basic text markup. Off widens what an editor may
     * write; HTML Purifier still runs either way, so scripts and `javascript:` links never pass.
     */
    public bool $purifyOutput = true;

    /**
     * Directories the detector reads when looking for third-party services.
     *
     * Relative to the Craft base path. The templates directory is the useful one; a build folder
     * with compiled JS in it is often the only place a tag manager snippet actually appears.
     *
     * Untyped, and normalised in {@see self::beforeValidate()}, because the shapes that arrive
     * here are not all arrays of strings: an editable table posts `[['path' => 'templates']]`, and
     * a `config/jack.php` written by hand is as likely to say `'twig,html'` as `['twig', 'html']`.
     * A typed property would fatal on both rather than cope.
     *
     * @var string[]|array<int, array{path: string}>|string
     */
    public $detectorPaths = ['templates'];

    /**
     * File extensions the detector opens.
     *
     * @var string[]|string
     */
    public $detectorExtensions = ['twig', 'html', 'js', 'php'];

    /** Largest file the detector will read, in kilobytes. */
    public int $detectorMaxFileSize = 512;

    /**
     * Whether front-end URLs are enabled for documents at all.
     *
     * Sites that render their legal texts inside their own entries do not want Jack routing
     * `/impressum` as well, and an accidental second copy at a second URL is a real problem: it
     * is the one that goes stale.
     */
    public bool $enableDocumentUrls = true;

    /**
     * Serve published documents as JSON at `/jack/documents.json` and `/jack/documents/<handle>.json`,
     * for front ends that do not render through Twig. Published text only, like every other way
     * out of Jack.
     */
    public bool $serveDocumentsJson = true;

    /**
     * Where the consent manager configs file services (Pro). Keys are inventory categories
     * (`maps`) or service keys (`google_maps`); values are one of the consent categories
     * `necessary`, `preferences`, `analytics`, `marketing`, or null to leave it out of every
     * banner. A service key wins over its category. Set in `config/jack.php`; there is no CP field,
     * because the defaults are the answer for nearly every site.
     *
     * Untyped and normalised in {@see self::beforeValidate()}, for the same reason as
     * {@see self::$detectorPaths}.
     *
     * @var array<string, string|null>|mixed
     */
    public $consentCategories = [];

    public function beforeValidate(): bool
    {
        $this->detectorPaths = $this->normalizeList($this->detectorPaths, 'path');
        $this->detectorExtensions = array_map(
            static fn(string $extension) => ltrim(strtolower($extension), '.'),
            $this->normalizeList($this->detectorExtensions),
        );

        $this->consentCategories = $this->normalizeMap($this->consentCategories);

        return parent::beforeValidate();
    }

    /**
     * @return array<string, string|null>
     */
    private function normalizeMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $category) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            $map[trim($key)] = is_string($category) && trim($category) !== '' ? trim($category) : null;
        }

        return $map;
    }

    /**
     * Flattens whichever of the three plausible shapes arrived into a list of non-empty strings.
     *
     * @param mixed $value
     * @return string[]
     */
    private function normalizeList(mixed $value, ?string $column = null): array
    {
        if (is_string($value)) {
            $value = preg_split('~\s*,\s*~', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $column !== null ? ($item[$column] ?? '') : reset($item);
            }

            $item = trim((string)$item);

            if ($item !== '') {
                $list[] = $item;
            }
        }

        return array_values(array_unique($list));
    }

    public function defineRules(): array
    {
        return [
            [['documentTemplate'], 'string'],
            [['autoCompile', 'showLastUpdated', 'purifyOutput', 'enableDocumentUrls', 'serveDocumentsJson'], 'boolean'],
            [['detectorPaths', 'detectorExtensions', 'consentCategories'], 'safe'],
            [['detectorMaxFileSize'], 'integer', 'min' => 1, 'max' => 10240],
        ];
    }
}
