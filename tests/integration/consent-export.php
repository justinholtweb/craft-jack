<?php
/**
 * Jack integration checks — consent manager configs.
 *
 * Run inside the plugin-testing container, from the site root:
 *
 *     ddev exec php /var/www/craft-jack/tests/integration/consent-export.php
 *
 * Builds every target from a known inventory, checks where each service is filed, that wildcard
 * cookies become real patterns, that nothing an editor typed can break out of the `<script>`,
 * that Jack reads Toss's categories and Consent Mode map when Toss is the consent manager, and
 * that the CP download refuses Lite, a user without the view permission and a site they cannot
 * edit.
 *
 * Edition, settings and Toss's kit switch are changed in memory only. The inventory and the
 * temporary user are restored and deleted from a shutdown handler.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

Craft::$app->getPlugins()->loadPlugins();

use craft\elements\User;
use craft\helpers\Json;
use justinholtweb\jack\elements\Document;
use justinholtweb\jack\helpers\CookiePattern;
use justinholtweb\jack\models\ServiceEntry;
use justinholtweb\jack\Plugin;
use justinholtweb\jack\records\ServiceRecord;
use justinholtweb\jack\services\ConsentExport;
use justinholtweb\jack\twig\JackVariable;

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
$export = $plugin->consentExport;
$primary = Craft::$app->getSites()->getPrimarySite();
$other = null;

foreach (Craft::$app->getSites()->getAllSites() as $site) {
    if (!$site->primary) {
        $other = $site;
        break;
    }
}

// ---------------------------------------------------------------------------------------------
// State to restore
// ---------------------------------------------------------------------------------------------

$originalEdition = $plugin->edition;
$originalServices = [];

foreach (ServiceRecord::find()->all() as $record) {
    $originalServices[$record->serviceKey] = [
        'enabled' => (bool)$record->enabled,
        'custom' => (bool)$record->custom,
        'sortOrder' => (int)$record->sortOrder,
        'overrides' => $record->overrides,
    ];
}

$tempUserIds = [];

register_shutdown_function(static function() use (&$tempUserIds, $originalServices, $plugin, $originalEdition) {
    foreach ($tempUserIds as $id) {
        $user = User::find()->id($id)->status(null)->one();

        if ($user) {
            Craft::$app->getElements()->deleteElement($user, true);
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

    $plugin->edition = $originalEdition;
    echo "\nCleaned up.\n";
});

// Pro in memory; the harness is normally on Pro anyway.
$plugin->edition = Plugin::EDITION_PRO;
$plugin->getSettings()->consentCategories = [];

// A known inventory: one service per interesting case, plus a custom service whose name an
// editor typed with a script in it.
foreach (['ga4', 'plausible', 'youtube', 'mailchimp', 'google_maps', 'meta_pixel', 'stripe'] as $key) {
    $plugin->inventory->add($key);
}

$evil = new ServiceEntry([
    'serviceKey' => 'jackchk_evil',
    'enabled' => true,
    'custom' => true,
    'overrides' => [
        'label' => 'Evil</script><script>alert(1)</script>',
        'category' => 'analytics',
        'legalBasis' => 'consent',
        'purpose' => ['en' => 'testing "quotes" & <tags>'],
        'cookies' => [['name' => 'evil_*', 'duration' => '1 day'], ['name' => 'we/ird*', 'duration' => '1 day']],
    ],
]);
$plugin->inventory->save($evil);

/** @return array<string, string> serviceKey => category, for one built target's service list */
$categoriesOf = static function(array $services, string $keyField = 'key', string $categoryField = 'category'): array {
    $map = [];

    foreach ($services as $key => $service) {
        $map[$service[$keyField] ?? $key] = $service[$categoryField] ?? $service['purposes'][0] ?? '?';
    }

    return $map;
};

// ---------------------------------------------------------------------------------------------

section('Where services are filed');

