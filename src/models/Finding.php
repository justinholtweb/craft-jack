<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * One thing wrong with a site's legal texts.
 *
 * Findings are ordered by how much trouble they are, not by how easy they are to explain. A
 * missing imprint is a cease-and-desist letter in Germany; a missing accessibility statement is
 * a thing to do this quarter. `remedy` is mandatory in spirit: a finding nobody can act on is a
 * worry, not a finding.
 */
class Finding extends Model
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_NOTICE = 'notice';

    public string $severity = self::SEVERITY_WARNING;
    public string $title = '';
    public string $detail = '';
    public string $remedy = '';
    public ?string $citation = null;
    public ?string $url = null;
    public ?int $siteId = null;

    public static function critical(string $title, string $detail, string $remedy, ?string $citation = null): self
    {
        return new self([
            'severity' => self::SEVERITY_CRITICAL,
            'title' => $title,
            'detail' => $detail,
            'remedy' => $remedy,
            'citation' => $citation,
        ]);
    }

    public static function warning(string $title, string $detail, string $remedy, ?string $citation = null): self
    {
        return new self([
            'severity' => self::SEVERITY_WARNING,
            'title' => $title,
            'detail' => $detail,
            'remedy' => $remedy,
            'citation' => $citation,
        ]);
    }

    public static function notice(string $title, string $detail, string $remedy, ?string $citation = null): self
    {
        return new self([
            'severity' => self::SEVERITY_NOTICE,
            'title' => $title,
            'detail' => $detail,
            'remedy' => $remedy,
            'citation' => $citation,
        ]);
    }

    public function weight(): int
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 0,
            self::SEVERITY_WARNING => 1,
            default => 2,
        };
    }
}
