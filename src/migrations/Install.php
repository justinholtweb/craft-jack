<?php

namespace justinholtweb\jack\migrations;

use craft\db\Migration;
use craft\db\Table;
use craft\helpers\Db;

/**
 * Jack's schema.
 *
 * Five tables. Documents split into a non-localized half (what kind of document this is) and a
 * per-site half (what it currently says), because the type of a document is the same everywhere
 * and its text is not. Versions are append-only. Profiles hold one global row plus per-site
 * override rows.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->seed();

        return true;
    }

    public function safeDown(): bool
    {
        // Dropped children first: the FKs point upward, and Postgres will not let a referenced
        // table go while a referencing one still exists.
        $this->dropTableIfExists('{{%jack_versions}}');
        $this->dropTableIfExists('{{%jack_documentsites}}');
        $this->dropTableIfExists('{{%jack_documents}}');
        $this->dropTableIfExists('{{%jack_services}}');
        $this->dropTableIfExists('{{%jack_profiles}}');

        // The element rows outlive the tables otherwise, and Craft trips over a type it can no
        // longer load the next time anything lists elements.
        $this->delete(Table::ELEMENTS, ['type' => 'justinholtweb\\jack\\elements\\Document']);

        return true;
    }

    private function createTables(): void
    {
        $this->createTable('{{%jack_documents}}', [
            'id' => $this->integer()->notNull(),
            'documentType' => $this->string(32)->notNull(),
            'handle' => $this->string(64)->notNull(),
            'jurisdiction' => $this->string(8)->notNull()->defaultValue('de'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createTable('{{%jack_documentsites}}', [
            'id' => $this->primaryKey(),
            'documentId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'compiledHtml' => $this->mediumText(),
            // What visitors see. Set only by publishing, so a recompile never reaches the site.
            'publishedHtml' => $this->mediumText(),
            'compiledText' => $this->mediumText(),
            'stamp' => $this->text(),
            'overrides' => $this->text(),
            'warnings' => $this->text(),
            'missingFacts' => $this->text(),
            'compiledAt' => $this->dateTime(),
            'publishedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%jack_profiles}}', [
            'id' => $this->primaryKey(),
            // Null is the global profile. Site rows hold only the keys that differ from it.
            'siteId' => $this->integer(),
            'facts' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%jack_services}}', [
            'id' => $this->primaryKey(),
            'serviceKey' => $this->string(128)->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'custom' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'overrides' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%jack_versions}}', [
            'id' => $this->primaryKey(),
            'documentId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'html' => $this->mediumText(),
            'stamp' => $this->text(),
            'note' => $this->string(255),
            'libraryVersion' => $this->string(32),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createIndexes(): void
    {
        $this->createIndex(null, '{{%jack_documents}}', ['handle'], true);
        $this->createIndex(null, '{{%jack_documents}}', ['documentType'], false);
        $this->createIndex(null, '{{%jack_documentsites}}', ['documentId', 'siteId'], true);
        $this->createIndex(null, '{{%jack_documentsites}}', ['siteId'], false);
        $this->createIndex(null, '{{%jack_profiles}}', ['siteId'], false);
        $this->createIndex(null, '{{%jack_services}}', ['serviceKey'], true);
        $this->createIndex(null, '{{%jack_versions}}', ['documentId', 'siteId', 'dateCreated'], false);
    }

    private function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%jack_documents}}', ['id'], Table::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%jack_documentsites}}', ['documentId'], '{{%jack_documents}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%jack_documentsites}}', ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%jack_profiles}}', ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%jack_versions}}', ['documentId'], '{{%jack_documents}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%jack_versions}}', ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * A fresh install is not blank.
     *
     * Every Craft site keeps server logs and sets a session cookie, so both are in the inventory
     * from the first minute and cannot be switched off. Starting empty would invite a privacy
     * policy whose first honest omission is the one thing that is certainly true.
     */
    private function seed(): void
    {
        $now = Db::prepareDateForDb(new \DateTime());

        $this->insert('{{%jack_profiles}}', [
            'siteId' => null,
            'facts' => '{}',
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => \craft\helpers\StringHelper::UUID(),
        ]);

        foreach (['server_logs' => 0, 'craft_sessions' => 1] as $key => $order) {
            $this->insert('{{%jack_services}}', [
                'serviceKey' => $key,
                'enabled' => true,
                'custom' => false,
                'sortOrder' => $order,
                'overrides' => '{}',
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);
        }
    }
}