check('consent-based analytics is analytics', function() use ($export, $plugin) {
    return $export->categoryFor($plugin->inventory->get('ga4')) === 'analytics';
});

check('a service that runs on legitimate interest is necessary, not asked about', function() use ($export, $plugin) {
    return $export->categoryFor($plugin->inventory->get('plausible')) === 'necessary';
});

check('embedded media is marketing, like Toss’s own YouTube preset', function() use ($export, $plugin) {
    return $export->categoryFor($plugin->inventory->get('youtube')) === 'marketing';
});

check('maps are preferences', function() use ($export, $plugin) {
    return $export->categoryFor($plugin->inventory->get('google_maps')) === 'preferences';
});

check('a newsletter stays out of the banner', function() use ($export, $plugin) {
    return $export->categoryFor($plugin->inventory->get('mailchimp')) === null;
});

check('services() leaves out first-party entries that set no cookie, keeps the session cookies', function() use ($export) {
    $keys = array_map(static fn(array $row) => $row['entry']->serviceKey, $export->services());

    return (!in_array('server_logs', $keys, true) && in_array('craft_sessions', $keys, true) && !in_array('mailchimp', $keys, true))
        ?: 'got ' . implode(', ', $keys);
});

check('services() lists necessary first and marketing last', function() use ($export) {
    $categories = array_map(static fn(array $row) => $row['category'], $export->services());
    $first = $categories[0] ?? null;
    $last = end($categories);

    return ($first === 'necessary' && $last === 'marketing') ?: implode(', ', $categories);
});

check('consentCategories: a service key wins over its category, null leaves it out, junk is ignored', function() use ($export, $plugin) {
    $plugin->getSettings()->consentCategories = [
        'maps' => 'marketing',
        'youtube' => 'preferences',
        'ga4' => null,
        'plausible' => 'not-a-category',
    ];

    $ok = $export->categoryFor($plugin->inventory->get('google_maps')) === 'marketing'
        && $export->categoryFor($plugin->inventory->get('youtube')) === 'preferences'
        && $export->categoryFor($plugin->inventory->get('ga4')) === null
        && $export->categoryFor($plugin->inventory->get('plausible')) === 'necessary';

    $plugin->getSettings()->consentCategories = [];

    return $ok;
});

check('a config/jack.php map in string-keyed shape survives settings validation', function() use ($plugin) {
    $settings = clone $plugin->getSettings();
    $settings->consentCategories = ['maps' => ' marketing ', 'ga4' => '', 3 => 'x'];
    $settings->validate();

    return $settings->consentCategories === ['maps' => 'marketing', 'ga4' => null] ?: Json::encode($settings->consentCategories);
});

// ---------------------------------------------------------------------------------------------

section('Klaro');

check('every service is there, under its purpose, required only when necessary', function() use ($export, $primary) {
    $config = $export->build('klaro', $primary->id);
    $byName = [];

    foreach ($config['services'] as $service) {
        $byName[$service['name']] = $service;
    }

    return (($byName['ga4']['purposes'] ?? null) === ['analytics']
        && ($byName['ga4']['required'] ?? null) === false
        && ($byName['plausible']['required'] ?? null) === true
        && ($byName['youtube']['purposes'] ?? null) === ['marketing']
        && !isset($byName['mailchimp']))
        ?: Json::encode(array_keys($byName));
});

check('wildcard cookies become patterns, exact names stay strings', function() use ($export, $primary) {
    $config = $export->build('klaro', $primary->id);

    foreach ($config['services'] as $service) {
        if ($service['name'] === 'ga4') {
            return ($service['cookies'][0] === '_ga' && $service['cookies'][1] instanceof CookiePattern && $service['cookies'][1]->source === '^_ga_.*$')
                ?: Json::encode($service['cookies']);
        }
    }

    return 'no ga4';
});

check('a wildcard name with a character outside cookie names is kept as a plain string', function() {
    return CookiePattern::fromName('we/ird*') === 'we/ird*';
});

