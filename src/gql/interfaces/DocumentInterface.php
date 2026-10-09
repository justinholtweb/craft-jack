<?php

namespace justinholtweb\jack\gql\interfaces;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\gql\types\DateTime;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use justinholtweb\jack\gql\types\generators\DocumentGenerator;
use justinholtweb\jack\gql\types\SectionType;
use justinholtweb\jack\gql\types\StampType;

/**
 * The GraphQL interface a Jack document implements.
 *
 * Carries what the document's own page shows a visitor — the published text, its sections and
 * when it was published — and what a front end needs to say how current it is. The compiled
 * draft, its warnings, missing facts and overrides are the editors', not the public's, so none of
 * them is a field here.
 */
class DocumentInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return DocumentGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by Jack legal documents.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));

        DocumentGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'JackDocumentInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The document’s own page on this site, when documents have URLs here.',
            ],
            'handle' => [
                'name' => 'handle',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The document’s handle — what `craft.jack.render()` and reference tags name.',
            ],
            'documentType' => [
                'name' => 'documentType',
                'type' => Type::nonNull(Type::string()),
                'description' => '`imprint`, `privacy`, `cookies`, `terms`, `withdrawal`, `disclaimer`, `accessibility` or `social`.',
            ],
            'documentTypeLabel' => [
                'name' => 'documentTypeLabel',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The document type’s name — “Privacy policy”.',
            ],
            'jurisdiction' => [
                'name' => 'jurisdiction',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The jurisdiction the document is written for: `de`, `at`, `ch`, `eu`, `uk` or `us`.',
            ],
            'html' => [
                'name' => 'html',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The published text as HTML: sections with stable anchors, headings at level 2, no wrapper.',
            ],
            'sections' => [
                'name' => 'sections',
                'type' => Type::nonNull(Type::listOf(Type::nonNull(SectionType::getType()))),
                'description' => 'The published text’s sections, in order — a contents list.',
            ],
            'publishedAt' => [
                'name' => 'publishedAt',
                'type' => DateTime::getType(),
                'description' => 'When the text was last published — the document’s “last updated” date.',
            ],
            'version' => [
                'name' => 'version',
                'type' => Type::int(),
                'description' => 'Which published version this is on this site: 1 for the first publish.',
            ],
            'libraryVersion' => [
                'name' => 'libraryVersion',
                'type' => Type::string(),
                'description' => 'The date of the clause library the published text was compiled from.',
            ],
            'stamp' => [
                'name' => 'stamp',
                'type' => Type::nonNull(Type::listOf(Type::nonNull(StampType::getType()))),
                'description' => 'The library clauses the published text was compiled from, each with its version.',
            ],
        ]), self::getName());
    }
}
