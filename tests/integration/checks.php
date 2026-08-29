<?php
/**
 * Jack integration checks.
 *
 * Run inside the plugin-testing container, from the site root:
 *
 *     ddev exec php /var/www/craft-jack/tests/integration/checks.php
 *
 * Covers what fixtures cannot: the two little languages the clause bodies are written in, the
 * condition evaluator against a real profile and a real inventory, whole documents compiled end
 * to end in both languages, element saves through Craft, and the drift detection that is the
 * plugin's reason to exist.
 *
 * Idempotent and self-cleaning. Cleanup runs from a shutdown handler rather than off the end of
 * the script, so a fatal halfway through still restores the profile, the inventory and the
 * edition — otherwise the next run fails for a reason that has nothing to do with the code.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

// Plugin modules are instantiated lazily, so `Plugin::getInstance()` is null until something has
// asked the plugins service for them. Nothing else in the script would, and the failure it causes
// reads like the plugin is not installed.
Craft::$app->getPlugins()->loadPlugins();

use craft\helpers\Json;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\models\Clause;
use justinholtweb\jack\models\Edition;
use justinholtweb\jack\models\Finding;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\records\ServiceRecord;
use justinholtweb\jack\records\VersionRecord;

$passed = 0;
$failed = 0;

function check(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        $result = $test();

        if ($result === true) {
            $passed++;
            echo "  ✓ $label\n";

            return;
        }

        $failed++;
        echo "  ✗ $label\n    " . (is_string($result) ? $result : 'returned ' . var_export($result, true)) . "\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✗ $label\n    " . get_class($e) . ': ' . $e->getMessage() . "\n    " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

function section(string $name): void
{
    echo "\n$name\n";
}

$plugin = Plugin::getInstance();
$suffix = 'jackcheck' . random_int(1000, 9999);

// ---------------------------------------------------------------------------------------------
// State to restore, whatever happens
// ---------------------------------------------------------------------------------------------

$originalEdition = $plugin->edition;
$originalProfile = $plugin->profiles->getGlobal()->facts;
$originalServices = [];

foreach (ServiceRecord::find()->all() as $record) {
    $originalServices[$record->serviceKey] = [
        'enabled' => (bool)$record->enabled,
        'custom' => (bool)$record->custom,
        'sortOrder' => (int)$record->sortOrder,
        'overrides' => $record->overrides,
    ];
}

$createdDocumentIds = [];
$tempFiles = [];

register_shutdown_function(static function() use (
    &$createdDocumentIds,
    &$tempFiles,
    $originalEdition,
    $originalProfile,
    $originalServices,
    $plugin
) {
    foreach ($createdDocumentIds as $id) {
        $element = Document::find()->id($id)->status(null)->siteId('*')->one();

        if ($element) {
            Craft::$app->getElements()->deleteElement($element, true);
        }
    }

    foreach ($tempFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    ServiceRecord::deleteAll(['not in', 'serviceKey', array_keys($originalServices)]);

    foreach ($originalServices as $key => $values) {
        $record = ServiceRecord::findOne(['serviceKey' => $key]);

        if ($record) {
            $record->setAttributes($values, false);
            $record->save(false);
        }
    }

    $plugin->profiles->saveGlobal($originalProfile);

    if ($plugin->edition !== $originalEdition) {
        Craft::$app->getPlugins()->switchEdition('jack', $originalEdition);
    }

    // Project config writes from a console script are buffered and simply lost otherwise.
    Craft::$app->getProjectConfig()->saveModifiedConfigData();

    echo "\nCleaned up.\n";
});

Craft::$app->getPlugins()->switchEdition('jack', Plugin::EDITION_PRO);

// Project config writes from a console script are buffered. Left unflushed, the next
// switchEdition in this run throws StaleResourceException rather than switching.
Craft::$app->getProjectConfig()->saveModifiedConfigData();

// ---------------------------------------------------------------------------------------------

section('Library');

check('loads every clause file', fn() => count($plugin->library->clauses()) >= 40
    ?: 'only ' . count($plugin->library->clauses()));

check('loads the processor registry', fn() => count($plugin->library->services()) >= 40
    ?: 'only ' . count($plugin->library->services()));

check('loads the fact schema', fn() => count($plugin->library->facts()) >= 50
    ?: 'only ' . count($plugin->library->facts()));

check('every clause has a key, a type and an order', function() use ($plugin) {
    foreach ($plugin->library->clauses() as $key => $clause) {
        if ($key === '' || $clause->documentTypes === []) {
            return "clause $key is incomplete";
        }
    }

    return true;
});

check('every written clause has German and English text', function() use ($plugin) {
    foreach ($plugin->library->clauses() as $key => $clause) {
        if ($clause->generator !== null) {
            continue;
        }

        if (trim($clause->body('de')) === '' || trim($clause->body('en')) === '') {
            return "clause $key is missing a language";
        }

        if (trim($clause->heading('de')) === '' || trim($clause->heading('en')) === '') {
            return "clause $key is missing a heading";
        }
    }

    return true;
});

check('German and English clause bodies are actually different text', function() use ($plugin) {
    foreach ($plugin->library->clauses() as $key => $clause) {
        if ($clause->generator !== null || $clause->body('de') !== $clause->body('en')) {
            continue;
        }

        // A clause whose body is nothing but placeholders and line breaks — an address block —
        // is legitimately the same in both languages. Only prose has to differ.
        $prose = trim(strip_tags(preg_replace('~\{\{[^}]+\}\}~', '', $clause->body('de')) ?? ''));

        if (strlen($prose) > 40) {
            return "clause $key has identical de and en bodies";
        }
    }

    return true;
});

check('clause bodies have balanced optional markers', function() use ($plugin) {
    foreach ($plugin->library->clauses() as $key => $clause) {
        foreach (['de', 'en'] as $language) {
            $body = $clause->body($language);

            if (substr_count($body, '[[') !== substr_count($body, ']]')) {
                return "clause $key ($language) has unbalanced [[ ]]";
            }
        }
    }

    return true;
});

check('every service has a category, a purpose and a legal basis', function() use ($plugin) {
    foreach ($plugin->library->services() as $key => $definition) {
        foreach (['category', 'legalBasis', 'transfer', 'purpose'] as $field) {
            if (!isset($definition[$field])) {
                return "service $key is missing $field";
            }
        }

        if (!isset($definition['purpose']['de'], $definition['purpose']['en'])) {
            return "service $key is missing a language";
        }
    }

    return true;
});

check('every service legal basis is one Jack can render', function() use ($plugin) {
    $known = [
        ServiceEntry::BASIS_CONSENT,
        ServiceEntry::BASIS_CONTRACT,
        ServiceEntry::BASIS_INTEREST,
        ServiceEntry::BASIS_OBLIGATION,
    ];

    foreach ($plugin->library->services() as $key => $definition) {
        if (!in_array($definition['legalBasis'], $known, true)) {
            return "service $key has an unknown basis: " . $definition['legalBasis'];
        }

        if ($plugin->library->template('service', 'basis.' . $definition['legalBasis'], 'de') === '') {
            return "no German template for basis " . $definition['legalBasis'];
        }
    }

    return true;
});

check('every transfer mechanism has a paragraph', function() use ($plugin) {
    foreach ($plugin->library->services() as $key => $definition) {
        $transfer = $definition['transfer'];

        if ($transfer === ServiceEntry::TRANSFER_NONE) {
            continue;
        }

        foreach (['de', 'en'] as $language) {
            if ($plugin->library->template('service', 'transfer.' . $transfer, $language) === '') {
                return "no $language template for transfer $transfer (service $key)";
            }
        }
    }

    return true;
});

check('every service category has a heading in both languages', function() use ($plugin) {
    foreach ($plugin->library->services() as $key => $definition) {
        foreach (['de', 'en'] as $language) {
            if ($plugin->library->template('categories', $definition['category'], $language) === '') {
                return "category {$definition['category']} has no $language heading";
            }
        }
    }

    return true;
});

check('every detect pattern is a valid regex', function() use ($plugin) {
    foreach ($plugin->library->services() as $key => $definition) {
        foreach ($definition['detect']['patterns'] ?? [] as $pattern) {
            if (@preg_match('~' . $pattern . '~i', '') === false) {
                return "service $key has a broken pattern: $pattern";
            }
        }
    }

    return true;
});

check('every fact a clause requires exists in the schema', function() use ($plugin) {
    $facts = $plugin->library->facts();
    $derived = ['controller.display', 'company.address', 'withdrawal.address', 'document.updated', 'accessibility.statusText', 'accessibility.contactEmail', 'library.version'];

    foreach ($plugin->library->clauses() as $key => $clause) {
        foreach ($clause->requires as $factKey) {
            if (!isset($facts[$factKey]) && !in_array($factKey, $derived, true)) {
                return "clause $key requires unknown fact $factKey";
            }
        }
    }

    return true;
});

check('every placeholder in a clause resolves to a known or derived fact', function() use ($plugin) {
    $facts = $plugin->library->facts();
    $derived = ['controller.display', 'company.address', 'withdrawal.address', 'document.updated', 'accessibility.statusText', 'accessibility.contactEmail', 'library.version', 'site.name', 'site.url'];

    foreach ($plugin->library->clauses() as $key => $clause) {
        foreach (['de', 'en'] as $language) {
            preg_match_all('~\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}~', $clause->body($language) . $clause->heading($language), $matches);

            foreach ($matches[1] as $placeholder) {
                if (!isset($facts[$placeholder]) && !in_array($placeholder, $derived, true)) {
                    return "clause $key ($language) uses unknown fact $placeholder";
                }
            }
        }
    }

    return true;
});

check('the imprint clauses cover the statutory list', function() use ($plugin) {
    $keys = array_keys($plugin->library->clausesFor('imprint', 'de'));

    foreach (['imprint.provider', 'imprint.contact', 'imprint.register', 'imprint.vat', 'imprint.profession'] as $required) {
        if (!in_array($required, $keys, true)) {
            return "missing $required";
        }
    }

    return true;
});

check('the imprint no longer links the ODR platform', function() use ($plugin) {
    foreach ($plugin->library->clauses() as $key => $clause) {
        foreach (['de', 'en'] as $language) {
            if (str_contains($clause->body($language), 'ec.europa.eu/consumers/odr')) {
                return "clause $key still links the shut-down ODR platform";
            }
        }
    }

    return true;
});

check('clauses use the current statute names', function() use ($plugin) {
    foreach ($plugin->library->clauses() as $key => $clause) {
        $body = $clause->body('de') . ' ' . ($clause->citation ?? '');

        if (preg_match('~§\s*5\s+TMG~', $body)) {
            return "clause $key still cites TMG rather than DDG";
        }

        if (preg_match('~TTDSG~', $body)) {
            return "clause $key still cites TTDSG rather than TDDDG";
        }
    }

    return true;
});

// ---------------------------------------------------------------------------------------------

section('Placeholders and optional runs');

$compiler = $plugin->compiler;

check('interpolates a fact', function() use ($compiler) {
    $missing = [];

    return $compiler->resolve('<p>{{ company.name }}</p>', ['company.name' => 'Acme GmbH'], $missing) === '<p>Acme GmbH</p>';
});

check('escapes what it interpolates', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('<p>{{ company.name }}</p>', ['company.name' => 'Tom & Jerry <script>'], $missing);

    return !str_contains($html, '<script>') && str_contains($html, '&amp;')
        ?: $html;
});

check('collects a missing fact rather than printing braces', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('<p>{{ contact.phone }}</p>', [], $missing);

    return $html === '<p></p>' && $missing === ['contact.phone']
        ?: $html . ' / ' . Json::encode($missing);
});

check('drops an optional run when its fact is empty', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('a[[ phone {{ contact.phone }}]] b', [], $missing);

    return trim($html) === 'a b' ?: $html;
});

check('keeps an optional run when its fact is present', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('a[[ phone {{ contact.phone }}]] b', ['contact.phone' => '030 1234'], $missing);

    return trim($html) === 'a phone 030 1234 b' ?: $html;
});

check('a dropped optional run reports no missing fact', function() use ($compiler) {
    $missing = [];
    $compiler->resolve('[[{{ contact.fax }}]]', [], $missing);

    return $missing === [] ?: Json::encode($missing);
});

check('handles a nested optional run whose inner fact is empty', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('[[<p>{{ authority.name }}[[<br>{{ authority.url }}]]</p>]]', ['authority.name' => 'BfDI'], $missing);

    return $html === '<p>BfDI</p>' ?: $html;
});

check('handles a nested optional run whose outer fact is empty', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('[[<p>{{ authority.name }}[[<br>{{ authority.url }}]]</p>]]', ['authority.url' => 'https://x'], $missing);

    return trim($html) === '' ?: $html;
});

check('keeps both halves of a nested run when both facts are present', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('[[<p>{{ authority.name }}[[<br>{{ authority.url }}]]</p>]]', [
        'authority.name' => 'BfDI',
        'authority.url' => 'https://example.test',
    ], $missing);

    return $html === '<p>BfDI<br>https://example.test</p>' ?: $html;
});

check('strips an unbalanced marker rather than printing it', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('a [[ b', ['x' => 'y'], $missing);

    return !str_contains($html, '[[') ?: $html;
});

check('does not escape the derived HTML facts twice', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('{{ company.address }}', ['company.address' => 'Acme GmbH<br>\nBerlin'], $missing);

    return str_contains($html, '<br>') ?: $html;
});

check('turns newlines into breaks in a textarea fact', function() use ($compiler) {
    $missing = [];
    $html = $compiler->resolve('{{ supervisory.authority }}', ['supervisory.authority' => "Line one\nLine two"], $missing);

    return str_contains($html, '<br') ?: $html;
});

// ---------------------------------------------------------------------------------------------

section('Conditions');

function clauseWith(array $when): Clause
{
    return Clause::fromDefinition([
        'key' => 'test.clause',
        'documentTypes' => ['privacy'],
        'when' => $when,
        'body' => ['de' => 'x', 'en' => 'x'],
    ]);
}

check('factTruthy passes when the fact is on', fn() => $compiler->applies(clauseWith(['factTruthy' => ['dpo.present']]), ['dpo.present' => true], 'de', 'de'));
check('factTruthy fails when the fact is off', fn() => !$compiler->applies(clauseWith(['factTruthy' => ['dpo.present']]), ['dpo.present' => false], 'de', 'de'));
check('factFilled fails on an empty string', fn() => !$compiler->applies(clauseWith(['factFilled' => ['company.vatId']]), ['company.vatId' => ''], 'de', 'de'));
check('factFilled fails on whitespace', fn() => !$compiler->applies(clauseWith(['factFilled' => ['company.vatId']]), ['company.vatId' => '   '], 'de', 'de'));
check('factIn matches a listed value', fn() => $compiler->applies(clauseWith(['factIn' => ['company.legalForm' => ['gmbh', 'ug']]]), ['company.legalForm' => 'gmbh'], 'de', 'de'));
check('factIn rejects an unlisted value', fn() => !$compiler->applies(clauseWith(['factIn' => ['company.legalForm' => ['gmbh']]]), ['company.legalForm' => 'sole'], 'de', 'de'));
check('jurisdiction filters', fn() => !$compiler->applies(clauseWith(['jurisdiction' => ['us']]), [], 'de', 'de'));
check('several conditions are ANDed', fn() => !$compiler->applies(clauseWith([
    'factTruthy' => ['dpo.present'],
    'jurisdiction' => ['de'],
]), ['dpo.present' => false], 'de', 'de'));
check('values within a condition are ORed', fn() => $compiler->applies(clauseWith(['jurisdiction' => ['us', 'de']]), [], 'de', 'de'));
check('unless suppresses a clause that would otherwise apply', function() use ($compiler) {
    $clause = Clause::fromDefinition([
        'key' => 'test.clause',
        'documentTypes' => ['privacy'],
        'unless' => ['factTruthy' => ['dpo.present']],
        'body' => ['de' => 'x', 'en' => 'x'],
    ]);

    return !$compiler->applies($clause, ['dpo.present' => true], 'de', 'de')
        && $compiler->applies($clause, ['dpo.present' => false], 'de', 'de');
});

// ---------------------------------------------------------------------------------------------

section('Profile');

$facts = [
    'company.name' => 'Testfirma & Söhne GmbH',
    'company.legalForm' => 'gmbh',
    'company.representative' => 'Erika Mustermann',
    'company.street' => 'Musterstraße 1',
    'company.postalCode' => '10115',
    'company.city' => 'Berlin',
    'company.country' => 'Deutschland',
    'company.registerCourt' => 'Amtsgericht Charlottenburg',
    'company.registerNumber' => 'HRB 12345',
    'company.vatId' => 'DE123456789',
    'contact.email' => 'kontakt@example.test',
    'contact.phone' => '030 1234567',
    'hosting.provider' => 'Hetzner Online GmbH',
    'hosting.address' => 'Industriestr. 25, 91710 Gunzenhausen',
    'hosting.country' => 'Deutschland',
    'hosting.dpa' => true,
    'authority.name' => 'Berliner Beauftragte für Datenschutz',
    'site.audience' => 'de',
    'site.name' => 'Testseite',
];

check('saves the global profile', fn() => $plugin->profiles->saveGlobal($facts));

check('reads the facts back', function() use ($plugin) {
    return ($plugin->profiles->factsForSite()['company.name'] ?? null) === 'Testfirma & Söhne GmbH';
});

check('drops facts the schema has never heard of', function() use ($plugin, $facts) {
    $plugin->profiles->saveGlobal($facts + ['made.up' => 'nope']);

    return !isset($plugin->profiles->getGlobal()->facts['made.up']);
});

check('derives the company address block', function() use ($plugin) {
    $map = $plugin->profiles->factMap(null, 'de');

    return str_contains($map['company.address'], 'Musterstraße 1')
        && str_contains($map['company.address'], '10115 Berlin')
        ?: $map['company.address'];
});

check('escapes the ampersand in the derived address block', function() use ($plugin) {
    return str_contains($plugin->profiles->factMap(null, 'de')['company.address'], '&amp;');
});

check('the controller defaults to the company', function() use ($plugin) {
    $map = $plugin->profiles->factMap(null, 'de');

    return $map['controller.display'] === $map['company.address'];
});

check('a separate controller overrides it', function() use ($plugin, $facts) {
    $plugin->profiles->saveGlobal($facts + [
        'controller.sameAsCompany' => false,
        'controller.name' => 'Andere Stelle GmbH',
        'controller.address' => "Weg 2\n20095 Hamburg",
    ]);
    $map = $plugin->profiles->factMap(null, 'de');
    $plugin->profiles->saveGlobal($facts);

    return str_contains($map['controller.display'], 'Andere Stelle GmbH') ?: $map['controller.display'];
});

check('falls back to the Craft site name', function() use ($plugin, $facts) {
    $without = $facts;
    unset($without['site.name']);
    $plugin->profiles->saveGlobal($without);
    $map = $plugin->profiles->factMap(null, 'de');
    $plugin->profiles->saveGlobal($facts);

    return ($map['site.name'] ?? '') !== '' ?: 'site.name was empty';
});

check('applies schema defaults', function() use ($plugin) {
    return ($plugin->profiles->factMap(null, 'de')['terms.withdrawalDays'] ?? null) === '14';
});

check('a per-site override wins', function() use ($plugin) {
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
    $plugin->profiles->saveOverride($siteId, ['company.city' => 'München']);
    $overridden = $plugin->profiles->factsForSite($siteId)['company.city'] ?? null;
    $plugin->profiles->saveOverride($siteId, []);

    return $overridden === 'München' ?: var_export($overridden, true);
});

check('a blank override falls back rather than erasing', function() use ($plugin) {
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
    $plugin->profiles->saveOverride($siteId, ['company.city' => 'München', 'company.name' => '']);
    $name = $plugin->profiles->factsForSite($siteId)['company.name'] ?? null;
    $plugin->profiles->saveOverride($siteId, []);

    return $name === 'Testfirma & Söhne GmbH' ?: var_export($name, true);
});

check('an empty override map removes the row entirely', function() use ($plugin) {
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
    $plugin->profiles->saveOverride($siteId, ['company.city' => 'München']);
    $plugin->profiles->saveOverride($siteId, []);

    return $plugin->profiles->getOverride($siteId) === null;
});

check('completeness is a fraction between nought and one', function() use ($plugin) {
    $value = $plugin->profiles->completeness();

    return $value > 0 && $value <= 1 ?: (string)$value;
});

// ---------------------------------------------------------------------------------------------

section('Inventory');

check('seeded the always-on entries on install', fn() => $plugin->inventory->has('server_logs') && $plugin->inventory->has('craft_sessions'));

check('refuses to delete an always-on entry', fn() => !$plugin->inventory->delete('server_logs') && $plugin->inventory->has('server_logs'));

check('adds a service from the library with its definition attached', function() use ($plugin) {
    $entry = $plugin->inventory->add('ga4');

    return $entry && $entry->isKnown() && $entry->get('provider') === 'Google Ireland Limited'
        ?: 'add failed';
});

check('an added service is enabled and reported as such', fn() => $plugin->inventory->isEnabled('ga4'));

check('reads the legal basis from the library', fn() => $plugin->inventory->get('ga4')->legalBasis() === ServiceEntry::BASIS_CONSENT);

check('an override beats the library', function() use ($plugin) {
    $entry = $plugin->inventory->get('ga4');
    $entry->overrides['legalBasis'] = ServiceEntry::BASIS_INTEREST;
    $plugin->inventory->save($entry);
    $basis = $plugin->inventory->get('ga4')->legalBasis();

    $entry = $plugin->inventory->get('ga4');
    unset($entry->overrides['legalBasis']);
    $entry->overrides['legalBasis'] = null;
    $plugin->inventory->save($entry);

    return $basis === ServiceEntry::BASIS_INTEREST ?: $basis;
});

check('a nulled override falls back to the library again', fn() => $plugin->inventory->get('ga4')->legalBasis() === ServiceEntry::BASIS_CONSENT);

check('notices a transfer out of the EEA', fn() => $plugin->inventory->hasTransfers());

check('lists the cookies of enabled services', function() use ($plugin) {
    $names = array_column($plugin->inventory->cookies(), 'name');

    return in_array('_ga', $names, true) && in_array('CraftSessionId', $names, true)
        ?: implode(', ', $names);
});

check('groups enabled services by category', function() use ($plugin) {
    $grouped = $plugin->inventory->enabledByCategory();

    return isset($grouped['analytics'], $grouped['functional']) ?: implode(', ', array_keys($grouped));
});

check('first-party entries sort before third parties', function() use ($plugin) {
    $enabled = $plugin->inventory->enabled();
    $first = reset($enabled);

    return $first->isFirstParty() ?: $first->serviceKey . ' came first';
});

check('the picker no longer offers a service already in the inventory', function() use ($plugin) {
    foreach ($plugin->inventory->available() as $services) {
        if (isset($services['ga4'])) {
            return 'ga4 is still on offer';
        }
    }

    return true;
});

check('adds a custom service the library has never heard of', function() use ($plugin, $suffix) {
    $entry = $plugin->inventory->add('custom_' . $suffix);

    return $entry && $entry->custom && !$entry->isKnown() ?: 'add failed';
});

check('rejects a service key with illegal characters', function() use ($plugin) {
    return $plugin->inventory->add('Not A Key!') === false;
});

check('disabling keeps the row but takes it out of the documents', function() use ($plugin, $suffix) {
    $entry = $plugin->inventory->get('custom_' . $suffix);
    $entry->enabled = false;
    $plugin->inventory->save($entry);

    return $plugin->inventory->has('custom_' . $suffix) && !$plugin->inventory->isEnabled('custom_' . $suffix);
});

check('deletes an ordinary entry', function() use ($plugin, $suffix) {
    $plugin->inventory->delete('custom_' . $suffix);

    return !$plugin->inventory->has('custom_' . $suffix);
});

// ---------------------------------------------------------------------------------------------

section('Compiling the imprint');

$imprint = $compiler->compile('imprint', 'de', null, 'de');

check('produces sections', fn() => count($imprint->sections) >= 6 ?: count($imprint->sections) . ' sections');

check('names the company', fn() => str_contains($imprint->toHtml(), 'Testfirma') ?: $imprint->toHtml());

check('prints the register entry because there is one', fn() => str_contains($imprint->toHtml(), 'HRB 12345'));

check('prints the VAT ID because there is one', fn() => str_contains($imprint->toHtml(), 'DE123456789'));

check('leaves out the fax line because there is no fax', fn() => !str_contains($imprint->toHtml(), 'Telefax'));

check('keeps the phone line because there is a phone', fn() => str_contains($imprint->toHtml(), '030 1234567'));

check('leaves out the professional clause for an unregulated business', fn() => !isset($imprint->stamp['imprint.profession']));

check('the document is complete', fn() => $imprint->isComplete() ?: implode(', ', $imprint->missingFacts));

check('every section has a stable anchor', function() use ($imprint) {
    foreach ($imprint->sections as $section) {
        if (!preg_match('~^jack-[a-z0-9\-]+$~', $section->anchor())) {
            return 'bad anchor: ' . $section->anchor();
        }
    }

    return true;
});

check('sections come out in library order', function() use ($imprint) {
    $orders = array_map(static fn($section) => $section->order, $imprint->sections);
    $sorted = $orders;
    sort($sorted);

    return $orders === $sorted ?: implode(',', $orders);
});

check('drops the VAT clause when there is no VAT ID', function() use ($plugin, $compiler, $facts) {
    $without = $facts;
    unset($without['company.vatId']);
    $plugin->profiles->saveGlobal($without);
    $compiled = $compiler->compile('imprint', 'de', null, 'de');
    $plugin->profiles->saveGlobal($facts);

    return !isset($compiled->stamp['imprint.vat']) ?: 'VAT clause survived';
});

check('reports a missing required fact instead of leaving a hole', function() use ($plugin, $compiler, $facts) {
    $without = $facts;
    unset($without['contact.email']);
    $plugin->profiles->saveGlobal($without);
    $compiled = $compiler->compile('imprint', 'de', null, 'de');
    $plugin->profiles->saveGlobal($facts);

    return in_array('contact.email', $compiled->missingFacts, true) ?: implode(', ', $compiled->missingFacts);
});

check('turns on the professional clause for a regulated profession', function() use ($plugin, $compiler, $facts) {
    $plugin->profiles->saveGlobal($facts + [
        'profession.regulated' => true,
        'profession.title' => 'Rechtsanwalt (Deutschland)',
        'profession.chamber' => 'Rechtsanwaltskammer Berlin',
        'profession.rules' => 'BRAO, BORA, RVG',
    ]);
    $compiled = $compiler->compile('imprint', 'de', null, 'de');
    $plugin->profiles->saveGlobal($facts);

    return str_contains($compiled->toHtml(), 'Rechtsanwaltskammer Berlin') ?: 'clause missing';
});

check('compiles the imprint in English too', function() use ($compiler) {
    $english = $compiler->compile('imprint', 'de', null, 'en');

    return str_contains($english->toHtml(), 'Information pursuant to')
        && !str_contains($english->toHtml(), 'Angaben gemäß')
        ?: substr($english->toHtml(), 0, 200);
});

// ---------------------------------------------------------------------------------------------

section('Compiling the privacy policy');

$privacy = $compiler->compile('privacy', 'de', null, 'de');
$privacyHtml = $privacy->toHtml();

check('names the controller', fn() => str_contains($privacyHtml, 'Testfirma'));

check('describes the hosting arrangement', fn() => str_contains($privacyHtml, 'Hetzner Online GmbH'));

check('mentions the data processing agreement because one is in place', fn() => str_contains($privacyHtml, 'Auftragsverarbeitung'));

check('writes up every enabled service', function() use ($plugin, $privacyHtml) {
    foreach ($plugin->inventory->enabled() as $entry) {
        if (!str_contains($privacyHtml, $entry->label('de'))) {
            return 'missing ' . $entry->label('de');
        }
    }

    return true;
});

check('names Google as the provider of Analytics', fn() => str_contains($privacyHtml, 'Google Ireland Limited'));

check('gives Analytics the consent basis', fn() => str_contains($privacyHtml, 'Art. 6 Abs. 1 lit. a DSGVO'));

check('gives the server logs the legitimate-interest basis', fn() => str_contains($privacyHtml, 'Art. 6 Abs. 1 lit. f DSGVO'));

check('adds the Data Privacy Framework paragraph for Google', fn() => str_contains($privacyHtml, 'Data Privacy Framework'));

check('adds the third-country clause because something transfers', fn() => isset($privacy->stamp['privacy.transfers']));

check('lists the Analytics cookies', fn() => str_contains($privacyHtml, '_ga'));

check('includes the rights and complaint clauses', fn() => isset($privacy->stamp['privacy.rights'], $privacy->stamp['privacy.complaint']));

check('names the supervisory authority in the complaint clause', fn() => str_contains($privacyHtml, 'Berliner Beauftragte'));

check('leaves out the California notice for a German audience', fn() => !isset($privacy->stamp['privacy.usNotice']));

check('adds the California notice for a US audience', function() use ($compiler) {
    $us = $compiler->compile('privacy', 'us', null, 'en');

    return isset($us->stamp['privacy.usNotice']) ?: implode(', ', array_keys($us->stamp));
});

check('leaves out the third-country clause when everything stays in the EEA', function() use ($plugin, $compiler) {
    $entry = $plugin->inventory->get('ga4');
    $entry->enabled = false;
    $plugin->inventory->save($entry);

    $compiled = $compiler->compile('privacy', 'de', null, 'de');

    $entry = $plugin->inventory->get('ga4');
    $entry->enabled = true;
    $plugin->inventory->save($entry);

    return !isset($compiled->stamp['privacy.transfers']) ?: 'transfer clause survived';
});

check('the English privacy policy is in English', function() use ($compiler) {
    $english = $compiler->compile('privacy', 'de', null, 'en');

    return str_contains($english->toHtml(), 'The legal basis is your consent')
        && !str_contains($english->toHtml(), 'Rechtsgrundlage ist Ihre Einwilligung')
        ?: 'language leaked';
});

check('is deterministic — the same inputs give the same bytes', function() use ($compiler) {
    $a = $compiler->compile('privacy', 'de', null, 'de')->toHtml();
    $b = $compiler->compile('privacy', 'de', null, 'de')->toHtml();

    return $a === $b ?: 'output differed between two compilations';
});

check('renders to readable plain text', function() use ($privacy) {
    $text = $privacy->toText();

    return !str_contains($text, '<') && str_contains($text, 'Testfirma') ?: substr($text, 0, 120);
});

// ---------------------------------------------------------------------------------------------

section('Generated tables and lists');

check('builds a cookie table', function() use ($compiler) {
    $cookies = $compiler->compile('cookies', 'de', null, 'de');

    return str_contains($cookies->toHtml(), '<table') && str_contains($cookies->toHtml(), '_ga')
        ?: 'no table';
});

check('marks strictly necessary cookies as necessary', function() use ($compiler) {
    $cookies = $compiler->compile('cookies', 'de', null, 'de');

    return str_contains($cookies->toHtml(), 'Notwendig') ?: 'no necessary label';
});

check('renders accessibility limitations as a list', function() use ($plugin, $compiler, $facts) {
    $plugin->profiles->saveGlobal($facts + [
        'accessibility.knownIssues' => "PDFs are not tagged\nOne video has no captions",
        'accessibility.feedbackEmail' => 'zugang@example.test',
    ]);
    $compiled = $compiler->compile('accessibility', 'de', null, 'de');
    $plugin->profiles->saveGlobal($facts);

    return str_contains($compiled->toHtml(), '<li>PDFs are not tagged</li>') ?: $compiled->toHtml();
});

check('says partially conformant when that is what was chosen', function() use ($compiler) {
    return str_contains($compiler->compile('accessibility', 'de', null, 'de')->toHtml(), 'teilweise konform');
});

check('resolves cookie durations into the document language', function() use ($compiler) {
    $german = $compiler->compile('cookies', 'de', null, 'de')->toHtml();
    $english = $compiler->compile('cookies', 'de', null, 'en')->toHtml();

    return str_contains($german, 'Sitzung') && !str_contains($german, 'session')
        && str_contains($english, 'session')
        ?: 'a duration leaked the wrong language';
});

check('names the generic first-party services in German', function() use ($compiler) {
    return str_contains($compiler->compile('privacy', 'de', null, 'de')->toHtml(), 'Server-Logfiles')
        ?: 'the German policy is calling them server log files';
});

check('no bilingual placeholder string reaches a document', function() use ($compiler) {
    foreach (['de', 'en'] as $language) {
        foreach (['privacy', 'cookies', 'imprint'] as $type) {
            $html = $compiler->compile($type, 'de', null, $language)->toHtml();

            // The registry once carried "Sitzung / session" in one field for both languages.
            if (preg_match('~[a-zA-Zäöüß] / [a-zA-Zäöüß]~u', strip_tags($html), $match)) {
                return "$type ($language) contains a slash-joined pair: " . $match[0];
            }
        }
    }

    return true;
});

check('warns when a document would compile to nothing', function() use ($compiler) {
    // Terms of sale on a site whose profile says it sells nothing.
    $compiled = $compiler->compile('withdrawal', 'de', null, 'de');

    return $compiled->sections === [] && $compiled->warnings !== []
        ?: 'an empty document went unremarked';
});

// ---------------------------------------------------------------------------------------------

section('Terms and the statutory withdrawal text');

check('leaves the terms empty for a site that sells nothing', function() use ($compiler) {
    $compiled = $compiler->compile('terms', 'de', null, 'de');

    return !isset($compiled->stamp['terms.withdrawal']) ?: 'withdrawal instructions on a non-shop';
});

check('produces the withdrawal instructions for a shop', function() use ($plugin, $compiler, $facts) {
    $plugin->profiles->saveGlobal($facts + ['site.sellsOnline' => true]);
    $compiled = $compiler->compile('terms', 'de', null, 'de');
    $html = $compiled->toHtml();
    $plugin->profiles->saveGlobal($facts);

    return str_contains($html, 'Widerrufsrecht')
        && str_contains($html, 'binnen 14 Tagen')
        && str_contains($html, 'Muster-Widerrufsformular')
        ?: 'missing statutory text';
});

check('fills the trader address into the withdrawal instructions', function() use ($plugin, $compiler, $facts) {
    $plugin->profiles->saveGlobal($facts + ['site.sellsOnline' => true]);
    $html = $compiler->compile('terms', 'de', null, 'de')->toHtml();
    $plugin->profiles->saveGlobal($facts);

    return str_contains($html, 'Testfirma') && str_contains($html, 'kontakt@example.test')
        ?: 'address not filled in';
});

// ---------------------------------------------------------------------------------------------

section('Documents as elements');

$document = $plugin->documents->create('imprint');

if ($document) {
    $createdDocumentIds[] = $document->id;
}

check('creates a document', fn() => $document instanceof Document && $document->id !== null);

check('gives it a German title and slug on a German-ish default', fn() => $document->title !== '' && $document->slug !== '');

check('compiles it on creation', fn() => $document->compiledAt !== null && trim((string)$document->compiledHtml) !== '');

check('stores a stamp of what it was built from', function() use ($document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();

    return $fresh->getStamp() !== [] ?: 'empty stamp';
});

check('is not stale immediately after compiling', function() use ($document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();

    return !$fresh->isStale() ?: 'stale on arrival';
});

check('reports no drift immediately after compiling', function() use ($document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $drift = $fresh->getDrift();

    return $drift['changed'] === [] && $drift['added'] === [] && $drift['removed'] === []
        ?: Json::encode($drift);
});

check('goes stale when a fact behind it changes', function() use ($plugin, $document, $facts) {
    $settings = $plugin->getSettings();
    $wasAuto = $settings->autoCompile;
    $settings->autoCompile = false;

    // array_merge, not `+`: the union operator keeps the left-hand value for a key both sides
    // have, so `$facts + ['company.city' => …]` would have changed nothing at all.
    $plugin->profiles->saveGlobal(array_merge($facts, ['company.city' => 'Hamburg']));
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $stale = $fresh->isStale();

    $plugin->profiles->saveGlobal($facts);
    $settings->autoCompile = $wasAuto;

    return $stale ?: 'a changed address did not register';
});

check('goes stale when a service is added to the inventory', function() use ($plugin, $document) {
    $settings = $plugin->getSettings();
    $wasAuto = $settings->autoCompile;
    $settings->autoCompile = false;

    // The imprint does not name services, so this must be checked on the privacy policy.
    $privacyDocument = $plugin->documents->create('privacy');
    $GLOBALS['jackPrivacyId'] = $privacyDocument->id;

    $plugin->inventory->add('stripe');
    $fresh = Document::find()->id($privacyDocument->id)->status(null)->one();
    $stale = $fresh->isStale();

    $settings->autoCompile = $wasAuto;

    return $stale ?: 'adding Stripe did not make the privacy policy stale';
});

if (isset($GLOBALS['jackPrivacyId'])) {
    $createdDocumentIds[] = $GLOBALS['jackPrivacyId'];
}

check('says which section drifted', function() use ($plugin) {
    $fresh = Document::find()->id($GLOBALS['jackPrivacyId'])->status(null)->one();
    $drift = $fresh->getDrift();

    return in_array('privacy.services', $drift['changed'], true)
        ?: Json::encode($drift);
});

check('recompiling clears the drift', function() use ($plugin) {
    $fresh = Document::find()->id($GLOBALS['jackPrivacyId'])->status(null)->one();
    $plugin->documents->compile($fresh);
    $again = Document::find()->id($GLOBALS['jackPrivacyId'])->status(null)->one();

    return !$again->isStale() ?: 'still stale after recompiling';
});

check('the recompiled privacy policy now names Stripe', function() {
    $fresh = Document::find()->id($GLOBALS['jackPrivacyId'])->status(null)->one();

    return str_contains((string)$fresh->compiledHtml, 'Stripe');
});

check('refuses a second document with the same handle', function() use ($document) {
    $duplicate = new Document();
    $duplicate->documentType = 'imprint';
    $duplicate->handle = $document->handle;
    $duplicate->title = 'Duplicate';

    return !Craft::$app->getElements()->saveElement($duplicate) ?: 'the duplicate saved';
});

check('finds a document by type', fn() => $plugin->documents->getByType('imprint')?->id === $document->id);

check('finds a document by handle', fn() => $plugin->documents->getByHandle($document->handle)?->id === $document->id);

check('lists the document types not yet created', function() use ($plugin) {
    $missing = $plugin->documents->missingTypes();

    return !in_array('imprint', $missing, true) && in_array('terms', $missing, true)
        ?: implode(', ', $missing);
});

// ---------------------------------------------------------------------------------------------

section('Overrides');

check('an override replaces the library wording', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $fresh->overrides = Json::encode([
        'imprint.copyright' => ['heading' => 'Unser Urheberrecht', 'body' => '<p>Alles gehört uns.</p>'],
    ]);
    $plugin->documents->compile($fresh);
    $again = Document::find()->id($document->id)->status(null)->one();

    return str_contains((string)$again->compiledHtml, 'Alles gehört uns.')
        && str_contains((string)$again->compiledHtml, 'Unser Urheberrecht')
        ?: 'override not applied';
});

check('an overridden section is marked as edited', function() use ($document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();

    foreach ($fresh->wouldCompile()->sections as $section) {
        if ($section->clauseKey === 'imprint.copyright') {
            return $section->isOverridden() ?: 'not marked';
        }
    }

    return 'section missing';
});

check('an override is purified', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $fresh->overrides = Json::encode([
        'imprint.copyright' => ['body' => '<p>Sicher</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>'],
    ]);
    $plugin->documents->compile($fresh);
    $again = Document::find()->id($document->id)->status(null)->one();
    $html = (string)$again->compiledHtml;

    return !str_contains($html, '<script') && !str_contains($html, 'javascript:') && str_contains($html, 'Sicher')
        ?: $html;
});

check('a disabled clause leaves the document', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $fresh->overrides = Json::encode(['imprint.copyright' => ['disabled' => true]]);
    $plugin->documents->compile($fresh);
    $again = Document::find()->id($document->id)->status(null)->one();

    return !isset($again->getStamp()['imprint.copyright']) ?: 'the clause survived';
});

check('a custom section is added at the end', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $fresh->overrides = Json::encode([
        'custom:1' => ['heading' => 'Etwas Eigenes', 'body' => '<p>Nur für uns.</p>'],
    ]);
    $plugin->documents->compile($fresh);
    $again = Document::find()->id($document->id)->status(null)->one();

    return str_contains((string)$again->compiledHtml, 'Nur für uns.') ?: 'custom section missing';
});

check('clears the overrides again', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $fresh->overrides = Json::encode([]);
    $plugin->documents->compile($fresh);
    $again = Document::find()->id($document->id)->status(null)->one();

    // Asserted on the stamp rather than on a German heading: this document belongs to whatever
    // site the harness has, and the harness speaks English.
    return isset($again->getStamp()['imprint.copyright'])
        && !str_contains((string)$again->compiledHtml, 'Nur für uns.')
        ?: 'overrides survived being cleared';
});

check('a document is written in the language of its site', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $clause = $plugin->library->clause('imprint.copyright');
    $expected = $clause->heading($fresh->languageForSite());

    return str_contains((string)$fresh->compiledHtml, $expected)
        ?: "expected the $expected heading for " . $fresh->languageForSite();
});

check('a German site would get German text', function() use ($plugin, $compiler) {
    $german = $compiler->compile('imprint', 'de', null, 'de-DE');

    return str_contains($german->toHtml(), 'Urheberrecht') ?: 'de-DE did not fall back to de';
});

// ---------------------------------------------------------------------------------------------

section('Versions and diffs');

check('publishing writes a version', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $plugin->documents->publish($fresh, 'First publication');

    return count($plugin->documents->versions($document->id, $fresh->siteId)) === 1;
});

check('publishing stamps the document', function() use ($document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();

    return $fresh->publishedAt !== null;
});

check('publishing again writes a second version', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->status(null)->one();
    $plugin->documents->publish($fresh, 'Second');

    return count($plugin->documents->versions($document->id, $fresh->siteId)) === 2;
});

check('the diff of identical text is all unchanged', function() use ($plugin) {
    $rows = $plugin->documents->diff('<p>one</p><p>two</p>', '<p>one</p><p>two</p>');

    foreach ($rows as $row) {
        if ($row['type'] !== 'same') {
            return 'found a ' . $row['type'];
        }
    }

    return count($rows) === 2 ?: count($rows) . ' rows';
});

check('the diff finds an added line', function() use ($plugin) {
    $rows = $plugin->documents->diff('<p>one</p>', '<p>one</p><p>two</p>');
    $added = array_values(array_filter($rows, static fn($row) => $row['type'] === 'added'));

    return count($added) === 1 && $added[0]['text'] === 'two' ?: Json::encode($rows);
});

check('the diff finds a removed line', function() use ($plugin) {
    $rows = $plugin->documents->diff('<p>one</p><p>two</p>', '<p>two</p>');
    $removed = array_values(array_filter($rows, static fn($row) => $row['type'] === 'removed'));

    return count($removed) === 1 && $removed[0]['text'] === 'one' ?: Json::encode($rows);
});

check('the diff finds a changed line as one removal and one addition', function() use ($plugin) {
    $rows = $plugin->documents->diff('<p>Berlin</p>', '<p>Hamburg</p>');
    $types = array_column($rows, 'type');
    sort($types);

    return $types === ['added', 'removed'] ?: implode(',', $types);
});

check('the diff ignores markup churn', function() use ($plugin) {
    $rows = $plugin->documents->diff('<p>one</p>', '<div class="x">one</div>');

    return count($rows) === 1 && $rows[0]['type'] === 'same' ?: Json::encode($rows);
});

check('the diff keeps the order of a long document', function() use ($plugin) {
    $before = '<p>a</p><p>b</p><p>c</p><p>d</p>';
    $after = '<p>a</p><p>x</p><p>c</p><p>d</p>';
    $rows = $plugin->documents->diff($before, $after);
    $text = implode(',', array_column($rows, 'text'));

    return str_starts_with($text, 'a,') && str_ends_with($text, ',c,d') ?: $text;
});

// ---------------------------------------------------------------------------------------------

section('Rendering');

check('renders a document', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->one();
    $html = (string)$plugin->renderer->render($fresh);

    return str_contains($html, 'jack-document') && str_contains($html, 'Testfirma') ?: substr($html, 0, 200);
});

check('renders a table of contents when asked', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->one();
    $html = (string)$plugin->renderer->render($fresh, ['toc' => true]);

    return str_contains($html, 'jack-toc') && str_contains($html, '#jack-imprint-provider') ?: 'no toc';
});

check('the contents list matches the document', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->one();
    $entries = $plugin->renderer->toc($fresh);

    return count($entries) === count($fresh->wouldCompile()->sections)
        ?: count($entries) . ' vs ' . count($fresh->wouldCompile()->sections);
});

check('shifts headings down for embedding', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->one();
    $html = (string)$plugin->renderer->render($fresh, ['headingLevel' => 3]);

    return str_contains($html, '<h3>') && !str_contains($html, '<h2>') ?: 'headings not shifted';
});

check('adds a last-updated line', function() use ($plugin, $document) {
    $fresh = Document::find()->id($document->id)->one();
    $html = (string)$plugin->renderer->render($fresh, ['lastUpdated' => true]);

    return str_contains($html, 'jack-updated') ?: 'no date line';
});

check('renders nothing rather than throwing for an uncompiled document', function() use ($plugin) {
    $blank = new Document();
    $blank->documentType = 'imprint';
    $blank->siteId = Craft::$app->getSites()->getPrimarySite()->id;

    return (string)$plugin->renderer->render($blank) === '';
});

check('the Twig variable finds a document by type', function() {
    $variable = new justinholtweb\jack\twig\JackVariable();

    return $variable->document('imprint') !== null;
});

check('the Twig variable exposes the consent-required services', function() {
    $variable = new justinholtweb\jack\twig\JackVariable();
    $keys = array_map(static fn($entry) => $entry->serviceKey, $variable->consentRequired());

    return in_array('ga4', $keys, true) ?: implode(', ', $keys);
});

check('a reference tag renders the document', function() use ($document) {
    $parsed = Craft::$app->getElements()->parseRefs('{jack:' . $document->handle . ':render}');

    return str_contains($parsed, 'Testfirma') ?: substr($parsed, 0, 200);
});

// ---------------------------------------------------------------------------------------------

section('Audit');

check('reports missing document types', function() use ($plugin) {
    $titles = array_map(static fn(Finding $finding) => $finding->title, $plugin->audit->run());

    return !in_array('No privacy policy', $titles, true) ?: 'complained about a policy that exists';
});

check('warns about Google Fonts served from Google', function() use ($plugin) {
    $plugin->inventory->add('google_fonts');
    $titles = array_map(static fn(Finding $finding) => $finding->title, $plugin->audit->run());
    $plugin->inventory->delete('google_fonts');

    return in_array('Google Fonts is loaded from Google', $titles, true) ?: implode(' / ', $titles);
});

check('notes the services that need consent', function() use ($plugin) {
    foreach ($plugin->audit->run() as $finding) {
        if (str_contains($finding->title, 'consent before they load')) {
            return true;
        }
    }

    return 'no consent finding';
});

check('findings are ordered worst first', function() use ($plugin) {
    $weights = array_map(static fn(Finding $finding) => $finding->weight(), $plugin->audit->run());
    $sorted = $weights;
    sort($sorted);

    return $weights === $sorted ?: implode(',', $weights);
});

check('every finding carries a remedy', function() use ($plugin) {
    foreach ($plugin->audit->run() as $finding) {
        if (trim($finding->remedy) === '') {
            return 'no remedy on: ' . $finding->title;
        }
    }

    return true;
});

check('flags a document that compiles to nothing', function() use ($plugin, &$createdDocumentIds) {
    $empty = $plugin->documents->create('withdrawal');
    $createdDocumentIds[] = $empty->id;

    $titles = array_map(static fn(Finding $finding) => $finding->title, $plugin->audit->run());
    $found = false;

    foreach ($titles as $title) {
        if (str_contains($title, 'compiles to nothing')) {
            $found = true;
        }
    }

    Craft::$app->getElements()->deleteElement($empty, true);
    array_pop($createdDocumentIds);

    return $found ?: implode(' / ', $titles);
});

check('the problem count ignores notices', function() use ($plugin) {
    $findings = $plugin->audit->run();
    $serious = count(array_filter($findings, static fn(Finding $f) => $f->severity !== Finding::SEVERITY_NOTICE));

    return $plugin->audit->problemCount() === $serious;
});

// ---------------------------------------------------------------------------------------------

section('Detector');

$templatePath = Craft::$app->getPath()->getSiteTemplatesPath() . '/jack-detector-' . $suffix . '.twig';
file_put_contents($templatePath, "<script src=\"https://www.googletagmanager.com/gtag/js?id=G-ABCDEF1234\"></script>\n<iframe src=\"https://www.youtube.com/embed/xyz\"></iframe>\n");
$tempFiles[] = $templatePath;

check('finds a service in a template', function() use ($plugin) {
    $detections = $plugin->detector->detect();

    return isset($detections['youtube']) ?: implode(', ', array_keys($detections));
});

check('records the file it found it in', function() use ($plugin, $suffix) {
    $detections = $plugin->detector->detect();
    $evidence = $detections['youtube']->evidence ?? [];

    foreach ($evidence as $item) {
        if (str_contains($item['location'], 'jack-detector-' . $suffix)) {
            return $item['line'] === 2 ?: 'wrong line: ' . var_export($item['line'], true);
        }
    }

    return 'no evidence pointing at the test template';
});

check('marks a service already in the inventory as declared', function() use ($plugin) {
    $detections = $plugin->detector->detect();

    return ($detections['ga4']->declared ?? null) === true ?: 'ga4 was not marked declared';
});

check('lists the undeclared ones separately', function() use ($plugin) {
    $undeclared = $plugin->detector->undeclared();

    return isset($undeclared['youtube']) && !isset($undeclared['ga4'])
        ?: implode(', ', array_keys($undeclared));
});

check('never puts a secret in the evidence', function() use ($plugin) {
    foreach ($plugin->detector->detect() as $detection) {
        foreach ($detection->evidence as $item) {
            if ($detection->via === 'env' && $item['excerpt'] !== 'set') {
                return 'an environment value leaked into the report';
            }
        }
    }

    return true;
});

check('the audit escalates an undeclared service to critical', function() use ($plugin) {
    foreach ($plugin->audit->run() as $finding) {
        if (str_contains($finding->title, 'not in the privacy policy')) {
            return $finding->severity === Finding::SEVERITY_CRITICAL ?: $finding->severity;
        }
    }

    return 'no undeclared-service finding';
});

// ---------------------------------------------------------------------------------------------

section('Settings');

check('normalises the editable table the settings screen posts', function() {
    $settings = new justinholtweb\jack\models\Settings();
    $settings->detectorPaths = [['path' => 'templates'], ['path' => ' web/dist '], ['path' => '']];
    $settings->validate();

    return $settings->detectorPaths === ['templates', 'web/dist'] ?: Json::encode($settings->detectorPaths);
});

check('normalises a comma-separated extension list', function() {
    $settings = new justinholtweb\jack\models\Settings();
    $settings->detectorExtensions = 'twig, .HTML , js';
    $settings->validate();

    return $settings->detectorExtensions === ['twig', 'html', 'js'] ?: Json::encode($settings->detectorExtensions);
});

check('leaves a plain array alone', function() {
    $settings = new justinholtweb\jack\models\Settings();
    $settings->detectorPaths = ['templates', 'modules'];
    $settings->validate();

    return $settings->detectorPaths === ['templates', 'modules'] ?: Json::encode($settings->detectorPaths);
});

check('validates with the defaults, so a fresh install can save them', function() {
    return (new justinholtweb\jack\models\Settings())->validate();
});

// ---------------------------------------------------------------------------------------------

section('Editions');

check('Pro allows every document type', fn() => Edition::documentTypes(true) === Document::TYPES);

check('Lite allows the two that are compulsory', fn() => Edition::documentTypes(false) === ['imprint', 'privacy']);

check('Lite refuses terms', fn() => !Edition::allowsDocumentType('terms', false));

check('Lite refuses overrides, detection, history and the console', fn() => !Edition::allowsOverrides(false)
    && !Edition::allowsDetection(false)
    && !Edition::allowsHistory(false)
    && !Edition::allowsConsole(false));

check('Lite still gets told a document is out of date', fn() => Edition::allowsChangeDetail(false) === false);

check('a document compiled under Pro still renders under Lite', function() use ($plugin, $document) {
    $config = Craft::$app->getProjectConfig();

    Craft::$app->getPlugins()->switchEdition('jack', Plugin::EDITION_LITE);
    $config->saveModifiedConfigData();

    $fresh = Document::find()->id($document->id)->one();
    $html = (string)$plugin->renderer->render($fresh);

    Craft::$app->getPlugins()->switchEdition('jack', Plugin::EDITION_PRO);
    $config->saveModifiedConfigData();

    return str_contains($html, 'Testfirma') ?: 'a lapsed licence blanked a published legal text';
});

// ---------------------------------------------------------------------------------------------

section('Deleting');

check('deleting a document parks its handle', function() use ($plugin) {
    $throwaway = $plugin->documents->create('cookies');
    $handle = $throwaway->handle;
    Craft::$app->getElements()->deleteElement($throwaway);

    $replacement = $plugin->documents->create('cookies');
    $ok = $replacement && $replacement->handle === $handle;

    if ($replacement) {
        Craft::$app->getElements()->deleteElement($replacement, true);
    }

    return $ok ?: 'the handle was not released';
});

check('deleting a document takes its versions with it', function() use ($plugin) {
    $throwaway = $plugin->documents->create('disclaimer');
    $plugin->documents->publish($throwaway, 'x');
    $id = $throwaway->id;
    Craft::$app->getElements()->deleteElement($throwaway, true);

    return VersionRecord::find()->where(['documentId' => $id])->count() == 0;
});

// ---------------------------------------------------------------------------------------------

echo "\n";
echo "$passed passed, $failed failed\n";

exit($failed === 0 ? 0 : 1);
