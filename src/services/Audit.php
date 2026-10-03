<?php

namespace justinholtweb\jack\services;

use Craft;
use craft\base\Component;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\models\Finding;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;
use yii\caching\TagDependency;

/**
 * What is wrong with this site's legal texts, in the order it is worth fixing.
 *
 * The audit exists because the expensive failures are absences, and absences do not appear on any
 * screen that lists what you have. Nobody notices that the imprint they never wrote is missing;
 * they notice the letter about it.
 *
 * Every finding carries a remedy. A compliance report that only worries is a compliance report
 * that gets closed.
 */
class Audit extends Component
{
    public const CACHE_TAG = 'jack:audit';

    /**
     * @return Finding[] ordered by severity
     */
    public function run(?int $siteId = null, bool $withDetections = true): array
    {
        $siteId ??= Craft::$app->getSites()->getPrimarySite()->id;
        $plugin = Plugin::getInstance();

        $findings = array_merge(
            $this->checkDocumentsExist($siteId),
            $this->checkDocumentHealth($siteId),
            $this->checkProfile($siteId),
            $this->checkInventory(),
            $plugin->isPro() && $withDetections ? $this->checkDetections() : [],
        );

        usort($findings, static fn(Finding $a, Finding $b) => [$a->weight(), $a->title] <=> [$b->weight(), $b->title]);

        return $findings;
    }

    /**
     * A single number for the nav badge: how many things need attention.
     *
     * The nav is built on every CP request, and a full audit recompiles every document, so the
     * count is cached until something it depends on changes. It leaves the detector out: reading
     * every template on the site is a job for the audit screen, not for each page load.
     */
    public function problemCount(?int $siteId = null): int
    {
        $siteId ??= Craft::$app->getSites()->getPrimarySite()->id;

        return (int)Craft::$app->getCache()->getOrSet(
            self::CACHE_TAG . ':count:' . $siteId,
            fn() => count(array_filter(
                $this->run($siteId, false),
                static fn(Finding $finding) => $finding->severity !== Finding::SEVERITY_NOTICE,
            )),
            3600,
            new TagDependency(['tags' => self::CACHE_TAG]),
        );
    }

