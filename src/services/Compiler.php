<?php

namespace justinholtweb\jack\services;

use craft\base\Component;
use craft\helpers\FileHelper;
use HTMLPurifier;
use HTMLPurifier_Config;
use justinholtweb\jack\models\Clause;
use justinholtweb\jack\models\CompiledDocument;
use justinholtweb\jack\models\Section;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;

/**
 * Facts plus inventory plus library, in — a document, out.
 *
 * The compiler is a pure function of things it is handed: the same profile, the same inventory and
 * the same library always produce the same bytes. That is not tidiness for its own sake. It is
 * what makes the staleness check meaningful (a difference in output means a difference in input,
 * never a difference in mood), what lets the CI check assert something, and what makes a diff
 * between two compilations readable.
 *
 * Two small languages do all the work:
 *
 * - `{{ dotted.key }}` interpolates a fact. Unknown or empty keys render as nothing and are
 *   collected as warnings rather than left as literal braces in a published legal text.
 * - `[[ … ]]` marks an optional run that disappears entirely if any placeholder inside it is
 *   empty. Nesting works, innermost first, which is how an address block can carry an optional
 *   country line inside an optional paragraph.
 *
 * Neither is Twig, deliberately. Clause bodies are edited by people who are not developers, and a
 * template language in that position is an execution surface.
 */
class Compiler extends Component
{
    /**
     * Derived facts that are already HTML and must not be escaped again.
     *
     * The list is short and closed on purpose: everything else that reaches a document is escaped,
     * so a company name containing an ampersand cannot break the page and a fact cannot smuggle
     * markup into a legal text.
     */
    private const RAW_FACTS = [
        'company.address',
        'controller.display',
        'withdrawal.address',
    ];

    private ?HTMLPurifier $purifier = null;

    /**
     * @param array<string, mixed> $overrides per-document clause overrides and custom sections
     */
    public function compile(
        string $documentType,
        string $jurisdiction = 'de',
        ?int $siteId = null,
        string $language = 'de',
        array $overrides = [],
    ): CompiledDocument {
        $plugin = Plugin::getInstance();
        $facts = $plugin->profiles->factMap($siteId, $language);
        $inventory = $plugin->inventory;

        $compiled = new CompiledDocument([
            'language' => $language,
            'documentType' => $documentType,
            'jurisdiction' => $jurisdiction,
        ]);

        foreach ($plugin->library->clausesFor($documentType, $jurisdiction) as $clause) {
            $override = $overrides[$clause->key] ?? [];

            if (!empty($override['disabled'])) {
                continue;
            }

            if (!$this->applies($clause, $facts, $jurisdiction, $language)) {
                continue;
            }

            $section = $this->renderClause($clause, $facts, $language, $override, $compiled);

            if ($section === null) {
                continue;
            }

            $compiled->sections[] = $section;
            $compiled->stamp[$clause->key] = $this->stampValue($clause, $section);
        }

        foreach ($this->customSections($overrides, $facts, $compiled) as $section) {
            $compiled->sections[] = $section;
        }

        usort($compiled->sections, static fn(Section $a, Section $b) => [$a->order, $a->clauseKey] <=> [$b->order, $b->clauseKey]);

        $compiled->missingFacts = array_values(array_unique($compiled->missingFacts));

        if ($compiled->sections === []) {
            // A document with no sections is a published page with a heading and nothing under
            // it. That reads as an oversight to a visitor and is invisible on any screen that
            // lists what exists, so it has to be said out loud here.
            $compiled->warnings[] = 'No clause in the library applies to this document with the facts Jack has, so it would be published empty.';
        }

        if ($documentType === 'privacy' && $inventory->enabled() === []) {
            $compiled->warnings[] = 'The inventory is empty, so the privacy policy names no recipients.';
        }

        return $compiled;
    }

