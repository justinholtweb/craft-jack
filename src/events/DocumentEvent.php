<?php

namespace justinholtweb\jack\events;

use justinholtweb\jack\elements\Document;
use yii\base\Event;

/**
 * Fired around compiling and publishing a document.
 */
class DocumentEvent extends Event
{
    public Document $document;

    /** The note a publisher left, for the publish events. */
    public ?string $note = null;

    /** Set to false in a before-event to stop the operation. */
    public bool $isValid = true;
}
