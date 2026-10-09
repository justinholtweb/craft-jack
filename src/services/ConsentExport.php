<?php

namespace justinholtweb\jack\services;

use Craft;
use craft\base\Component;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\helpers\CookiePattern;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;
use Twig\Markup;

/**
 * The inventory as configuration for a consent manager.
 *
 * Jack is not a consent banner, but it holds the list a banner needs: which services run, what
 * each is for, which cookies it sets and which of them need asking about. Writing that list into a
 * banner's config by hand is how a banner, a privacy policy and the tags that actually fire come
 * to disagree. This writes it for them, from the same inventory the policy is compiled from.
 *
 * Four targets:
 *
 * - `toss` — the family's consent manager. Services under Toss's four categories, with the Toss
 *   preset each matches, and, when Toss is installed and its consent kit is on, what Toss's own
 *   script inventory says about each one.
 * - `klaro` — a `klaroConfig` object for Klaro.
 * - `cookieconsent` — the object `CookieConsent.run()` takes, for orestbida's CookieConsent v3.
 * - `consent-mode` — Google Consent Mode v2 types and Tape's three categories per service, so the
 *   tags Tape fires wait on the same answer the banner asked for.
 *
 * Like the compiler, a pure function of the inventory, the settings and the library: no dates, no
 * visitor, so a config can be committed, diffed, and cached in a page.
 *
 * Every service is filed under one of Toss's four canonical categories ({@see self::CATEGORIES}),
 * which is the vocabulary all four targets share:
 *
 * 1. a `consentCategories` setting naming the service key wins;
 * 2. a service whose legal basis is not consent is `necessary` — it runs without asking, and a
 *    banner offering to switch it off would be making a promise the site does not keep;
 * 3. otherwise its inventory category decides, through {@see self::DEFAULT_CATEGORY_MAP} and any
 *    `consentCategories` entry naming that category.
 *
 * A category mapped to `null` stays out of every config. Newsletters are, by default: consent to
 * a newsletter is given in the sign-up form, and nothing about it runs in the visitor's browser
 * for a banner to hold back. First-party entries that set no cookie — server logs, forms — are
 * left out for the same reason.
 */
class ConsentExport extends Component
{
    public const FORMAT_TOSS = 'toss';
    public const FORMAT_KLARO = 'klaro';
    public const FORMAT_COOKIECONSENT = 'cookieconsent';
    public const FORMAT_CONSENT_MODE = 'consent-mode';

    public const FORMATS = [
        self::FORMAT_TOSS,
        self::FORMAT_KLARO,
        self::FORMAT_COOKIECONSENT,
        self::FORMAT_CONSENT_MODE,
    ];

    /** Toss's `Consent::CATEGORIES`, the family's consent vocabulary. */
    public const CATEGORIES = ['necessary', 'preferences', 'analytics', 'marketing'];

    /**
     * Inventory category → consent category, for services that need consent.
     *
     * Embedded media, advertising and social networks are `marketing` because each of them can
     * recognise a visitor across other sites — the same call Toss's own YouTube preset makes.
     *
     * @var array<string, string|null>
     */
    public const DEFAULT_CATEGORY_MAP = [
        'functional' => 'preferences',
        'infrastructure' => 'preferences',
        'security' => 'preferences',
        'payment' => 'preferences',
        'email' => null,
        'support' => 'preferences',
        'booking' => 'preferences',
        'media' => 'marketing',
        'fonts' => 'preferences',
        'maps' => 'preferences',
        'analytics' => 'analytics',
        'advertising' => 'marketing',
        'social' => 'marketing',
    ];

    /**
     * Google Consent Mode v2 type → consent category. The same table as Toss's
     * `Consent::CONSENT_MODE_MAP` and Tape's; when Toss is active its own map, overrides included,
     * is read instead, so all three can never send Google different answers.
     *
     * @var array<string, string>
     */
    public const CONSENT_MODE_MAP = [
        'ad_storage' => 'marketing',
        'ad_user_data' => 'marketing',
        'ad_personalization' => 'marketing',
        'analytics_storage' => 'analytics',
        'functionality_storage' => 'preferences',
        'personalization_storage' => 'preferences',
        'security_storage' => 'necessary',
    ];

    /** Tape's destination categories → consent category, as Tape reads them from Toss. */
    public const TAPE_CATEGORIES = [
        'ads' => 'marketing',
        'analytics' => 'analytics',
        'functionality' => 'preferences',
    ];