check('the purposes have titles and descriptions in the site’s language', function() use ($export, $primary) {
    $config = $export->build('klaro', $primary->id);
    $purposes = $config['translations']['en']['purposes'] ?? [];

    return (($purposes['analytics']['title'] ?? '') === 'Analytics' && isset($purposes['necessary'], $purposes['marketing']))
        ?: Json::encode($purposes);
});

check('a German site gets a German banner', function() use ($export, $primary) {
    $language = $primary->language;
    $primary->language = 'de-DE';

    try {
        $config = $export->build('klaro', $primary->id);
        $ga4 = array_values(array_filter($config['services'], static fn($s) => $s['name'] === 'ga4'))[0] ?? [];

        return ($config['lang'] === 'de'
            && ($config['translations']['de']['purposes']['analytics']['title'] ?? '') === 'Statistik'
            && str_starts_with($ga4['description'] ?? '', 'Zweck: die Analyse'))
            ?: Json::encode([$config['lang'], $ga4['description'] ?? null]);
    } finally {
        $primary->language = $language;
    }
});

check('the privacy policy link is the published privacy policy, or nothing', function() use ($export, $primary) {
    $config = $export->build('klaro', $primary->id);
    $document = Document::find()->documentType('privacy')->siteId($primary->id)->orderBy(['jack_documents.id' => SORT_ASC])->one();
    $expected = $document && trim((string)$document->publishedHtml) !== '' ? $document->getUrl() : null;
    $got = $config['translations']['zz']['privacyPolicyUrl'] ?? null;

    return $got === $expected ?: var_export([$got, $expected], true);
});

// ---------------------------------------------------------------------------------------------

section('CookieConsent v3');

check('necessary is read-only, analytics clears its cookies, services carry labels', function() use ($export, $primary) {
    $config = $export->build('cookieconsent', $primary->id);
    $categories = $config['categories'];

    return (($categories['necessary']['readOnly'] ?? false) === true
        && !isset($categories['necessary']['autoClear'])
        && ($categories['analytics']['services']['ga4']['label'] ?? '') === 'Google Analytics 4'
        && in_array('^_ga_.*$', array_map(static fn($c) => $c['name'] instanceof CookiePattern ? $c['name']->source : null, $categories['analytics']['autoClear']['cookies'] ?? []), true))
        ?: Json::encode($categories);
});

check('every category has a preferences section linked to it, with a cookie table where there are cookies', function() use ($export, $primary) {
    $config = $export->build('cookieconsent', $primary->id);
    $sections = $config['language']['translations']['en']['preferencesModal']['sections'];
    $linked = array_column($sections, 'linkedCategory');
    $analytics = array_values(array_filter($sections, static fn($s) => ($s['linkedCategory'] ?? '') === 'analytics'))[0] ?? [];

    return (array_diff(array_keys($config['categories']), $linked) === []
        && in_array('_ga', array_column($analytics['cookieTable']['body'] ?? [], 'name'), true))
        ?: Json::encode($linked);
});

// ---------------------------------------------------------------------------------------------

section('Consent Mode and Tape');

check('each service carries its Google types and Tape category', function() use ($export, $primary) {
    $config = $export->build('consent-mode', $primary->id);
    $s = $config['services'];

    return (($s['ga4']['consentMode'] ?? null) === ['analytics_storage']
        && ($s['ga4']['tape'] ?? null) === 'analytics'
        && ($s['meta_pixel']['consentMode'] ?? null) === ['ad_storage', 'ad_user_data', 'ad_personalization']
        && ($s['meta_pixel']['tape'] ?? null) === 'ads'
        && ($s['google_maps']['tape'] ?? null) === 'functionality'
        && ($s['plausible']['consentMode'] ?? null) === ['security_storage']
        && array_key_exists('tape', $s['plausible']) && $s['plausible']['tape'] === null)
        ?: Json::encode($s);
});

