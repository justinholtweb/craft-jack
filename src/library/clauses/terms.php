<?php

$sells = ['when' => ['factTruthy' => ['site.sellsOnline']]];

/**
 * Terms and conditions clauses, including the statutory withdrawal instructions.
 *
 * The whole document is conditional on `site.sellsOnline`: terms of sale on a site that sells
 * nothing are worse than no terms, because they promise a contractual relationship that does not
 * exist and invite a reader to rely on it.
 *
 * `terms.withdrawal` and `terms.withdrawalForm` reproduce the model instructions and model form
 * from Anlage 1 and Anlage 2 to Art. 246a § 1 (2) EGBGB. Those are statutory texts: departing
 * from the wording loses the safe harbour, so Jack fills the blanks and changes nothing else —
 * and it is the one place in the library where an override should be considered carefully.
 *
 * @var array<int, array<string, mixed>>
 */

return [
    [
        'key' => 'terms.scope',
        'documentTypes' => ['terms'],
        'order' => 100,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'requires' => ['company.name'],
        'heading' => [
            'de' => 'Geltungsbereich',
            'en' => 'Scope',
        ],
        'body' => [
            'de' => '<p>Diese Allgemeinen Geschäftsbedingungen gelten für alle Verträge, die über {{ site.name }} zwischen {{ company.name }} und Ihnen geschlossen werden, in der zum Zeitpunkt der Bestellung gültigen Fassung. Abweichende Bedingungen des Bestellers erkennen wir nicht an, es sei denn, wir hätten ihrer Geltung ausdrücklich schriftlich zugestimmt.</p><p>Verbraucher ist jede natürliche Person, die ein Rechtsgeschäft zu Zwecken abschließt, die überwiegend weder ihrer gewerblichen noch ihrer selbständigen beruflichen Tätigkeit zugerechnet werden können. Unternehmer ist eine natürliche oder juristische Person oder eine rechtsfähige Personengesellschaft, die bei Abschluss eines Rechtsgeschäfts in Ausübung ihrer gewerblichen oder selbständigen beruflichen Tätigkeit handelt.</p>',
            'en' => '<p>These terms and conditions apply to every contract concluded through {{ site.name }} between {{ company.name }} and you, in the version in force when the order is placed. We do not accept the customer’s differing terms unless we have expressly agreed to them in writing.</p><p>A consumer is any natural person entering into a transaction for purposes that are predominantly outside their trade, business or profession. A trader is a natural or legal person, or a partnership with legal capacity, acting in the exercise of their trade, business or profession.</p>',
        ],
    ],
    [
        'key' => 'terms.formation',
        'documentTypes' => ['terms'],
        'order' => 200,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => $sells['when'],
        'heading' => [
            'de' => 'Vertragsschluss',
            'en' => 'Formation of contract',
        ],
        'body' => [
            'de' => '<p>Die Darstellung der Produkte im Online-Shop stellt kein rechtlich bindendes Angebot dar, sondern eine Aufforderung zur Bestellung. Durch Anklicken des Bestellbuttons geben Sie eine verbindliche Bestellung der im Warenkorb enthaltenen Waren ab. Der Eingang Ihrer Bestellung wird unmittelbar nach dem Absenden per E-Mail bestätigt; diese Bestätigung stellt noch keine Annahme dar.</p><p>Der Vertrag kommt zustande, wenn wir Ihre Bestellung durch eine Auftragsbestätigung oder durch Versand der Ware annehmen. Der Vertragstext wird von uns gespeichert und Ihnen mit den Vertragsbedingungen in Textform zugesandt.</p>',
            'en' => '<p>The display of products in the shop is not a legally binding offer but an invitation to order. By clicking the order button you place a binding order for the goods in your basket. We confirm receipt of your order by email immediately after you send it; that confirmation is not yet an acceptance.</p><p>The contract comes into being when we accept your order by an order confirmation or by dispatching the goods. We store the contract text and send it to you in text form together with the terms.</p>',
        ],
    ],
    [
        'key' => 'terms.prices',
        'documentTypes' => ['terms'],
        'order' => 300,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => $sells['when'],
        'heading' => [
            'de' => 'Preise und Zahlung',
            'en' => 'Prices and payment',
        ],
        'body' => [
            'de' => '<p>Es gelten die zum Zeitpunkt der Bestellung angegebenen Preise. Alle Preise verstehen sich einschließlich der gesetzlichen Umsatzsteuer; etwaige Versandkosten werden vor Abschluss der Bestellung gesondert ausgewiesen. Die zur Verfügung stehenden Zahlungsarten werden im Bestellvorgang angezeigt.</p>',
            'en' => '<p>The prices shown at the time of the order apply. All prices include statutory VAT; any delivery charges are shown separately before the order is completed. The available payment methods are shown during checkout.</p>',
        ],
    ],
    [
        'key' => 'terms.delivery',
        'documentTypes' => ['terms'],
        'order' => 400,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => $sells['when'],
        'heading' => [
            'de' => 'Lieferung',
            'en' => 'Delivery',
        ],
        'body' => [
            'de' => '<p>Wir liefern an die von Ihnen angegebene Lieferanschrift.[[ Die Lieferzeit beträgt, sofern nicht anders angegeben, {{ terms.deliveryTime }}.]] Ist ein bestellter Artikel nicht verfügbar, informieren wir Sie unverzüglich und erstatten bereits geleistete Zahlungen ohne schuldhaftes Zögern zurück.</p><p>Beim Versand an Verbraucher geht die Gefahr des zufälligen Untergangs erst mit Übergabe der Ware auf Sie über.</p>',
            'en' => '<p>We deliver to the address you give us.[[ Unless stated otherwise, delivery takes {{ terms.deliveryTime }}.]] If an ordered item is unavailable we will tell you without delay and refund any payment already made without undue delay.</p><p>On sales to consumers, the risk of accidental loss passes to you only when the goods are handed over.</p>',
        ],
    ],
    [
        'key' => 'terms.retentionOfTitle',
        'documentTypes' => ['terms'],
        'order' => 500,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => $sells['when'],
        'heading' => [
            'de' => 'Eigentumsvorbehalt',
            'en' => 'Retention of title',
        ],
        'body' => [
            'de' => '<p>Die gelieferte Ware bleibt bis zur vollständigen Bezahlung unser Eigentum.</p>',
            'en' => '<p>Goods delivered remain our property until they have been paid for in full.</p>',
        ],
    ],
    [
        'key' => 'terms.warranty',
        'documentTypes' => ['terms'],
        'order' => 600,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => $sells['when'],
        'heading' => [
            'de' => 'Gewährleistung',
            'en' => 'Warranty',
        ],
        'body' => [
            'de' => '<p>Es gelten die gesetzlichen Mängelhaftungsrechte. Für Verbraucher beträgt die Verjährungsfrist für Ansprüche wegen Mängeln neuer Sachen zwei Jahre ab Ablieferung. Eine Garantie besteht nur, soweit sie ausdrücklich abgegeben wurde.</p>',
            'en' => '<p>The statutory rights in respect of defects apply. For consumers, claims for defects in new goods become time-barred two years after delivery. A guarantee exists only where one has been expressly given.</p>',
        ],
    ],
    [
        'key' => 'terms.liability',
        'documentTypes' => ['terms'],
        'order' => 650,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Haftung',
            'en' => 'Liability',
        ],
        'body' => [
            'de' => '<p>Wir haften unbeschränkt für Vorsatz und grobe Fahrlässigkeit sowie für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit. Bei einfacher Fahrlässigkeit haften wir nur für die Verletzung wesentlicher Vertragspflichten, deren Erfüllung die ordnungsgemäße Durchführung des Vertrags überhaupt erst ermöglicht und auf deren Einhaltung Sie regelmäßig vertrauen dürfen; in diesem Fall ist die Haftung auf den vertragstypischen, vorhersehbaren Schaden begrenzt. Die Haftung nach dem Produkthaftungsgesetz bleibt unberührt.</p>',
            'en' => '<p>We are liable without limitation for intent and gross negligence, and for damage arising from injury to life, body or health. In cases of ordinary negligence we are liable only for breach of a material contractual obligation — one whose fulfilment makes proper performance of the contract possible in the first place and on whose observance you may routinely rely — and in that case liability is limited to the foreseeable damage typical of the contract. Liability under product liability law is unaffected.</p>',
        ],
    ],
    [
        'key' => 'terms.withdrawal',
        'documentTypes' => ['terms', 'withdrawal'],
        'order' => 700,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Anlage 1 zu Art. 246a § 1 Abs. 2 EGBGB',
        'jurisdictions' => ['de', 'at', 'eu'],
        'when' => $sells['when'],
        'requires' => ['company.name', 'contact.email', 'terms.withdrawalDays'],
        'heading' => [
            'de' => 'Widerrufsbelehrung',
            'en' => 'Right of withdrawal',
        ],
        'body' => [
            'de' => "<h3>Widerrufsrecht</h3><p>Sie haben das Recht, binnen {{ terms.withdrawalDays }} Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt {{ terms.withdrawalDays }} Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.</p><p>Um Ihr Widerrufsrecht auszuüben, müssen Sie uns</p><p>{{ withdrawal.address }}</p><p>mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.</p><p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p><h3>Folgen des Widerrufs</h3><p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.</p><p>Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist.</p><p>Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden. Sie tragen die unmittelbaren Kosten der Rücksendung der Waren.</p><p>Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist.</p>",
            'en' => "<h3>Right of withdrawal</h3><p>You have the right to withdraw from this contract within {{ terms.withdrawalDays }} days without giving any reason. The withdrawal period expires {{ terms.withdrawalDays }} days from the day on which you, or a third party other than the carrier indicated by you, acquire physical possession of the goods.</p><p>To exercise the right of withdrawal you must inform us</p><p>{{ withdrawal.address }}</p><p>of your decision to withdraw from this contract by an unequivocal statement (for example, a letter sent by post or an email). You may use the model withdrawal form below, but it is not obligatory.</p><p>To meet the withdrawal deadline, it is sufficient for you to send your communication concerning your exercise of the right of withdrawal before the withdrawal period has expired.</p><h3>Effects of withdrawal</h3><p>If you withdraw from this contract, we shall reimburse to you all payments received from you, including the costs of delivery (with the exception of the supplementary costs resulting from your choice of a type of delivery other than the least expensive type of standard delivery offered by us), without undue delay and in any event not later than 14 days from the day on which we are informed about your decision to withdraw. We will carry out such reimbursement using the same means of payment as you used for the initial transaction, unless you have expressly agreed otherwise; in any event, you will not incur any fees as a result of such reimbursement.</p><p>We may withhold reimbursement until we have received the goods back or you have supplied evidence of having sent back the goods, whichever is the earliest.</p><p>You shall send back the goods or hand them over to us without undue delay and in any event not later than 14 days from the day on which you communicate your withdrawal from this contract to us. The deadline is met if you send back the goods before the period of 14 days has expired. You will have to bear the direct cost of returning the goods.</p><p>You are only liable for any diminished value of the goods resulting from the handling other than what is necessary to establish the nature, characteristics and functioning of the goods.</p>",
        ],
    ],
    [
        'key' => 'terms.withdrawalForm',
        'documentTypes' => ['terms', 'withdrawal'],
        'order' => 710,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'citation' => 'Anlage 2 zu Art. 246a § 1 Abs. 2 EGBGB',
        'jurisdictions' => ['de', 'at', 'eu'],
        'when' => $sells['when'],
        'requires' => ['company.name', 'contact.email'],
        'heading' => [
            'de' => 'Muster-Widerrufsformular',
            'en' => 'Model withdrawal form',
        ],
        'body' => [
            'de' => "<p><em>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</em></p><p>An<br>\n{{ withdrawal.address }}</p><p>Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)/die Erbringung der folgenden Dienstleistung (*)</p><p>— Bestellt am (*)/erhalten am (*)<br>\n— Name des/der Verbraucher(s)<br>\n— Anschrift des/der Verbraucher(s)<br>\n— Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)<br>\n— Datum</p><p><em>(*) Unzutreffendes streichen.</em></p>",
            'en' => "<p><em>(Complete and return this form only if you wish to withdraw from the contract.)</em></p><p>To<br>\n{{ withdrawal.address }}</p><p>I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*)/for the provision of the following service (*)</p><p>— Ordered on (*)/received on (*)<br>\n— Name of consumer(s)<br>\n— Address of consumer(s)<br>\n— Signature of consumer(s) (only if this form is notified on paper)<br>\n— Date</p><p><em>(*) Delete as appropriate.</em></p>",
        ],
    ],
    [
        'key' => 'terms.disputes',
        'documentTypes' => ['terms'],
        'order' => 800,
        'version' => '2.0.0',
        'updated' => '2026-08-18',
        'citation' => '§ 36 VSBG',
        'jurisdictions' => ['de', 'at', 'eu'],
        'heading' => [
            'de' => 'Streitbeilegung',
            'en' => 'Dispute resolution',
        ],
        'body' => [
            'de' => '<p>Wir sind nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen. Die Europäische Plattform zur Online-Streitbeilegung wurde zum 20. Juli 2025 eingestellt.</p>',
            'en' => '<p>We are neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board. The European online dispute resolution platform ceased operating on 20 July 2025.</p>',
        ],
    ],
    [
        'key' => 'terms.law',
        'documentTypes' => ['terms'],
        'order' => 900,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'when' => ['factFilled' => ['terms.governingLaw']],
        'heading' => [
            'de' => 'Anwendbares Recht und Gerichtsstand',
            'en' => 'Governing law and jurisdiction',
        ],
        'body' => [
            'de' => '<p>Es gilt {{ terms.governingLaw }} unter Ausschluss des UN-Kaufrechts. Handeln Sie als Verbraucher mit gewöhnlichem Aufenthalt in der Europäischen Union, bleiben zwingende Verbraucherschutzvorschriften Ihres Aufenthaltsstaates hiervon unberührt.[[ Gerichtsstand für Streitigkeiten mit Kaufleuten, juristischen Personen des öffentlichen Rechts oder öffentlich-rechtlichen Sondervermögen ist {{ terms.venue }}.]]</p>',
            'en' => '<p>{{ terms.governingLaw }} applies, to the exclusion of the UN Convention on Contracts for the International Sale of Goods. If you are a consumer habitually resident in the European Union, mandatory consumer protection provisions of your country of residence remain unaffected.[[ The place of jurisdiction for disputes with merchants, legal entities under public law and special funds under public law is {{ terms.venue }}.]]</p>',
        ],
    ],
    [
        'key' => 'terms.severability',
        'documentTypes' => ['terms'],
        'order' => 950,
        'version' => '1.0.0',
        'updated' => '2026-08-18',
        'heading' => [
            'de' => 'Salvatorische Klausel',
            'en' => 'Severability',
        ],
        'body' => [
            'de' => '<p>Sollten einzelne Bestimmungen dieser Bedingungen unwirksam sein oder werden, bleibt die Wirksamkeit der übrigen Bestimmungen davon unberührt. An die Stelle der unwirksamen Bestimmung treten die gesetzlichen Vorschriften.</p>',
            'en' => '<p>Should individual provisions of these terms be or become invalid, the validity of the remaining provisions is unaffected. The statutory provisions take the place of the invalid provision.</p>',
        ],
    ],
];
