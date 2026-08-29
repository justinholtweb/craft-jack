<?php

/**
 * Standalone disclaimer clauses.
 *
 * German sites normally carry this material inside the imprint, which is why
 * `imprint.liabilityContent` and friends exist. This document type is for the English-speaking
 * convention of a separate disclaimer page, and it says the things a brochure site actually needs
 * to say rather than reprinting the imprint under a different title.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'disclaimer.general',
        'documentTypes' => ['disclaimer'],
        'order' => 100,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Allgemeiner Hinweis',
            'en' => 'General disclaimer',
        ],
        'body' => [
            'de' => '<p>Die Inhalte von {{ site.name }} werden mit Sorgfalt erstellt und dienen ausschließlich der allgemeinen Information. Eine Gewähr für Richtigkeit, Vollständigkeit und Aktualität können wir nicht übernehmen.</p>',
            'en' => '<p>The content of {{ site.name }} is prepared with care and is provided for general information only. We give no warranty as to its accuracy, completeness or currency.</p>',
        ],
    ],
    [
        'key' => 'disclaimer.noAdvice',
        'documentTypes' => ['disclaimer'],
        'order' => 200,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Keine Beratung',
            'en' => 'Not professional advice',
        ],
        'body' => [
            'de' => '<p>Die bereitgestellten Informationen ersetzen keine rechtliche, steuerliche, medizinische oder sonstige fachliche Beratung im Einzelfall. Entscheidungen, die Sie auf Grundlage dieser Inhalte treffen, treffen Sie in eigener Verantwortung.</p>',
            'en' => '<p>Nothing here is a substitute for legal, tax, medical or other professional advice on your particular situation. Any decision you take on the basis of this content is your own.</p>',
        ],
    ],
    [
        'key' => 'disclaimer.links',
        'documentTypes' => ['disclaimer'],
        'order' => 300,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Externe Links',
            'en' => 'External links',
        ],
        'body' => [
            'de' => '<p>Diese Website verweist auf Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Für diese Inhalte ist ausschließlich der jeweilige Anbieter verantwortlich. Eine Verlinkung bedeutet keine Empfehlung.</p>',
            'en' => '<p>This website links to third-party sites whose content is outside our control. The respective provider alone is responsible for that content. A link is not an endorsement.</p>',
        ],
    ],
    [
        'key' => 'disclaimer.liability',
        'documentTypes' => ['disclaimer'],
        'order' => 400,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Haftungsbeschränkung',
            'en' => 'Limitation of liability',
        ],
        'body' => [
            'de' => '<p>Ansprüche gegen uns wegen Schäden materieller oder ideeller Art, die durch die Nutzung oder Nichtnutzung der dargebotenen Informationen verursacht wurden, sind ausgeschlossen, sofern uns kein nachweislich vorsätzliches oder grob fahrlässiges Verschulden trifft. Die Haftung für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit sowie nach dem Produkthaftungsgesetz bleibt unberührt.</p>',
            'en' => '<p>Claims against us for material or immaterial damage caused by the use or non-use of the information offered are excluded unless we are shown to have acted intentionally or with gross negligence. Liability for damage arising from injury to life, body or health, and liability under product liability law, remain unaffected.</p>',
        ],
    ],
];
