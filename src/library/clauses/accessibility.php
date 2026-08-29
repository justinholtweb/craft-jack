<?php

/**
 * Accessibility statement clauses — EN 301 549 / WCAG, BFSG.
 *
 * Deliberately blunt about limitations: a statement claiming full conformance with no known
 * issues is the one nobody believes, and under the BFSG an inaccurate statement is worse than a
 * modest one. `accessibility.knownIssues` renders as a list, one line per issue.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'accessibility.statement',
        'documentTypes' => ['accessibility'],
        'order' => 100,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Erklärung zur Barrierefreiheit',
            'en' => 'Accessibility statement',
        ],
        'body' => [
            'de' => '<p>{{ company.name }} ist bemüht, {{ site.name }} barrierefrei zugänglich zu machen. Maßgeblich ist {{ accessibility.standard }}.</p><p>Diese Website ist {{ accessibility.statusText }}.[[ Die Bewertung erfolgte zuletzt am {{ accessibility.assessedOn }}.]]</p>',
            'en' => '<p>{{ company.name }} is committed to making {{ site.name }} accessible. The standard applied is {{ accessibility.standard }}.</p><p>This website is {{ accessibility.statusText }}.[[ It was last assessed on {{ accessibility.assessedOn }}.]]</p>',
        ],
    ],
    [
        'key' => 'accessibility.limitations',
        'documentTypes' => ['accessibility'],
        'order' => 200,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => ['factFilled' => ['accessibility.knownIssues']],
        'generator' => 'accessibilityIssues',
        'heading' => [
            'de' => 'Bekannte Einschränkungen',
            'en' => 'Known limitations',
        ],
        'body' => [
            'de' => '<p>Uns sind die folgenden Einschränkungen bekannt. Wir arbeiten daran.</p>',
            'en' => '<p>We know about the following limitations and are working on them.</p>',
        ],
    ],
    [
        'key' => 'accessibility.feedback',
        'documentTypes' => ['accessibility'],
        'order' => 300,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Rückmeldung und Kontakt',
            'en' => 'Feedback and contact',
        ],
        'body' => [
            'de' => '<p>Sind Ihnen Barrieren aufgefallen, oder benötigen Sie Informationen in einer zugänglicheren Form? Schreiben Sie an <a href="mailto:{{ accessibility.contactEmail }}">{{ accessibility.contactEmail }}</a>. Wir antworten so schnell wie möglich und stellen Ihnen den betreffenden Inhalt auf einem anderen Weg zur Verfügung.</p>',
            'en' => '<p>Have you hit a barrier, or do you need information in a more accessible form? Write to <a href="mailto:{{ accessibility.contactEmail }}">{{ accessibility.contactEmail }}</a>. We will reply as quickly as we can and make the content available another way.</p>',
        ],
    ],
];
