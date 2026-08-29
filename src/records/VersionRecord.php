<?php

namespace justinholtweb\jack\records;

use craft\db\ActiveRecord;

/**
 * A published version of a document, kept forever.
 *
 * Append-only on purpose. The question a legal team eventually asks is "what did our privacy
 * policy say on the day this customer signed up", and no amount of current-state modelling
 * answers it.
 *
 * @property int $id
 * @property int $documentId
 * @property int $siteId
 * @property string|null $html
 * @property string|null $stamp
 * @property string|null $note
 * @property string|null $libraryVersion
 */
class VersionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%jack_versions}}';
    }
}
