<?php

namespace justinholtweb\jack\gql\types\generators;

use Craft;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Gql as GqlHelper;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\gql\interfaces\DocumentInterface;
use justinholtweb\jack\gql\types\DocumentType;

/**
 * The one `JackDocument` type, generated only for a schema that may read documents at all.
 */
class DocumentGenerator extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        if (!GqlHelper::isSchemaAwareOf(Document::gqlScopesByContext(null))) {
            return [];
        }

        $type = static::generateType($context);

        return [$type->name => $type];
    }

    public static function generateType(mixed $context): ObjectType
    {
        $typeName = Document::gqlTypeName();

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new DocumentType([
            'name' => $typeName,
            'fields' => fn() => Craft::$app->getGql()->prepareFieldDefinitions(DocumentInterface::getFieldDefinitions(), $typeName),
        ]));
    }
}
