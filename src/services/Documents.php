<?php

namespace justinholtweb\jack\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use DateTime;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\records\VersionRecord;
use Throwable;

/**
 * Documents: making them, compiling them, publishing them and remembering what they used to say.
 *
 * Compiling and publishing are separate acts on purpose. Compiling is cheap, automatic and
 * happens whenever the facts move; publishing is deliberate, writes an immutable version row and
 * is the thing somebody can be asked to sign off. A plugin that conflated them would either
 * publish legal text nobody read or leave the site showing an address it no longer has.
 */
class Documents extends Component
{
    /**
     * Compiles a document for one site and stores the result.
     *
     * The compiled text is stored, not rendered on the fly. A legal document has to keep saying
     * what it said until somebody changes it — rendering live would mean a half-finished edit to
     * the profile appearing on a published privacy policy the moment it was saved.
     */
    public function compile(Document $document, bool $save = true): bool
    {
        $compiled = $document->compile();

        $document->compiledHtml = $compiled->toHtml();
        $document->compiledText = $compiled->toText();
        $document->stamp = Json::encode($compiled->stamp);
        $document->warnings = Json::encode($compiled->warnings);
        $document->missingFacts = Json::encode($compiled->missingFacts);
        $document->compiledAt = Db::prepareDateForDb(new DateTime());

        if (!$save) {
            return true;
        }

        return Craft::$app->getElements()->saveElement($document, false);
    }

    /**
     * Compiles every document for every site it is allowed to exist on.
     *
     * Returns the number compiled. Failures are logged rather than thrown: this runs from a
     * settings save and from the console, and one document with a broken override must not stop
     * the other seven from being brought up to date.
     */
    public function compileAll(?int $onlySiteId = null): int
    {
        $count = 0;

        foreach ($this->siteIds() as $siteId) {
            if ($onlySiteId !== null && $siteId !== $onlySiteId) {
                continue;
            }

            foreach (Document::find()->siteId($siteId)->status(null)->all() as $document) {
                try {
                    if ($this->compile($document)) {
                        $count++;
                    }
                } catch (Throwable $e) {
                    Craft::error(
                        sprintf('Could not compile document %s for site %s: %s', $document->handle, $siteId, $e->getMessage()),
                        Plugin::LOG_CATEGORY,
                    );
                }
            }
        }

        return $count;
    }

    /**
     * Called whenever the profile or the inventory changes.
     *
     * With `autoCompile` on this recompiles everything, which is what most sites want: the facts
     * are the document. With it off, nothing happens here and the documents show as out of date
     * until somebody looks — which is what a site with a review process wants, and why it is a
     * setting rather than a decision.
     */
    public function onFactsChanged(): void
    {
        if (!Plugin::getInstance()->getSettings()->autoCompile) {
            return;
        }

        // Installation and migrations both touch these tables before the element table is ready.
        if (!Craft::$app->getIsInstalled() || Craft::$app->getPlugins()->isPluginUpdatePending(Plugin::getInstance())) {
            return;
        }

        try {
            $this->compileAll();
        } catch (Throwable $e) {
            Craft::error('Could not recompile documents: ' . $e->getMessage(), Plugin::LOG_CATEGORY);
        }
    }

    /**
     * Creates a document of a type, with sensible defaults for its title and URL.
     */
    public function create(string $documentType, ?int $siteId = null): Document|false
    {
        $siteId ??= Craft::$app->getSites()->getPrimarySite()->id;
        $site = Craft::$app->getSites()->getSiteById($siteId);
        $language = $site ? strtolower($site->language) : 'en';

        $document = new Document();
        $document->documentType = $documentType;
        $document->siteId = $siteId;
        $document->handle = $this->uniqueHandle($documentType);
        $document->jurisdiction = (string)(Plugin::getInstance()->profiles->factsForSite($siteId)['site.audience'] ?? 'de');
        $document->title = self::defaultTitle($documentType, $language);
        $document->slug = self::defaultSlug($documentType, $language);
        $document->enabled = true;

        if (!Craft::$app->getElements()->saveElement($document)) {
            return false;
        }

        $this->compile($document);

        return $document;
    }

