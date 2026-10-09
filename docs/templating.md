---
title: Templating
slug: templating
order: 40
summary: Document URLs, your own document template, the craft.jack Twig API, reference tags, the Legal document field, and driving a consent banner from the inventory.
---

Everything on the front end shows the **published** text, and only the published text. The draft
on the control panel's edit screen never reaches a visitor until someone publishes it. A document
that has never been published, or is disabled, renders nothing. See
[Usage](usage#compiling-and-publishing).

The Twig API is available in Lite and Pro.

## Document URLs

With **Give documents their own URLs** on (the default), every document has a page at its slug:
`/impressum`, `/datenschutz` and so on. With **Document template** blank, Jack serves a plain,
readable, printable page of its own, with a contents list and the *Last updated* line. That's a
working answer, not a placeholder. A legal text has to be reachable, and that shouldn't wait on
someone writing Twig.

Turn **Give documents their own URLs** off if your site publishes its legal texts through its own
entries instead. Two copies at two URLs is one too many, and one of them goes stale.

### Your own template

Set **Document template** to a template path. It's rendered with a `document` variable:

```twig
{# templates/_legal/document.twig #}
{% extends '_layout' %}

{% block content %}
    <h1>{{ document.title }}</h1>
    {{ craft.jack.render(document.handle, { toc: true }) }}
{% endblock %}
```

`document.render` gives you the same output with the default options.

## Rendering a document

```twig
{{ craft.jack.render('privacy') }}
{{ craft.jack.render('privacy', { toc: true, headingLevel: 3 }) }}
```

Every lookup takes a document's handle **or** its type, so a footer can ask for `'privacy'` without
knowing what anyone called the document. A handle is tried first. Failing that, you get the first
document of that type. The first document of each type gets the type as its handle: `imprint`,
`privacy`, `cookies`, `terms`, `withdrawal`, `disclaimer`, `accessibility`, `social`.

### Options

| Option | Default | What it does |
|---|---|---|
| `toc` | `false` | Puts a contents list above the document |
| `lastUpdated` | **Show a “last updated” line** setting | Prints *Stand:* (German) or *Last updated:* (English) with the publish date |
| `headingLevel` | `2` | The level of each section heading. Use `3` when the document sits under an existing `<h2>`, so the outline stays valid |
| `class` | `jack-document` | The class on the wrapping `<div>` |

### The markup

Plain on purpose: headings, paragraphs, lists and the occasional table. Legal texts are read,
printed and quoted, and every class Jack invents is one your theme has to override.

| Element | Class or attribute |
|---|---|
| Wrapper | `<div class="jack-document" data-jack-document="privacy">` |
| Each section | `<section id="…" class="jack-section">`, with a stable anchor such as `jack-imprint-liability-content` |
| Contents list | `<nav class="jack-toc">` holding an `<ol>` |
| Last updated line | `<p class="jack-updated">` |

## Methods

| Method | Returns |
|---|---|
| `craft.jack.render(handleOrType, options, siteId)` | The published document as HTML. Empty if there's no published text or the document is disabled |
| `craft.jack.document(handleOrType, siteId)` | The document element, or `null` |
| `craft.jack.documents(siteId)` | Every document |
| `craft.jack.url(handleOrType, siteId)` | The document's URL, or `null` |
| `craft.jack.toc(handleOrType, siteId)` | The published document's sections, as `{ anchor, title }` pairs |
| `craft.jack.lastUpdated(handleOrType, siteId)` | The publish date as a `DateTime`, or `null` if never published |
| `craft.jack.profile(siteId)` | The profile's facts, as a flat array with dotted keys |
| `craft.jack.services(category)` | Every service in the inventory that's in use, optionally for one category |
| `craft.jack.consentRequired()` | Services whose legal basis is consent |
| `craft.jack.cookies(language)` | Every cookie the inventory declares |
| `craft.jack.libraryVersion()` | The date the bundled clause library was last reviewed |
| `craft.jack.consentConfig(format, siteId)` | Pro. The inventory as a consent manager's config: `toss`, `klaro`, `cookieconsent` or `consent-mode`. See [Consent managers](consent-managers) |
| `craft.jack.consentScript(format, options, siteId)` | Pro. The same config in a `<script>` that assigns it to a global |

`siteId` is optional everywhere and defaults to the current site.

`toc()` and `lastUpdated()` read the published copy, so they can't disagree with the document they
sit beside.

## Links in the footer

```twig
<a href="{{ craft.jack.url('imprint') }}">Impressum</a>
<a href="{{ craft.jack.url('privacy') }}">Datenschutz</a>
```

## A contents sidebar

```twig
<nav>
    <ol>
        {% for item in craft.jack.toc('privacy') %}
            <li><a href="#{{ item.anchor }}">{{ item.title }}</a></li>
        {% endfor %}
    </ol>
</nav>

{{ craft.jack.render('privacy') }}

{% set updated = craft.jack.lastUpdated('privacy') %}
{% if updated %}
    <p>Last updated {{ updated|date('long') }}</p>
{% endif %}
```

If you print your own date, pass `lastUpdated: false` to `render()` so it doesn't appear twice.

## The company address, once

The facts are readable from Twig, so a footer address and the imprint can't disagree:

```twig
{% set facts = craft.jack.profile() %}
{{ facts['company.name'] }}<br>
{{ facts['company.street'] }}<br>
{{ facts['company.postalCode'] }} {{ facts['company.city'] }}
```

Keys are flat and dotted. Derived facts like `company.address` and `controller.display` are there
too, already assembled. On a multi-site install, a site's overrides are applied.

## Driving a consent banner

Jack isn't a consent banner. But if you have one, drive it from the inventory, and the banner and
the privacy policy can't disagree about which services exist:

```twig
{% for service in craft.jack.consentRequired() %}
    <label>
        <input type="checkbox" name="consent[]" value="{{ service.serviceKey }}">
        {{ service.label() }} ({{ service.category() }})
    </label>
{% endfor %}
```

A cookie table for a cookie banner or your own cookie page:

```twig
<table>
    {% for cookie in craft.jack.cookies() %}
        <tr>
            <td><code>{{ cookie.name }}</code></td>
            <td>{{ cookie.service }}</td>
            <td>{{ cookie.duration }}</td>
            <td>{{ cookie.essential ? 'Essential' : 'Optional' }}</td>
        </tr>
    {% endfor %}
</table>
```

Each cookie has `service`, `category`, `essential`, `name` and `duration`. Labels and durations come
in the current site's language unless you pass one.

Unlike documents, the inventory is live: `services()`, `consentRequired()` and `cookies()` reflect
the inventory as it is now, not as of the last publish.

On Pro, Jack can write the whole config for Toss, Klaro or CookieConsent v3, and the Consent Mode
types for Tape, so you don't have to. See [Consent managers](consent-managers).

## Reference tags

Inside a rich-text field, a reference tag drops a published document in place:

```
{jack:privacy:render}
```

The middle part is the document's handle. Like `render()`, it shows the published text only, and
nothing for a disabled or unpublished document.

## The Legal document field

Jack adds a **Legal document** field type, for relating entries to documents: a checkout page that
links its terms, say.

```twig
{% set terms = entry.legalDocs.one() %}
{% if terms %}
    <a href="{{ terms.url }}">{{ terms.title }}</a>
{% endif %}
```
