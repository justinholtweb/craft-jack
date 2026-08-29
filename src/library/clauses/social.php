<?php

/**
 * Social media privacy clauses — the text for your *pages on other people's platforms*.
 *
 * This is the document eRecht24 calls the social media privacy policy, and it exists because a
 * company Facebook or Instagram page is a processing operation you are jointly responsible for
 * (Wirtschaftsakademie, C-210/16) but cannot describe in the website's own privacy policy — the
 * data never touches your site. Publish it where your profiles link to it.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'social.intro',
        'documentTypes' => ['social'],
        'order' => 100,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'requires' => ['company.name'],
        'heading' => [
            'de' => 'Datenschutz auf unseren Social-Media-Profilen',
            'en' => 'Privacy on our social media profiles',
        ],
        'body' => [
            'de' => '<p>{{ company.name }} unterhält Profile in sozialen Netzwerken, um mit Interessenten und Kunden zu kommunizieren. Rufen Sie eines dieser Profile auf, verarbeitet der jeweilige Plattformbetreiber Ihre Daten nach seinen eigenen Bedingungen — auch dann, wenn Sie dort kein Konto haben.</p>',
            'en' => '<p>{{ company.name }} runs profiles on social networks in order to talk to prospective and existing customers. When you visit one of them, the platform operator processes your data on their own terms — including when you have no account there.</p>',
        ],
    ],
    [
        'key' => 'social.jointControl',
        'documentTypes' => ['social'],
        'order' => 200,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Art. 26 DSGVO',
        'heading' => [
            'de' => 'Gemeinsame Verantwortlichkeit',
            'en' => 'Joint controllership',
        ],
        'body' => [
            'de' => '<p>Für die Erhebung und weitere Verarbeitung der Daten von Besuchern unserer Profile sind wir gemeinsam mit dem Plattformbetreiber verantwortlich (Art. 26 DSGVO). Ausgenommen sind Verarbeitungen, die der Betreiber in alleiniger Verantwortung durchführt. Ihre Betroffenenrechte können Sie sowohl uns gegenüber als auch gegenüber dem Plattformbetreiber geltend machen; wir leiten Anfragen weiter, die nur dieser beantworten kann.</p><p>Die Verarbeitung erfolgt auf Grundlage unseres berechtigten Interesses an einer wirksamen Außendarstellung und Kommunikation (Art. 6 Abs. 1 lit. f DSGVO). Werden Sie um eine Einwilligung gebeten, ist Art. 6 Abs. 1 lit. a DSGVO maßgeblich.</p>',
            'en' => '<p>We are jointly responsible with the platform operator for collecting and further processing the data of people who visit our profiles (Art. 26 GDPR). Processing the operator carries out on its own account is excluded. You may exercise your rights against us or against the platform operator; we pass on requests that only they can answer.</p><p>The processing rests on our legitimate interest in effective communication and public presence (Art. 6 (1) (f) GDPR). Where you are asked for consent, Art. 6 (1) (a) GDPR applies.</p>',
        ],
    ],
    [
        'key' => 'social.insights',
        'documentTypes' => ['social'],
        'order' => 300,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Statistiken und Reichweitenmessung',
            'en' => 'Insights and reach measurement',
        ],
        'body' => [
            'de' => '<p>Die Plattformen stellen uns aggregierte Statistiken über die Nutzung unserer Profile bereit. Diese werden aus dem Verhalten der Besucher erzeugt, in der Regel unter Einsatz von Cookies. Wir erhalten keine Einzelprofile und können einzelne Nutzer daraus nicht identifizieren; die zugrunde liegende Verarbeitung nimmt der Plattformbetreiber vor.</p>',
            'en' => '<p>The platforms give us aggregated statistics about how our profiles are used. These are generated from visitor behaviour, usually with the help of cookies. We receive no individual profiles and cannot identify individual users from them; the underlying processing is carried out by the platform operator.</p>',
        ],
    ],
    [
        'key' => 'social.rights',
        'documentTypes' => ['social'],
        'order' => 400,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'requires' => ['contact.email'],
        'heading' => [
            'de' => 'Ihre Rechte',
            'en' => 'Your rights',
        ],
        'body' => [
            'de' => '<p>Sie können Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung verlangen sowie der Verarbeitung widersprechen. Wenden Sie sich an <a href="mailto:{{ contact.email }}">{{ contact.email }}</a> oder unmittelbar an den Plattformbetreiber, der über die vollständigen Daten verfügt. Ein Beschwerderecht bei einer Aufsichtsbehörde steht Ihnen ebenfalls zu.</p>',
            'en' => '<p>You may request access, rectification, erasure and restriction of processing, and you may object to processing. Contact <a href="mailto:{{ contact.email }}">{{ contact.email }}</a>, or the platform operator directly since they hold the complete data. You may also complain to a supervisory authority.</p>',
        ],
    ],
];
