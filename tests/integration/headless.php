<?php
/**
 * Jack integration checks — headless delivery: GraphQL and the JSON endpoint.
 *
 * Run inside the plugin-testing container, from the site root:
 *
 *     ddev exec php /var/www/craft-jack/tests/integration/headless.php
 *
 * Every GraphQL check executes a real query through `Craft::$app->getGql()->executeQuery()`
 * against an in-memory schema with a fresh UUID and GraphQL caching off, so a "denied" case can
 * never be answered from a cached "allowed" one. The JSON checks are real anonymous HTTP requests.
 *
 * Craft builds its GraphQL types once per process, and a second schema after `flushCaches()`
 * fails with "multiple types named QueryArgument". So the run is three processes: this one builds
 * the fixtures, runs the schema without Jack's permission first, flushes once, then the documents
 * schema and the JSON checks; it re-invokes itself as `headless.php inactive` for a schema that
 * may also read inactive elements, and once more as `headless.php teardown`.
 *
 * Fixtures: one document published twice and then recompiled to a draft that differs, one only
 * ever compiled, one published and then disabled, one published and then trashed.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

Craft::$app->getPlugins()->loadPlugins();

use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\gql\DocumentQueries;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\services\Library;

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
$mode = $argv[1] ?? 'main';
$fixtureFile = __DIR__ . '/.headless-fixtures.json';
$marker = 'JACKDRAFTMARKER';

// Never answer a check from a cached result: an in-memory schema without a UID shares a cache
// key across processes, and a stale "allowed" would pass a "denied" check.
Craft::$app->getConfig()->getGeneral()->enableGraphqlCaching = false;

$schema = static fn(array $scope): GqlSchema => new GqlSchema([
    'name' => 'Jack check',
    'scope' => $scope,
    'uid' => StringHelper::UUID(),
]);

$run = static fn(GqlSchema $schema, string $query): array => Craft::$app->getGql()->executeQuery($schema, $query);

$sweep = static function(): void {
    foreach (Document::find()->status(null)->trashed(null)->siteId('*')->unique()->all() as $document) {
        if (str_starts_with($document->handle, 'gqlchk-')) {
            Craft::$app->getElements()->deleteElement($document, true);
        }
    }
};

if ($mode === 'teardown') {
    $sweep();
    @unlink($fixtureFile);
    $left = 0;

    foreach (Document::find()->status(null)->trashed(null)->siteId('*')->unique()->all() as $document) {
        $left += str_starts_with($document->handle, 'gqlchk-') ? 1 : 0;
    }

    echo $left === 0 ? "  ✓ fixtures removed\n" : "  ✗ $left fixture documents left\n";
    exit($left === 0 ? 0 : 1);
}

if ($mode === 'main') {
    $sweep();
    $suffix = substr(md5((string)microtime(true)), 0, 6);

    $make = static function(string $type, string $name, int $publishes) use ($plugin, $suffix): Document {
        $document = $plugin->documents->create($type);

        if (!$document) {
            throw new RuntimeException("Could not create a $type document");
        }

        $document->handle = "gqlchk-$name-$suffix";
        Craft::$app->getElements()->saveElement($document, false);
        $plugin->documents->compile($document);

        // A document whose clauses need facts the harness profile lacks may compile to nothing;
        // these checks are about delivery, not wording.
        if (trim((string)$document->compiledHtml) === '') {
            $document->compiledHtml = '<section id="jack-check" class="jack-section"><h2>Check</h2><p>Published text.</p></section>';
            Craft::$app->getElements()->saveElement($document, false);
        }

        for ($i = 0; $i < $publishes; $i++) {
            $plugin->documents->publish($document, 'headless check');
        }

        return $document;
    };

    $published = $make('disclaimer', 'published', 2);
    // A draft that differs from what is published, as after a profile edit nobody has published.
    $published->compiledHtml = $published->compiledHtml . "<p>$marker</p>";
    Craft::$app->getElements()->saveElement($published, false);

    $draftOnly = $make('accessibility', 'draft', 0);

    $disabled = $make('social', 'disabled', 1);
    $disabled->enabled = false;
    Craft::$app->getElements()->saveElement($disabled, false);

    $trashed = $make('cookies', 'trashed', 1);
    Craft::$app->getElements()->deleteElement($trashed);

    file_put_contents($fixtureFile, Json::encode([
        'published' => [$published->id, $published->handle],
        'draft' => [$draftOnly->id, $draftOnly->handle],
        'disabled' => [$disabled->id, $disabled->handle],
        'trashed' => [$trashed->id, $trashed->handle . '--trashed-' . $trashed->id],
    ]));
}

$fixtures = Json::decode((string)file_get_contents($fixtureFile));
[$publishedId, $publishedHandle] = $fixtures['published'];
[, $draftHandle] = $fixtures['draft'];
[, $disabledHandle] = $fixtures['disabled'];
[, $trashedHandle] = $fixtures['trashed'];
$allHandles = Json::encode([$publishedHandle, $draftHandle, $disabledHandle, $trashedHandle]);

$handlesOf = static fn(array $result, string $key = 'jackDocuments'): array => array_column($result['data'][$key] ?? [], 'handle');

if ($mode === 'inactive') {
    section('GraphQL — a schema that may read inactive elements');

    $inactive = $schema([DocumentQueries::SCOPE . ':read', 'elements.inactive:read']);

    check('asking for disabled documents still returns none', function() use ($run, $inactive, $allHandles, $handlesOf, $publishedHandle) {
        $result = $run($inactive, "{ jackDocuments(handle: $allHandles, status: [\"enabled\", \"disabled\"]) { handle } }");

        return $handlesOf($result) === [$publishedHandle] ?: Json::encode($result);
    });

    check('asking for the trash does not reach the trashed document', function() use ($run, $inactive, $allHandles, $handlesOf, $trashedHandle) {
        $result = $run($inactive, "{ inTrash: jackDocuments(handle: $allHandles, trashed: true) { handle } }");

        return (empty($result['errors']) && !in_array($trashedHandle, $handlesOf($result, 'inTrash'), true)) ?: Json::encode($result);
    });

    echo "\n$passed passed, $failed failed\n";
    exit($failed === 0 ? 0 : 1);
}

// ---------------------------------------------------------------------------------------------

section('GraphQL — a schema without Jack’s permission (run first)');

$none = $schema([]);

check('jackDocuments is not in the schema: the key is absent and nothing leaks', function() use ($run, $none, $publishedHandle) {
    $result = $run($none, '{ jackDocuments { handle html } }');

    return (!array_key_exists('jackDocuments', $result['data'] ?? []) && !str_contains(Json::encode($result), $publishedHandle))
        ?: Json::encode($result);
});

check('jackDocument and jackDocumentCount are not either', function() use ($run, $none, $publishedHandle) {
    $result = $run($none, "{ one: jackDocument(handle: \"$publishedHandle\") { handle } count: jackDocumentCount }");
    $data = $result['data'] ?? [];

    return (!array_key_exists('one', $data) && !array_key_exists('count', $data)) ?: Json::encode($result);
});

Craft::$app->getGql()->flushCaches();

// ---------------------------------------------------------------------------------------------

section('GraphQL — with “Query for published legal documents”');

$docs = $schema([DocumentQueries::SCOPE . ':read']);

check('only the published, enabled, untrashed document comes back', function() use ($run, $docs, $allHandles, $handlesOf, $publishedHandle) {
    $result = $run($docs, "{ jackDocuments(handle: $allHandles) { handle } }");

    return $handlesOf($result) === [$publishedHandle] ?: Json::encode($result);
});

check('jackDocumentCount agrees', function() use ($run, $docs, $allHandles) {
    $result = $run($docs, "{ jackDocumentCount(handle: $allHandles) }");

    return ($result['data']['jackDocumentCount'] ?? null) === 1 ?: Json::encode($result);
});

check('a document that was only ever compiled is null, not an empty document', function() use ($run, $docs, $draftHandle) {
    $result = $run($docs, "{ jackDocument(handle: \"$draftHandle\") { handle html } }");

    return (array_key_exists('jackDocument', $result['data'] ?? []) && $result['data']['jackDocument'] === null) ?: Json::encode($result);
});

// Craft only offers `status` and `trashed` to a schema that may read inactive elements; here they
// are refused outright, and the `inactive` process checks the resolver holds even when offered.
check('asking by status cannot reach a disabled document', function() use ($run, $docs, $disabledHandle) {
    $result = $run($docs, "{ jackDocuments(handle: \"$disabledHandle\", status: \"disabled\") { handle } }");

    return (($result['data']['jackDocuments'] ?? []) === [] && !str_contains(Json::encode($result['data'] ?? []), $disabledHandle)) ?: Json::encode($result);
});

check('asking for the trash cannot reach a trashed document', function() use ($run, $docs, $trashedHandle) {
    $result = $run($docs, "{ jackDocuments(handle: \"$trashedHandle\", trashed: true) { handle } }");

    return (($result['data']['jackDocuments'] ?? []) === [] && !str_contains(Json::encode($result['data'] ?? []), $trashedHandle)) ?: Json::encode($result);
});

$fields = 'handle documentType documentTypeLabel jurisdiction url html publishedAt version libraryVersion sections { anchor title } stamp { clause version }';
$one = $run($docs, "{ jackDocument(handle: \"$publishedHandle\") { $fields } }");
$document = Document::find()->id($publishedId)->one();

check('html is the published text, not the newer draft', function() use ($one, $document, $marker) {
    $html = $one['data']['jackDocument']['html'] ?? null;

    return ($html === $document->publishedHtml && !str_contains((string)$html, $marker) && str_contains((string)$document->compiledHtml, $marker))
        ?: Json::encode($one);
});

check('sections are the published text’s contents list', function() use ($one, $document, $plugin) {
    $sections = $one['data']['jackDocument']['sections'] ?? null;

    return ($sections !== [] && $sections === $plugin->renderer->toc($document)) ?: Json::encode($sections);
});

check('version counts publishes, and the library date and stamp are the published version’s', function() use ($one, $plugin, $document) {
    $data = $one['data']['jackDocument'] ?? [];
    $record = $plugin->documents->publishedVersion($document)['record'];
    $stamp = [];

    foreach ($data['stamp'] ?? [] as $row) {
        $stamp[$row['clause']] = $row['version'];
    }

    return (($data['version'] ?? null) === 2
        && ($data['libraryVersion'] ?? null) === Library::VERSION
        && $stamp === (Json::decodeIfJson((string)$record->stamp) ?: []))
        ?: Json::encode($data);
});

check('type, label and publish date come through', function() use ($one) {
    $data = $one['data']['jackDocument'] ?? [];

    return (($data['documentType'] ?? null) === 'disclaimer' && ($data['documentTypeLabel'] ?? null) === 'Disclaimer' && !empty($data['publishedAt']))
        ?: Json::encode($data);
});

// Craft drops a field the type lacks rather than erroring, so the check is that nothing came back.
check('the draft, warnings and missing facts are not fields at all', function() use ($run, $docs, $publishedHandle, $marker) {
    foreach (['compiledHtml', 'compiledText', 'warnings', 'missingFacts', 'overrides'] as $i => $field) {
        $result = $run($docs, "{ q$i: jackDocument(handle: \"$publishedHandle\") { $field } }");
        $data = $result['data']["q$i"] ?? null;

        if ((is_array($data) && array_key_exists($field, $data)) || str_contains(Json::encode($result), $marker)) {
            return "$field answered: " . Json::encode($result);
        }
    }

    return true;
});

// ---------------------------------------------------------------------------------------------

section('JSON endpoint (anonymous HTTP)');

$base = rtrim(getenv('JACK_BASE_URL') ?: 'http://127.0.0.1', '/');

$fetch = static function(string $path) use ($base): array {
    $headers = [];
    $ch = curl_init($base . '/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADERFUNCTION => static function($ch, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            return strlen($line);
        },
    ]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    return [$code, $body, $headers];
};

$serving = $plugin->getSettings()->serveDocumentsJson;

if (!$serving) {
    echo "  - serveDocumentsJson is off in this harness; expecting 404s\n";
}

check('/jack/documents/<handle>.json is the published text, open to any origin', function() use ($fetch, $publishedHandle, $document, $marker, $serving) {
    [$code, $body, $headers] = $fetch("jack/documents/$publishedHandle.json");

    if (!$serving) {
        return $code >= 400 ?: "answered $code";
    }

    $payload = Json::decodeIfJson($body)['document'] ?? null;

    return ($code === 200
        && ($payload['html'] ?? null) === $document->publishedHtml
        && !str_contains($body, $marker)
        && ($payload['version'] ?? null) === 2
        && ($headers['access-control-allow-origin'] ?? '') === '*'
        && str_starts_with($headers['content-type'] ?? '', 'application/json'))
        ?: "$code " . substr($body, 0, 200);
});

check('a type works where a handle would — the same lookup as craft.jack.render()', function() use ($fetch, $serving) {
    [$code, $body] = $fetch('jack/documents/disclaimer.json');

    if (!$serving) {
        return $code >= 400 ?: "answered $code";
    }

    // The harness may have its own disclaimer; whichever is first, it must be published text.
    $payload = Json::decodeIfJson($body)['document'] ?? null;

    return ($code === 200 && ($payload['type'] ?? null) === 'disclaimer' && ($payload['html'] ?? '') !== '') ?: "$code " . substr($body, 0, 200);
});

check('the list carries the published document without bodies, and none of the others', function() use ($fetch, $publishedHandle, $draftHandle, $disabledHandle, $trashedHandle, $serving) {
    [$code, $body] = $fetch('jack/documents.json');

    if (!$serving) {
        return $code >= 400 ?: "answered $code";
    }

    $handles = array_column(Json::decodeIfJson($body)['documents'] ?? [], 'handle');

    return ($code === 200
        && in_array($publishedHandle, $handles, true)
        && array_intersect([$draftHandle, $disabledHandle, $trashedHandle], $handles) === []
        && !str_contains($body, '"html"'))
        ?: "$code " . substr($body, 0, 300);
});

foreach (['draft' => $draftHandle, 'disabled' => $disabledHandle, 'trashed' => $trashedHandle] as $label => $handle) {
    check("a $label document is not found", function() use ($fetch, $handle) {
        [$code, $body] = $fetch("jack/documents/$handle.json");

        // A front-end 404 renders as a 500 in this harness (another plugin's 404 handler throws).
        return ($code >= 400 && !str_contains($body, '"document"')) ?: "answered $code";
    });
}

check('a handle with characters a handle cannot have does not route', function() use ($fetch) {
    [$code, $body] = $fetch('jack/documents/..%2Fx.json');

    return ($code >= 400 && !str_contains($body, '"document"')) ?: "answered $code";
});

// ---------------------------------------------------------------------------------------------

echo "\n$passed passed, $failed failed\n";

$self = escapeshellarg(__FILE__);
passthru(PHP_BINARY . " $self inactive", $inactiveExit);
passthru(PHP_BINARY . " $self teardown", $teardownExit);

exit($failed === 0 && $inactiveExit === 0 && $teardownExit === 0 ? 0 : 1);
