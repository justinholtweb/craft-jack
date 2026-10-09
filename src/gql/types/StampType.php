<?php

namespace justinholtweb\jack\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * One library clause a published document was compiled from, and the clause's version.
 */
class StampType
{
    public static function getName(): string
    {
        return 'JackClauseStamp';
    }

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new ObjectType([
            'name' => self::getName(),
            'description' => 'A library clause and the version of it a published Jack document carries.',
            'fields' => [
                'clause' => ['type' => Type::nonNull(Type::string()), 'description' => 'The clause key — `privacy.controller`.'],
                'version' => ['type' => Type::nonNull(Type::string()), 'description' => 'The clause’s version when the text was published.'],
            ],
        ]));
    }
}
