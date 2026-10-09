# Release Notes for Jack

## Unreleased

### Added

- **Consent manager configs** (Pro). The inventory written out as configuration for Toss (with the
  matching Toss preset per service and, when Toss is the consent manager, which services its script
  inventory is missing or files differently), Klaro, CookieConsent v3, and Google Consent Mode
  types with the Tape category per service. Download one from **Inventory → Export consent config**,
  print it with `jack/inventory/consent-config --format=…`, or put it on a page with
  `craft.jack.consentScript()` and `craft.jack.consentConfig()`. Services run on legitimate
  interest are filed as necessary; newsletters and cookie-free first-party entries stay out. The
  `consentCategories` setting moves a service or a category.
- **GraphQL for documents.** `jackDocuments`, `jackDocument` and `jackDocumentCount`, behind a new
  **Query for published legal documents** schema permission: published text, sections, publish
  date, version number, library date and clause stamp. Enabled, published documents only, for every
  schema.
- **A JSON endpoint**, `/jack/documents.json` and `/jack/documents/<handle>.json`, with the same
  published-only rule. Switch it off with **Serve documents as JSON** (`serveDocumentsJson`).

## 5.0.0 - 2026-10-03

Initial release.

### Added

- **The profile** — sixty facts about a business, entered once, feeding every document in every
  language. One global set, with per-site overrides on multi-site installs.
- **The inventory** — the third parties a site hands data to, with forty-one third-party services described in
  full: provider, address, purpose, data categories, legal basis, retention, transfer mechanism and
  cookies. The privacy policy's service sections are rendered from it rather than written.
- **The clause library** — fifty-nine clauses across eight document types, in German and English,
  shipped inside the plugin and stamped with the date they were last reviewed.
- **Documents** as elements: localized, with URLs of their own and a plain readable page when no
  template has been configured.
- **Drift detection** — a document is compared against a fresh compilation of itself, so a
  reworded clause, an added service and a changed address all surface the same way. Pro names the
  paragraphs that differ.
- **The detector** (Pro) — reads templates, installed plugins and environment variables and reports
  services that are on the site but not in the privacy policy, with the file and line as evidence.
- **The audit** — everything wrong with a site's legal texts, worst first, each with a remedy.
- **Publishing** — compiling writes a draft; publishing puts it on the site and freezes it into an
  immutable version. Visitors only ever see published text, so a profile edit never goes live
  half-typed. Before- and after-publish events let a module tell legal when a version goes out.
- **Version history** (Pro) — every published version, with a line diff between any two.
- **Console commands** (Pro) — `jack/documents/compile`, `jack/documents/check`,
  `jack/documents/list`, `jack/inventory/detect`, `jack/inventory/list`. `check` and `detect` exit
  non-zero, for CI.
- **Twig** — `craft.jack.render()`, `document()`, `url()`, `toc()`, `lastUpdated()`, `profile()`,
  `services()`, `consentRequired()`, `cookies()`, plus `{jack:handle:render}` reference tags and a
  “Legal document” relation field.

### Notes on the bundled texts

- Statutes are current as at the library date: `§ 5 DDG` (not TMG, renamed May 2024) and
  `§ 25 TDDDG` (not TTDSG).
- The EU's online dispute resolution platform ceased operating on 20 July 2025. The imprint and
  terms clauses reflect that and do not link it; a stale ODR link is itself a liability.
- The withdrawal instructions and model form reproduce Anlage 1 and Anlage 2 to
  Art. 246a § 1 Abs. 2 EGBGB. Jack fills the blanks and changes nothing else.
