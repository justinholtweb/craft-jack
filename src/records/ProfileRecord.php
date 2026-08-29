<?php

namespace justinholtweb\jack\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $siteId
 * @property string|null $facts
 */
class ProfileRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%jack_profiles}}';
    }
}
