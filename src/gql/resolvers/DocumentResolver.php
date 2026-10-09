<?php

namespace justinholtweb\jack\gql\resolvers;

use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\elements\ElementCollection;
use craft\gql\base\ElementResolver;
use justinholtweb\jack\elements\db\DocumentQuery;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\gql\DocumentQueries;
use yii\base\UnknownMethodException;

/**
 * Resolves document queries.
 *
 * GraphQL is a public door the same as a document's own page, so it shows what that shows:
 * enabled documents with published text, on the sites asked for. Craft's inactive-elements
 * permission does not widen it — a disabled legal text is one an editor switched off because it
 * was wrong, and a trashed or never-published one has nothing a visitor may read.
 */
class DocumentResolver extends ElementResolver
{
    /** The most documents one query builds. There are eight types; this is generous. */
    public const MAX_LIMIT = 100;

    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        $query = $source === null ? Document::find() : $source->$fieldName;

        if (!$query instanceof ElementQuery) {
            return $query;
        }

        if (!DocumentQueries::canQueryDocuments()) {
            return ElementCollection::empty();
        }

        foreach ($arguments as $key => $value) {
            try {
                $query->$key($value);
            } catch (UnknownMethodException $e) {
                if ($value !== null) {
                    throw $e;
                }
            }
        }

        /** @var DocumentQuery $query */
        $query
            ->status(Element::STATUS_ENABLED)
            ->trashed(false)
            ->published();

        if ($query->limit === null || (int)$query->limit > self::MAX_LIMIT) {
            $query->limit(self::MAX_LIMIT);
        }

        return $query;
    }
}
