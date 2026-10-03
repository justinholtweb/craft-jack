<?php

namespace justinholtweb\jack\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * Gives each per-site document row its own published text.
 *
 * Until now the site rendered the compiled text, which every profile and inventory save rewrites.
 * The backfill takes the latest published version where there is one and the compiled text where
 * there is not, so no page that rendered before this migration renders blank after it.
 */
class m261003_000000_published_html extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%jack_documentsites}}', 'publishedHtml')) {
            $this->addColumn('{{%jack_documentsites}}', 'publishedHtml', $this->mediumText()->after('compiledHtml'));
        }

        $rows = (new Query())
            ->select(['id', 'documentId', 'siteId', 'compiledHtml'])
            ->from('{{%jack_documentsites}}')
            ->where(['publishedHtml' => null])
            ->all($this->db);

        foreach ($rows as $row) {
            $html = (new Query())
                ->select('html')
                ->from('{{%jack_versions}}')
                ->where(['documentId' => $row['documentId'], 'siteId' => $row['siteId']])
                ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
                ->scalar($this->db);

            $this->update('{{%jack_documentsites}}', [
                'publishedHtml' => $html !== false ? $html : $row['compiledHtml'],
            ], ['id' => $row['id']], [], false);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%jack_documentsites}}', 'publishedHtml');

        return true;
    }
}
