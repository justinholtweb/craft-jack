<?php

namespace justinholtweb\jack\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * One section of a published document: its anchor and its heading.
 */
class SectionType
{
    public static function getName(): string
    {
        return 'JackDocumentSection';
    }

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), fn() => new ObjectType([
            'name' => self::getName(),
            'description' => 'A section of a published Jack document.',
            'fields' => [
                'anchor' => ['type' => Type::nonNull(Type::string()), 'description' => 'The section’s `id` in `html`, for a `#` link.'],
                'title' => ['type' => Type::nonNull(Type::string()), 'description' => 'The section heading, as plain text.'],
            ],
        ]));
    }
}
