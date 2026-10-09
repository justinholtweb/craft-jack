<?php

namespace justinholtweb\jack\twig;

use Craft;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;
use Twig\Markup;

/**
 * `craft.jack` — everything a template needs from Jack.
 *
 * Lookups take a handle *or* a document type, so a footer can ask for `'privacy'` without knowing
 * or caring what somebody called the document in the control panel. That matters because the
 * template outlives the naming decision.
 */
class JackVariable
{
    /**
     * A document by handle, or failing that the first document of that type.
     */
    public function document(string $handleOrType, ?int $siteId = null): ?Document
    {
        $documents = Plugin::getInstance()->documents;

        return $documents->getByHandle($handleOrType, $siteId)
            ?? $documents->getByType($handleOrType, $siteId);
    }

    /**
     * The rendered text of a document, ready to print.
     *
     * @param array<string, mixed> $options `toc`, `lastUpdated`, `class`, `headingLevel`
     */
    public function render(string $handleOrType, array $options = [], ?int $siteId = null): Markup
    {
        $document = $this->document($handleOrType, $siteId);

        if (!$document) {
            return \craft\helpers\Template::raw('');
        }

        return Plugin::getInstance()->renderer->render($document, $options);
    }

    /**
     * @return Document[]
     */
    public function documents(?int $siteId = null): array
    {
        return Document::find()->siteId($siteId)->all();
    }

    public function url(string $handleOrType, ?int $siteId = null): ?string
    {
        return $this->document($handleOrType, $siteId)?->getUrl();
    }

    /**
     * The document's contents list, for building a sidebar.
     *
     * @return array<int, array{anchor: string, title: string}>
     */
    public function toc(string $handleOrType, ?int $siteId = null): array
    {
        $document = $this->document($handleOrType, $siteId);

        return $document ? Plugin::getInstance()->renderer->toc($document) : [];
    }

    public function lastUpdated(string $handleOrType, ?int $siteId = null): ?\DateTime
    {
        $document = $this->document($handleOrType, $siteId);
        $date = $document?->publishedAt;

        return $date instanceof \DateTime ? $date : null;
    }

    /**
     * The facts, for a template that wants the company address in its footer rather than a whole
     * imprint. One source for the address beats two.
     *
     * @return array<string, mixed>
     */
    public function profile(?int $siteId = null): array
    {
        return Plugin::getInstance()->profiles->factMap(
            $siteId ?? Craft::$app->getSites()->getCurrentSite()->id,
        );
    }

    /**
     * The inventory, so a consent banner can be driven from the same list the privacy policy is.
     *
     * Jack does not ship a banner. It does make sure that if you have one, it and your privacy
     * policy cannot disagree about which services exist.
     *
     * @return ServiceEntry[]
     */
    public function services(?string $category = null): array
    {
        $entries = Plugin::getInstance()->inventory->enabled();

        if ($category === null) {
            return array_values($entries);
        }

        return array_values(array_filter($entries, static fn(ServiceEntry $entry) => $entry->category() === $category));
    }

    /**
     * Services that must not run before the visitor agrees.
     *
     * @return ServiceEntry[]
     */
    public function consentRequired(): array
    {
        return array_values(array_filter(
            Plugin::getInstance()->inventory->enabled(),
            static fn(ServiceEntry $entry) => $entry->legalBasis() === ServiceEntry::BASIS_CONSENT,
        ));
    }

    /**
     * @return array<int, array{service: string, category: string, essential: bool, name: string, duration: string}>
     */
    public function cookies(?string $language = null): array
    {
        return Plugin::getInstance()->inventory->cookies(
            $language ?? strtolower(Craft::$app->getSites()->getCurrentSite()->language),
        );
    }

    /**
     * The inventory as a consent manager's config (Pro): `toss`, `klaro`, `cookieconsent` or
     * `consent-mode`. An empty array on Lite.
     *
     * Wildcard cookie names come back as pattern objects; use {@see self::consentScript()} to put
     * the config on a page, or `|json_encode` for their pattern source as a string.
     *
     * @return array<string, mixed>
     */
    public function consentConfig(string $format, ?int $siteId = null): array
    {
        $plugin = Plugin::getInstance();

        if (!Edition::allowsConsentExport($plugin->isPro())) {
            Craft::warning('craft.jack.consentConfig() is a Jack Pro feature.', Plugin::LOG_CATEGORY);

            return [];
        }

        return $plugin->consentExport->build($format, $siteId);
    }

    /**
     * A `<script>` assigning the consent manager config to a global (Pro) — `klaroConfig` for
     * Klaro, `jackConsentConfig` otherwise, or `options.variable`. Nothing on Lite.
     *
     * The same for every visitor, so it is safe in a statically cached page.
     *
     * @param array{variable?: string} $options
     */
    public function consentScript(string $format, array $options = [], ?int $siteId = null): Markup
    {
        $plugin = Plugin::getInstance();

        if (!Edition::allowsConsentExport($plugin->isPro())) {
            Craft::warning('craft.jack.consentScript() is a Jack Pro feature.', Plugin::LOG_CATEGORY);

            return \craft\helpers\Template::raw('');
        }

        return $plugin->consentExport->script($format, $siteId, $options['variable'] ?? null);
    }

    /** The date the bundled clause library was last reviewed. */
    public function libraryVersion(): string
    {
        return \justinholtweb\jack\services\Library::VERSION;
    }
}
