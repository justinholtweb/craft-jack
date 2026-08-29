<?php

namespace justinholtweb\jack\fields;

use Craft;
use craft\fields\BaseRelationField;
use justinholtweb\jack\elements\Document;

/**
 * A field that points at a legal document.
 *
 * A relation field rather than a copy of the text: the point of Jack is that there is one privacy
 * policy and it is current. A field that stored a snapshot would create a second one that is not.
 *
 * Typical use is a "legal links" section in the footer, or an entry that exists to give a document
 * a place in the site's own navigation.
 */
class DocumentField extends BaseRelationField
{
    public static function displayName(): string
    {
        return Craft::t('jack', 'Legal document');
    }

    public static function icon(): string
    {
        return 'scale-balanced';
    }

    public static function elementType(): string
    {
        return Document::class;
    }

    public static function defaultSelectionLabel(): string
    {
        return Craft::t('jack', 'Add a legal document');
    }

}
