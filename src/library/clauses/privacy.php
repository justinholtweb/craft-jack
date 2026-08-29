<?php

/**
 * Privacy policy clauses — the frame around the processor sections.
 *
 * These are the paragraphs that hold true regardless of which third parties a site uses:
 * who the controller is, what the legal bases mean, what rights the reader has and how to use
 * them. The middle of the document — one section per service — is not written here at all. It is
 * rendered from {@see src/library/services.php} by {@see justinholtweb\jack\services\Compiler},
 * which is why a site that adds Stripe gets a correct Stripe section without anybody writing one.
 *
 * Ordering leaves 500–599 free for those generated sections.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'privacy.intro',
        'documentTypes' => ['privacy'],
        'order' => 50,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Datenschutz auf einen Blick',
            'en' => 'Privacy at a glance',
        ],
        'body' => [
            'de' => '<p>Wir nehmen den Schutz Ihrer persönlichen Daten sehr ernst. Diese Datenschutzerklärung erläutert, welche personenbezogenen Daten wir erheben, wenn Sie {{ site.name }} nutzen, wozu wir sie verwenden und welche Rechte Ihnen zustehen.</p><p>Personenbezogene Daten sind alle Daten, mit denen Sie persönlich identifiziert werden können. Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber; dessen Kontaktdaten finden Sie im nächsten Abschnitt.</p>',
            'en' => '<p>We take the protection of your personal data seriously. This privacy policy explains what personal data we collect when you use {{ site.name }}, what we use it for, and what rights you have.</p><p>Personal data is any data by which you can be personally identified. Data processing on this website is carried out by the site operator, whose contact details are in the next section.</p>',
        ],
    ],
    [
        'key' => 'privacy.controller',
        'documentTypes' => ['privacy'],
        'order' => 100,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 13 Abs. 1 lit. a DSGVO',
        'requires' => ['contact.email'],
        'heading' => [
            'de' => 'Verantwortliche Stelle',
            'en' => 'Controller',
        ],
        'body' => [
            'de' => "<p>Verantwortlich für die Datenverarbeitung auf dieser Website ist:</p><p>{{ controller.display }}</p><p>[[Telefon: {{ contact.phone }}<br>\n]]E-Mail: <a href=\"mailto:{{ contact.email }}\">{{ contact.email }}</a></p><p>Verantwortliche Stelle ist die natürliche oder juristische Person, die allein oder gemeinsam mit anderen über die Zwecke und Mittel der Verarbeitung von personenbezogenen Daten entscheidet.</p>",
            'en' => "<p>The controller responsible for data processing on this website is:</p><p>{{ controller.display }}</p><p>[[Phone: {{ contact.phone }}<br>\n]]Email: <a href=\"mailto:{{ contact.email }}\">{{ contact.email }}</a></p><p>The controller is the natural or legal person who alone or jointly with others determines the purposes and means of processing personal data.</p>",
        ],
    ],
    [
        'key' => 'privacy.dpo',
        'documentTypes' => ['privacy'],
        'order' => 150,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 37 DSGVO',
        'when' => ['factTruthy' => ['dpo.present']],
        'requires' => ['dpo.name'],
        'heading' => [
            'de' => 'Datenschutzbeauftragter',
            'en' => 'Data protection officer',
        ],
        'body' => [
            'de' => "<p>Wir haben einen Datenschutzbeauftragten benannt:</p><p>{{ dpo.name }}[[<br>\n{{ dpo.address }}]][[<br>\nTelefon: {{ dpo.phone }}]][[<br>\nE-Mail: <a href=\"mailto:{{ dpo.email }}\">{{ dpo.email }}</a>]]</p>",
            'en' => "<p>We have appointed a data protection officer:</p><p>{{ dpo.name }}[[<br>\n{{ dpo.address }}]][[<br>\nPhone: {{ dpo.phone }}]][[<br>\nEmail: <a href=\"mailto:{{ dpo.email }}\">{{ dpo.email }}</a>]]</p>",
        ],
    ],
    [
        'key' => 'privacy.legalBases',
        'documentTypes' => ['privacy'],
        'order' => 200,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 6 DSGVO',
        'heading' => [
            'de' => 'Rechtsgrundlagen der Verarbeitung',
            'en' => 'Legal bases for processing',
        ],
        'body' => [
            'de' => '<p>Wir verarbeiten personenbezogene Daten nur, wenn uns eine Rechtsgrundlage zur Seite steht. In den folgenden Abschnitten benennen wir zu jeder Verarbeitung die einschlägige Grundlage. Es kommen in Betracht:</p><ul><li><strong>Einwilligung</strong> (Art. 6 Abs. 1 lit. a DSGVO) — Sie haben der Verarbeitung ausdrücklich zugestimmt. Soweit dabei Informationen auf Ihrem Endgerät gespeichert oder ausgelesen werden, tritt § 25 Abs. 1 TDDDG hinzu. Eine erteilte Einwilligung können Sie jederzeit mit Wirkung für die Zukunft widerrufen.</li><li><strong>Vertrag</strong> (Art. 6 Abs. 1 lit. b DSGVO) — die Verarbeitung ist zur Erfüllung eines Vertrags mit Ihnen oder zur Durchführung vorvertraglicher Maßnahmen erforderlich.</li><li><strong>Rechtliche Verpflichtung</strong> (Art. 6 Abs. 1 lit. c DSGVO) — etwa handels- und steuerrechtliche Aufbewahrungspflichten.</li><li><strong>Berechtigtes Interesse</strong> (Art. 6 Abs. 1 lit. f DSGVO) — die Verarbeitung ist zur Wahrung unserer berechtigten Interessen erforderlich und Ihre Interessen überwiegen nicht. Welches Interesse dies jeweils ist, nennen wir bei der betreffenden Verarbeitung.</li></ul>',
            'en' => '<p>We only process personal data where we have a legal basis for doing so. Each section below names the basis that applies to it. The possibilities are:</p><ul><li><strong>Consent</strong> (Art. 6 (1) (a) GDPR) — you have expressly agreed to the processing. Where information is stored on or read from your device, § 25 (1) TDDDG applies in addition. You can withdraw consent at any time with effect for the future.</li><li><strong>Contract</strong> (Art. 6 (1) (b) GDPR) — processing is necessary to perform a contract with you or to take steps before entering into one.</li><li><strong>Legal obligation</strong> (Art. 6 (1) (c) GDPR) — such as commercial and tax retention duties.</li><li><strong>Legitimate interest</strong> (Art. 6 (1) (f) GDPR) — processing is necessary for our legitimate interests and yours do not override them. We name the interest in question wherever we rely on this.</li></ul>',
        ],
    ],
    [
        'key' => 'privacy.hosting',
        'documentTypes' => ['privacy'],
        'order' => 300,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => ['factFilled' => ['hosting.provider']],
        'heading' => [
            'de' => 'Hosting',
            'en' => 'Hosting',
        ],
        'body' => [
            'de' => "<p>Diese Website wird bei einem externen Dienstleister gehostet. Personenbezogene Daten, die auf dieser Website erfasst werden, werden auf dessen Servern gespeichert. Dabei kann es sich vor allem um IP-Adressen, Kontaktanfragen, Meta- und Kommunikationsdaten, Vertragsdaten, Kontaktdaten, Namen, Websitezugriffe und sonstige über eine Website generierte Daten handeln.</p><p>Anbieter ist {{ hosting.provider }}[[, {{ hosting.address }}]].[[ Die Server stehen in {{ hosting.country }}.]][[ Weitere Informationen: <a href=\"{{ hosting.privacyUrl }}\">{{ hosting.privacyUrl }}</a>.]]</p><p>Das Hosting erfolgt zum Zwecke der Vertragserfüllung gegenüber unseren potenziellen und bestehenden Kunden (Art. 6 Abs. 1 lit. b DSGVO) sowie im Interesse einer sicheren, schnellen und effizienten Bereitstellung unseres Online-Angebots durch einen professionellen Anbieter (Art. 6 Abs. 1 lit. f DSGVO).</p>",
            'en' => "<p>This website is hosted by an external service provider. Personal data collected on this website is stored on their servers — chiefly IP addresses, contact enquiries, metadata and communications data, contract data, contact details, names, page views and other data generated through a website.</p><p>The provider is {{ hosting.provider }}[[, {{ hosting.address }}]].[[ The servers are located in {{ hosting.country }}.]][[ Further information: <a href=\"{{ hosting.privacyUrl }}\">{{ hosting.privacyUrl }}</a>.]]</p><p>Hosting takes place for the purpose of performing our contracts with prospective and existing customers (Art. 6 (1) (b) GDPR) and in the interest of a secure, fast and efficient provision of our online offering by a professional provider (Art. 6 (1) (f) GDPR).</p>",
        ],
    ],
    [
        'key' => 'privacy.hostingDpa',
        'documentTypes' => ['privacy'],
        'order' => 310,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 28 DSGVO',
        'when' => ['factFilled' => ['hosting.provider'], 'factTruthy' => ['hosting.dpa']],
        'heading' => [
            'de' => 'Auftragsverarbeitung',
            'en' => 'Data processing agreement',
        ],
        'body' => [
            'de' => '<p>Wir haben mit dem oben genannten Anbieter einen Vertrag über Auftragsverarbeitung (AVV) gemäß Art. 28 DSGVO geschlossen. Dabei handelt es sich um einen datenschutzrechtlich vorgeschriebenen Vertrag, der gewährleistet, dass dieser die personenbezogenen Daten unserer Websitebesucher nur nach unseren Weisungen und unter Einhaltung der DSGVO verarbeitet.</p>',
            'en' => '<p>We have concluded a data processing agreement with the provider named above pursuant to Art. 28 GDPR. This is a contract required by data protection law which ensures that they process our visitors’ personal data only on our instructions and in compliance with the GDPR.</p>',
        ],
    ],
    [
        'key' => 'privacy.cookies',
        'documentTypes' => ['privacy', 'cookies'],
        'order' => 400,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 25 TDDDG',
        'heading' => [
            'de' => 'Cookies und ähnliche Technologien',
            'en' => 'Cookies and similar technologies',
        ],
        'body' => [
            'de' => '<p>Unsere Website verwendet Cookies — kleine Textdateien, die auf Ihrem Endgerät gespeichert werden und keinen Schaden anrichten. Sie dienen dazu, unser Angebot nutzerfreundlicher, effektiver und sicherer zu machen. Vergleichbare Techniken wie Local Storage oder Pixel behandeln wir hier wie Cookies.</p><p>Cookies, die zur Durchführung des elektronischen Kommunikationsvorgangs oder zur Bereitstellung von Ihnen ausdrücklich gewünschter Funktionen unbedingt erforderlich sind, werden auf Grundlage von § 25 Abs. 2 TDDDG gespeichert; die weitere Verarbeitung stützt sich auf Art. 6 Abs. 1 lit. f DSGVO. Alle übrigen Cookies werden nur mit Ihrer Einwilligung nach § 25 Abs. 1 TDDDG und Art. 6 Abs. 1 lit. a DSGVO gesetzt. Diese Einwilligung können Sie jederzeit für die Zukunft widerrufen.</p><p>Sie können Ihren Browser so einstellen, dass Sie über das Setzen von Cookies informiert werden, Cookies nur im Einzelfall erlauben, die Annahme generell ausschließen und das automatische Löschen beim Schließen des Browsers aktivieren. Bei der Deaktivierung von Cookies kann die Funktionalität dieser Website eingeschränkt sein.</p>',
            'en' => '<p>Our website uses cookies — small text files stored on your device that do no harm. They make our offering more user-friendly, effective and secure. We treat comparable techniques such as local storage and pixels as cookies here.</p><p>Cookies that are strictly necessary to carry out the electronic communication or to provide a function you have expressly requested are stored on the basis of § 25 (2) TDDDG; any further processing relies on Art. 6 (1) (f) GDPR. All other cookies are only set with your consent under § 25 (1) TDDDG and Art. 6 (1) (a) GDPR. You can withdraw that consent at any time with effect for the future.</p><p>You can configure your browser to notify you when cookies are set, to permit them case by case, to refuse them entirely, and to delete them automatically when the browser closes. Disabling cookies may limit the functionality of this website.</p>',
        ],
    ],
    [
        'key' => 'privacy.transfers',
        'documentTypes' => ['privacy'],
        'order' => 600,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 44 ff. DSGVO',
        'when' => ['anyTransfer' => true],
        'heading' => [
            'de' => 'Übermittlung in Drittländer',
            'en' => 'Transfers to third countries',
        ],
        'body' => [
            'de' => '<p>Einige der oben genannten Dienste verarbeiten Daten außerhalb der Europäischen Union. Für diese Länder besteht nicht in jedem Fall ein Angemessenheitsbeschluss der Europäischen Kommission. Wo ein solcher Beschluss fehlt, stützen wir die Übermittlung auf Standardvertragsklauseln nach Art. 46 Abs. 2 lit. c DSGVO oder — bei zertifizierten US-Anbietern — auf den EU-US Data Privacy Framework nach Art. 45 DSGVO.</p><p>Wir weisen darauf hin, dass in Drittländern kein mit der EU vergleichbares Datenschutzniveau garantiert werden kann. Insbesondere können US-Behörden unter Umständen auf Daten zugreifen, ohne dass Ihnen dagegen wirksame Rechtsbehelfe zur Verfügung stehen. Bei jedem Dienst oben ist angegeben, auf welcher Grundlage die Übermittlung erfolgt.</p>',
            'en' => '<p>Some of the services named above process data outside the European Union. An adequacy decision by the European Commission does not exist for every such country. Where one is missing, we base the transfer on standard contractual clauses under Art. 46 (2) (c) GDPR or — for certified US providers — on the EU–US Data Privacy Framework under Art. 45 GDPR.</p><p>Please note that a level of data protection comparable to the EU cannot be guaranteed in third countries. In particular, US authorities may in certain circumstances access data without effective remedies being available to you. Each service above states the basis on which its transfer takes place.</p>',
        ],
    ],
    [
        'key' => 'privacy.minors',
        'documentTypes' => ['privacy'],
        'order' => 620,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 8 DSGVO',
        'when' => ['factTruthy' => ['site.minors']],
        'heading' => [
            'de' => 'Kinder und Jugendliche',
            'en' => 'Children and young people',
        ],
        'body' => [
            'de' => '<p>Für Dienste der Informationsgesellschaft, die einem Kind unter 16 Jahren angeboten werden, ist eine Einwilligung nur wirksam, wenn sie durch den Träger der elterlichen Verantwortung erteilt oder gebilligt wird (Art. 8 DSGVO). Wir unternehmen angemessene Anstrengungen, um zu prüfen, ob eine solche Einwilligung vorliegt. Erlangen wir Kenntnis davon, dass wir Daten eines Kindes ohne die erforderliche Einwilligung erhoben haben, löschen wir diese unverzüglich.</p>',
            'en' => '<p>For information society services offered to a child under 16, consent is only valid where it is given or authorised by the holder of parental responsibility (Art. 8 GDPR). We make reasonable efforts to verify that such consent exists. If we learn that we have collected a child’s data without the required consent, we delete it without delay.</p>',
        ],
    ],
    [
        'key' => 'privacy.rights',
        'documentTypes' => ['privacy'],
        'order' => 700,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 15–21 DSGVO',
        'requires' => ['contact.email'],
        'heading' => [
            'de' => 'Ihre Rechte',
            'en' => 'Your rights',
        ],
        'body' => [
            'de' => '<p>Sie haben jederzeit das Recht:</p><ul><li><strong>Auskunft</strong> über die zu Ihrer Person gespeicherten Daten, deren Herkunft und Empfänger sowie den Zweck der Verarbeitung zu verlangen (Art. 15 DSGVO),</li><li>die <strong>Berichtigung</strong> unrichtiger Daten zu verlangen (Art. 16 DSGVO),</li><li>die <strong>Löschung</strong> Ihrer Daten zu verlangen, soweit keine Aufbewahrungspflicht entgegensteht (Art. 17 DSGVO),</li><li>die <strong>Einschränkung der Verarbeitung</strong> zu verlangen (Art. 18 DSGVO),</li><li>Ihre Daten in einem gängigen, maschinenlesbaren Format zu erhalten oder an einen anderen Verantwortlichen übertragen zu lassen (<strong>Datenübertragbarkeit</strong>, Art. 20 DSGVO),</li><li>der Verarbeitung zu <strong>widersprechen</strong>, soweit wir sie auf ein berechtigtes Interesse stützen (Art. 21 DSGVO).</li></ul><p>Wenden Sie sich dafür formlos an <a href="mailto:{{ contact.email }}">{{ contact.email }}</a>. Wir beantworten Ihr Anliegen unverzüglich, spätestens innerhalb eines Monats.</p>',
            'en' => '<p>You have the right at any time to:</p><ul><li>request <strong>information</strong> about the data we hold about you, its origin and recipients, and the purpose of processing (Art. 15 GDPR),</li><li>request <strong>rectification</strong> of inaccurate data (Art. 16 GDPR),</li><li>request <strong>erasure</strong> of your data, where no retention obligation prevents it (Art. 17 GDPR),</li><li>request <strong>restriction of processing</strong> (Art. 18 GDPR),</li><li>receive your data in a common, machine-readable format or have it transferred to another controller (<strong>portability</strong>, Art. 20 GDPR),</li><li><strong>object</strong> to processing where we rely on a legitimate interest (Art. 21 GDPR).</li></ul><p>Just write to <a href="mailto:{{ contact.email }}">{{ contact.email }}</a>. We will respond without undue delay and within one month at the latest.</p>',
        ],
    ],
    [
        'key' => 'privacy.objection',
        'documentTypes' => ['privacy'],
        'order' => 710,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 21 DSGVO',
        'heading' => [
            'de' => 'Widerspruchsrecht',
            'en' => 'Right to object',
        ],
        'body' => [
            'de' => '<p><strong>Wenn die Datenverarbeitung auf Grundlage von Art. 6 Abs. 1 lit. e oder f DSGVO erfolgt, haben Sie jederzeit das Recht, aus Gründen, die sich aus Ihrer besonderen Situation ergeben, gegen die Verarbeitung Ihrer personenbezogenen Daten Widerspruch einzulegen; dies gilt auch für ein auf diese Bestimmungen gestütztes Profiling. Legen Sie Widerspruch ein, werden wir Ihre personenbezogenen Daten nicht mehr verarbeiten, es sei denn, wir können zwingende schutzwürdige Gründe für die Verarbeitung nachweisen, die Ihre Interessen, Rechte und Freiheiten überwiegen, oder die Verarbeitung dient der Geltendmachung, Ausübung oder Verteidigung von Rechtsansprüchen.</strong></p><p><strong>Werden Ihre personenbezogenen Daten verarbeitet, um Direktwerbung zu betreiben, so haben Sie das Recht, jederzeit Widerspruch gegen die Verarbeitung einzulegen; dies gilt auch für das Profiling, soweit es mit solcher Direktwerbung in Verbindung steht. Widersprechen Sie, werden Ihre personenbezogenen Daten anschließend nicht mehr zum Zwecke der Direktwerbung verwendet.</strong></p>',
            'en' => '<p><strong>Where processing is based on Art. 6 (1) (e) or (f) GDPR, you have the right at any time to object, on grounds relating to your particular situation, to the processing of your personal data, including profiling based on those provisions. If you object, we will no longer process your personal data unless we can demonstrate compelling legitimate grounds for the processing which override your interests, rights and freedoms, or the processing serves to establish, exercise or defend legal claims.</strong></p><p><strong>Where your personal data is processed for direct marketing purposes, you have the right to object at any time, including to profiling connected with such direct marketing. If you object, your personal data will no longer be used for direct marketing.</strong></p>',
        ],
    ],
    [
        'key' => 'privacy.complaint',
        'documentTypes' => ['privacy'],
        'order' => 720,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 77 DSGVO',
        'heading' => [
            'de' => 'Beschwerderecht bei der Aufsichtsbehörde',
            'en' => 'Right to complain to a supervisory authority',
        ],
        'body' => [
            'de' => "<p>Im Falle von Verstößen gegen die DSGVO steht Ihnen ein Beschwerderecht bei einer Aufsichtsbehörde zu, insbesondere in dem Mitgliedstaat Ihres gewöhnlichen Aufenthalts, Ihres Arbeitsplatzes oder des Orts des mutmaßlichen Verstoßes. Das Beschwerderecht besteht unbeschadet anderweitiger verwaltungsrechtlicher oder gerichtlicher Rechtsbehelfe.</p>[[<p>Für uns zuständig ist:<br>\n{{ authority.name }}[[<br>\n<a href=\"{{ authority.url }}\">{{ authority.url }}</a>]]</p>]]",
            'en' => "<p>If you believe the GDPR has been infringed, you may lodge a complaint with a supervisory authority — in particular in the member state of your habitual residence, your place of work or the place of the alleged infringement. This right is without prejudice to any other administrative or judicial remedy.</p>[[<p>The authority responsible for us is:<br>\n{{ authority.name }}[[<br>\n<a href=\"{{ authority.url }}\">{{ authority.url }}</a>]]</p>]]",
        ],
    ],
    [
        'key' => 'privacy.revoke',
        'documentTypes' => ['privacy'],
        'order' => 730,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 7 Abs. 3 DSGVO',
        'heading' => [
            'de' => 'Widerruf Ihrer Einwilligung',
            'en' => 'Withdrawing your consent',
        ],
        'body' => [
            'de' => '<p>Viele Datenverarbeitungsvorgänge sind nur mit Ihrer ausdrücklichen Einwilligung möglich. Sie können eine bereits erteilte Einwilligung jederzeit widerrufen. Die Rechtmäßigkeit der bis zum Widerruf erfolgten Datenverarbeitung bleibt vom Widerruf unberührt. Eine formlose Mitteilung genügt.</p>',
            'en' => '<p>Many processing operations are only possible with your express consent. You can withdraw consent you have already given at any time. The lawfulness of processing carried out before the withdrawal is unaffected. An informal message is enough.</p>',
        ],
    ],
    [
        'key' => 'privacy.retention',
        'documentTypes' => ['privacy'],
        'order' => 740,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 13 Abs. 2 lit. a DSGVO',
        'heading' => [
            'de' => 'Speicherdauer',
            'en' => 'How long we keep data',
        ],
        'body' => [
            'de' => '<p>Soweit in dieser Datenschutzerklärung keine speziellere Speicherdauer genannt wird, verbleiben Ihre personenbezogenen Daten bei uns, bis der Zweck für die Datenverarbeitung entfällt. Wenn Sie ein berechtigtes Löschersuchen geltend machen oder eine Einwilligung widerrufen, werden Ihre Daten gelöscht, sofern wir keine anderen rechtlich zulässigen Gründe für die Speicherung haben — etwa handels- oder steuerrechtliche Aufbewahrungsfristen von in der Regel sechs bzw. zehn Jahren. Im letztgenannten Fall erfolgt die Löschung nach Fortfall dieser Gründe.</p>',
            'en' => '<p>Unless a more specific retention period is stated in this policy, your personal data stays with us until the purpose for processing it no longer applies. If you make a valid erasure request or withdraw consent, your data is deleted unless we have other legally permissible grounds for keeping it — such as commercial or tax retention periods, usually six or ten years. In that case, deletion follows once those grounds cease to apply.</p>',
        ],
    ],
    [
        'key' => 'privacy.ssl',
        'documentTypes' => ['privacy'],
        'order' => 750,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 32 DSGVO',
        'heading' => [
            'de' => 'SSL- bzw. TLS-Verschlüsselung',
            'en' => 'SSL/TLS encryption',
        ],
        'body' => [
            'de' => '<p>Diese Seite nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte eine SSL- bzw. TLS-Verschlüsselung. Eine verschlüsselte Verbindung erkennen Sie daran, dass die Adresszeile des Browsers von „http://“ auf „https://“ wechselt und am Schloss-Symbol in Ihrer Browserzeile. Wenn die Verschlüsselung aktiviert ist, können die Daten, die Sie an uns übermitteln, nicht von Dritten mitgelesen werden.</p>',
            'en' => '<p>For security reasons and to protect the transmission of confidential content, this site uses SSL/TLS encryption. You can recognise an encrypted connection by the browser address bar switching from “http://” to “https://” and by the padlock icon. When encryption is active, the data you send us cannot be read by third parties.</p>',
        ],
    ],
    [
        'key' => 'privacy.automated',
        'documentTypes' => ['privacy'],
        'order' => 760,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 22 DSGVO',
        'heading' => [
            'de' => 'Automatisierte Entscheidungsfindung',
            'en' => 'Automated decision-making',
        ],
        'body' => [
            'de' => '<p>Eine automatisierte Entscheidungsfindung einschließlich Profiling im Sinne des Art. 22 DSGVO, die Ihnen gegenüber rechtliche Wirkung entfaltet oder Sie in ähnlicher Weise erheblich beeinträchtigt, findet nicht statt.</p>',
            'en' => '<p>We do not carry out automated decision-making, including profiling, within the meaning of Art. 22 GDPR that produces legal effects concerning you or similarly significantly affects you.</p>',
        ],
    ],
    [
        'key' => 'privacy.usNotice',
        'documentTypes' => ['privacy'],
        'order' => 850,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'CCPA/CPRA § 1798.100 ff.',
        'when' => ['jurisdiction' => ['us']],
        'requires' => ['contact.email'],
        'heading' => [
            'de' => 'Zusätzliche Hinweise für Personen in Kalifornien',
            'en' => 'Additional notice for California residents',
        ],
        'body' => [
            'de' => '<p>Für Verbraucherinnen und Verbraucher mit Wohnsitz in Kalifornien gelten ergänzend der California Consumer Privacy Act und der California Privacy Rights Act. Sie haben danach das Recht zu erfahren, welche personenbezogenen Daten wir erheben und mit wem wir sie teilen, deren Löschung oder Berichtigung zu verlangen, dem Verkauf oder der Weitergabe zu Zwecken übergreifender verhaltensbezogener Werbung zu widersprechen und die Nutzung sensibler Daten einzuschränken. Wegen der Ausübung dieser Rechte werden Sie nicht benachteiligt. Wenden Sie sich an <a href="mailto:{{ contact.email }}">{{ contact.email }}</a>.</p><p>Wir verkaufen personenbezogene Daten nicht gegen Entgelt. Sofern die oben genannten Werbedienste Daten für übergreifende verhaltensbezogene Werbung weitergeben, gilt dies nach kalifornischem Recht als „sharing“; Sie können dem widersprechen und wir beachten das Global Privacy Control-Signal Ihres Browsers.</p>',
            'en' => '<p>If you live in California, the California Consumer Privacy Act and the California Privacy Rights Act apply in addition. You have the right to know what personal information we collect and who we share it with, to request its deletion or correction, to opt out of the sale or sharing of personal information for cross-context behavioural advertising, and to limit the use of sensitive personal information. You will not be discriminated against for exercising these rights. Contact <a href="mailto:{{ contact.email }}">{{ contact.email }}</a>.</p><p>We do not sell personal information for money. Where the advertising services named above pass data on for cross-context behavioural advertising, that counts as “sharing” under California law; you may opt out, and we honour the Global Privacy Control signal sent by your browser.</p>',
        ],
    ],
    [
        'key' => 'privacy.changes',
        'documentTypes' => ['privacy', 'cookies', 'terms'],
        'order' => 900,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Änderungen dieser Erklärung',
            'en' => 'Changes to this notice',
        ],
        'body' => [
            'de' => '<p>Wir passen diese Erklärung an, wenn sich die Rechtslage, unsere Dienste oder die Datenverarbeitung ändern. Es gilt jeweils die hier veröffentlichte Fassung. Stand: {{ document.updated }}.</p>',
            'en' => '<p>We update this notice when the law, our services or our data processing change. The version published here is the one that applies. Last updated: {{ document.updated }}.</p>',
        ],
    ],
];