    /**
     * Default titles, in the language of the site the document is being created for.
     *
     * German first because that is who this plugin is for; an English site gets English.
     */
    public static function defaultTitle(string $documentType, string $language = 'de'): string
    {
        $german = str_starts_with($language, 'de');

        $titles = [
            Document::TYPE_IMPRINT => ['de' => 'Impressum', 'en' => 'Legal notice'],
            Document::TYPE_PRIVACY => ['de' => 'Datenschutzerklärung', 'en' => 'Privacy policy'],
            Document::TYPE_COOKIES => ['de' => 'Cookie-Richtlinie', 'en' => 'Cookie policy'],
            Document::TYPE_TERMS => ['de' => 'Allgemeine Geschäftsbedingungen', 'en' => 'Terms and conditions'],
            Document::TYPE_WITHDRAWAL => ['de' => 'Widerrufsbelehrung', 'en' => 'Right of withdrawal'],
            Document::TYPE_DISCLAIMER => ['de' => 'Haftungsausschluss', 'en' => 'Disclaimer'],
            Document::TYPE_ACCESSIBILITY => ['de' => 'Erklärung zur Barrierefreiheit', 'en' => 'Accessibility statement'],
            Document::TYPE_SOCIAL => ['de' => 'Datenschutz in sozialen Netzwerken', 'en' => 'Social media privacy'],
        ];

        return $titles[$documentType][$german ? 'de' : 'en'] ?? ucfirst($documentType);
    }

    public static function defaultSlug(string $documentType, string $language = 'de'): string
    {
        $german = str_starts_with($language, 'de');

        $slugs = [
            Document::TYPE_IMPRINT => ['de' => 'impressum', 'en' => 'legal-notice'],
            Document::TYPE_PRIVACY => ['de' => 'datenschutz', 'en' => 'privacy-policy'],
            Document::TYPE_COOKIES => ['de' => 'cookies', 'en' => 'cookie-policy'],
            Document::TYPE_TERMS => ['de' => 'agb', 'en' => 'terms'],
            Document::TYPE_WITHDRAWAL => ['de' => 'widerruf', 'en' => 'withdrawal'],
            Document::TYPE_DISCLAIMER => ['de' => 'haftungsausschluss', 'en' => 'disclaimer'],
            Document::TYPE_ACCESSIBILITY => ['de' => 'barrierefreiheit', 'en' => 'accessibility'],
            Document::TYPE_SOCIAL => ['de' => 'social-media-datenschutz', 'en' => 'social-media-privacy'],
        ];

        return $slugs[$documentType][$german ? 'de' : 'en'] ?? $documentType;
    }

    // -----------------------------------------------------------------------------------------
    // Publishing and history
    // -----------------------------------------------------------------------------------------

    /**
     * Freezes what a document currently says into an immutable version row.
     */
    public function publish(Document $document, ?string $note = null): bool
    {
        if (!$document->compiledHtml) {
            return false;
        }

        $record = new VersionRecord([
            'documentId' => $document->id,
            'siteId' => $document->siteId,
            'html' => $document->compiledHtml,
            'stamp' => $document->stamp,
            'note' => $note ? StringHelper::safeTruncate($note, 250) : null,
            'libraryVersion' => Library::VERSION,
        ]);

        if (!$record->save()) {
            return false;
        }

        $document->publishedAt = Db::prepareDateForDb(new DateTime());

        return Craft::$app->getElements()->saveElement($document, false);
    }

