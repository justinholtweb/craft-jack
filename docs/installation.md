---
title: Installation
slug: installation
order: 10
summary: Requirements, install, your first document on the site, the two editions, and upgrading from Lite to Pro.
---

## Requirements

- Craft CMS 5.3 or later
- PHP 8.2 or later

No build step, and no dependencies beyond Craft. HTML Purifier comes with Craft itself.

## Install

```sh
composer require justinholtweb/craft-jack
php craft plugin/install jack
```

Or find **Jack** in the Craft Plugin Store and install it from there.

Installing creates five tables: documents, their per-site text, the profile, the inventory and the
version history. It adds no fields, sections or entry types, so there's no content model to build.

A fresh install isn't blank. The inventory already holds **Server log files** and **Session and
security cookies**, because every Craft site has both. They're marked **always on** and can't be removed.

## Nothing reaches your visitors until you publish

Jack keeps two copies of every document:

- **The draft.** Jack writes it from your profile and inventory, and rewrites it whenever either
  changes. The edit screen in the control panel shows you this copy.
- **The published text.** What visitors see, at the document's URL and anywhere a template renders
  it. It changes only when someone with the **Publish documents** permission publishes the
  document.

A document you've just created has a draft and nothing published. Its page is empty until you
publish it. See [Usage](usage#compiling-and-publishing).

## Your first ten minutes

1. Open **Jack → Profile**. Work through the sections: **Company**, **Contact**, **Data
   protection**, **Hosting**, **This site** and the rest. The bar at the top shows what percentage
   of the facts the clause library asks for you've filled in.
2. Open **Jack → Inventory**. Under **Add a service**, pick everything this site uses from **From
   the library**: analytics, payment providers, embedded videos, fonts, maps, newsletters. On Pro,
   **Find undeclared services** reads your templates and tells you what you missed.
3. Open **Jack → Documents** and press **New document**. Pick **Imprint / legal notice** and press
   **Create**. Do the same for **Privacy policy**.
4. Read each draft on its edit screen. Fix anything listed under **This document is incomplete.**
   on the profile, and read the result again.
5. Click **Publish** beside **Save** and confirm. The document is
   now live at its URL: `/impressum` and `/datenschutz` on a German site, `/legal-notice` and
   `/privacy-policy` on an English one.
6. Open **Jack → Audit** and work down the list.
7. Link both documents from every page, usually in the footer. See [Templating](templating).

Before step 5, have someone accountable read the draft. Jack is not a lawyer. See
[What Jack is not](#what-jack-is-not).

## Editions

Lite is free. Pro is $99, then $79 a year for updates.

The split follows one rule: Lite gets you legal, Pro keeps you maintained. Lite compiles the two
documents German law requires of a website, from the whole clause library and the whole processor
registry. Pro adds everything that keeps them true over time and across sites.

| | Lite | Pro |
|---|---|---|
| **Price** | **Free** | **$99**, then $79/year for updates |
| Imprint and privacy policy | ✅ | ✅ |
| The whole clause library and all 44 services in the registry | ✅ | ✅ |
| The profile, the inventory and the audit | ✅ | ✅ |
| Publishing a document | ✅ | ✅ |
| Told when a document is out of date | ✅ | ✅ |
| Twig API, reference tags and the **Legal document** field | ✅ | ✅ |
| Published documents over GraphQL and as JSON | ✅ | ✅ |
| Cookie policy, terms and conditions, withdrawal instructions, disclaimer, accessibility statement, social media privacy | — | ✅ |
| Every site and language, with per-site profile overrides | — | ✅ |
| Rewriting a section, leaving one out, adding your own | — | ✅ |
| Seeing *which* paragraphs changed when a document is out of date | — | ✅ |
| **Find undeclared services** | — | ✅ |
| Version history and comparing versions | — | ✅ |
| Console commands and the CI check | — | ✅ |
| Consent manager config for Toss, Klaro, CookieConsent and Consent Mode / Tape | — | ✅ |

On Lite, documents are compiled for the primary site only, in that site's language.

Publishing works the same on both editions. Every publish on Lite still writes a version to the
history table. You just can't browse or compare those versions until you switch to Pro.

## Upgrading from Lite to Pro

Buy Pro in the Plugin Store, or switch the edition in **Settings → Plugins** and add the licence
key there. There's nothing to migrate. Documents, the profile, the inventory and every version
published on Lite are all still there.

After switching:

- **New document** offers every document type.
- On a multi-site install, the profile screen links to per-site overrides, and documents compile
  for every site.
- Each section on a document's edit screen gets a **Rewrite this section** button.
- **Find undeclared services** appears on the inventory screen, and the audit starts reporting
  services it finds on the site that aren't declared.
- **History** appears in a document's **Actions** menu.
- The `jack/*` console commands work.

The edition is stored in project config. If a deploy re-applies a `project.yaml` that still says
Lite, the Pro features stop working without an error. Commit the edition change with the rest of
your config.

## A lapsed licence never blanks a document

Editions gate *creating* and *editing*. Text that's already published keeps rendering if a Pro
licence lapses, including a cookie policy or terms document that Lite couldn't create. A privacy
policy that 404s over a billing problem is worse than anything that would prevent.

## What Jack is not

**A lawyer.** Jack produces drafts from facts you supply, using boilerplate that is conventional
rather than bespoke. It is not legal advice. Somebody accountable should read a document before it
is published, and a business with unusual processing needs advice, not a generator.

**A consent banner.** Jack describes what the site does. It doesn't hold scripts back until a
visitor agrees. If you have a banner, drive it from Jack's inventory so the two can't disagree. See
[Templating](templating#driving-a-consent-banner).

## Uninstalling

Uninstalling drops all five tables. Documents, the profile, the inventory and the version history
go with them. Export anything you need to keep, especially the published versions, first.
