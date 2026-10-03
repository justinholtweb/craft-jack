---
title: Configuration
slug: configuration
order: 20
summary: Every setting, the config file, user permissions, the profile and its per-site overrides, and the inventory.
---

## Settings

Open **Jack → Settings**, or **Settings → Plugins → Jack**. The screen is for admins only, and
only appears where `allowAdminChanges` is on. Every setting can also be set in `config/jack.php`.

| Setting | Config key | Default | What it does |
|---|---|---|---|
| Recompile automatically | `autoCompile` | on | Rebuilds every document's draft when the profile, the inventory or these settings are saved. Off, drafts stay as they are and documents show as out of date instead |
| Give documents their own URLs | `enableDocumentUrls` | on | Gives each document a front-end page. Turn it off if the site already publishes its legal texts through its own entries |
| Document template | `documentTemplate` | blank | Your template for a document's own page, rendered with a `document` variable. Blank means Jack's own plain page |
| Show a “last updated” line | `showLastUpdated` | on | Prints *Stand:* or *Last updated:* with the publish date at the foot of every rendered document |
| Restrict edited text to basic markup | `purifyOutput` | on | Limits text you've rewritten, and sections you've added, to basic text markup. Off allows more HTML; HTML Purifier runs either way, so scripts and `javascript:` links never pass. Library text is never touched |

**Recompile automatically** only ever touches drafts. Turning it on or off doesn't change what
visitors see. That moves only when someone publishes. See
[Usage](usage#compiling-and-publishing).

Settings that change the output, like **Show a “last updated” line**, recompile every draft when
you save them, if **Recompile automatically** is on.

### Finding undeclared services (Pro)

These control what **Find undeclared services** and `jack/inventory/detect` read.

| Setting | Config key | Default | What it does |
|---|---|---|---|
| Directories to scan | `detectorPaths` | `templates` | Relative to the project root. Add your build folder: a tag manager snippet often appears only in compiled JavaScript |
| File extensions | `detectorExtensions` | `twig, html, js, php` | Comma separated |
| Largest file to read | `detectorMaxFileSize` | `512` | In kilobytes, 1–10240. Larger files are skipped |

The foot of the screen says when the bundled clause library was last reviewed.

### The config file

```php
<?php
// config/jack.php

return [
    'autoCompile' => false,
    'enableDocumentUrls' => true,
    'documentTemplate' => '_legal/document',
    'showLastUpdated' => true,
    'purifyOutput' => true,
    'detectorPaths' => ['templates', 'web/dist'],
    'detectorExtensions' => ['twig', 'html', 'js'],
    'detectorMaxFileSize' => 512,
];
```

`detectorPaths` and `detectorExtensions` also accept a comma-separated string, such as
`'twig,html,js'`.

## User permissions

Jack adds four permissions under **Jack** in each user group's settings. Admins have all of them.

| Permission | Handle | What it allows |
|---|---|---|
| View legal documents | `jack:view-documents` | Opening Jack's screens: documents, profile, inventory and audit, read-only |
| Create and edit documents, the profile and the inventory | `jack:manage-documents` | Creating, editing, recompiling and deleting documents. Editing the profile and the inventory |
| Publish documents | `jack:publish-documents` | Publishing a document, which is what puts its text on the site |
| Edit the company profile | `jack:manage-profile` | Editing the profile without being able to touch documents or the inventory |

The last three are nested under **View legal documents**.

**Publish documents** is separate from editing on purpose. Compiling a document produces a draft.
Publishing it is the site's public legal position, and plenty of organisations want those to be
different people. Give your editors **Create and edit…** and give whoever signs off **Publish
documents**.

## The profile

**Jack → Profile** holds the facts about the business. Each field is a fact some clause needs. Fill
one in and it's right in every document, in every language, at once.

The fields are grouped into sections:

| Section | What goes in it |
|---|---|
| **Company** | Legal name, legal form, authorised representative, address, register court and number, VAT ID, economic ID |
| **Contact** | Email, phone, fax, contact form URL, and the person responsible for editorial content |
| **Profession** | For regulated professions: title, chamber, professional regulations, supervisory authority |
| **Data protection** | Controller, data protection officer, the supervisory authority, default retention |
| **Hosting** | Your host, their address, where the servers are, their privacy policy, whether a data processing agreement is in place |
| **This site** | Site name and URL, **Primary audience**, whether it's aimed at under-16s, sells online, or has accounts |
| **Selling** | Delivery time, withdrawal period, returns address, governing law, place of jurisdiction |
| **Accessibility** | Conformance, standard, known limitations, last assessed, feedback address |

The hints under each field matter. **Legal name** wants the name exactly as registered, not the
brand. **VAT ID** is the USt-IdNr., not your tax number. **Legal form** decides which imprint
entries German law requires of you.

**Primary audience** sets the jurisdiction new documents are created with. You can change it per
document afterwards.

### Links must be web addresses

Every URL fact, such as **Contact form URL**, **Authority website** or **Their privacy policy**,
only accepts an `http://` or `https://` link. Anything else is rejected when you save. The same
goes for **Privacy policy URL** and **Opt-out URL** on an inventory entry.

### Per-site overrides (Pro)

On a multi-site install, the profile screen links to each site under **Per-site overrides:**. A
site's profile holds only the answers that differ from the global one. Anything left blank falls
back to the global profile.

Blanking a field on a site's profile doesn't erase it. It stops overriding it. A German site and
its English translation are usually one company, so you type the address once.

## The inventory

**Jack → Inventory** lists everyone the site hands data to. This list *is* the privacy policy's
services section. Add Stripe here and the Stripe paragraph appears in every language you publish
in, with the right legal basis and the right transfer wording.

The inventory is global, not per site. The processors a business uses don't change between its
German and English sites. Only the language does.

### Adding a service

- **From the library:** pick one of the 44 bundled services and press **Add**. Provider, address,
  legal basis, transfer wording and cookies all come with it.
- **Something else:** type a key for a service Jack doesn't know, using lowercase letters, numbers
  and underscores, and press **Add a custom service**. You describe it on the next screen. Anything
  you leave blank won't appear.

### Editing a service

Each field on a bundled service shows the library's value as a placeholder. Change one and this
site stops following the library for that field. Change what your adviser told you to change, and
leave the rest.

| Field | What it is |
|---|---|
| Name, Provider, Provider address | Who the recipient is |
| Legal basis | Consent, contract, legal obligation or legitimate interest. Jack's reading, not a ruling |
| Where the data goes | **Stays in the EEA**, or the transfer mechanism: an adequacy decision, the EU–US Data Privacy Framework, or standard contractual clauses |
| Purpose, Data processed, Retention, Extra note | In **German** and **English**. Short phrases, not sentences: Jack supplies the grammar around them |
| Privacy policy URL, Opt-out URL | `http(s)` links only |
| In use | Off keeps the entry but leaves it out of every document. It's then listed under **Switched off** |

**Server log files** and **Session and security cookies** are always on. They describe something the site
does whether or not it's written down, so they can't be switched off or removed.
