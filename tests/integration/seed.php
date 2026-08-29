<?php
/**
 * Creates one document of every type for the smoke test, and deletes them again with `--clean`.
 *
 * Prints `type id uri` per line so the shell script can walk the edit screens without guessing
 * at IDs.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

Craft::$app->getPlugins()->loadPlugins();

use justinholtweb\jack\elements\Document;
use justinholtweb\jack\Plugin;

$plugin = Plugin::getInstance();
$clean = in_array('--clean', $argv, true);

if ($clean) {
    foreach (Document::find()->status(null)->siteId('*')->all() as $document) {
        if (str_starts_with($document->handle, 'smoke-')) {
            Craft::$app->getElements()->deleteElement($document, true);
        }
    }

    echo "cleaned\n";
    exit(0);
}

// Enough of a profile that the documents have something to say.
$facts = $plugin->profiles->getGlobal()->facts;

// Fills only what is blank, so running this twice is the same as running it once, and so a
// profile somebody is actually using is never overwritten.
$plugin->profiles->saveGlobal(array_merge([
    'company.name' => 'Smoke Test GmbH',
    'company.street' => 'Teststraße 1',
    'company.postalCode' => '10115',
    'company.city' => 'Berlin',
    'contact.email' => 'smoke@example.test',
    'hosting.provider' => 'Hetzner Online GmbH',
    // Without these the terms and withdrawal documents compile to nothing, and the smoke test
    // would be walking two blank pages while reporting success.
    'site.sellsOnline' => true,
    'terms.governingLaw' => 'Recht der Bundesrepublik Deutschland',
    'accessibility.feedbackEmail' => 'smoke@example.test',
], array_filter($facts, static fn($value) => $value !== '' && $value !== null)));

foreach (Document::TYPES as $type) {
    $document = $plugin->documents->create($type);

    if (!$document) {
        continue;
    }

    $document->handle = 'smoke-' . $type;
    Craft::$app->getElements()->saveElement($document, false);
    $plugin->documents->compile($document);
    $plugin->documents->publish($document, 'smoke test');

    $fresh = Document::find()->id($document->id)->status(null)->one();

    echo sprintf("%s %d %s\n", $type, $document->id, $fresh?->uri ?: '-');
}