    /**
     * What goes in the stamp for one section.
     *
     * A written clause is stamped with its version, which is the thing that moves when the
     * library is revised. A *generated* one has no version worth the name — `privacy.services`
     * has said the same "here are the recipients" for years while its contents changed every time
     * somebody added a payment provider — so it carries a fingerprint of what it produced. That is
     * what lets the drift report say "the services section changed" rather than staying silent
     * about the half of the privacy policy that actually moves.
     */
    private function stampValue(Clause $clause, Section $section): string
    {
        if ($clause->generator === null && $section->source !== Section::SOURCE_OVERRIDE) {
            return $clause->version;
        }

        return $clause->version . '@' . substr(sha1($section->html), 0, 8);
    }

    /**
     * Whether a clause's conditions hold.
     *
     * Conditions are declarative arrays rather than expressions. Every named condition must pass
     * (they are ANDed), and the values inside one condition are alternatives (ORed). That covers
     * every clause in the library without giving a legal text an expression evaluator.
     *
     * @param array<string, mixed> $facts
     */
    public function applies(Clause $clause, array $facts, string $jurisdiction, string $language): bool
    {
        if ($clause->when !== [] && !$this->conditionsHold($clause->when, $facts, $jurisdiction, $language)) {
            return false;
        }

        if ($clause->unless !== [] && $this->conditionsHold($clause->unless, $facts, $jurisdiction, $language)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $facts
     */
    private function conditionsHold(array $conditions, array $facts, string $jurisdiction, string $language): bool
    {
        $inventory = Plugin::getInstance()->inventory;

        foreach ($conditions as $type => $value) {
            $holds = match ($type) {
                'factTruthy' => $this->every((array)$value, static fn($key) => !empty($facts[$key])),
                'factFalsy' => $this->every((array)$value, static fn($key) => empty($facts[$key])),
                'factFilled' => $this->every((array)$value, static fn($key) => trim((string)($facts[$key] ?? '')) !== ''),
                'factIn' => $this->every(array_keys((array)$value), static fn($key) => in_array($facts[$key] ?? null, (array)$value[$key], true)),
                'jurisdiction' => in_array($jurisdiction, (array)$value, true),
                'language' => in_array(substr($language, 0, 2), (array)$value, true),
                'service' => $this->any((array)$value, static fn($key) => $inventory->isEnabled($key)),
                'category' => $inventory->hasCategory((array)$value),
                'anyTransfer' => $inventory->hasTransfers() === (bool)$value,
                default => true,
            };

            if (!$holds) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $facts
     * @param array<string, mixed> $override
     */
    private function renderClause(
        Clause $clause,
        array $facts,
        string $language,
        array $override,
        CompiledDocument $compiled,
    ): ?Section {
        $missing = [];
        $isOverridden = isset($override['body']) && trim((string)$override['body']) !== '';

        if ($clause->generator !== null && !$isOverridden) {
            $body = $this->generate($clause, $facts, $language, $compiled);

            if ($body === null) {
                return null;
            }
        } else {
            $raw = $isOverridden ? (string)$override['body'] : $clause->body($language);
            $body = $this->resolve($raw, $facts, $missing);

            if ($isOverridden) {
                $body = $this->purify($body);
            }
        }

        if (trim($body) === '') {
            return null;
        }

        foreach ($clause->requires as $key) {
            if (trim((string)($facts[$key] ?? '')) === '') {
                $missing[] = $key;
            }
        }

        $compiled->missingFacts = array_merge($compiled->missingFacts, $missing);

        $heading = isset($override['heading']) && trim((string)$override['heading']) !== ''
            ? (string)$override['heading']
            : $clause->heading($language);

        return new Section([
            'clauseKey' => $clause->key,
            'clauseVersion' => $clause->version,
            'heading' => $this->resolve($heading, $facts, $missing, false),
            'html' => $body,
            'order' => isset($override['order']) ? (int)$override['order'] : $clause->order,
            'source' => $isOverridden
                ? Section::SOURCE_OVERRIDE
                : ($clause->generator !== null ? Section::SOURCE_GENERATED : Section::SOURCE_LIBRARY),
            'citation' => $clause->citation,
            'note' => $clause->note($language),
            'missingFacts' => array_values(array_unique($missing)),
        ]);
    }

    /**
     * Sections a person added that the library has no clause for.
     *
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $facts
     * @return Section[]
     */
    private function customSections(array $overrides, array $facts, CompiledDocument $compiled): array
    {
        $sections = [];

        foreach ($overrides as $key => $override) {
            if (!str_starts_with((string)$key, 'custom:') || !empty($override['disabled'])) {
                continue;
            }

            $body = trim((string)($override['body'] ?? ''));

            if ($body === '') {
                continue;
            }

            $missing = [];
            $sections[] = new Section([
                'clauseKey' => (string)$key,
                'clauseVersion' => '',
                'heading' => $this->resolve((string)($override['heading'] ?? ''), $facts, $missing, false),
                'html' => $this->purify($this->resolve($body, $facts, $missing)),
                'order' => (int)($override['order'] ?? 990),
                'source' => Section::SOURCE_CUSTOM,
                'missingFacts' => array_values(array_unique($missing)),
            ]);

            $compiled->missingFacts = array_merge($compiled->missingFacts, $missing);
        }

        return $sections;
    }

    // -----------------------------------------------------------------------------------------
    // Generators
    // -----------------------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $facts
     */
    private function generate(Clause $clause, array $facts, string $language, CompiledDocument $compiled): ?string
    {
        $intro = $this->resolve($clause->body($language), $facts, $compiled->missingFacts);

        $generated = match ($clause->generator) {
            'services' => $this->generateServices($language, $compiled),
            'cookieTable' => $this->generateCookieTable($language),
            'accessibilityIssues' => $this->generateIssues($facts),
            default => null,
        };

        if ($generated === null || trim($generated) === '') {
            return null;
        }

        return $intro . "\n" . $generated;
    }

    /**
     * One subsection per service in the inventory, grouped by category.
     */
    private function generateServices(string $language, CompiledDocument $compiled): ?string
    {
        $plugin = Plugin::getInstance();
        $grouped = $plugin->inventory->enabledByCategory();

        if ($grouped === []) {
            return null;
        }

        $order = array_keys($plugin->library->templates()['categories'] ?? []);
        uksort($grouped, static function($a, $b) use ($order) {
            $ai = array_search($a, $order, true);
            $bi = array_search($b, $order, true);

            return [$ai === false ? PHP_INT_MAX : $ai, $a] <=> [$bi === false ? PHP_INT_MAX : $bi, $b];
        });

        $out = [];

        foreach ($grouped as $category => $entries) {
            $label = $plugin->library->template('categories', $category, $language)
                ?: Inventory::categoryLabel($category);

            $out[] = '<h3>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h3>';

            foreach ($entries as $entry) {
                $out[] = $this->renderService($entry, $language, $compiled);
            }
        }

        return implode("\n", array_filter($out));
    }

    private function renderService(ServiceEntry $entry, string $language, CompiledDocument $compiled): string
    {
        $library = Plugin::getInstance()->library;

        if (!$entry->isKnown() && $entry->text('purpose', $language) === '') {
            $compiled->warnings[] = sprintf(
                'The inventory entry “%s” has no description, so it cannot be written up.',
                $entry->serviceKey,
            );

            return '';
        }

        $facts = [
            'service.label' => $entry->label($language),
            'service.provider' => (string)$entry->get('provider', ''),
            'service.providerAddress' => (string)$entry->get('providerAddress', ''),
            'service.parent' => (string)$entry->get('parent', ''),
            'service.purpose' => $entry->text('purpose', $language),
            'service.data' => $entry->text('data', $language),
            'service.retention' => $entry->text('retention', $language),
            'service.privacyUrl' => (string)$entry->get('privacyUrl', ''),
            'service.optOutUrl' => (string)$entry->get('optOutUrl', ''),
        ];

        $missing = [];
        $parts = ['<h4>' . htmlspecialchars($entry->label($language), ENT_QUOTES, 'UTF-8') . '</h4>'];

        $parts[] = $this->resolve(
            $library->template('service', $entry->isFirstParty() ? 'introFirstParty' : 'intro', $language),
            $facts,
            $missing,
        );

        if (!$entry->isFirstParty() && $facts['service.provider'] !== '') {
            $parts[] = $this->resolve($library->template('service', 'provider', $language), $facts, $missing);
        }

        if ($facts['service.data'] !== '') {
            $parts[] = $this->resolve($library->template('service', 'data', $language), $facts, $missing);
        }

        $parts[] = $this->resolve(
            $library->template('service', 'basis.' . $entry->legalBasis(), $language),
            $facts,
            $missing,
        );

        if ($entry->isEssential() && $entry->cookies() !== []) {
            $parts[] = $library->template('service', 'strictlyNecessary', $language);
        }

        if ($facts['service.retention'] !== '') {
            $parts[] = $this->resolve($library->template('service', 'retention', $language), $facts, $missing);
        }

        if ($entry->transfer() !== ServiceEntry::TRANSFER_NONE) {
            $parts[] = $this->resolve(
                $library->template('service', 'transfer.' . $entry->transfer(), $language),
                $facts,
                $missing,
            );
        }

        if ($entry->get('jointController')) {
            $parts[] = $library->template('service', 'jointController', $language);
        }

        if ($entry->get('cookieless')) {
            $parts[] = $library->template('service', 'cookieless', $language);
        }

        $cookies = $entry->cookiesFor($language);

        if ($cookies !== []) {
            $items = [];

            foreach ($cookies as $cookie) {
                $items[] = $this->resolve(
                    $library->template('service', 'cookieItem', $language),
                    [
                        'cookie.name' => $cookie['name'],
                        'cookie.duration' => $cookie['duration'],
                    ],
                    $missing,
                );
            }

            $parts[] = $library->template('service', 'cookiesIntro', $language) . '<ul>' . implode('', $items) . '</ul>';
        }

        $note = $entry->text('notes', $language);

        if ($note !== '') {
            $parts[] = '<p>' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if ($facts['service.privacyUrl'] !== '' || $facts['service.optOutUrl'] !== '') {
            $parts[] = $this->resolve($library->template('service', 'links', $language), $facts, $missing);
        }

        return implode("\n", array_filter($parts, static fn($part) => trim((string)$part) !== ''));
    }

    private function generateCookieTable(string $language): string
    {
        $plugin = Plugin::getInstance();
        $rows = $plugin->inventory->cookies($language);

        if ($rows === []) {
            return $plugin->library->template('cookieTable', 'empty', $language);
        }

        $necessary = $plugin->library->template('cookieTable', 'necessary', $language);
        $missing = [];
        $body = [];

        foreach ($rows as $row) {
            $category = $row['essential']
                ? $necessary
                : ($plugin->library->template('categories', $row['category'], $language)
                    ?: Inventory::categoryLabel($row['category']));

            $body[] = $this->resolve(
                $plugin->library->template('cookieTable', 'row', $language),
                [
                    'cookie.name' => $row['name'],
                    'cookie.service' => $row['service'],
                    'cookie.category' => $category,
                    'cookie.duration' => $row['duration'],
                ],
                $missing,
            );
        }

        return '<table class="jack-cookies"><thead>'
            . $plugin->library->template('cookieTable', 'columns', $language)
            . '</thead><tbody>' . implode('', $body) . '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $facts
     */
    private function generateIssues(array $facts): ?string
    {
        $lines = preg_split('~\R~', trim((string)($facts['accessibility.knownIssues'] ?? ''))) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '') {
                $items[] = '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }

        return $items === [] ? null : '<ul>' . implode('', $items) . '</ul>';
    }

    // -----------------------------------------------------------------------------------------
    // The two little languages
    // -----------------------------------------------------------------------------------------

    /**
     * Resolves `[[ optional ]]` runs and `{{ fact }}` placeholders.
     *
     * @param array<string, mixed> $facts
     * @param string[] $missing collects fact keys that were asked for and were not there
     */
    public function resolve(string $template, array $facts, array &$missing, bool $html = true): string
    {
        if ($template === '') {
            return '';
        }

        $template = $this->resolveOptional($template, $facts);

        return preg_replace_callback(
            '~\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}~',
            function(array $match) use ($facts, &$missing, $html): string {
                $key = $match[1];
                $value = $facts[$key] ?? null;

                if ($value === null || $value === '' || $value === false) {
                    $missing[] = $key;

                    return '';
                }

                if (!$html || in_array($key, self::RAW_FACTS, true)) {
                    return (string)$value;
                }

                $escaped = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

                return $this->isMultiline($key) ? nl2br($escaped) : $escaped;
            },
            $template,
        ) ?? '';
    }

    /**
     * Drops `[[ … ]]` runs whose placeholders are not all filled, innermost first.
     *
     * @param array<string, mixed> $facts
     */
    private function resolveOptional(string $template, array $facts): string
    {
        // Innermost first: the pattern matches only runs containing no further markers, so each
        // pass peels one layer. An outer run can then judge itself on what is left of its inner
        // ones, which is what makes an optional line inside an optional paragraph behave.
        $guard = 0;

        while (str_contains($template, '[[') && $guard++ < 20) {
            $replaced = preg_replace_callback(
                '~\[\[((?:(?!\[\[|\]\]).)*)\]\]~s',
                static function(array $match) use ($facts): string {
                    $inner = $match[1];

                    if (!preg_match_all('~\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}~', $inner, $keys)) {
                        // No placeholders left inside — an inner run already resolved, so keep it.
                        return $inner;
                    }

                    foreach ($keys[1] as $key) {
                        $value = $facts[$key] ?? null;

                        if ($value === null || $value === '' || $value === false) {
                            return '';
                        }
                    }

                    return $inner;
                },
                $template,
            );

            if ($replaced === null || $replaced === $template) {
                break;
            }

            $template = $replaced;
        }

        // An unbalanced marker is an authoring mistake in an override; strip it rather than print
        // it in a legal text.
        return str_replace(['[[', ']]'], '', $template);
    }

    private function isMultiline(string $key): bool
    {
        return (Plugin::getInstance()->library->fact($key)['type'] ?? '') === 'textarea';
    }

    /**
     * @param array<int, mixed> $values
     */
    private function every(array $values, callable $test): bool
    {
        foreach ($values as $value) {
            if (!$test($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $values
     */
    private function any(array $values, callable $test): bool
    {
        foreach ($values as $value) {
            if ($test($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cleans HTML that came from a person rather than from the library.
     */
    public function purify(string $html): string
    {
        if (!Plugin::getInstance()->getSettings()->purifyOutput || trim($html) === '') {
            return $html;
        }

        return $this->purifier()->purify($html);
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier !== null) {
            return $this->purifier;
        }

        $cachePath = \Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'jack' . DIRECTORY_SEPARATOR . 'htmlpurifier';

        // HTML Purifier will not create this directory and throws if it is missing, which turns
        // the first override on a fresh install into a 500.
        FileHelper::createDirectory($cachePath);

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['noopener', 'nofollow', 'noreferrer']);
        $config->set('Attr.EnableID', true);
        $config->set('HTML.AllowedElements', 'p,br,strong,em,b,i,u,a,ul,ol,li,h3,h4,h5,table,thead,tbody,tr,th,td,code,blockquote,small,sup,sub,hr,span,dl,dt,dd');
        $config->set('HTML.AllowedAttributes', 'a.href,a.rel,a.target,a.title,th.scope,td.colspan,th.colspan,span.class,table.class');
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);

        return $this->purifier = new HTMLPurifier($config);
    }
}
