<?php

namespace justinholtweb\jack\records;

use craft\db\ActiveRecord;

/**
 * The per-site half of a document: the compiled text, and what it was compiled from.
 *
 * @property int $id
 * @property int $documentId
 * @property int $siteId
 * @property string|null $compiledHtml
 * @property string|null $compiledText
 * @property string|null $stamp
 * @property string|null $overrides
 * @property string|null $warnings
 * @property string|null $missingFacts
 * @property string|null $compiledAt
 * @property string|null $publishedAt
 */
class DocumentSiteRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%jack_documentsites}}';
    }
}
