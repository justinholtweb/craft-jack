<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * A third-party service found on the site that the inventory does not mention.
 *
 * `evidence` is the point of this model. "Jack thinks you use Google Fonts" is an argument;
 * "`templates/_layout.twig` line 14 links fonts.googleapis.com" ends it.
 */
class Detection extends Model
{
    public const VIA_TEMPLATE = 'template';
    public const VIA_PLUGIN = 'plugin';
    public const VIA_ENV = 'env';

    public string $serviceKey = '';
    public string $label = '';
    public string $category = '';
    public string $via = self::VIA_TEMPLATE;

    /**
     * Where it was found: file path and line, plugin handle, or environment variable name.
     *
     * @var array<int, array{location: string, line: int|null, excerpt: string}>
     */
    public array $evidence = [];

    /** Already switched on in the inventory — found, but not news. */
    public bool $declared = false;

    public function evidenceCount(): int
    {
        return count($this->evidence);
    }
}
