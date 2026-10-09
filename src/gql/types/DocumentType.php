<?php

namespace justinholtweb\jack\gql\types;

use craft\gql\types\elements\Element;
use craft\helpers\Json;
use GraphQL\Type\Definition\ResolveInfo;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\gql\interfaces\DocumentInterface;
use justinholtweb\jack\gql\resolvers\DocumentResolver;
use justinholtweb\jack\Plugin;

/**
 * The GraphQL object type for documents. One type: documents have no field layout to vary by.
 *
 * Every text field reads the published copy through the renderer, the same as a rendered page,
 * so GraphQL cannot be the one way out that shows a draft.
 */
class DocumentType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            DocumentInterface::getType(),
        ];

        parent::__construct($config);
    }

    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var Document $source */
        $plugin = Plugin::getInstance();

        return match ($resolveInfo->fieldName) {
            'url' => $source->getUrl(),
            'documentTypeLabel' => $source->typeLabel(),
            'html' => $plugin->renderer->body($source),
            'sections' => $plugin->renderer->toc($source),
            'version' => $plugin->documents->publishedVersion($source)['number'] ?? null,
            'libraryVersion' => ($plugin->documents->publishedVersion($source)['record'] ?? null)?->libraryVersion,
            'stamp' => self::stamp($source),
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }

    protected static function elementResolverClass(): ?string
    {
        return DocumentResolver::class;
    }

    /**
     * @return array<int, array{clause: string, version: string}>
     */
    private static function stamp(Document $document): array
    {
        $record = Plugin::getInstance()->documents->publishedVersion($document)['record'] ?? null;
        $stamp = $record ? Json::decodeIfJson((string)$record->stamp) : [];
        $rows = [];

        foreach (is_array($stamp) ? $stamp : [] as $clause => $version) {
            $rows[] = ['clause' => (string)$clause, 'version' => (string)$version];
        }

        return $rows;
    }
}
