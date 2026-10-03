<?php

namespace justinholtweb\jack\records;

use craft\db\ActiveRecord;
use craft\helpers\DateTimeHelper;
use DateTime;

/**
 * A published version of a document, kept for as long as the document exists (a trashed
 * document takes its history with it once Craft garbage-collects it).
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
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 * @property-read DateTime $created
 */
class VersionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%jack_versions}}';
    }

    /**
     * When the version was published, as a date. The column is naive UTC, which `|datetime` on the
     * raw string would read as system time.
     */
    public function getCreated(): DateTime
    {
        return DateTimeHelper::toDateTime($this->dateCreated) ?: new DateTime();
    }
}
