<?php

/**
 * Cookie policy clauses.
 *
 * A separate cookie policy is optional — `privacy.cookies` already appears inside the privacy
 * policy, and both documents share it rather than saying the same thing in two voices. What this
 * file adds is the material that only makes sense at length: what the categories mean, and how to
 * take consent back.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'cookies.intro',
        'documentTypes' => ['cookies'],
        'order' => 50,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Cookie-Richtlinie',
            'en' => 'Cookie policy',
        ],
        'body' => [
            'de' => '<p>Diese Richtlinie erklärt, welche Cookies und vergleichbaren Techniken {{ site.name }} einsetzt, warum wir das tun und wie Sie darüber entscheiden. Sie ergänzt unsere Datenschutzerklärung.</p>',
            'en' => '<p>This policy explains which cookies and comparable technologies {{ site.name }} uses, why we use them, and how you decide about them. It supplements our privacy policy.</p>',
        ],
    ],
    [
        'key' => 'cookies.categories',
        'documentTypes' => ['cookies'],
        'order' => 300,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Kategorien',
            'en' => 'Categories',
        ],
        'body' => [
            'de' => '<ul><li><strong>Notwendig</strong> — ohne diese Cookies funktioniert die Website nicht. Sie halten Ihre Sitzung, sichern Formulare gegen Missbrauch und speichern Ihre Cookie-Entscheidung selbst. Sie sind nach § 25 Abs. 2 TDDDG einwilligungsfrei.</li><li><strong>Funktional</strong> — sie merken sich Einstellungen wie Sprache oder Region.</li><li><strong>Statistik</strong> — sie helfen uns zu verstehen, wie die Website genutzt wird.</li><li><strong>Marketing</strong> — sie ermöglichen die Messung und Ausspielung von Werbung, auch website­übergreifend.</li></ul><p>Alles außerhalb der Kategorie „Notwendig“ setzen wir nur nach Ihrer Einwilligung.</p>',
            'en' => '<ul><li><strong>Necessary</strong> — without these the site does not work. They hold your session, protect forms against abuse and remember your cookie choice itself. They need no consent under § 25 (2) TDDDG.</li><li><strong>Functional</strong> — they remember settings such as language or region.</li><li><strong>Statistics</strong> — they help us understand how the site is used.</li><li><strong>Marketing</strong> — they allow adverts to be measured and delivered, including across sites.</li></ul><p>We set nothing outside the “necessary” category without your consent.</p>',
        ],
    ],
    [
        'key' => 'cookies.manage',
        'documentTypes' => ['cookies'],
        'order' => 700,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Ihre Entscheidung ändern',
            'en' => 'Changing your mind',
        ],
        'body' => [
            'de' => '<p>Sie können Ihre Einwilligung jederzeit mit Wirkung für die Zukunft widerrufen. Zusätzlich lässt sich in jedem gängigen Browser einstellen, ob Cookies angenommen, nur im Einzelfall erlaubt oder generell abgelehnt werden und ob sie beim Schließen des Browsers gelöscht werden. Werden notwendige Cookies blockiert, funktionieren Teile dieser Website nicht mehr.</p>',
            'en' => '<p>You can withdraw your consent at any time with effect for the future. Every current browser also lets you decide whether cookies are accepted, permitted case by case or refused outright, and whether they are deleted when the browser closes. Blocking necessary cookies will stop parts of this website working.</p>',
        ],
    ],
];