// ---------------------------------------------------------------------------------------------

section('Toss');

$tossInstalled = Craft::$app->getPlugins()->isPluginEnabled('toss');
$tossKits = null;
$tossMap = null;

check('every service carries its Toss preset, mapped where Toss’s key differs', function() use ($export, $primary) {
    $config = $export->build('toss', $primary->id);
    $byKey = array_column($config['services'], 'tossPreset', 'key');

    return (($byKey['meta_pixel'] ?? null) === 'meta-pixel' && ($byKey['ga4'] ?? null) === 'ga4' && array_key_exists('google_maps', $byKey) && $byKey['google_maps'] === null)
        ?: Json::encode($byKey);
});

check('the four canonical categories, necessary first', function() use ($export, $primary) {
    $config = $export->build('toss', $primary->id);

    return array_slice($config['categories'], 0, 4) === ['necessary', 'preferences', 'analytics', 'marketing'] ?: Json::encode($config['categories']);
});

if ($tossInstalled) {
    $toss = Craft::$app->getPlugins()->getPlugin('toss');
    $tossSettings = $toss->getSettings();
    $tossKits = $tossSettings->kits;
    $tossMap = $tossSettings->consentModeMap;
    $kitHandle = \justinholtweb\toss\kits\CookieConsentKit::handle();

    check('Toss installed but its consent kit off: Jack does not defer to it', function() use ($export, $primary, $tossSettings, $kitHandle) {
        $kits = $tossSettings->kits;
        $kits[$kitHandle]['enabled'] = false;
        $tossSettings->kits = $kits;
        $config = $export->build('toss', $primary->id);

        return ($config['tossActive'] === false && !array_key_exists('inToss', $config['services'][0])) ?: Json::encode($config['services'][0]);
    });

    check('Toss active: each service says whether Toss’s script inventory has it, and under which category', function() use ($export, $primary, $tossSettings, $kitHandle, $toss) {
        $kits = $tossSettings->kits;
        $kits[$kitHandle]['enabled'] = true;
        $tossSettings->kits = $kits;
        $config = $export->build('toss', $primary->id);
        $tossKeys = array_map(static fn($script) => $script->key, $toss->consent->scripts($primary->id));

        foreach ($config['services'] as $service) {
            $expected = in_array($service['tossPreset'] ?? $service['key'], $tossKeys, true);

            if (($service['inToss'] ?? null) !== $expected) {
                return "{$service['key']}: inToss " . var_export($service['inToss'] ?? null, true);
            }
        }

        return $config['tossActive'] === true;
    });

    check('Toss active: Consent Mode types follow Toss’s own map, overrides included', function() use ($export, $primary, $tossSettings) {
        $tossSettings->consentModeMap = ['ad_user_data' => 'analytics'];
        $config = $export->build('consent-mode', $primary->id);

        return ($config['consentModeMap']['ad_user_data'] === 'analytics'
            && $config['services']['ga4']['consentMode'] === ['ad_user_data', 'analytics_storage'])
            ?: Json::encode($config['services']['ga4']);
    });

    $tossSettings->kits = $tossKits;
    $tossSettings->consentModeMap = $tossMap;
} else {
    echo "  - Toss is not installed here; the Toss-active checks were skipped\n";
}

// ---------------------------------------------------------------------------------------------

section('Output');

check('the same inputs give the same bytes', function() use ($export, $primary) {
    foreach (ConsentExport::FORMATS as $format) {
        if ($export->json($format, $primary->id) !== $export->json($format, $primary->id)) {
            return "$format differs between two runs";
        }
    }

    return true;
});

check('JSON writes a pattern as its source string', function() use ($export, $primary) {
    return str_contains($export->json('klaro', $primary->id), '"^_ga_.*$"') ?: 'no pattern source';
});