    /**
     * @return VersionRecord[]
     */
    public function versions(int $documentId, int $siteId, int $limit = 50): array
    {
        return VersionRecord::find()
            ->where(['documentId' => $documentId, 'siteId' => $siteId])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    public function version(int $id): ?VersionRecord
    {
        return VersionRecord::findOne($id);
    }

    /**
     * A line diff between two versions of a document.
     *
     * Compares the text rendering rather than the HTML: a legal review is about words, and a diff
     * full of `<p>` churn buries the sentence that changed.
     *
     * @return array<int, array{type: string, text: string}>
     */
    public function diff(string $before, string $after): array
    {
        $a = $this->diffLines($before);
        $b = $this->diffLines($after);

        // Longest common subsequence over lines. The inputs are legal documents — hundreds of
        // lines, not hundreds of thousands — so the quadratic table is the right trade for a diff
        // that is exactly right rather than nearly right.
        $lcs = [];

        for ($i = count($a); $i >= 0; $i--) {
            for ($j = count($b); $j >= 0; $j--) {
                if ($i === count($a) || $j === count($b)) {
                    $lcs[$i][$j] = 0;
                } elseif ($a[$i] === $b[$j]) {
                    $lcs[$i][$j] = 1 + $lcs[$i + 1][$j + 1];
                } else {
                    $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
        }

        $rows = [];
        $i = $j = 0;

        while ($i < count($a) && $j < count($b)) {
            if ($a[$i] === $b[$j]) {
                $rows[] = ['type' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $rows[] = ['type' => 'removed', 'text' => $a[$i++]];
            } else {
                $rows[] = ['type' => 'added', 'text' => $b[$j++]];
            }
        }

        while ($i < count($a)) {
            $rows[] = ['type' => 'removed', 'text' => $a[$i++]];
        }

        while ($j < count($b)) {
            $rows[] = ['type' => 'added', 'text' => $b[$j++]];
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    private function diffLines(string $html): array
    {
        $text = preg_replace('~<(li|p|div|h[1-6]|tr|br)\b[^>]*>~i', "\n", $html);
        $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES, 'UTF-8');
        $lines = preg_split('~\R~', $text) ?: [];

        return array_values(array_filter(array_map(
            static fn($line) => trim(preg_replace('~\s+~u', ' ', $line) ?? ''),
            $lines,
        ), static fn($line) => $line !== ''));
    }

    // -----------------------------------------------------------------------------------------
    // Questions the CP asks
    // -----------------------------------------------------------------------------------------

    /**
     * Documents whose text no longer matches what they would compile to.
     *
     * @return Document[]
     */
    public function stale(?int $siteId = null): array
    {
        $stale = [];

        foreach ($this->siteIds() as $id) {
            if ($siteId !== null && $id !== $siteId) {
                continue;
            }

            foreach (Document::find()->siteId($id)->status(null)->all() as $document) {
                if ($document->isStale()) {
                    $stale[] = $document;
                }
            }
        }

        return $stale;
    }

    /**
     * Document types a site does not have yet, in the order they matter.
     *
     * @return string[]
     */
    public function missingTypes(?int $siteId = null): array
    {
        $siteId ??= Craft::$app->getSites()->getPrimarySite()->id;

        $existing = Document::find()
            ->siteId($siteId)
            ->status(null)
            ->select(['jack_documents.documentType'])
            ->column();

        $allowed = Edition::documentTypes(Plugin::getInstance()->isPro());

        return array_values(array_diff($allowed, $existing));
    }

    public function getByHandle(string $handle, ?int $siteId = null): ?Document
    {
        return Document::find()->handle($handle)->siteId($siteId)->status(null)->one();
    }

    /**
     * The first document of a type — how a template asks for "the privacy policy" without
     * knowing what somebody called it.
     */
    public function getByType(string $documentType, ?int $siteId = null): ?Document
    {
        return Document::find()
            ->documentType($documentType)
            ->siteId($siteId)
            ->status(null)
            ->orderBy(['jack_documents.id' => SORT_ASC])
            ->one();
    }

    /**
     * @return int[]
     */
    private function siteIds(): array
    {
        if (!Edition::allowsMultiSite(Plugin::getInstance()->isPro())) {
            return [Craft::$app->getSites()->getPrimarySite()->id];
        }

        return Craft::$app->getSites()->getAllSiteIds();
    }

    private function uniqueHandle(string $base): string
    {
        $handle = $base;
        $suffix = 1;

        while (Document::find()->handle($handle)->status(null)->siteId('*')->unique()->exists()) {
            $handle = $base . '-' . (++$suffix);
        }

        return $handle;
    }
}
