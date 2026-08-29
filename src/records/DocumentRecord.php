<?php

namespace justinholtweb\jack\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $documentType
 * @property string $handle
 * @property string $jurisdiction
 */
class DocumentRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%jack_documents}}';
    }
}
