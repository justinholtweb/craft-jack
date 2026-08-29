<?php

namespace justinholtweb\jack\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $serviceKey
 * @property bool $enabled
 * @property bool $custom
 * @property int $sortOrder
 * @property string|null $overrides
 */
class ServiceRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%jack_services}}';
    }
}
