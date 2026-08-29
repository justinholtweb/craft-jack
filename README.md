# Jack

Legal texts for Craft CMS — imprint, privacy policy, cookies and terms — generated from the facts
about your site and kept current as the law moves.

Jack is built on one idea: **a legal document is a projection, not a document.** Nobody should
write a privacy policy. They should answer two questions — *who are you* and *what do you use* —
and the policy should follow, in every language the site publishes in, and change by itself when
either answer changes.

- **Free (Lite)** — the two documents German law actually requires: an imprint and a privacy
  policy, in one language, from the whole clause library and the whole processor registry.
- **Pro** — every other document type, every site and language, editing the wording, finding the
  services nobody declared, version history with diffs, and a CI check.

Requires Craft CMS 5.3+ and PHP 8.2+.

## How it works

Three things go in:

| | |
|---|---|
| **The profile** | Who you are. Company name, address, register entry, VAT ID, data protection officer, hosting provider — about sixty facts, entered once. |
| **The inventory** | Who you hand data to. Google Analytics, Stripe, YouTube, your host, your own contact form. Forty-four services ship with Jack, described in full. |
| **The library** | Forty-six clauses and the processor registry, shipped inside the plugin and stamped with the date they were last reviewed. |

One document comes out. The compiler is a pure function of the three, which is what makes the rest
possible: it can tell you a published document has drifted from what it would say now, a diff
between two published versions means something, and a CI check can fail a deploy over a privacy
policy that quietly stopped being true.

```
Profile  ─┐
Inventory ├─→  Compiler  ─→  Document  ─→  /impressum
Library  ─┘                     │
                                └─→ stamp ─→ "out of date, and here is which paragraph"
```

### The privacy policy is mostly not written

Jack keeps no hand-written paragraph per service. It keeps the facts a paragraph is made of — who
the provider is, where they are, what the data is for, which legal basis carries it, whether it
leaves the EEA and how — and renders the same templated clause over each of them. Adding Klarna to
your checkout is a dropdown, not a writing job, and every service reads in the same voice as every
other one.

When Meta moves its Irish entity, one file in the plugin changes and every site's privacy policy is
right again after a `composer update`.

## Getting started

```sh
composer require justinholtweb/craft-jack
php craft plugin/install jack
```

Then, in the control panel:

1. **Jack → Profile.** Work through the tabs. Every field is one that some clause needs; the
   progress bar tells you how much of the library you have unlocked.
2. **Jack → Inventory.** Add the services this site uses. On Pro, *Find undeclared services* reads
   your templates, your installed plugins and your environment and tells you what you missed.
3. **Jack → Documents → New.** Pick a document. It is written immediately.
4. **Jack → Audit.** Everything wrong, worst first, each with a remedy.

## Putting a document on the site

Every document has a URL of its own by default (`/impressum`, `/datenschutz`), rendered with a
plain, readable, printable page if you have not written a template. That is a real answer rather
than a placeholder: a legal text is required to be *reachable*, and making that wait on somebody
writing Twig is how sites ship without one.

To render one inside your own templates:

```twig
{{ craft.jack.render('privacy') }}
{{ craft.jack.render('privacy', { toc: true, headingLevel: 3 }) }}

<a href="{{ craft.jack.url('imprint') }}">Impressum</a>
```

Lookups take a handle *or* a document type, so a footer can ask for `'privacy'` without knowing
what somebody called the document in the control panel.

Inside a rich-text field, a reference tag works:

```
{jack:privacy:render}
```

### Other things templates can ask for

```twig
{# The facts, so the footer address and the imprint cannot disagree #}
{{ craft.jack.profile()['company.name'] }}

{# The inventory, so a consent banner and the privacy policy cannot disagree #}
{% for service in craft.jack.consentRequired() %}
    <label>{{ service.label() }} — {{ service.category() }}</label>
{% endfor %}

{# Every cookie the site declares #}
{% for cookie in craft.jack.cookies() %}
    <tr><td>{{ cookie.name }}</td><td>{{ cookie.duration }}</td></tr>
{% endfor %}

{{ craft.jack.lastUpdated('privacy')|date('long') }}
{{ craft.jack.toc('privacy') }}
```

## Keeping documents true

A legal text that quietly stopped being true is not something anybody notices by looking at the
site. Jack watches three things:

- **The library moved.** A clause was reworded because the law changed. Jack knows which one.
- **The inventory moved.** Somebody added a payment provider. The services section changed.
- **The profile moved.** You moved office. The imprint has your old address on it.

All three show up the same way — the document is marked out of date, and Pro shows exactly which
paragraphs differ. With *recompile automatically* on (the default) documents follow the facts by
themselves; turn it off if legal text has to be reviewed before it goes live.

### In CI

```sh
php craft jack/documents/check          # exits non-zero on anything serious
php craft jack/documents/check --strict # …and on warnings too
php craft jack/inventory/detect         # exits non-zero on an undeclared service
php craft jack/documents/compile        # rebuild everything
```

`jack/inventory/detect` is the one worth wiring up. It fails the build when a developer embeds a
YouTube video without anybody adding YouTube to the privacy policy — which is how almost every
inaccurate privacy policy becomes inaccurate.

## What ships in the library

**Documents.** Imprint (§ 5 DDG), privacy policy, cookie policy, terms and conditions with the
statutory withdrawal instructions and model form, standalone withdrawal instructions, disclaimer,
accessibility statement, and a social media privacy policy for your pages on other people's
platforms.

**Jurisdictions.** German-first — DDG, TDDDG, MStV, VSBG, EGBGB — with GDPR throughout, plus
Austria, Switzerland, the EU generally, the UK, and a CCPA/CPRA layer for California. Every clause
is written in German and English.

**Processors.** Forty-four, with provider, address, purpose, data categories, legal basis,
retention, transfer mechanism and cookies: Google Analytics, Tag Manager, Ads, Matomo, Plausible,
Fathom, Hotjar, Clarity, Meta Pixel, LinkedIn, TikTok, Pinterest, Microsoft Advertising, Stripe,
PayPal, Klarna, Mollie, YouTube, Vimeo, Spotify, SoundCloud, Google Fonts, Adobe Fonts, Font
Awesome, Google Maps, Mapbox, OpenStreetMap, Mailchimp, Brevo, CleverReach, Postmark, SendGrid,
Cloudflare, reCAPTCHA, Turnstile, hCaptcha, Sentry, Intercom, Crisp, HubSpot, Calendly — and the
first-party ones every Craft site has whether it admits them or not.

**Currency.** The library is stamped with the date it was last reviewed and tracks the statutes as
they are renamed: TMG became DDG in May 2024, TTDSG became TDDDG, and the EU's online dispute
resolution platform shut down on 20 July 2025, so Jack does not link it — a stale link there is
itself a liability.

## What Jack is not

**A consent banner.** Jack's job ends at describing accurately what the site does. Holding a script
back until somebody agrees is a different job with a different failure mode, and a plugin that did
both badly would be worse than two that each do one. What Jack does is make sure that if you have a
banner, it and your privacy policy cannot disagree about which services exist — the inventory is
readable from Twig for exactly that.

**A lawyer.** Jack produces drafts from facts you supply, using boilerplate that is conventional
rather than bespoke. Somebody accountable should read one before it is published, and a business
with unusual processing needs advice, not a generator.

## Editions

| | Lite | Pro |
|---|:---:|:---:|
| Imprint and privacy policy | ● | ● |
| The whole clause library and processor registry | ● | ● |
| Told when a document goes out of date | ● | ● |
| Cookie policy, terms, withdrawal, disclaimer, accessibility, social | | ● |
| Every site and every language | | ● |
| Rewriting a clause, adding your own sections | | ● |
| Finding undeclared services | | ● |
| Version history and diffs | | ● |
| Console commands and the CI check | | ● |

A document compiled under Pro keeps rendering if the licence lapses. A privacy policy that 404s
because of a billing problem is a worse outcome than any it could prevent.

## Settings

| Setting | What it does |
|---|---|
| `autoCompile` | Rebuild every document when the profile or inventory changes. Default on. |
| `enableDocumentUrls` | Give documents front-end pages of their own. Turn off if the site publishes its legal texts through its own entries — two copies is one too many. |
| `documentTemplate` | Your template, rendered with a `document` variable. Empty means Jack's own plain page. |
| `showLastUpdated` | Print a “Stand:” / “Last updated:” line. |
| `purifyOutput` | Run text *you* have rewritten through HTML Purifier. Library text is never touched. |
| `detectorPaths` | Directories the detector reads. Compiled JavaScript is worth including. |

## Licence

Proprietary. See `LICENSE.md`.
