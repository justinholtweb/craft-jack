<?php

/**
 * Clauses whose body is not written but built.
 *
 * A generated clause carries no text of its own — it names a `generator`, and
 * {@see justinholtweb\jack\services\Compiler} fills it from the inventory. They are ordinary
 * clause definitions in every other respect so that ordering, editions, jurisdiction filtering
 * and per-clause overrides work on them exactly as they do on written ones, rather than being
 * a second mechanism nobody remembers exists.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'privacy.services',
        'documentTypes' => ['privacy'],
        'order' => 500,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'generator' => 'services',
        'heading' => [
            'de' => 'Dienste und Empfänger',
            'en' => 'Services and recipients',
        ],
        'body' => [
            'de' => '<p>Im Folgenden nennen wir die Dienste Dritter, die auf dieser Website zum Einsatz kommen, sowie Zweck, Rechtsgrundlage und Empfänger der jeweiligen Verarbeitung.</p>',
            'en' => '<p>Below we name the third-party services used on this website, together with the purpose, legal basis and recipient of each processing operation.</p>',
        ],
    ],
    [
        'key' => 'cookies.table',
        'documentTypes' => ['cookies'],
        'order' => 500,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'generator' => 'cookieTable',
        'heading' => [
            'de' => 'Eingesetzte Cookies',
            'en' => 'Cookies in use',
        ],
        'body' => [
            'de' => '<p>Die folgende Übersicht nennt die Cookies, die auf dieser Website gesetzt werden können, sowie ihre Speicherdauer und ihren Zweck.</p>',
            'en' => '<p>The following table lists the cookies this website may set, how long they last and what they are for.</p>',
        ],
    ],
];
