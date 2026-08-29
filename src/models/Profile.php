<?php

namespace justinholtweb\jack\models;

use craft\base\Model;

/**
 * The facts about a site, as one flat map.
 *
 * There is a single global profile plus, on multi-site installs, an override map per site holding
 * only the keys that differ. That shape is chosen for the common case rather than the tidy one: a
 * German site and its English translation are two Craft sites with one company behind them, and
 * making somebody type the company address twice would guarantee that one of the two copies is
 * eventually wrong.
 *
 * @see justinholtweb\jack\services\Profiles::factsForSite()
 */
class Profile extends Model
{
    public ?int $id = null;

    /** Null for the global profile; a site ID for an override map. */
    public ?int $siteId = null;

    /** @var array<string, mixed> */
    public array $facts = [];

    public ?string $uid = null;

    public function defineRules(): array
    {
        return [
            [['siteId'], 'integer'],
            [['facts'], 'safe'],
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->facts[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public function isOverride(): bool
    {
        return $this->siteId !== null;
    }
}