    /**
     * Jack service key → Toss script preset key, where Toss ships a preset for the same service.
     * Toss's keys are its own (`meta-pixel`, `linkedin`), so they are mapped rather than assumed.
     */
    public const TOSS_PRESETS = [
        'ga4' => 'ga4',
        'gtm' => 'gtm',
        'meta_pixel' => 'meta-pixel',
        'google_ads' => 'google-ads',
        'linkedin_insight' => 'linkedin',
        'tiktok_pixel' => 'tiktok',
        'hotjar' => 'hotjar',
        'clarity' => 'clarity',
        'plausible' => 'plausible',
        'fathom' => 'fathom',
        'youtube' => 'youtube',
        'intercom' => 'intercom',
        'stripe' => 'stripe',
        'recaptcha' => 'recaptcha',
    ];

    /** A JavaScript global the script helper may assign to: `klaroConfig`, `window.myBanner.config`. */
    private const VARIABLE_PATTERN = '/^[A-Za-z_$][A-Za-z0-9_$]*(\.[A-Za-z_$][A-Za-z0-9_$]*)*$/';

    /** @var array<string, mixed>|null */
    private ?array $words = null;

    /**
     * The config for one consent manager, as data.
     *
     * Cookie names with a wildcard are {@see CookiePattern}s; `Json::encode()` writes them as
     * their pattern source.
     *
     * @return array<string, mixed>
     * @throws \InvalidArgumentException for an unknown format
     */
    public function build(string $format, ?int $siteId = null): array
    {
        $siteId ??= Craft::$app->getSites()->getCurrentSite()->id;
        $language = $this->languageFor($siteId);

        return match ($format) {
            self::FORMAT_TOSS => $this->tossConfig($siteId, $language),
            self::FORMAT_KLARO => $this->klaro($siteId, $language),
            self::FORMAT_COOKIECONSENT => $this->cookieConsent($siteId, $language),
            self::FORMAT_CONSENT_MODE => $this->consentMode($language),
            default => throw new \InvalidArgumentException(sprintf('Unknown consent manager format “%s”.', $format)),
        };
    }

