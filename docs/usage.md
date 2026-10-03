---
title: Usage
slug: usage
order: 30
summary: How documents are written, compiling versus publishing, out-of-date documents, rewriting sections, history, the audit, finding undeclared services and the console.
---

## How a document is written

You don't write a privacy policy in Jack. You answer two questions, and the document follows:

- **Who are you?** The [profile](configuration#the-profile).
- **What do you use?** The [inventory](configuration#the-inventory).

Jack runs those through its clause library, which ships inside the plugin, and the result is the
document. The same profile, inventory and library always produce the same text. That's what lets
Jack tell you when a document no longer says what it should.

The services section of a privacy policy isn't written per service either. The library describes
each of its 44 services as facts: provider, address, purpose, data, legal basis, transfer mechanism
and cookies. Jack renders the same paragraph over every one. Adding Klarna is a dropdown, not a
writing job, and forty services read in one voice.

### Facts the document still needs

A clause that needs a fact you haven't given is still included, with a gap where the detail
belongs. Jack never prints a placeholder or a stray `{{ }}` into a legal text. The edit screen
says so at the top, under **This document is incomplete.**, and lists the missing fields. Press
**Fill these in** to go to the profile.

Optional details, like a second address line or a fax number, simply disappear when blank, along
with the words around them.

## Document types

| Type | Default URL (German / English site) | Edition |
|---|---|---|
| Imprint / legal notice | `/impressum` / `/legal-notice` | Lite and Pro |
| Privacy policy | `/datenschutz` / `/privacy-policy` | Lite and Pro |
| Cookie policy | `/cookies` / `/cookie-policy` | Pro |
| Terms and conditions | `/agb` / `/terms` | Pro |
| Withdrawal instructions | `/widerruf` / `/withdrawal` | Pro |
| Disclaimer | `/haftungsausschluss` / `/disclaimer` | Pro |
| Accessibility statement | `/barrierefreiheit` / `/accessibility` | Pro |
| Social media privacy | `/social-media-datenschutz` / `/social-media-privacy` | Pro |

Titles and URLs default to the language of the site the document is created for. Change both on
the edit screen under **Title** and **Slug**.

The terms document carries the statutory withdrawal instructions and the model withdrawal form.
That wording is set by law (Anlage 1 and 2 to Art. 246a § 1 Abs. 2 EGBGB). Departing from it loses
the legal safe harbour, so Jack fills the blanks and changes nothing else.

## Creating a document

Open **Jack → Documents** and press **New document**. Pick a type and press **Create**. Types you
already have are marked *you already have one*. Types your edition can't create are marked *Pro*.
The sidebar of the documents index lists the types under **Not created yet**.

The document is compiled straight away. It is **not** published. Nothing appears on the site until
you publish it.

## Compiling and publishing

These are two separate acts.

**Compiling** writes the draft from the current profile, inventory and library. It's cheap and
usually automatic. With **Recompile automatically** on (the default), every save of the profile,
the inventory or the plugin settings recompiles every document. You can also compile one by hand
with **Recompile** in the **Actions** menu beside **Save**. The edit screen always previews the
draft.

**Publishing** copies the draft to the site. Open the **Actions** menu and choose **Publish this
version**. You need the **Publish documents** permission. Publishing:

- puts the draft on the document's URL, and everywhere a template renders it
- writes an immutable version to the history, stamped with the library date
- sets the date on the *Stand:* / *Last updated:* line

Nothing else changes what visitors see. A half-typed address saved on the profile updates the
draft, not the live imprint. Publishing works the same on Lite and Pro.

The edit screen's sidebar shows two dates, **Compiled** and **Published**. If **Compiled** is later
than **Published**, the site is showing an older text than the one on your screen.

### A typical review cycle

1. An editor changes the profile or adds a service. The drafts recompile.
2. The reviewer opens each document, reads the draft and checks the audit.
3. The reviewer clicks **Publish**.

If legal text has to be reviewed even before it becomes a draft, turn off **Recompile
automatically**. Documents then keep their draft and show as out of date until someone presses
**Recompile**.

### Disabling a document

Switch **Enabled** off in the edit screen's sidebar and the document disappears from the site. Its
URL returns a 404, and `craft.jack.render()` and reference tags render nothing. The audit reports a
disabled document as critical, because a legal text visitors can't reach is the same as not having
one.

## Out of date

A document is **out of date** when its draft no longer matches what Jack would write now. The edit
screen shows **Out of date.** at the top, and the audit lists it.

Three things cause it, and Jack catches all three the same way:

- **The library moved.** A `composer update` brought reworded clauses, usually because the law
  changed.
- **The inventory moved.** Someone added or switched off a service.
- **The profile moved.** The address changed, or a new fact was filled in.

With **Recompile automatically** on, the inventory and profile cases fix themselves on save. A
library update doesn't save anything, so after an update documents show as out of date until you
press **Recompile now**.

On Lite, you're told a document is out of date. On Pro, you're also told which paragraphs changed:
listed as **Changed**, **New** or **No longer applies**, with each clause's key and heading.

Recompiling brings the draft up to date. It still isn't on the site until you publish it.

## Rewriting a section (Pro)

Every section on the edit screen shows its clause key and the law it cites. Press **Rewrite this
section** to:

- change its **Heading**
- replace its **Text** with your own HTML
- tick **Leave this section out**

Edited sections are marked *edited*. An edited section stops receiving library updates. Leave
**Text** empty to keep the library's wording and keep getting those updates.

**Add a section** at the bottom of the screen is for something the library has no clause for. It's
yours to maintain. Jack will never update it.

Your HTML always goes through HTML Purifier; with **Restrict edited text to basic markup** on, only basic text markup survives. Library text is never
touched.

## Jurisdiction and language

Each document has a **Jurisdiction** in the edit screen's sidebar: Germany, Austria, Switzerland,
European Union, United Kingdom or United States. New documents take it from **Primary audience** on
the profile.

The library is written for German law first, with the GDPR throughout. Clauses that only apply in
German-speaking or EU law, such as parts of the imprint and the consumer terms, are left out for
other jurisdictions. Choosing **United States** adds a California notice (CCPA/CPRA) to the privacy
policy.

Every clause is written in German and English. A document is written in its site's language:
German for a `de` site, English for everything else. On Pro, a multi-site install gets each
document in each site's language, from one profile and one inventory.

## History and comparing versions (Pro)

Open a document's **Actions** menu and choose **History**. Every published version is listed with
its date, a note and the library date it was compiled against. Versions are kept forever. They
answer the question a legal team eventually asks: what did our privacy policy say on the day this
customer signed up?

Press **Compare with latest** on any version to see a line-by-line diff against the most recent
one. Because a document is a function of its inputs, two versions differ only because an input
did.

Lite keeps writing versions when you publish. They become browsable when you switch to Pro.

## The audit

**Jack → Audit** lists everything wrong with the site's legal texts, worst first: **critical**,
**to fix**, then **worth knowing**. Each finding comes with a **Fix:**. The number of critical and
to-fix findings appears as a badge on Jack's nav item.

Findings include:

| Finding | Severity |
|---|---|
| No imprint | Critical for a German, Austrian, Swiss or EU audience. Worth knowing otherwise |
| No privacy policy | Critical |
| “…” has never been compiled | Critical |
| “…” compiles to nothing | Critical. No clause applies with the facts Jack has |
| “…” is missing required details | Critical |
| “…” is disabled | Critical |
| The inventory is empty | Critical |
| Services on the site but not in the privacy policy | Critical (Pro) |
| “…” is out of date | To fix |
| Selling online without terms | To fix |
| No hosting provider named | To fix |
| No data processing agreement with the host | To fix |
| The profile is mostly empty | To fix. Under 60% of facts filled in |
| Google Fonts is loaded from Google | To fix. Cites LG München I, 20 January 2022, 3 O 17493/20 |
| No supervisory authority named | Worth knowing |
| Services that need consent before they load | Worth knowing |

## Finding undeclared services (Pro)

On **Jack → Inventory**, press **Find undeclared services**. Jack reads your templates, your
installed plugins and your environment variable names, and lists every third-party service it can
see. The ones not in the inventory are flagged, with the file and line where each was found.

Tick the ones to add and press **Add the ticked services**.

Detection reads files. A service loaded by a tag manager, or injected by another script at runtime,
won't appear unless it's written down somewhere Jack reads. Add your compiled JavaScript to
**Directories to scan** to catch more. Jack never prints the value of an environment variable,
only its name, so the report is safe to paste into a ticket.

## Console commands (Pro)

| Command | What it does |
|---|---|
| `php craft jack/documents/compile` | Recompiles every document's draft. `--site=handle` for one site |
| `php craft jack/documents/check` | Runs the audit and prints every finding with its fix. Exits non-zero on any critical finding. `--strict` fails on to-fix findings too. `--site=handle` for one site |
| `php craft jack/documents/list` | Lists documents with their type, URI and state: *never compiled*, *N facts missing*, *out of date* or *current* |
| `php craft jack/inventory/detect` | Finds services on the site and exits non-zero if any aren't declared. `--adopt` adds them to the inventory instead |
| `php craft jack/inventory/list` | Lists the inventory: key, category, legal basis, transfer, on or off |

`compile` updates drafts only. It never publishes.

On Lite, every command prints *The console commands are a Jack Pro feature.* and exits non-zero,
so a CI step that relies on them fails visibly instead of passing silently.

### In CI

```sh
php craft jack/documents/check
php craft jack/inventory/detect
```

`jack/inventory/detect` is the one worth wiring up. It fails the build when a developer embeds a
YouTube video without anyone adding YouTube to the privacy policy, which is how most inaccurate
privacy policies become inaccurate.
