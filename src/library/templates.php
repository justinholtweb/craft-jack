<?php

/**
 * Sentence templates for the generated parts of a document.
 *
 * Every word a visitor can read lives under `src/library`, including these. Putting the service
 * paragraph's grammar in the compiler instead would mean legal wording sat in two places and only
 * one of them was reviewable by somebody who is not a PHP developer.
 *
 * Placeholders are the ordinary `{{ key }}` and `[[ … ]]` of the clause library, resolved against
 * a per-service fact map assembled by {@see justinholtweb\jack\services\Compiler}.
 *
 * @var array<string, array<string, array<string, string>>>
 */

return [
    'service' => [
        'intro' => [
            'de' => '<p>Diese Website nutzt {{ service.label }} für {{ service.purpose }}.</p>',
            'en' => '<p>This website uses {{ service.label }} for {{ service.purpose }}.</p>',
        ],
        'introFirstParty' => [
            'de' => '<p>Wir verarbeiten Daten für {{ service.purpose }}.</p>',
            'en' => '<p>We process data for {{ service.purpose }}.</p>',
        ],
        'provider' => [
            'de' => '<p>Anbieter ist {{ service.provider }}[[, {{ service.providerAddress }}]].[[ Muttergesellschaft: {{ service.parent }}.]]</p>',
            'en' => '<p>The provider is {{ service.provider }}[[, {{ service.providerAddress }}]].[[ Parent company: {{ service.parent }}.]]</p>',
        ],
        // Colons rather than sentences, in both languages, because the phrase being dropped in
        // is a noun phrase whose number and case Jack does not know: "Verarbeitet werden dabei
        // eine zufällige Sitzungskennung" is wrong, and so is every other fixed verb.
        'data' => [
            'de' => '<p>Dabei werden die folgenden Daten verarbeitet: {{ service.data }}.</p>',
            'en' => '<p>The following data is processed: {{ service.data }}.</p>',
        ],
        'basis.consent' => [
            'de' => '<p>Rechtsgrundlage ist Ihre Einwilligung nach Art. 6 Abs. 1 lit. a DSGVO und § 25 Abs. 1 TDDDG. Sie können sie jederzeit mit Wirkung für die Zukunft widerrufen.</p>',
            'en' => '<p>The legal basis is your consent under Art. 6 (1) (a) GDPR and § 25 (1) TDDDG. You may withdraw it at any time with effect for the future.</p>',
        ],
        'basis.contract' => [
            'de' => '<p>Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO: die Verarbeitung ist zur Erfüllung des Vertrags mit Ihnen oder zur Durchführung vorvertraglicher Maßnahmen erforderlich.</p>',
            'en' => '<p>The legal basis is Art. 6 (1) (b) GDPR: the processing is necessary to perform our contract with you or to take steps before entering into one.</p>',
        ],
        // Refers back to the purpose rather than repeating it. The purpose phrases are written
        // for the accusative slot after "für" in the intro sentence; dropping the same words into
        // a nominative or dative slot here produced "Unser berechtigtes Interesse: den technisch
        // fehlerfreien Betrieb", which is wrong in a way an English-speaking author will not see.
        // In an inflected language a phrase belongs to one grammatical frame, and generated text
        // must not borrow it for a second.
        'basis.interest' => [
            'de' => '<p>Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO. Unser berechtigtes Interesse ergibt sich aus dem oben genannten Zweck.</p>',
            'en' => '<p>The legal basis is Art. 6 (1) (f) GDPR. Our legitimate interest is the purpose named above.</p>',
        ],
        // Only said where the service actually touches the device.
        'strictlyNecessary' => [
            'de' => '<p>Soweit dabei Informationen auf Ihrem Endgerät gespeichert oder ausgelesen werden, ist dies nach § 25 Abs. 2 TDDDG für den von Ihnen ausdrücklich gewünschten Dienst unbedingt erforderlich.</p>',
            'en' => '<p>Where information is stored on or read from your device, this is strictly necessary under § 25 (2) TDDDG for the service you have expressly requested.</p>',
        ],
        'basis.obligation' => [
            'de' => '<p>Rechtsgrundlage ist Art. 6 Abs. 1 lit. c DSGVO: die Verarbeitung erfüllt eine rechtliche Verpflichtung, der wir unterliegen.</p>',
            'en' => '<p>The legal basis is Art. 6 (1) (c) GDPR: the processing fulfils a legal obligation to which we are subject.</p>',
        ],
        'retention' => [
            'de' => '<p>Speicherdauer: {{ service.retention }}.</p>',
            'en' => '<p>Retention: {{ service.retention }}.</p>',
        ],
        'transfer.adequacy' => [
            'de' => '<p>Die Verarbeitung findet auch außerhalb der EU statt. Für das betreffende Land liegt ein Angemessenheitsbeschluss der Europäischen Kommission nach Art. 45 DSGVO vor.</p>',
            'en' => '<p>Processing also takes place outside the EU. The European Commission has issued an adequacy decision for the country concerned under Art. 45 GDPR.</p>',
        ],
        'transfer.dpf' => [
            'de' => '<p>Die Verarbeitung findet auch in den USA statt. Der Anbieter ist unter dem EU-US Data Privacy Framework zertifiziert; die Übermittlung stützt sich auf den Angemessenheitsbeschluss der Europäischen Kommission vom 10. Juli 2023 nach Art. 45 DSGVO.</p>',
            'en' => '<p>Processing also takes place in the United States. The provider is certified under the EU–US Data Privacy Framework, and the transfer relies on the European Commission’s adequacy decision of 10 July 2023 under Art. 45 GDPR.</p>',
        ],
        'transfer.scc' => [
            'de' => '<p>Die Verarbeitung findet auch außerhalb der EU statt. Die Übermittlung stützt sich auf Standardvertragsklauseln der Europäischen Kommission nach Art. 46 Abs. 2 lit. c DSGVO. Ein dem europäischen Recht entsprechendes Schutzniveau kann dort nicht in jedem Fall garantiert werden.</p>',
            'en' => '<p>Processing also takes place outside the EU. The transfer relies on the European Commission’s standard contractual clauses under Art. 46 (2) (c) GDPR. A level of protection equivalent to European law cannot be guaranteed there in every case.</p>',
        ],
        'jointController' => [
            'de' => '<p>Für die Erhebung und Übermittlung der Daten besteht eine gemeinsame Verantwortlichkeit nach Art. 26 DSGVO.</p>',
            'en' => '<p>Collection and transmission are subject to joint controllership under Art. 26 GDPR.</p>',
        ],
        'links' => [
            'de' => '<p>[[Datenschutzerklärung des Anbieters: <a href="{{ service.privacyUrl }}" rel="noopener nofollow" target="_blank">{{ service.privacyUrl }}</a>.]][[ Widerspruchsmöglichkeit: <a href="{{ service.optOutUrl }}" rel="noopener nofollow" target="_blank">{{ service.optOutUrl }}</a>.]]</p>',
            'en' => '<p>[[Provider’s privacy policy: <a href="{{ service.privacyUrl }}" rel="noopener nofollow" target="_blank">{{ service.privacyUrl }}</a>.]][[ Opt out: <a href="{{ service.optOutUrl }}" rel="noopener nofollow" target="_blank">{{ service.optOutUrl }}</a>.]]</p>',
        ],
        'cookiesIntro' => [
            'de' => '<p>Gesetzte Cookies:</p>',
            'en' => '<p>Cookies set:</p>',
        ],
        'cookieItem' => [
            'de' => '<li><code>{{ cookie.name }}</code> — {{ cookie.duration }}</li>',
            'en' => '<li><code>{{ cookie.name }}</code> — {{ cookie.duration }}</li>',
        ],
        'cookieless' => [
            'de' => '<p>Der Dienst arbeitet ohne Cookies und ohne wiedererkennbare Kennungen.</p>',
            'en' => '<p>The service works without cookies and without any re-identifiable identifier.</p>',
        ],
    ],

    'cookieTable' => [
        'columns' => [
            'de' => '<tr><th scope="col">Name</th><th scope="col">Dienst</th><th scope="col">Kategorie</th><th scope="col">Speicherdauer</th></tr>',
            'en' => '<tr><th scope="col">Name</th><th scope="col">Service</th><th scope="col">Category</th><th scope="col">Duration</th></tr>',
        ],
        'row' => [
            'de' => '<tr><td><code>{{ cookie.name }}</code></td><td>{{ cookie.service }}</td><td>{{ cookie.category }}</td><td>{{ cookie.duration }}</td></tr>',
            'en' => '<tr><td><code>{{ cookie.name }}</code></td><td>{{ cookie.service }}</td><td>{{ cookie.category }}</td><td>{{ cookie.duration }}</td></tr>',
        ],
        'empty' => [
            'de' => '<p>Auf dieser Website werden derzeit keine Cookies gesetzt, die einer Einwilligung bedürfen.</p>',
            'en' => '<p>This website currently sets no cookies that require consent.</p>',
        ],
        'necessary' => ['de' => 'Notwendig', 'en' => 'Necessary'],
    ],

    'categories' => [
        'functional' => ['de' => 'Funktionen dieser Website', 'en' => 'Site functions'],
        'infrastructure' => ['de' => 'Betrieb und Auslieferung', 'en' => 'Infrastructure and delivery'],
        'security' => ['de' => 'Sicherheit', 'en' => 'Security'],
        'payment' => ['de' => 'Zahlungsdienste', 'en' => 'Payment services'],
        'email' => ['de' => 'E-Mail und Newsletter', 'en' => 'Email and newsletters'],
        'support' => ['de' => 'Support und Chat', 'en' => 'Support and chat'],
        'booking' => ['de' => 'Terminbuchung', 'en' => 'Appointment booking'],
        'media' => ['de' => 'Eingebettete Medien', 'en' => 'Embedded media'],
        'fonts' => ['de' => 'Schriftarten und Symbole', 'en' => 'Fonts and icons'],
        'maps' => ['de' => 'Karten', 'en' => 'Maps'],
        'analytics' => ['de' => 'Analyse und Reichweitenmessung', 'en' => 'Analytics'],
        'advertising' => ['de' => 'Werbung', 'en' => 'Advertising'],
        'social' => ['de' => 'Soziale Netzwerke', 'en' => 'Social networks'],
    ],
    // The registry writes each provider's address once, ending in the country in German. The
    // compiler swaps that last part for the document's language, so an English privacy policy
    // does not send readers to "Dublin 2, Irland".
    'countries' => [
        'Deutschland' => ['de' => 'Deutschland', 'en' => 'Germany'],
        'Estland' => ['de' => 'Estland', 'en' => 'Estonia'],
        'Frankreich' => ['de' => 'Frankreich', 'en' => 'France'],
        'Irland' => ['de' => 'Irland', 'en' => 'Ireland'],
        'Kanada' => ['de' => 'Kanada', 'en' => 'Canada'],
        'Vereinigtes Königreich' => ['de' => 'Vereinigtes Königreich', 'en' => 'United Kingdom'],
        'Luxemburg' => ['de' => 'Luxemburg', 'en' => 'Luxembourg'],
        'Malta' => ['de' => 'Malta', 'en' => 'Malta'],
        'Neuseeland' => ['de' => 'Neuseeland', 'en' => 'New Zealand'],
        'Niederlande' => ['de' => 'Niederlande', 'en' => 'Netherlands'],
        'Schweden' => ['de' => 'Schweden', 'en' => 'Sweden'],
        'USA' => ['de' => 'USA', 'en' => 'USA'],
    ],
    // Printed by the renderer under a published document, not compiled into it: the date is the
    // publish date, which the compiled text cannot know.
    'document' => [
        'lastUpdated' => ['de' => 'Stand: {{ date }}', 'en' => 'Last updated: {{ date }}'],
    ],
];