    /** The config as pretty-printed JSON, for a download or a file in the repository. */
    public function json(string $format, ?int $siteId = null): string
    {
        return Json::encode(
            $this->build($format, $siteId),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n";
    }

    /**
     * A `<script>` assigning the config to a JavaScript global, with cookie wildcards as real
     * regex literals.
     *
     * The default global is the one each manager looks for — `klaroConfig` for Klaro — and
     * `jackConsentConfig` otherwise, for `CookieConsent.run(jackConsentConfig)`.
     *
     * @throws \InvalidArgumentException for an unknown format or a variable name that is not one
     */
    public function script(string $format, ?int $siteId = null, ?string $variable = null): Markup
    {
        $variable ??= $format === self::FORMAT_KLARO ? 'klaroConfig' : 'jackConsentConfig';

        if (preg_match(self::VARIABLE_PATTERN, $variable) !== 1) {
            throw new \InvalidArgumentException(sprintf('“%s” is not a JavaScript variable name.', $variable));
        }

        $target = str_contains($variable, '.') ? $variable : 'window.' . $variable;

        return Template::raw(Html::tag('script', $target . ' = ' . $this->toJs($this->build($format, $siteId)) . ';'));
    }

    /**
     * The consent category a service is filed under, or null when it stays out of a banner.
     */
    public function categoryFor(ServiceEntry $entry): ?string
    {
        $map = $this->categoryMap();

        if (array_key_exists($entry->serviceKey, $map)) {
            return $map[$entry->serviceKey];
        }

        if ($entry->legalBasis() !== ServiceEntry::BASIS_CONSENT) {
            return 'necessary';
        }

        $category = $entry->category();

        return array_key_exists($category, $map) ? $map[$category] : 'preferences';
    }

    /**
     * Enabled services that belong in a consent manager, each with its consent category.
     *
     * @return array<int, array{entry: ServiceEntry, category: string}>
     */
    public function services(): array
    {
        $rows = [];

        foreach (Plugin::getInstance()->inventory->enabled() as $entry) {
            if ($entry->isFirstParty() && $entry->cookies() === []) {
                continue;
            }

            $category = $this->categoryFor($entry);

            if ($category !== null) {
                $rows[] = ['entry' => $entry, 'category' => $category];
            }
        }

        // Category order first, so every target lists necessary before marketing; the inventory's
        // own order within a category.
        $order = array_flip($this->categories());
        $index = 0;

        foreach ($rows as &$row) {
            $row['sort'] = [$order[$row['category']] ?? count($order), $index++];
        }

        unset($row);
        usort($rows, static fn(array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_map(static fn(array $row) => ['entry' => $row['entry'], 'category' => $row['category']], $rows);
    }

    /**
     * The categories a service may be filed under: Toss's four, plus any a site added to Toss.
     *
     * @return string[]
     */
    public function categories(): array
    {
        $toss = $this->tossConsent();

        if ($toss !== null) {
            return array_values(array_unique(array_merge(self::CATEGORIES, array_map('strval', $toss->categories()))));
        }

        return self::CATEGORIES;
    }

    /**
     * Whether Toss is installed and its consent kit is on — the family's test for "Toss is the
     * consent manager" (docs/consent-api.md in craft-toss).
     */
    public function tossIsActive(): bool
    {
        return $this->tossConsent() !== null;
    }

    // -----------------------------------------------------------------------------------------
    // Targets
    // -----------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function tossConfig(int $siteId, string $language): array
    {
        $toss = $this->tossConsent();
        $tossScripts = [];

        if ($toss !== null) {
            foreach ($toss->scripts($siteId) as $script) {
                $tossScripts[(string)$script->key] = (string)$script->category;
            }
        }

        $services = [];

        foreach ($this->services() as ['entry' => $entry, 'category' => $category]) {
            $preset = self::TOSS_PRESETS[$entry->serviceKey] ?? null;
            $row = [
                'key' => $entry->serviceKey,
                'name' => $entry->label($language),
                'category' => $category,
                'purpose' => $entry->text('purpose', $language) ?: null,
                'provider' => $entry->get('provider') ?: null,
                'privacyUrl' => $this->httpUrl($entry->get('privacyUrl')),
                'cookies' => array_column($entry->cookiesFor($language), 'name'),
                'tossPreset' => $preset,
            ];

            if ($toss !== null) {
                $tossKey = $preset ?? $entry->serviceKey;
                $row['inToss'] = isset($tossScripts[$tossKey]);
                $row['tossCategory'] = $tossScripts[$tossKey] ?? null;
            }

            $services[] = $row;
        }

        return [
            'version' => 1,
            'source' => 'jack',
            'library' => Library::VERSION,
            'tossActive' => $toss !== null,
            'categories' => $this->categories(),
            'privacyPolicyUrl' => $this->privacyUrl($siteId),
            'services' => $services,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function klaro(int $siteId, string $language): array
    {
        $privacyUrl = $this->privacyUrl($siteId);
        $services = [];
        $used = ['necessary' => true];

        foreach ($this->services() as ['entry' => $entry, 'category' => $category]) {
            $used[$category] = true;
            $required = $category === 'necessary';

            $services[] = [
                'name' => $entry->serviceKey,
                'title' => $entry->label($language),
                'description' => $this->describe($entry, $language),
                'purposes' => [$category],
                'cookies' => $this->cookiePatterns($entry),
                'required' => $required,
                'default' => $required,
                'optOut' => false,
                'onlyOnce' => true,
            ];
        }

        $purposes = [];

        foreach (array_keys($used) as $category) {
            $purposes[$category] = [
                'title' => $this->categoryWord($category, 'title', $language),
                'description' => $this->categoryWord($category, 'description', $language),
            ];
        }

        $translations = [$language => ['purposes' => $purposes]];

        if ($privacyUrl !== null) {
            $translations['zz'] = ['privacyPolicyUrl' => $privacyUrl];
        }

        return [
            'version' => 1,
            'elementID' => 'klaro',
            'storageMethod' => 'cookie',
            'cookieName' => 'klaro',
            'cookieExpiresAfterDays' => 365,
            'default' => false,
            'mustConsent' => false,
            'acceptAll' => true,
            'hideDeclineAll' => false,
            'htmlTexts' => false,
            'lang' => $language,
            'translations' => $translations,
            'services' => $services,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cookieConsent(int $siteId, string $language): array
    {
        $privacyUrl = $this->privacyUrl($siteId);
        $byCategory = ['necessary' => []];

        foreach ($this->services() as $row) {
            $byCategory[$row['category']][] = $row['entry'];
        }

        $categories = [];
        $sections = [];
        $consentDescription = $this->word('modal', 'consentDescription', $language);

        if ($privacyUrl !== null) {
            $consentDescription .= ' ' . $this->withUrl($this->word('modal', 'privacyLink', $language), $privacyUrl);
        }

        foreach ($byCategory as $category => $entries) {
            $config = $category === 'necessary' ? ['enabled' => true, 'readOnly' => true] : [];
            $cookies = [];
            $services = [];
            $table = [];

            foreach ($entries as $entry) {
                $services[$entry->serviceKey] = ['label' => $entry->label($language)];

                foreach ($entry->cookiesFor($language) as $cookie) {
                    $cookies[] = ['name' => CookiePattern::fromName($cookie['name'])];
                    $table[] = [
                        'name' => $cookie['name'],
                        'service' => $entry->label($language),
                        'duration' => $cookie['duration'],
                    ];
                }
            }

            // Nothing to clear for necessary cookies: declining is not an option for them.
            if ($cookies !== [] && $category !== 'necessary') {
                $config['autoClear'] = ['cookies' => $cookies];
            }

            if ($services !== []) {
                $config['services'] = $services;
            }

            $categories[$category] = $config;

            $section = [
                'title' => $this->categoryWord($category, 'title', $language),
                'description' => $this->categoryWord($category, 'description', $language),
                'linkedCategory' => $category,
            ];

            if ($table !== []) {
                $section['cookieTable'] = [
                    'headers' => [
                        'name' => $this->word('modal', 'cookieName', $language),
                        'service' => $this->word('modal', 'cookieService', $language),
                        'duration' => $this->word('modal', 'cookieDuration', $language),
                    ],
                    'body' => $table,
                ];
            }

            $sections[] = $section;
        }

        if ($privacyUrl !== null) {
            $sections[] = [
                'title' => $this->word('modal', 'moreTitle', $language),
                'description' => $this->withUrl($this->word('modal', 'moreDescription', $language), $privacyUrl),
            ];
        }

        return [
            'categories' => $categories,
            'language' => [
                'default' => $language,
                'translations' => [
                    $language => [
                        'consentModal' => [
                            'title' => $this->word('modal', 'consentTitle', $language),
                            'description' => $consentDescription,
                            'acceptAllBtn' => $this->word('modal', 'acceptAll', $language),
                            'acceptNecessaryBtn' => $this->word('modal', 'acceptNecessary', $language),
                            'showPreferencesBtn' => $this->word('modal', 'showPreferences', $language),
                        ],
                        'preferencesModal' => [
                            'title' => $this->word('modal', 'preferencesTitle', $language),
                            'acceptAllBtn' => $this->word('modal', 'acceptAll', $language),
                            'acceptNecessaryBtn' => $this->word('modal', 'acceptNecessary', $language),
                            'savePreferencesBtn' => $this->word('modal', 'savePreferences', $language),
                            'closeIconLabel' => $this->word('modal', 'close', $language),
                            'sections' => $sections,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consentMode(string $language): array
    {
        $map = $this->consentModeMap();
        $tapeByCategory = array_flip(self::TAPE_CATEGORIES);
        $services = [];

        foreach ($this->services() as ['entry' => $entry, 'category' => $category]) {
            $services[$entry->serviceKey] = [
                'name' => $entry->label($language),
                'category' => $category,
                // The Google types this service's answer travels as.
                'consentMode' => array_keys(array_filter($map, static fn(string $mapped) => $mapped === $category)),
                // The Tape destination category that waits on the same answer; null for a
                // necessary service, which Tape never holds back.
                'tape' => $tapeByCategory[$category] ?? null,
            ];
        }

        return [
            'version' => 1,
            'source' => 'jack',
            'consentModeMap' => $map,
            'tapeCategories' => self::TAPE_CATEGORIES,
            'services' => $services,
        ];
    }

    // -----------------------------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------------------------

    /**
     * Toss's consent service (`justinholtweb\toss\services\Consent`) when Toss is the consent
     * manager, else null.
     *
     * `isPluginEnabled()` is asked before Toss is touched, and Toss is reached through the plugins
     * service rather than by class name, so a site without Toss never loads one of its classes
     * and Jack carries no dependency on it. Untyped for the same reason.
     *
     * @return mixed
     */
    private function tossConsent(): mixed
    {
        $plugins = Craft::$app->getPlugins();

        if (!$plugins->isPluginEnabled('toss')) {
            return null;
        }

        $plugin = $plugins->getPlugin('toss');
        $consent = $plugin instanceof \yii\base\Module ? $plugin->get('consent', false) : null;

        if (!is_object($consent) || !method_exists($consent, 'isActive') || !$consent->isActive()) {
            return null;
        }

        return $consent;
    }

    /**
     * @return array<string, string>
     */
    private function consentModeMap(): array
    {
        $toss = $this->tossConsent();

        if ($toss !== null && method_exists($toss, 'consentModeMap')) {
            return $toss->consentModeMap();
        }

        return self::CONSENT_MODE_MAP;
    }

    /**
     * The `consentCategories` setting merged over the defaults, values checked.
     *
     * @return array<string, string|null>
     */
    private function categoryMap(): array
    {
        $map = self::DEFAULT_CATEGORY_MAP;
        $allowed = $this->categories();

        foreach ((array)Plugin::getInstance()->getSettings()->consentCategories as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if ($value === null || $value === '' || $value === false) {
                $map[$key] = null;
            } elseif (is_string($value) && in_array($value, $allowed, true)) {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    /**
     * @return array<int, string|CookiePattern>
     */
    private function cookiePatterns(ServiceEntry $entry): array
    {
        return array_map(
            static fn(array $cookie) => CookiePattern::fromName($cookie['name']),
            array_values(array_filter($entry->cookiesFor(), static fn(array $cookie) => $cookie['name'] !== '')),
        );
    }

    private function describe(ServiceEntry $entry, string $language): string
    {
        $parts = [];
        $purpose = $entry->text('purpose', $language);
        $provider = (string)$entry->get('provider', '');

        if ($purpose !== '') {
            $parts[] = str_replace('{{ purpose }}', $purpose, $this->word('service', 'purpose', $language));
        }

        if ($provider !== '') {
            $parts[] = str_replace('{{ provider }}', $provider, $this->word('service', 'provider', $language));
        }

        return implode(' ', $parts);
    }

    /** The published privacy policy's URL for a site, or null when there isn't one to link. */
    private function privacyUrl(int $siteId): ?string
    {
        $document = Document::find()->documentType(Document::TYPE_PRIVACY)->siteId($siteId)->orderBy(['jack_documents.id' => SORT_ASC])->one();

        if (!$document || trim((string)$document->publishedHtml) === '') {
            return null;
        }

        return $this->httpUrl($document->getUrl());
    }

    private function httpUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('~^https?://~i', $url) === 1 ? $url : null;
    }

    private function withUrl(string $html, string $url): string
    {
        return str_replace('{{ privacyUrl }}', Html::encode($url), $html);
    }

    private function languageFor(int $siteId): string
    {
        $site = Craft::$app->getSites()->getSiteById($siteId);

        // The library speaks German and English; a document for any other site is English, and
        // so is its banner.
        return $site && str_starts_with(strtolower($site->language), 'de') ? 'de' : 'en';
    }

    private function categoryWord(string $category, string $word, string $language): string
    {
        $value = $this->words()['categories'][$category][$word] ?? null;

        if (!is_array($value)) {
            // A category a site added to Toss: Jack has no words for it, so it uses the name.
            return $word === 'title' ? ucfirst($category) : '';
        }

        return $value[$language] ?? $value['en'] ?? '';
    }

    private function word(string $group, string $key, string $language): string
    {
        $value = $this->words()[$group][$key] ?? [];

        return is_array($value) ? ($value[$language] ?? $value['en'] ?? '') : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function words(): array
    {
        return $this->words ??= require dirname(__DIR__) . '/library/consent.php';
    }

    /**
     * JSON with every {@see CookiePattern} written as a regex literal, safe inside `<script>`.
     *
     * `JSON_HEX_TAG` and friends mean no string in the config — a custom service's label is typed
     * by an editor — can close the script element or open a comment.
     */
    private function toJs(array $config): string
    {
        $patterns = [];

        array_walk_recursive($config, static function(mixed &$value) use (&$patterns) {
            if ($value instanceof CookiePattern) {
                $token = '__jack_pattern_' . count($patterns) . '__';
                $patterns['"' . $token . '"'] = $value->toJs();
                $value = $token;
            }
        });

        $json = Json::encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return strtr($json, $patterns);
    }
}