check('the script is a regex literal for a pattern, and assigns klaroConfig by default', function() use ($export, $primary) {
    $html = (string)$export->script('klaro', $primary->id);

    return (str_starts_with($html, '<script>window.klaroConfig = {') && str_contains($html, '/^_ga_.*$/') && !str_contains($html, '__jack_pattern_'))
        ?: substr($html, 0, 200);
});

check('nothing an editor typed can close the script element', function() use ($export, $primary) {
    $html = (string)$export->script('cookieconsent', $primary->id);
    $inner = substr($html, strlen('<script>'), -strlen('</script>'));

    return (!str_contains($inner, '</script') && !str_contains($inner, '<script') && str_contains($inner, 'Evil' . '\\u003C/script\\u003E'))
        ?: 'the custom label reached the page unescaped: ' . (preg_match('~.{40}</?script.{20}~', $inner, $m) ? $m[0] : 'no escaped label');
});

check('a variable that is not a JavaScript identifier is refused', function() use ($export, $primary) {
    try {
        $export->script('klaro', $primary->id, 'x;alert(1)//');
    } catch (InvalidArgumentException) {
        return (string)$export->script('klaro', $primary->id, 'window.site.klaro') !== '';
    }

    return 'accepted';
});

check('an unknown format is refused', function() use ($export) {
    try {
        $export->build('onetrust');
    } catch (InvalidArgumentException) {
        return true;
    }

    return 'accepted';
});

// ---------------------------------------------------------------------------------------------

section('Editions');

check('Twig: Pro gets the config and the script', function() {
    $variable = new JackVariable();

    return ($variable->consentConfig('klaro') !== [] && str_contains((string)$variable->consentScript('cookieconsent'), 'jackConsentConfig')) ?: 'empty on Pro';
});

check('Twig: Lite gets nothing, without an exception', function() use ($plugin) {
    $plugin->edition = Plugin::EDITION_LITE;

    try {
        $variable = new JackVariable();

        return ($variable->consentConfig('klaro') === [] && (string)$variable->consentScript('klaro') === '') ?: 'Lite got a config';
    } finally {
        $plugin->edition = Plugin::EDITION_PRO;
    }
});

// ---------------------------------------------------------------------------------------------

section('The CP download');

// 127.0.0.1, not localhost, which does not answer inside the web container. JACK_BASE_URL overrides.
$base = rtrim(getenv('JACK_BASE_URL') ?: 'http://127.0.0.1', '/');

/**
 * A cookie jar holding a CP session for a user, started with a one-use impersonation token — what
 * `php craft users/impersonate` prints. No password goes through the login action, which a
 * security plugin another builder is testing may have locked for this IP.
 */
$login = static function(User $user) use ($base): string {
    $token = Craft::$app->getTokens()->createToken(
        ['users/impersonate-with-token', ['userId' => $user->id, 'prevUserId' => $user->id]],
        1,
        new DateTime('+10 minutes'),
    );
    $jar = tempnam(sys_get_temp_dir(), 'jackjar');
    // The site URL, not the CP one: the CP sends an anonymous request to its login screen before
    // any token route runs. And a User-Agent, or Craft refuses to start the session at all
    // (`requireUserAgentAndIpForSession`), which reads like a broken token.
    $ch = curl_init($base . '/?' . Craft::$app->getConfig()->getGeneral()->tokenParam . '=' . urlencode((string)$token));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_USERAGENT => 'jack-check']);
    curl_exec($ch);
    unset($ch);

    return $jar;
};

$get = static function(?string $jar, string $path) use ($base): array {
    $ch = curl_init($base . '/admin/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'jack-check']);

    if ($jar) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }

    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    return [$code, $body, $type];
};

