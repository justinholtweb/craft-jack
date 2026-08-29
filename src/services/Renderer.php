<?php

namespace justinholtweb\jack\services;

use Craft;
use craft\base\Component;
use craft\helpers\Html;
use craft\helpers\Template;
use DateTime;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\Plugin;
use Twig\Markup;

/**
 * Puts a compiled document on a page.
 *
 * Renders the *stored* text and never recompiles on the way out. Front-end rendering that reached
 * for the profile would make a half-typed address appear on a live privacy policy the instant it
 * was saved, and would put a compile — dozens of clause evaluations — on every page view of a
 * document that changes four times a year.
 *
 * The markup is plain and unstyled by design: headings, paragraphs, lists, a table. Legal texts
 * are read, printed and quoted, and every class name Jack invents is one a theme has to override.
 */
class Renderer extends Component
{
    /**
     * @param array<string, mixed> $options
     */
    public function render(Document $document, array $options = []): Markup
    {
        $options += [
            'toc' => false,
            'lastUpdated' => Plugin::getInstance()->getSettings()->showLastUpdated,
            'class' => 'jack-document',
            'headingLevel' => 2,
        ];

        $html = (string)$document->compiledHtml;

        if (trim($html) === '') {
            // Never an exception and never a blank page: a legal document that does not render is
            // a page somebody has to be told about.
            Craft::warning(
                sprintf('Document “%s” has no compiled text for site %s.', $document->handle, $document->siteId),
                Plugin::LOG_CATEGORY,
            );

            return Template::raw('');
        }

        if ($options['headingLevel'] !== 2) {
            $html = $this->shiftHeadings($html, (int)$options['headingLevel'] - 2);
        }

        $parts = [];

        if ($options['toc']) {
            $parts[] = $this->tocHtml($document);
        }

        $parts[] = $html;

        if ($options['lastUpdated']) {
            $parts[] = $this->lastUpdatedHtml($document);
        }

        return Template::raw(Html::tag('div', implode("\n", array_filter($parts)), [
            'class' => $options['class'],
            'data-jack-document' => $document->handle,
        ]));
    }

    /**
     * The document's sections as anchor/title pairs, read back out of the stored markup.
     *
     * Read back rather than recompiled, so that the contents list can never disagree with the
     * document it sits above.
     *
     * @return array<int, array{anchor: string, title: string}>
     */
    public function toc(Document $document): array
    {
        if (!preg_match_all(
            '~<section id="(?P<anchor>[^"]+)"[^>]*>\s*<h2[^>]*>(?P<title>.*?)</h2>~s',
            (string)$document->compiledHtml,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        return array_map(static fn(array $match) => [
            'anchor' => $match['anchor'],
            'title' => trim(html_entity_decode(strip_tags($match['title']), ENT_QUOTES, 'UTF-8')),
        ], $matches);
    }

    private function tocHtml(Document $document): string
    {
        $entries = $this->toc($document);

        if ($entries === []) {
            return '';
        }

        $items = array_map(
            static fn(array $entry) => Html::tag('li', Html::a(Html::encode($entry['title']), '#' . $entry['anchor'])),
            $entries,
        );

        return Html::tag('nav', Html::tag('ol', implode('', $items)), [
            'class' => 'jack-toc',
            'aria-label' => Craft::t('jack', 'Contents'),
        ]);
    }

    private function lastUpdatedHtml(Document $document): string
    {
        $date = $document->publishedAt ?: $document->compiledAt;

        if (!$date) {
            return '';
        }

        $formatted = Craft::$app->getFormatter()->asDate(new DateTime($date), 'long');
        $german = str_starts_with($document->languageForSite(), 'de');

        return Html::tag(
            'p',
            Html::encode(($german ? 'Stand: ' : 'Last updated: ') . $formatted),
            ['class' => 'jack-updated'],
        );
    }

    /**
     * Moves every heading in the document down (or up) by a number of levels.
     *
     * A document dropped inside an entry that already has an `<h1>` and an `<h2>` above it needs
     * its own headings to start lower, or a screen reader is handed a broken outline.
     */
    private function shiftHeadings(string $html, int $by): string
    {
        if ($by === 0) {
            return $html;
        }

        return preg_replace_callback(
            '~<(/?)h([1-6])\b~i',
            static function(array $match) use ($by): string {
                $level = min(6, max(1, (int)$match[2] + $by));

                return '<' . $match[1] . 'h' . $level;
            },
            $html,
        ) ?? $html;
    }
}
