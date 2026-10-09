<?php

/**
 * Words a consent banner shows, for the configs {@see justinholtweb\jack\services\ConsentExport}
 * writes.
 *
 * Kept here with the rest of the visitor-readable text rather than in the exporter: a banner is
 * read by the same people as the privacy policy, and its wording belongs where somebody who is not
 * a PHP developer can review it.
 *
 * `{{ privacyUrl }}` is the published privacy policy's URL. A line that needs it is dropped when
 * there is no published privacy policy, rather than linking to nothing.
 *
 * @var array<string, mixed>
 */

return [
    // The four category names the family agrees on — Toss's `Consent::CATEGORIES`.
    'categories' => [
        'necessary' => [
            'title' => ['de' => 'Notwendig', 'en' => 'Necessary'],
            'description' => [
                'de' => 'Diese Dienste sind für den Betrieb der Website erforderlich und können nicht abgewählt werden.',
                'en' => 'These services are needed for the website to work and cannot be switched off.',
            ],
        ],
        'preferences' => [
            'title' => ['de' => 'Funktionen', 'en' => 'Preferences'],
            'description' => [
                'de' => 'Dienste, die zusätzliche Funktionen bereitstellen, etwa Karten, Schriften, Terminbuchung oder einen Chat.',
                'en' => 'Services that add features, such as maps, fonts, booking or chat.',
            ],
        ],
        'analytics' => [
            'title' => ['de' => 'Statistik', 'en' => 'Analytics'],
            'description' => [
                'de' => 'Dienste, die uns zeigen, wie die Website genutzt wird.',
                'en' => 'Services that show us how the website is used.',
            ],
        ],
        'marketing' => [
            'title' => ['de' => 'Marketing', 'en' => 'Marketing'],
            'description' => [
                'de' => 'Dienste für Werbung, eingebettete Medien und soziale Netzwerke, die Sie auch über andere Websites hinweg wiedererkennen können.',
                'en' => 'Services for advertising, embedded media and social networks, which may recognise you across other websites.',
            ],
        ],
    ],

    // One line per service, built from registry fields. Colons, not sentences, because the
    // purpose phrases are written for the slot after "für" and read wrongly anywhere else.
    'service' => [
        'purpose' => ['de' => 'Zweck: {{ purpose }}.', 'en' => 'Purpose: {{ purpose }}.'],
        'provider' => ['de' => 'Anbieter: {{ provider }}.', 'en' => 'Provider: {{ provider }}.'],
    ],

    // CookieConsent v3's modal wording.
    'modal' => [
        'consentTitle' => ['de' => 'Cookies und externe Dienste', 'en' => 'Cookies and third-party services'],
        'consentDescription' => [
            'de' => 'Wir verwenden Cookies und Dienste Dritter. Einige sind notwendig, andere helfen uns, die Website zu verbessern. Sie können selbst entscheiden.',
            'en' => 'We use cookies and third-party services. Some are necessary, others help us improve the website. The choice is yours.',
        ],
        'privacyLink' => [
            'de' => '<a href="{{ privacyUrl }}">Datenschutzerklärung</a>',
            'en' => '<a href="{{ privacyUrl }}">Privacy policy</a>',
        ],
        'acceptAll' => ['de' => 'Alle akzeptieren', 'en' => 'Accept all'],
        'acceptNecessary' => ['de' => 'Nur notwendige', 'en' => 'Necessary only'],
        'showPreferences' => ['de' => 'Einstellungen', 'en' => 'Settings'],
        'savePreferences' => ['de' => 'Auswahl speichern', 'en' => 'Save choices'],
        'preferencesTitle' => ['de' => 'Einstellungen', 'en' => 'Settings'],
        'close' => ['de' => 'Schließen', 'en' => 'Close'],
        'moreTitle' => ['de' => 'Weitere Informationen', 'en' => 'More information'],
        'moreDescription' => [
            'de' => 'Einzelheiten zu jedem Dienst stehen in unserer <a href="{{ privacyUrl }}">Datenschutzerklärung</a>.',
            'en' => 'Details of every service are in our <a href="{{ privacyUrl }}">privacy policy</a>.',
        ],
        'cookieName' => ['de' => 'Name', 'en' => 'Name'],
        'cookieService' => ['de' => 'Dienst', 'en' => 'Service'],
        'cookieDuration' => ['de' => 'Speicherdauer', 'en' => 'Duration'],
    ],
];
