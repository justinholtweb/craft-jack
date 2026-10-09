<?php

namespace justinholtweb\jack\gql;

use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;
use justinholtweb\jack\gql\arguments\DocumentArguments;
use justinholtweb\jack\gql\interfaces\DocumentInterface;
use justinholtweb\jack\gql\resolvers\DocumentResolver;

/**
 * The GraphQL queries Jack registers, prefixed `jack` so they cannot collide with anybody else's
 * `documents`.
 *
 * Published legal texts and nothing else. There is no field for the draft a compile produced, its
 * warnings, the missing facts, overrides, the profile or the inventory, under any permission: a
 * schema token is far easier to leak than a control-panel session, and a half-typed address must
 * not reach a headless front end any more than it may reach a rendered page.
 */
class DocumentQueries
{
    /** The schema component that unlocks every query here. */
    public const SCOPE = 'jackDocuments.all';

    /** @return array<string, array<string, mixed>> */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !self::canQueryDocuments()) {
            return [];
        }

        return [
            'jackDocuments' => [
                'type' => Type::listOf(DocumentInterface::getType()),
                'args' => DocumentArguments::getArguments(),
                'resolve' => DocumentResolver::class . '::resolve',
                'description' => 'This query is used to query for published Jack legal documents.',
                'complexity' => GqlHelper::relatedArgumentComplexity(),
            ],
            'jackDocumentCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => DocumentArguments::getArguments(),
                'resolve' => DocumentResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of published Jack legal documents.',
                'complexity' => GqlHelper::relatedArgumentComplexity(),
            ],
            'jackDocument' => [
                'type' => DocumentInterface::getType(),
                'args' => DocumentArguments::getArguments(),
                'resolve' => DocumentResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a single published Jack legal document.',
                'complexity' => GqlHelper::relatedArgumentComplexity(),
            ],
        ];
    }

    /** Whether the active schema may read documents. */
    public static function canQueryDocuments(): bool
    {
        return GqlHelper::canSchema(self::SCOPE);
    }
}
