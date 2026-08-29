<?php

/**
 * Every fact a bundled clause can ask for.
 *
 * This file is the schema for {@see justinholtweb\jack\models\Profile}: the profile form is
 * generated from it, the compiler resolves `{{ dotted.key }}` placeholders against it, and a
 * clause that names a key here in its `requires` list produces a "you still need to tell Jack X"
 * warning instead of a document with a hole in it.
 *
 * Keys are dotted and flat. There is no nesting anywhere in Jack — a fact map is a
 * `['company.name' => 'Acme GmbH']` array from end to end, because the moment it is a tree,
 * half the code has to know the shape of the tree.
 *
 * `group` drives the tabs on the profile screen. `showWhen` hides a field until it can matter
 * (register numbers only exist for registered legal forms), and is advisory only: the compiler
 * never trusts it, since a legal form can change after the fields were filled in.
 *
 * @var array<string, array<string, mixed>>
 */

return [
    // ---------------------------------------------------------------------------------------
    // Who you are
    // ---------------------------------------------------------------------------------------
    'company.name' => [
        'group' => 'company',
        'label' => 'Legal name',
        'hint' => 'Exactly as registered — not the brand or the domain.',
        'type' => 'text',
        'placeholder' => 'Acme GmbH',
    ],
    'company.legalForm' => [
        'group' => 'company',
        'label' => 'Legal form',
        'hint' => 'Decides which imprint entries German law requires of you.',
        'type' => 'select',
        'options' => [
            '' => '',
            'sole' => 'Sole trader / Einzelunternehmen',
            'freelance' => 'Freelancer / Freiberufler',
            'gbr' => 'GbR',
            'ug' => 'UG (haftungsbeschränkt)',
            'gmbh' => 'GmbH',
            'gmbhcokg' => 'GmbH & Co. KG',
            'ag' => 'AG',
            'ohg' => 'OHG',
            'kg' => 'KG',
            'ev' => 'e.V.',
            'ltd' => 'Ltd / Limited company',
            'llc' => 'LLC',
            'inc' => 'Inc / Corporation',
            'other' => 'Other',
        ],
    ],
    'company.representative' => [
        'group' => 'company',
        'label' => 'Authorised representative',
        'hint' => 'Managing director, board member or owner. § 5 (1) no. 1 DDG.',
        'type' => 'text',
        'showWhen' => ['company.legalForm' => ['ug', 'gmbh', 'gmbhcokg', 'ag', 'ohg', 'kg', 'ev', 'ltd', 'llc', 'inc', 'other']],
    ],
    'company.street' => [
        'group' => 'company',
        'label' => 'Street and number',
        'hint' => 'A PO box is not enough — the address has to be one you can be served at.',
        'type' => 'text',
    ],
    'company.addressExtra' => [
        'group' => 'company',
        'label' => 'Address line 2',
        'type' => 'text',
    ],
    'company.postalCode' => [
        'group' => 'company',
        'label' => 'Postal code',
        'type' => 'text',
    ],
    'company.city' => [
        'group' => 'company',
        'label' => 'City',
        'type' => 'text',
    ],
    'company.state' => [
        'group' => 'company',
        'label' => 'State / province',
        'type' => 'text',
    ],
    'company.country' => [
        'group' => 'company',
        'label' => 'Country',
        'type' => 'text',
        'placeholder' => 'Deutschland',
    ],
    'company.registerCourt' => [
        'group' => 'company',
        'label' => 'Register court',
        'hint' => 'Registergericht, e.g. “Amtsgericht Charlottenburg”.',
        'type' => 'text',
        'showWhen' => ['company.legalForm' => ['ug', 'gmbh', 'gmbhcokg', 'ag', 'ohg', 'kg', 'ev']],
    ],
    'company.registerNumber' => [
        'group' => 'company',
        'label' => 'Register number',
        'hint' => 'HRB 12345, VR 4711 …',
        'type' => 'text',
        'showWhen' => ['company.legalForm' => ['ug', 'gmbh', 'gmbhcokg', 'ag', 'ohg', 'kg', 'ev']],
    ],
    'company.vatId' => [
        'group' => 'company',
        'label' => 'VAT ID',
        'hint' => 'USt-IdNr. under § 27 a UStG. Leave empty if you do not have one — do not put the tax number here.',
        'type' => 'text',
        'placeholder' => 'DE123456789',
    ],
    'company.economicId' => [
        'group' => 'company',
        'label' => 'Economic ID',
        'hint' => 'Wirtschafts-Identifikationsnummer, if issued.',
        'type' => 'text',
    ],

    // ---------------------------------------------------------------------------------------
    // Reaching you
    // ---------------------------------------------------------------------------------------
    'contact.email' => [
        'group' => 'contact',
        'label' => 'Email address',
        'hint' => 'Must be a real, monitored address. § 5 (1) no. 2 DDG requires one.',
        'type' => 'email',
    ],
    'contact.phone' => [
        'group' => 'contact',
        'label' => 'Phone',
        'type' => 'tel',
    ],
    'contact.fax' => [
        'group' => 'contact',
        'label' => 'Fax',
        'type' => 'tel',
    ],
    'contact.formUrl' => [
        'group' => 'contact',
        'label' => 'Contact form URL',
        'type' => 'url',
    ],

    // ---------------------------------------------------------------------------------------
    // Editorial responsibility (§ 18 (2) MStV)
    // ---------------------------------------------------------------------------------------
    'editorial.required' => [
        'group' => 'contact',
        'label' => 'Site carries journalistic or editorial content',
        'hint' => 'A blog with opinion pieces or news usually does. A brochure site usually does not.',
        'type' => 'bool',
    ],
    'editorial.name' => [
        'group' => 'contact',
        'label' => 'Responsible for content',
        'hint' => 'A named natural person, not a department.',
        'type' => 'text',
        'showWhen' => ['editorial.required' => [true]],
    ],
    'editorial.address' => [
        'group' => 'contact',
        'label' => 'Their address',
        'hint' => 'May be the company address.',
        'type' => 'textarea',
        'showWhen' => ['editorial.required' => [true]],
    ],

    // ---------------------------------------------------------------------------------------
    // Regulated professions (§ 5 (1) no. 5 DDG)
    // ---------------------------------------------------------------------------------------
    'profession.regulated' => [
        'group' => 'profession',
        'label' => 'Regulated profession',
        'hint' => 'Doctors, lawyers, tax advisers, architects, engineers, pharmacists and similar.',
        'type' => 'bool',
    ],
    'profession.title' => [
        'group' => 'profession',
        'label' => 'Professional title',
        'hint' => 'And the state that awarded it.',
        'type' => 'text',
        'showWhen' => ['profession.regulated' => [true]],
    ],
    'profession.chamber' => [
        'group' => 'profession',
        'label' => 'Supervising chamber',
        'type' => 'text',
        'showWhen' => ['profession.regulated' => [true]],
    ],
    'profession.rules' => [
        'group' => 'profession',
        'label' => 'Professional regulations',
        'type' => 'text',
        'showWhen' => ['profession.regulated' => [true]],
    ],
    'profession.rulesUrl' => [
        'group' => 'profession',
        'label' => 'Where those regulations are published',
        'type' => 'url',
        'showWhen' => ['profession.regulated' => [true]],
    ],
    'supervisory.authority' => [
        'group' => 'profession',
        'label' => 'Supervisory authority',
        'hint' => 'Required where your activity needs official authorisation — brokerage, insurance mediation, gambling, financial services.',
        'type' => 'textarea',
    ],

    // ---------------------------------------------------------------------------------------
    // Data protection
    // ---------------------------------------------------------------------------------------
    'controller.sameAsCompany' => [
        'group' => 'privacy',
        'label' => 'The controller is the company above',
        'hint' => 'Turn off only if a different entity decides why and how data is processed.',
        'type' => 'bool',
        'default' => true,
    ],
    'controller.name' => [
        'group' => 'privacy',
        'label' => 'Controller',
        'type' => 'text',
        'showWhen' => ['controller.sameAsCompany' => [false]],
    ],
    'controller.address' => [
        'group' => 'privacy',
        'label' => 'Controller address',
        'type' => 'textarea',
        'showWhen' => ['controller.sameAsCompany' => [false]],
    ],
    'dpo.present' => [
        'group' => 'privacy',
        'label' => 'We have a data protection officer',
        'hint' => 'Mandatory in Germany from 20 people regularly processing personal data, and for some activities regardless of headcount.',
        'type' => 'bool',
    ],
    'dpo.name' => [
        'group' => 'privacy',
        'label' => 'DPO name',
        'type' => 'text',
        'showWhen' => ['dpo.present' => [true]],
    ],
    'dpo.address' => [
        'group' => 'privacy',
        'label' => 'DPO address',
        'type' => 'textarea',
        'showWhen' => ['dpo.present' => [true]],
    ],
    'dpo.email' => [
        'group' => 'privacy',
        'label' => 'DPO email',
        'type' => 'email',
        'showWhen' => ['dpo.present' => [true]],
    ],
    'dpo.phone' => [
        'group' => 'privacy',
        'label' => 'DPO phone',
        'type' => 'tel',
        'showWhen' => ['dpo.present' => [true]],
    ],
    'authority.name' => [
        'group' => 'privacy',
        'label' => 'Data protection authority',
        'hint' => 'The one people complain to under Art. 77 GDPR — normally the authority of the state you are established in.',
        'type' => 'text',
        'placeholder' => 'Der Hamburgische Beauftragte für Datenschutz und Informationsfreiheit',
    ],
    'authority.url' => [
        'group' => 'privacy',
        'label' => 'Authority website',
        'type' => 'url',
    ],
    'retention.default' => [
        'group' => 'privacy',
        'label' => 'Default retention',
        'hint' => 'What you say when nothing more specific applies.',
        'type' => 'text',
        'placeholder' => 'until the purpose no longer applies, subject to statutory retention periods',
    ],

    // ---------------------------------------------------------------------------------------
    // Hosting
    // ---------------------------------------------------------------------------------------
    'hosting.provider' => [
        'group' => 'hosting',
        'label' => 'Hosting provider',
        'type' => 'text',
        'placeholder' => 'Hetzner Online GmbH',
    ],
    'hosting.address' => [
        'group' => 'hosting',
        'label' => 'Their address',
        'type' => 'textarea',
    ],
    'hosting.country' => [
        'group' => 'hosting',
        'label' => 'Where the servers are',
        'type' => 'text',
        'placeholder' => 'Deutschland',
    ],
    'hosting.privacyUrl' => [
        'group' => 'hosting',
        'label' => 'Their privacy policy',
        'type' => 'url',
    ],
    'hosting.dpa' => [
        'group' => 'hosting',
        'label' => 'A data processing agreement is in place',
        'hint' => 'Art. 28 GDPR. If this is off, the hosting clause says so out loud rather than claiming otherwise.',
        'type' => 'bool',
    ],

    // ---------------------------------------------------------------------------------------
    // The site itself
    // ---------------------------------------------------------------------------------------
    'site.name' => [
        'group' => 'site',
        'label' => 'Site name',
        'hint' => 'Left empty, Jack uses the Craft site’s name.',
        'type' => 'text',
    ],
    'site.url' => [
        'group' => 'site',
        'label' => 'Site URL',
        'hint' => 'Left empty, Jack uses the Craft site’s base URL.',
        'type' => 'url',
    ],
    'site.audience' => [
        'group' => 'site',
        'label' => 'Primary audience',
        'hint' => 'Which regime the generated texts lead with.',
        'type' => 'select',
        'options' => [
            'de' => 'Germany',
            'at' => 'Austria',
            'ch' => 'Switzerland',
            'eu' => 'European Union (other)',
            'uk' => 'United Kingdom',
            'us' => 'United States',
        ],
        'default' => 'de',
    ],
    'site.minors' => [
        'group' => 'site',
        'label' => 'The site is aimed at, or knowingly used by, under-16s',
        'type' => 'bool',
    ],
    'site.sellsOnline' => [
        'group' => 'site',
        'label' => 'We sell online',
        'hint' => 'Turns on the terms, withdrawal and dispute-resolution clauses.',
        'type' => 'bool',
    ],
    'site.accounts' => [
        'group' => 'site',
        'label' => 'Visitors can register an account',
        'type' => 'bool',
    ],

    // ---------------------------------------------------------------------------------------
    // Selling
    // ---------------------------------------------------------------------------------------
    'terms.deliveryTime' => [
        'group' => 'terms',
        'label' => 'Standard delivery time',
        'type' => 'text',
        'placeholder' => '2–5 Werktage',
        'showWhen' => ['site.sellsOnline' => [true]],
    ],
    'terms.withdrawalDays' => [
        'group' => 'terms',
        'label' => 'Withdrawal period (days)',
        'hint' => 'Fourteen days is the statutory minimum for EU consumer sales.',
        'type' => 'text',
        'default' => '14',
        'showWhen' => ['site.sellsOnline' => [true]],
    ],
    'terms.returnsAddress' => [
        'group' => 'terms',
        'label' => 'Returns address',
        'type' => 'textarea',
        'showWhen' => ['site.sellsOnline' => [true]],
    ],
    'terms.governingLaw' => [
        'group' => 'terms',
        'label' => 'Governing law',
        'type' => 'text',
        'placeholder' => 'Recht der Bundesrepublik Deutschland',
    ],
    'terms.venue' => [
        'group' => 'terms',
        'label' => 'Place of jurisdiction',
        'hint' => 'Only enforceable against merchants — the clause says as much.',
        'type' => 'text',
    ],

    // ---------------------------------------------------------------------------------------
    // Accessibility
    // ---------------------------------------------------------------------------------------
    'accessibility.status' => [
        'group' => 'accessibility',
        'label' => 'Conformance',
        'type' => 'select',
        'options' => [
            'full' => 'Fully conformant',
            'partial' => 'Partially conformant',
            'none' => 'Not conformant',
        ],
        'default' => 'partial',
    ],
    'accessibility.standard' => [
        'group' => 'accessibility',
        'label' => 'Standard',
        'type' => 'text',
        'default' => 'EN 301 549 / WCAG 2.1 AA',
    ],
    'accessibility.knownIssues' => [
        'group' => 'accessibility',
        'label' => 'Known limitations',
        'hint' => 'One per line. An accessibility statement claiming no known issues is rarely believed.',
        'type' => 'textarea',
    ],
    'accessibility.assessedOn' => [
        'group' => 'accessibility',
        'label' => 'Last assessed',
        'type' => 'text',
    ],
    'accessibility.feedbackEmail' => [
        'group' => 'accessibility',
        'label' => 'Feedback address',
        'type' => 'email',
    ],
];
