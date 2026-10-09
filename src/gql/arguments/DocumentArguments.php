<?php

namespace justinholtweb\jack\gql\arguments;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

/**
 * Arguments for `jackDocuments`, `jackDocument` and `jackDocumentCount`.
 *
 * Craft's element arguments, plus the three things a document is looked up by. `status` and
 * `trashed` are accepted, as on every element query, but the resolver overrides them: only
 * enabled, published documents are public.
 */
class DocumentArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'handle' => [
                'name' => 'handle',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the results to documents with these handles.',
            ],
            'documentType' => [
                'name' => 'documentType',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the results by type: `imprint`, `privacy`, `cookies`, `terms`, `withdrawal`, `disclaimer`, `accessibility`, `social`.',
            ],
            'jurisdiction' => [
                'name' => 'jurisdiction',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the results by jurisdiction: `de`, `at`, `ch`, `eu`, `uk`, `us`.',
            ],
        ]);
    }
}
