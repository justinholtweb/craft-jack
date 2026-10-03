<?php

namespace justinholtweb\jack\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @template TKey of array-key
 * @template TElement of \justinholtweb\jack\elements\Document
 * @extends ElementQuery<TKey,TElement>
 */
class DocumentQuery extends ElementQuery
{
    public mixed $documentType = null;
    public mixed $handle = null;
    public mixed $jurisdiction = null;

    public function documentType(mixed $value): self
    {
        $this->documentType = $value;

        return $this;
    }

    public function handle(mixed $value): self
    {
        $this->handle = $value;

        return $this;
    }

    public function jurisdiction(mixed $value): self
    {
        $this->jurisdiction = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('jack_documents');

        // The per-site half is joined on the site the query is already resolving, so a document
        // fetched for the German site arrives carrying German text and never English.
        //
        // Outer query only. Craft joins `elements_sites` into the outer query while building it,
        // but into the *subquery* after `beforePrepare()` has run — so the same join there refers
        // to a table that does not exist yet, and every `exists()` call dies on it. Nothing here
        // filters or sorts on a per-site column, so the subquery has no need of it.
        $this->query->leftJoin(
            ['jack_documentsites' => '{{%jack_documentsites}}'],
            '[[jack_documentsites.documentId]] = [[jack_documents.id]] AND [[jack_documentsites.siteId]] = [[elements_sites.siteId]]',
        );

        $this->query->addSelect([
            'jack_documents.documentType',
            'jack_documents.handle',
            'jack_documents.jurisdiction',
            'jack_documentsites.compiledHtml',
            'jack_documentsites.publishedHtml',
            'jack_documentsites.compiledText',
            'jack_documentsites.stamp',
            'jack_documentsites.overrides',
            'jack_documentsites.warnings',
            'jack_documentsites.missingFacts',
            'jack_documentsites.compiledAt',
            'jack_documentsites.publishedAt',
        ]);

        if ($this->documentType) {
            $this->subQuery->andWhere(Db::parseParam('jack_documents.documentType', $this->documentType));
        }

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam('jack_documents.handle', $this->handle));
        }

        if ($this->jurisdiction) {
            $this->subQuery->andWhere(Db::parseParam('jack_documents.jurisdiction', $this->jurisdiction));
        }

        // `ref` is what a `{jack:privacy:render}` tag arrives as. Each element type has to apply
        // it itself — the base query only stores it — and without this the tag silently resolves
        // to whichever document happens to sort first.
        if ($this->ref) {
            $this->subQuery->andWhere(Db::parseParam('jack_documents.handle', $this->ref));
        }

        return parent::beforePrepare();
    }
}
