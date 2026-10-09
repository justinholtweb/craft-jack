---
title: Headless
slug: headless
order: 47
summary: Published legal documents over GraphQL and as JSON, for front ends that don't render through Twig.
---

A headless front end never runs `craft.jack.render()`, so Jack offers the same published text two
other ways: GraphQL and a JSON endpoint. Both are in **Lite and Pro**.

Both show exactly what a document's own page shows: the **published** text of **enabled**
documents. A draft a compile produced, its warnings, missing facts and overrides are never in
either, under any permission. A document that has only ever been compiled isn't found at all. It
doesn't appear as a document with nothing in it.

## GraphQL

Give a schema **Jack → Query for published legal documents** (`jackDocuments.all:read`) under
**GraphQL → Schemas**. Without it, the queries aren't in the schema at all.

```graphql
{
  jackDocument(documentType: "privacy") {
    title
    html
    publishedAt @formatDateTime(format: "j. F Y")
    sections { anchor title }
  }
}
```

| Query | Returns |
|---|---|
| `jackDocuments` | Published documents |
| `jackDocument` | One published document, or `null` |
| `jackDocumentCount` | How many published documents match |

They take Craft's usual element arguments (`site`, `siteId`, `id`, `uid`, `slug`, `uri`, `limit`,
`orderBy` …) plus:

| Argument | Narrows to |
|---|---|
| `handle` | Documents with these handles |
| `documentType` | `imprint`, `privacy`, `cookies`, `terms`, `withdrawal`, `disclaimer`, `accessibility`, `social` |
| `jurisdiction` | `de`, `at`, `ch`, `eu`, `uk`, `us` |

A schema that may also query inactive elements still gets enabled documents only. A disabled legal
text is one somebody switched off because it was wrong. Asking with `status` or `trashed` doesn't
reach it.

### Fields

Besides Craft's element fields (`id`, `title`, `slug`, `uri`, `siteHandle`, `language` …):

| Field | Type | What it is |
|---|---|---|
| `url` | `String` | The document's own page, when documents have URLs |
| `handle` | `String!` | What `craft.jack.render()` and reference tags name |
| `documentType` | `String!` | `privacy`, `imprint` … |
| `documentTypeLabel` | `String!` | “Privacy policy” |
| `jurisdiction` | `String!` | `de`, `eu`, `us` … |
| `html` | `String!` | The published text: sections with stable anchors, headings at level 2, no wrapper |
| `sections` | `[JackDocumentSection!]!` | `anchor` and `title` for each section, for a contents list |
| `publishedAt` | `DateTime` | When it was last published: the “last updated” date |
| `version` | `Int` | Which published version this is on this site, 1 for the first |
| `libraryVersion` | `String` | The date of the clause library the text was compiled from |
| `stamp` | `[JackClauseStamp!]!` | Each library `clause` the text came from, with its `version` |

`html` has no “last updated” line and no contents list. Print `publishedAt` and `sections` where
your design wants them.

## JSON

Two URLs on every site, open to anyone and any origin:

| URL | Returns |
|---|---|
| `/jack/documents.json` | `{"documents": […]}`: every published document on the site, without `html`, `sections` or `stamp` |
| `/jack/documents/<handle>.json` | `{"document": {…}}`: one document, by handle or by type, the same lookup as `craft.jack.render('privacy')` |

```json
{
  "document": {
    "handle": "privacy",
    "type": "privacy",
    "typeLabel": "Privacy policy",
    "title": "Datenschutzerklärung",
    "jurisdiction": "de",
    "language": "de-de",
    "url": "https://example.com/datenschutz",
    "publishedAt": "2026-10-09T09:12:44+00:00",
    "version": 4,
    "libraryVersion": "2026-10-03",
    "sections": [{ "anchor": "jack-privacy-controller", "title": "Verantwortliche Stelle" }],
    "stamp": { "privacy.controller": "1.0.0" },
    "html": "<section id=\"jack-privacy-controller\" …"
  }
}
```

The site is the one the request resolved to, like any front-end URL: request
`https://example.com/en/jack/documents/privacy.json` for the English site. There's no parameter
for another site, a draft or an older version. A document that isn't published, is disabled or
doesn't exist is a 404.

Responses carry `Cache-Control: public, max-age=300`, so a CDN may keep a document for five
minutes after a publish.

Switch the endpoint off with **Serve documents as JSON** (`serveDocumentsJson`) in Jack's
settings. GraphQL is controlled by schema permissions instead.
