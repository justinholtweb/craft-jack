<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * The result of compiling one document for one site: sections, warnings, and a stamp.
 *
 * The **stamp** is the interesting part — `clauseKey => version` for every clause that made it
 * into the document. Comparing a stored stamp against the current library is how Jack knows a
 * published privacy policy predates a wording change, and comparing them key by key is how it
 * knows *which* paragraph moved. Without it, "your documents may be out of date" is a banner
 * nobody can act on.
 */
class CompiledDocument extends Model
{
    /** @var Section[] */
    public array $sections = [];

    /**
     * Fact keys some clause needed and the profile did not have, deduplicated.
     *
     * @var string[]
     */
    public array $missingFacts = [];

    /**
     * Free-text warnings that are not about a missing fact — an inventory entry with no library
     * definition, an edition limit that dropped something.
     *
     * @var string[]
     */
    public array $warnings = [];

    /** @var array<string, string> clause key => clause version */
    public array $stamp = [];

    public string $language = 'en';
    public string $documentType = '';
    public string $jurisdiction = 'de';

    public function isComplete(): bool
    {
        return $this->missingFacts === [];
    }

    public function toHtml(): string
    {
        $out = [];

        foreach ($this->sections as $section) {
            $out[] = sprintf(
                '<section id="%s" class="jack-section">%s%s</section>',
                htmlspecialchars($section->anchor(), ENT_QUOTES, 'UTF-8'),
                $section->heading !== ''
                    ? '<h2>' . htmlspecialchars($section->heading, ENT_QUOTES, 'UTF-8') . '</h2>'
                    : '',
                $section->html,
            );
        }

        return implode("\n", $out);
    }

    /**
     * Plain text, for a diff or an email. Not a rendering target — nobody publishes this.
     */
    public function toText(): string
    {
        $out = [];

        foreach ($this->sections as $section) {
            if ($section->heading !== '') {
                $out[] = $section->heading;
                $out[] = str_repeat('-', mb_strlen($section->heading));
            }

            $text = preg_replace('~<(li|p|div|h[1-6]|tr)\b[^>]*>~i', "\n", $section->html);
            $text = html_entity_decode(strip_tags($text ?? ''), ENT_QUOTES, 'UTF-8');
            $text = preg_replace("~\n{3,}~", "\n\n", trim($text));

            $out[] = $text;
            $out[] = '';
        }

        return trim(implode("\n", $out));
    }
}
