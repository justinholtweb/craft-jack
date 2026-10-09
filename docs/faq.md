---
title: FAQ
slug: faq
order: 60
summary: Common questions about generating an imprint, privacy policy and other legal texts for Craft CMS with Jack.
---

### What does Jack do?

It writes your site's legal texts from facts you give it: an imprint, a privacy policy, and on Pro
a cookie policy, terms and conditions, withdrawal instructions, a disclaimer, an accessibility
statement and a social media privacy policy. You fill in a profile (who you are) and an inventory
(which services you use). Jack compiles the documents from its clause library, tells you when one
goes out of date, and puts the published text on the site.

### Is this legal advice?

No. Jack produces drafts from facts you supply, using boilerplate that is conventional rather than
bespoke. It is not a lawyer, and it can't know anything you haven't told it. Somebody accountable
should read every document before it's published. A business with unusual processing needs advice
from a lawyer, not a generator.

### Which Craft and PHP versions are supported?

Craft CMS 5.3+ and PHP 8.2+.

### How much does it cost?

Lite is free. Pro is $99, then $79 a year for updates. See
[Installation](installation#editions).

### What's the difference between Lite and Pro?

Lite gets you legal. It creates the two documents German law requires of a website, an imprint and
a privacy policy, for your primary site, from the whole clause library and all 44 bundled services.
You can publish, you're told when a document is out of date, and the audit and Twig API work.

Pro keeps you maintained. It adds the other six document types, every site and language, rewriting
or adding sections, seeing which paragraphs changed, finding services nobody declared, version
history with diffs, and the console commands for CI.

### Which languages does it write in?

German and English. Every clause in the library is written in both. A document is written in its
site's language: German for a German site, English for any other. On Pro, a multi-site install
gets each document in each site's language from one profile and one inventory.

### Which jurisdictions does it cover?

It's German-first, with the GDPR throughout: the imprint follows § 5 DDG, and the library tracks
the TDDDG, MStV, VSBG and EGBGB. Each document has a jurisdiction: Germany, Austria, Switzerland,
European Union, United Kingdom or United States. Clauses specific to German-speaking or EU law are
left out for jurisdictions they don't apply to, and **United States** adds a California notice for
the CCPA/CPRA to the privacy policy.

There's no clause written specifically for UK or Swiss data protection law. If that's your main
market, have the documents reviewed with that in mind.

### Does a change to the profile go live straight away?

No. Saving the profile or the inventory recompiles the drafts, which you see in the control panel.
Visitors keep seeing the last published text until someone with the **Publish documents**
permission publishes again. A half-typed address never reaches the live imprint.

### If my Pro licence lapses, do my documents disappear?

No. Editions gate creating and editing. Text that's already published keeps rendering, including
document types Lite can't create. A privacy policy that 404s over a billing problem would be worse
than anything that prevents.

### How does it stay current when the law changes?

The clause library ships inside the plugin and is stamped with the date it was last reviewed.
When a statute is renamed or a ruling changes what a clause should say, the library is updated and
you get it with `composer update`. Your documents then show as out of date, and on Pro you see
which paragraphs changed. Recompile, review and publish.

Recent examples: § 5 TMG became § 5 DDG in May 2024, and the TTDSG became the TDDDG.

### Why doesn't my imprint link the EU's ODR platform?

The EU's online dispute resolution platform shut down on 20 July 2025. Linking it is now a stale
mandatory disclosure, not a harmless extra, so Jack's imprint and terms clauses don't. If your
current imprint still links it, replace it.

### Is Jack a consent banner?

No. Jack describes what the site does. It doesn't block scripts until a visitor agrees. If you have
a banner, drive it from the inventory, so the banner and the privacy policy can't disagree. On Pro,
Jack writes the config for [Toss](/plugins/craft-toss), Klaro or CookieConsent v3, and the Consent
Mode types for Tape. See [Consent managers](consent-managers). On Lite, use
`craft.jack.consentRequired()` and `craft.jack.cookies()`; see
[Templating](templating#driving-a-consent-banner).

### Can a headless front end show the documents?

Yes, in both editions. Published documents are available over GraphQL (`jackDocuments`, behind
their own schema permission) and as JSON at `/jack/documents/<handle>.json`. Both show published
text only, never a draft. See [Headless](headless).

### Can I change the wording?

On Pro, yes. Press **Rewrite this section** on any section to change its heading or text, or to
leave it out, and use **Add a section** for something the library doesn't cover. An edited section
stops receiving library updates, so change only what you need to. The statutory withdrawal
instructions are the exception worth leaving alone: departing from the wording loses the legal safe
harbour.

### Can I see what the privacy policy said on a given day?

On Pro, yes. Every publish writes an immutable version, kept forever. Open **History** from the
document's **Actions** menu and **Compare with latest** to diff any version against the newest.
Lite writes the versions too, so they're there when you switch to Pro.

### Which services does it know about?

Forty-four, each with provider, address, purpose, data, legal basis, retention, transfer mechanism
and cookies. They include Google Analytics, Tag Manager and Ads, Matomo, Plausible, Fathom, Hotjar,
Clarity, Meta Pixel, LinkedIn, TikTok, Pinterest, Microsoft Advertising, Stripe, PayPal, Klarna,
Mollie, YouTube, Vimeo, Spotify, SoundCloud, Google Fonts, Adobe Fonts, Font Awesome, Google Maps,
Mapbox, OpenStreetMap, Mailchimp, Brevo, CleverReach, Postmark, SendGrid, Cloudflare, reCAPTCHA,
Turnstile, hCaptcha, Sentry, Intercom, Crisp, HubSpot and Calendly, plus the first-party ones every
Craft site has. Anything else can be added as a custom service.

### What's the licence?

Jack is licensed under the Craft License. See `LICENSE.md`.
