---
title: Consent managers
slug: consent-managers
order: 45
summary: Writing the inventory out as configuration for Toss, Klaro, CookieConsent v3 and Google Consent Mode / Tape, so the banner, the privacy policy and the tags that fire share one list.
---

Jack isn't a consent banner. But a banner needs the same list the privacy policy is written from:
which services run, what each is for, which cookies it sets, and which of them need asking about.
Copying that list into the banner's config by hand is how a banner and a privacy policy come to
disagree. On **Pro**, Jack writes the config for you, from the inventory.

There are four targets:

| Format | For |
|---|---|
| `toss` | [Toss](/plugins/craft-toss), the consent manager in this plugin family |
| `klaro` | [Klaro](https://klaro.org), as the `klaroConfig` object it reads |
| `cookieconsent` | [CookieConsent v3](https://cookieconsent.orestbida.com), as the object `CookieConsent.run()` takes |
| `consent-mode` | Google Consent Mode v2 types and [Tape](/plugins/craft-tape)'s destination categories, per service |

The config is a pure function of the inventory, the settings and the clause library. It carries
no date and nothing about the visitor, so you can commit it, diff it, and cache it inside a page.

## Getting a config

**In the control panel:** Jack → Inventory → **Export consent config**, then pick a format. The
file is JSON, for the site you're looking at.

**From the command line:**

```sh
php craft jack/inventory/consent-config --format=klaro
php craft jack/inventory/consent-config --format=cookieconsent --site=german > web/consent.json
```

`--site` takes a site handle and defaults to the primary site.

**In a template:**

```twig
{# A <script> that assigns the config to a global #}
{{ craft.jack.consentScript('klaro') }}
<script defer src="https://cdn.kiprotect.com/klaro/v0.7/klaro.js"></script>

<script src="https://cdn.jsdelivr.net/gh/orestbida/cookieconsent@3.1.0/dist/cookieconsent.umd.js"></script>
{{ craft.jack.consentScript('cookieconsent') }}
<script>CookieConsent.run(window.jackConsentConfig);</script>

{# Or the data, to build something of your own #}
{% set config = craft.jack.consentConfig('toss') %}
```

`consentScript()` assigns to `window.klaroConfig` for Klaro, which is where Klaro looks, and to
`window.jackConsentConfig` otherwise. Pass `{ variable: 'myBanner.config' }` to choose. The output
is the same for every visitor, so it's safe in a statically cached page. Anything an editor typed,
like a custom service's name, is escaped so it can't break out of the `<script>`.

On Lite, `consentConfig()` returns an empty array and `consentScript()` prints nothing. The
inventory itself is still readable on Lite with `craft.jack.services()` and `craft.jack.cookies()`,
so a banner can be driven from it by hand. See
[Templating](templating#driving-a-consent-banner).

## How services are sorted

Every target files each service under one of four categories. They're the ones Toss uses, and
the rest of the family reads them from Toss:

| Category | Meaning |
|---|---|
| `necessary` | Runs without asking. Can't be refused |
| `preferences` | Adds features: maps, fonts, booking, chat |
| `analytics` | Measures how the site is used |
| `marketing` | Advertising, embedded media and social networks, which can recognise a visitor on other sites |

The rules, in order:

1. A `consentCategories` setting naming the **service key** wins.
2. A service whose legal basis is **not consent** is `necessary`. Plausible, Stripe, Cloudflare
   and the session cookie run without asking, and a banner offering to switch them off would
   promise something the site doesn't do.
3. Otherwise the service's **inventory category** decides:

| Inventory category | Consent category |
|---|---|
| Analytics | `analytics` |
| Advertising, Social media, Embedded media | `marketing` |
| Maps, Fonts and icons, Support and chat, Bookings, Payments, Security, Infrastructure, Site functions | `preferences` |
| Email and newsletters | left out |

Newsletters are left out because consent to a newsletter is given in the sign-up form, and nothing
about them runs in the visitor's browser for a banner to hold back. First-party entries that set no
cookie, like server log files and the contact form, are left out for the same reason.

### Changing where a service goes

Set `consentCategories` in `config/jack.php`. Keys are inventory categories or service keys; a
service key wins over its category. `null` leaves it out of every banner.

```php
return [
    'consentCategories' => [
        'maps' => 'marketing',      // every map service
        'youtube' => 'preferences', // just YouTube
        'hubspot' => null,          // not in the banner at all
    ],
];
```

Values must be one of the four categories, or a category you've added in Toss. Anything else is
ignored.

## Toss

When Toss is installed and its cookie consent kit is on, Jack treats Toss as the consent manager:

- The `toss` export lists each service with its category, purpose, provider, privacy policy link,
  cookie names and `tossPreset`: the Toss script preset for the same service (Toss calls the Meta
  Pixel `meta-pixel` where Jack calls it `meta_pixel`), or `null` when Toss has no preset for it.
- Each service also carries `inToss` and `tossCategory`: whether Toss's script inventory for that
  site has it, and under which category. A service in Jack's inventory that isn't in Toss is a
  service the privacy policy describes and the banner doesn't hold back. A different category is a
  disagreement worth resolving one way or the other.
- Categories you've added in Toss become valid `consentCategories` values.
- The `consent-mode` export uses Toss's own Consent Mode map, with any `consentModeMap` override
  from `config/toss.php`. Toss, Tape and Jack's export can never tell Google different things.

Toss doesn't import the file. The export is the list to check Toss's script inventory against.
When Toss isn't installed, or its consent kit is off, `tossActive` is `false` and the two `inToss`
keys aren't there.

## Klaro

Each service becomes a Klaro service named by its Jack key, with one purpose (its category). It's
`required` when it's `necessary`. Its description is the purpose and the provider, in the site's
language, and its cookies are the ones the inventory declares. The purposes carry titles and
descriptions in German or English, and the privacy policy link is your **published** privacy
policy's URL. It's left out if there isn't one.

## CookieConsent v3

`necessary` is read-only. Every other category lists its services with their labels, and
`autoClear` lists their cookies, so refusing a category deletes what it set. The preferences modal
has one section per category with a cookie table (name, service, duration), plus a section that
links the privacy policy. The modal text is in German or English.

## Google Consent Mode and Tape

For each service, `consent-mode` gives the Consent Mode v2 types its answer travels as, and the
Tape destination category that waits on the same answer:

| Consent category | Consent Mode types | Tape category |
|---|---|---|
| `marketing` | `ad_storage`, `ad_user_data`, `ad_personalization` | `ads` |
| `analytics` | `analytics_storage` | `analytics` |
| `preferences` | `functionality_storage`, `personalization_storage` | `functionality` |
| `necessary` | `security_storage` | none: Tape never holds it back |

Use it to check that a pixel Tape fires is waiting on the category the privacy policy and the
banner say it belongs to.

## Wildcard cookies

Some services set cookies with a variable suffix: Google Analytics sets `_ga_` followed by a
measurement ID. Klaro and CookieConsent match such a name only with a JavaScript regular
expression. `consentScript()` writes one, `/^_ga_.*$/`. JSON has no regular expressions, so the
JSON download and `consentConfig()|json_encode` give the pattern's source as a string,
`"^_ga_.*$"`. If you load the JSON yourself, turn those back into `new RegExp(…)` before handing
the config to the banner.

## Languages

A config is written in its site's language: German for a site whose language starts with `de`,
English for any other. That's the same rule documents follow.