    /**
     * Drops the cached badge count. Called whenever a document, the profile, the inventory or the
     * settings change.
     */
    public function invalidate(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG);
    }

    /**
     * @return Finding[]
     */
    private function checkDocumentsExist(int $siteId): array
    {
        $findings = [];
        $facts = Plugin::getInstance()->profiles->factsForSite($siteId);
        $audience = (string)($facts['site.audience'] ?? 'de');
        $european = in_array($audience, ['de', 'at', 'ch', 'eu'], true);

        $existing = Document::find()->siteId($siteId)->status(null)->select(['jack_documents.documentType'])->column();

        if (!in_array(Document::TYPE_IMPRINT, $existing, true)) {
            $findings[] = $european
                ? Finding::critical(
                    Craft::t('jack', 'No imprint'),
                    Craft::t('jack', 'German, Austrian and Swiss sites must carry a reachable imprint. Its absence is the single most commonly enforced website failing, and it is enforced by competitors rather than by regulators.'),
                    Craft::t('jack', 'Create an imprint document and link it from every page of the site.'),
                    '§ 5 DDG',
                )
                : Finding::notice(
                    Craft::t('jack', 'No imprint'),
                    Craft::t('jack', 'Your audience is outside the German-speaking market, where an imprint is not compulsory — but a legal notice naming who runs the site is still expected.'),
                    Craft::t('jack', 'Consider creating an imprint or a disclaimer document.'),
                );
        }

        if (!in_array(Document::TYPE_PRIVACY, $existing, true)) {
            $findings[] = Finding::critical(
                Craft::t('jack', 'No privacy policy'),
                Craft::t('jack', 'Any site that processes personal data — which includes writing an IP address to a log file — has to say so before it does it.'),
                Craft::t('jack', 'Create a privacy policy document.'),
                'Art. 13 DSGVO',
            );
        }

        $sells = !empty($facts['site.sellsOnline']);

        if ($sells && !in_array(Document::TYPE_TERMS, $existing, true)) {
            $findings[] = Finding::warning(
                Craft::t('jack', 'Selling online without terms'),
                Craft::t('jack', 'The profile says this site sells online. Consumers buying from an EU trader must get withdrawal instructions before they order, and getting them wrong extends the withdrawal period to a year.'),
                Craft::t('jack', 'Create a terms document — it carries the statutory withdrawal instructions and the model form.'),
                'Art. 246a EGBGB',
            );
        }

        return $findings;
    }

    /**
     * @return Finding[]
     */
    private function checkDocumentHealth(int $siteId): array
    {
        $findings = [];
        $library = Plugin::getInstance()->library;

        foreach (Document::find()->siteId($siteId)->status(null)->all() as $document) {
            if (!$document->compiledAt) {
                $findings[] = Finding::critical(
                    Craft::t('jack', '“{title}” has never been compiled', ['title' => $document->title]),
                    Craft::t('jack', 'The document exists but has no text, so its page is blank.'),
                    Craft::t('jack', 'Open the document and compile it.'),
                );

                continue;
            }

            if (trim((string)$document->compiledHtml) === '') {
                $findings[] = Finding::critical(
                    Craft::t('jack', '“{title}” compiles to nothing', ['title' => $document->title]),
                    Craft::t('jack', 'No clause applies to this document with the facts Jack has, so its page is a heading with nothing under it. Terms of sale on a site whose profile says it sells nothing are the usual cause.'),
                    Craft::t('jack', 'Either answer the profile questions the document depends on, or delete it.'),
                );

                continue;
            }

            if (trim((string)$document->publishedHtml) === '') {
                $findings[] = Finding::critical(
                    Craft::t('jack', '“{title}” has never been published', ['title' => $document->title]),
                    Craft::t('jack', 'Visitors only ever see a published version, so this document’s page is empty until somebody publishes it.'),
                    Craft::t('jack', 'Open the document, read it through, and publish it.'),
                );
            } elseif (trim((string)$document->publishedHtml) !== trim((string)$document->compiledHtml)) {
                $findings[] = Finding::warning(
                    Craft::t('jack', '“{title}” has unpublished changes', ['title' => $document->title]),
                    Craft::t('jack', 'The draft has moved on since the last published version, and visitors still see the old text.'),
                    Craft::t('jack', 'Review the draft and publish it.'),
                );
            }

            $missing = $document->getMissingFacts();

            if ($missing !== []) {
                $labels = array_map(
                    static fn($key) => $library->fact($key)['label'] ?? $key,
                    array_slice($missing, 0, 6),
                );

                $findings[] = Finding::critical(
                    Craft::t('jack', '“{title}” is missing required details', ['title' => $document->title]),
                    Craft::t('jack', 'Clauses in this document need facts the profile does not have: {facts}.', [
                        'facts' => implode(', ', $labels) . (count($missing) > 6 ? ' …' : ''),
                    ]),
                    Craft::t('jack', 'Fill those fields in on the profile screen, then recompile.'),
                );
            }

            if ($document->isStale()) {
                $findings[] = Finding::warning(
                    Craft::t('jack', '“{title}” is out of date', ['title' => $document->title]),
                    Craft::t('jack', 'What this document says and what it would say if compiled now have parted company — the library, the profile or the inventory has moved since.'),
                    Craft::t('jack', 'Review the differences and recompile.'),
                );
            }

            if (!$document->enabled || !$document->getEnabledForSite()) {
                $findings[] = Finding::critical(
                    Craft::t('jack', '“{title}” is disabled', ['title' => $document->title]),
                    Craft::t('jack', 'The document exists in the control panel but returns a 404 to visitors, which is the same as not having it.'),
                    Craft::t('jack', 'Enable the document.'),
                );
            }
        }

        return $findings;
    }

    /**
     * @return Finding[]
     */
    private function checkProfile(int $siteId): array
    {
        $findings = [];
        $facts = Plugin::getInstance()->profiles->factsForSite($siteId);

        if (trim((string)($facts['hosting.provider'] ?? '')) === '') {
            $findings[] = Finding::warning(
                Craft::t('jack', 'No hosting provider named'),
                Craft::t('jack', 'Your host receives every visitor’s IP address, which makes them a recipient of personal data and one your privacy policy currently does not name.'),
                Craft::t('jack', 'Add the hosting provider to the profile.'),
                'Art. 13 Abs. 1 lit. e DSGVO',
            );
        } elseif (empty($facts['hosting.dpa'])) {
            $findings[] = Finding::warning(
                Craft::t('jack', 'No data processing agreement with the host'),
                Craft::t('jack', 'The profile names a hosting provider but says no agreement under Art. 28 GDPR is in place. Handing a processor personal data without one is unlawful, not merely undocumented.'),
                Craft::t('jack', 'Sign your host’s data processing agreement — most publish one you can accept online — then tick the box.'),
                'Art. 28 DSGVO',
            );
        }

        if (trim((string)($facts['authority.name'] ?? '')) === '') {
            $findings[] = Finding::notice(
                Craft::t('jack', 'No supervisory authority named'),
                Craft::t('jack', 'Readers have a right to complain to a supervisory authority, and the privacy policy is where they find out which one.'),
                Craft::t('jack', 'Add the data protection authority for the state you are established in.'),
                'Art. 77 DSGVO',
            );
        }

        $completeness = Plugin::getInstance()->profiles->completeness($siteId);

        if ($completeness < 0.6) {
            $findings[] = Finding::warning(
                Craft::t('jack', 'The profile is mostly empty'),
                Craft::t('jack', 'Only {percent}% of the facts the clause library asks for are filled in.', [
                    'percent' => round($completeness * 100),
                ]),
                Craft::t('jack', 'Work through the profile screen — every field there is one some clause needs.'),
            );
        }

        return $findings;
    }

    /**
     * @return Finding[]
     */
    private function checkInventory(): array
    {
        $findings = [];
        $inventory = Plugin::getInstance()->inventory;
        $consentNeeded = [];

        foreach ($inventory->enabled() as $entry) {
            if ($entry->legalBasis() === ServiceEntry::BASIS_CONSENT) {
                $consentNeeded[] = $entry->label();
            }
        }

        if ($consentNeeded !== []) {
            $findings[] = Finding::notice(
                Craft::t('jack', '{n, plural, =1{One service needs} other{# services need}} consent before they load', ['n' => count($consentNeeded)]),
                Craft::t('jack', 'Jack has written the clauses that say you ask for consent: {services}. It does not itself hold the consent back — that is a consent banner’s job, and Jack is not one.', [
                    'services' => implode(', ', array_slice($consentNeeded, 0, 8)),
                ]),
                Craft::t('jack', 'Make sure these scripts do not run until the visitor has agreed. Jack’s inventory is readable from Twig, so a banner can be driven from the same list.'),
                '§ 25 Abs. 1 TDDDG',
            );
        }

        if ($inventory->isEnabled('google_fonts')) {
            $findings[] = Finding::warning(
                Craft::t('jack', 'Google Fonts is loaded from Google'),
                Craft::t('jack', 'Munich Regional Court I awarded damages against a site for embedding Google Fonts dynamically without consent (20 January 2022, 3 O 17493/20), and a cottage industry of warning letters followed.'),
                Craft::t('jack', 'Download the fonts and serve them from your own domain, then remove the entry from the inventory.'),
            );
        }

        if ($inventory->enabled() === []) {
            $findings[] = Finding::critical(
                Craft::t('jack', 'The inventory is empty'),
                Craft::t('jack', 'A privacy policy that names no recipients is describing a site that does not exist — even a plain Craft install writes server logs and sets a session cookie.'),
                Craft::t('jack', 'Add the services this site uses. Pro can find most of them for you.'),
            );
        }

        return $findings;
    }

    /**
     * @return Finding[]
     */
    private function checkDetections(): array
    {
        if (!Edition::allowsDetection(Plugin::getInstance()->isPro())) {
            return [];
        }

        $undeclared = Plugin::getInstance()->detector->undeclared();

        if ($undeclared === []) {
            return [];
        }

        $labels = array_map(static fn($detection) => $detection->label, $undeclared);

        return [
            Finding::critical(
                Craft::t('jack', '{n, plural, =1{A service is} other{# services are}} on the site but not in the privacy policy', ['n' => count($undeclared)]),
                Craft::t('jack', 'Found in your templates, plugins or environment but never declared: {services}.', [
                    'services' => implode(', ', array_slice($labels, 0, 8)) . (count($labels) > 8 ? ' …' : ''),
                ]),
                Craft::t('jack', 'Add them to the inventory — or take them off the site.'),
                'Art. 13 DSGVO',
            ),
        ];
    }
}
