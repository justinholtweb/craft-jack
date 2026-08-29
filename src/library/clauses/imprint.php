<?php

/**
 * Imprint clauses — § 5 DDG, § 18 (2) MStV.
 *
 * The imprint is the one legal text with a statutory *list* behind it, so these clauses map almost
 * one to one onto the numbered requirements of § 5 (1) DDG and appear in the order the statute
 * names them. Anything not required of you never renders: a sole trader's imprint has no register
 * entry, and Jack does not print an empty heading to prove it thought about one.
 *
 * `{{ dotted.key }}` is a fact. `[[ … ]]` is an optional run that vanishes whole if any fact
 * inside it is empty — that is how "Telefon:" stops appearing on the imprint of a business that
 * does not publish a phone number.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'imprint.provider',
        'documentTypes' => ['imprint'],
        'order' => 100,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 5 Abs. 1 Nr. 1 DDG',
        'requires' => ['company.name', 'company.street', 'company.postalCode', 'company.city'],
        'heading' => [
            'de' => 'Angaben gemäß § 5 DDG',
            'en' => 'Information pursuant to § 5 DDG',
        ],
        'body' => [
            'de' => "<p>{{ company.name }}<br>\n[[{{ company.addressExtra }}<br>\n]]{{ company.street }}<br>\n{{ company.postalCode }} {{ company.city }}[[<br>\n{{ company.country }}]]</p>",
            'en' => "<p>{{ company.name }}<br>\n[[{{ company.addressExtra }}<br>\n]]{{ company.street }}<br>\n{{ company.postalCode }} {{ company.city }}[[<br>\n{{ company.country }}]]</p>",
        ],
    ],
    [
        'key' => 'imprint.representative',
        'documentTypes' => ['imprint'],
        'order' => 150,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 5 Abs. 1 Nr. 1 DDG',
        'when' => ['factFilled' => ['company.representative']],
        'heading' => [
            'de' => 'Vertreten durch',
            'en' => 'Represented by',
        ],
        'body' => [
            'de' => '<p>{{ company.representative }}</p>',
            'en' => '<p>{{ company.representative }}</p>',
        ],
    ],
    [
        'key' => 'imprint.contact',
        'documentTypes' => ['imprint'],
        'order' => 200,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 5 Abs. 1 Nr. 2 DDG',
        'requires' => ['contact.email'],
        'heading' => [
            'de' => 'Kontakt',
            'en' => 'Contact',
        ],
        'body' => [
            'de' => "<p>[[Telefon: {{ contact.phone }}<br>\n]][[Telefax: {{ contact.fax }}<br>\n]]E-Mail: <a href=\"mailto:{{ contact.email }}\">{{ contact.email }}</a>[[<br>\nKontaktformular: <a href=\"{{ contact.formUrl }}\">{{ contact.formUrl }}</a>]]</p>",
            'en' => "<p>[[Phone: {{ contact.phone }}<br>\n]][[Fax: {{ contact.fax }}<br>\n]]Email: <a href=\"mailto:{{ contact.email }}\">{{ contact.email }}</a>[[<br>\nContact form: <a href=\"{{ contact.formUrl }}\">{{ contact.formUrl }}</a>]]</p>",
        ],
    ],
    [
        'key' => 'imprint.register',
        'documentTypes' => ['imprint'],
        'order' => 300,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 5 Abs. 1 Nr. 4 DDG',
        'when' => ['factFilled' => ['company.registerNumber']],
        'requires' => ['company.registerCourt'],
        'heading' => [
            'de' => 'Registereintrag',
            'en' => 'Register entry',
        ],
        'body' => [
            'de' => "<p>Registergericht: {{ company.registerCourt }}<br>\nRegisternummer: {{ company.registerNumber }}</p>",
            'en' => "<p>Register court: {{ company.registerCourt }}<br>\nRegister number: {{ company.registerNumber }}</p>",
        ],
    ],
    [
        'key' => 'imprint.vat',
        'documentTypes' => ['imprint'],
        'order' => 400,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 27 a UStG',
        'when' => ['factFilled' => ['company.vatId']],
        'heading' => [
            'de' => 'Umsatzsteuer-ID',
            'en' => 'VAT identification number',
        ],
        'body' => [
            'de' => "<p>Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:<br>\n{{ company.vatId }}</p>",
            'en' => "<p>VAT identification number pursuant to § 27 a of the German VAT Act:<br>\n{{ company.vatId }}</p>",
        ],
    ],
    [
        'key' => 'imprint.economicId',
        'documentTypes' => ['imprint'],
        'order' => 410,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => ['factFilled' => ['company.economicId']],
        'heading' => [
            'de' => 'Wirtschafts-ID',
            'en' => 'Economic identification number',
        ],
        'body' => [
            'de' => '<p>Wirtschafts-Identifikationsnummer: {{ company.economicId }}</p>',
            'en' => '<p>Economic identification number: {{ company.economicId }}</p>',
        ],
    ],
    [
        'key' => 'imprint.profession',
        'documentTypes' => ['imprint'],
        'order' => 500,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 5 Abs. 1 Nr. 5 DDG',
        'when' => ['factTruthy' => ['profession.regulated']],
        'requires' => ['profession.title', 'profession.chamber', 'profession.rules'],
        'heading' => [
            'de' => 'Angaben zur Berufshaftpflicht und Berufsbezeichnung',
            'en' => 'Regulated profession',
        ],
        'body' => [
            'de' => "<p>Berufsbezeichnung: {{ profession.title }}<br>\nZuständige Kammer: {{ profession.chamber }}<br>\nEs gelten folgende berufsrechtliche Regelungen: {{ profession.rules }}[[<br>\nEinsehbar unter: <a href=\"{{ profession.rulesUrl }}\">{{ profession.rulesUrl }}</a>]]</p>",
            'en' => "<p>Professional title: {{ profession.title }}<br>\nSupervising chamber: {{ profession.chamber }}<br>\nThe following professional regulations apply: {{ profession.rules }}[[<br>\nPublished at: <a href=\"{{ profession.rulesUrl }}\">{{ profession.rulesUrl }}</a>]]</p>",
        ],
    ],
    [
        'key' => 'imprint.supervisory',
        'documentTypes' => ['imprint'],
        'order' => 550,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 5 Abs. 1 Nr. 3 DDG',
        'when' => ['factFilled' => ['supervisory.authority']],
        'heading' => [
            'de' => 'Aufsichtsbehörde',
            'en' => 'Supervisory authority',
        ],
        'body' => [
            'de' => '<p>{{ supervisory.authority }}</p>',
            'en' => '<p>{{ supervisory.authority }}</p>',
        ],
    ],
    [
        'key' => 'imprint.editorial',
        'documentTypes' => ['imprint'],
        'order' => 600,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 18 Abs. 2 MStV',
        'when' => ['factTruthy' => ['editorial.required']],
        'requires' => ['editorial.name'],
        'heading' => [
            'de' => 'Redaktionell verantwortlich',
            'en' => 'Responsible for editorial content',
        ],
        'body' => [
            'de' => "<p>{{ editorial.name }}[[<br>\n{{ editorial.address }}]]</p>",
            'en' => "<p>{{ editorial.name }}[[<br>\n{{ editorial.address }}]]</p>",
        ],
    ],
    [
        'key' => 'imprint.dispute',
        'documentTypes' => ['imprint'],
        'order' => 700,
        'version' => '2.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 36 VSBG',
        'jurisdictions' => ['de', 'at', 'eu'],
        'heading' => [
            'de' => 'Verbraucherstreitbeilegung',
            'en' => 'Consumer dispute resolution',
        ],
        'body' => [
            'de' => '<p>Wir sind nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>',
            'en' => '<p>We are neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board.</p>',
        ],
        'note' => [
            'de' => 'Die Europäische Plattform zur Online-Streitbeilegung (OS-Plattform) wurde zum 20. Juli 2025 eingestellt. Ein Link darauf gehört seitdem nicht mehr ins Impressum und kann als veraltete Pflichtangabe abgemahnt werden.',
            'en' => 'The EU’s online dispute resolution platform shut down on 20 July 2025. Linking to it is no longer required, and a stale link is itself a liability.',
        ],
    ],
    [
        'key' => 'imprint.liabilityContent',
        'documentTypes' => ['imprint'],
        'order' => 800,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§§ 7, 8–10 DDG',
        'jurisdictions' => ['de', 'at', 'ch', 'eu'],
        'heading' => [
            'de' => 'Haftung für Inhalte',
            'en' => 'Liability for content',
        ],
        'body' => [
            'de' => '<p>Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.</p><p>Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.</p>',
            'en' => '<p>As a service provider we are responsible for our own content on these pages under general law, pursuant to § 7 (1) DDG. Under §§ 8 to 10 DDG, however, we are not obliged to monitor third-party information that we transmit or store, nor to investigate circumstances that suggest unlawful activity.</p><p>Obligations to remove or block the use of information under general law remain unaffected. Liability in this respect arises only from the point at which we become aware of a specific infringement. On becoming aware of such infringements we will remove the content in question without delay.</p>',
        ],
    ],
    [
        'key' => 'imprint.liabilityLinks',
        'documentTypes' => ['imprint'],
        'order' => 810,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'jurisdictions' => ['de', 'at', 'ch', 'eu'],
        'heading' => [
            'de' => 'Haftung für Links',
            'en' => 'Liability for links',
        ],
        'body' => [
            'de' => '<p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar.</p><p>Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.</p>',
            'en' => '<p>Our site contains links to external third-party websites whose content is outside our control. We therefore cannot accept any responsibility for that third-party content. The respective provider or operator of the linked pages is always responsible for their content. The linked pages were checked for possible legal violations at the time of linking; no unlawful content was apparent at that point.</p><p>Permanent monitoring of linked pages is not reasonable without concrete evidence of an infringement. On becoming aware of infringements we will remove such links without delay.</p>',
        ],
    ],
    [
        'key' => 'imprint.copyright',
        'documentTypes' => ['imprint'],
        'order' => 820,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'jurisdictions' => ['de', 'at', 'ch', 'eu'],
        'heading' => [
            'de' => 'Urheberrecht',
            'en' => 'Copyright',
        ],
        'body' => [
            'de' => '<p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet.</p><p>Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.</p>',
            'en' => '<p>The content and works created by the site operators on these pages are subject to German copyright law. Reproduction, adaptation, distribution and any kind of exploitation beyond the limits of copyright require the written consent of the respective author or creator. Downloads and copies of this page are permitted for private, non-commercial use only.</p><p>Where the content on this page was not created by the operator, third-party copyright is respected — such content is marked as third-party content. Should you nevertheless become aware of a copyright infringement, please let us know. On becoming aware of infringements we will remove such content without delay.</p>',
        ],
    ],
];