$makeUser = static function(string $label, array $permissions, bool $admin = false) use (&$tempUserIds): User {
    $user = new User();
    $user->active = true;
    $user->pending = false;
    $user->admin = $admin;
    $user->username = 'jackchk_' . $label . '_' . random_int(1000, 9999);
    $user->email = $user->username . '@example.test';
    $user->newPassword = 'jackcheckpass1';
    Craft::$app->getElements()->saveElement($user, false);
    $tempUserIds[] = $user->id;
    Craft::$app->getUserPermissions()->saveUserPermissions($user->id, $permissions);

    return $user;
};

$isPro = Craft::$app->getProjectConfig()->get('plugins.jack.edition') === 'pro';

if (!$isPro) {
    echo "  - Jack is on Lite in the harness's project config, so the HTTP checks expect refusals only\n";
}

// A throwaway admin rather than the harness's own, whose password is not this suite's to rely on.
$admin = $makeUser('admin', [], true);
$adminJar = $login($admin);
$query = 'jack/inventory/consent-config?format=klaro&site=' . $primary->handle;

check('an admin downloads the Klaro config as a JSON attachment (Pro)', function() use ($get, $adminJar, $query, $isPro) {
    [$code, $body, $type] = $get($adminJar, $query);

    if (!$isPro) {
        return $code === 403 ?: "Lite answered $code";
    }

    $config = Json::decodeIfJson($body);

    return ($code === 200 && str_starts_with($type, 'application/json') && is_array($config) && isset($config['services']))
        ?: "$code $type " . substr($body, 0, 120);
});

check('the inventory screen renders, with the export menu on Pro', function() use ($get, $adminJar, $isPro) {
    [$code, $body] = $get($adminJar, 'jack/inventory');

    return ($code === 200 && str_contains($body, 'Export consent config') === $isPro && (!$isPro || str_contains($body, 'consent-config?format=cookieconsent')))
        ?: "$code, menu " . (str_contains($body, 'Export consent config') ? 'shown' : 'missing');
});

check('the settings screen renders the JSON endpoint switch', function() use ($get, $adminJar) {
    [$code, $body] = $get($adminJar, 'settings/plugins/jack');

    return ($code === 200 && str_contains($body, 'serveDocumentsJson')) ?: "answered $code";
});

check('an unknown format is a 404', function() use ($get, $adminJar, $isPro) {
    [$code] = $get($adminJar, 'jack/inventory/consent-config?format=onetrust');

    return $code === ($isPro ? 404 : 403) ?: "answered $code";
});

check('anonymous: no config', function() use ($get, $query) {
    [$code, $body] = $get(null, $query);

    return (in_array($code, [302, 403], true) && !str_contains($body, '"services"')) ?: "answered $code";
});

$siteUids = ['editSite:' . strtolower($primary->uid)];

$noView = $makeUser('noview', array_merge(['accessCp', 'accessPlugin-jack'], $siteUids));
$viewer = $makeUser('viewer', array_merge(['accessCp', 'accessPlugin-jack', 'jack:view-documents'], $siteUids));

check('a CP user without “View legal documents” is refused', function() use ($get, $login, $noView, $query) {
    [$code, $body] = $get($login($noView), $query);

    return ($code === 403 && !str_contains($body, '"services"')) ?: "answered $code";
});

$viewerJar = $login($viewer);

check('a user with “View legal documents” downloads it (Pro)', function() use ($get, $viewerJar, $query, $isPro) {
    [$code, $body] = $get($viewerJar, $query);

    return $isPro
        ? (($code === 200 && str_contains($body, '"services"')) ?: "answered $code")
        : ($code === 403 ?: "Lite answered $code");
});

if ($other) {
    check('…but not for a site they cannot edit', function() use ($get, $viewerJar, $other) {
        [$code, $body] = $get($viewerJar, 'jack/inventory/consent-config?format=klaro&site=' . $other->handle);

        return ($code === 403 && !str_contains($body, '"services"')) ?: "answered $code";
    });
}

// ---------------------------------------------------------------------------------------------

echo "\n";
echo "$passed passed, $failed failed\n";

exit($failed === 0 ? 0 : 1);
