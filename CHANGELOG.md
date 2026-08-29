# Release Notes for Jack

## 5.0.0

Initial release.

### Added

- **The profile** — sixty facts about a business, entered once, feeding every document in every
  language. One global set, with per-site overrides on multi-site installs.
- **The inventory** — the third parties a site hands data to, with forty-four services described in
  full: provider, address, purpose, data categories, legal basis, retention, transfer mechanism and
  cookies. The privacy policy's service sections are rendered from it rather than written.
- **The clause library** — forty-six clauses across eight document types, in German and English,
  shipped inside the plugin and stamped with the date they were last reviewed.
- **Documents** as elements: localized, with URLs of their own and a plain readable page when no
  template has been configured.
- **Drift detection** — a document is compared against a fresh compilation of itself, so a
  reworded clause, an added service and a changed address all surface the same way. Pro names the
  paragraphs that differ.
- **The detector** (Pro) — reads templates, installed plugins and environment variables and reports
  services that are on the site but not in the privacy policy, with the file and line as evidence.
- **The audit** — everything wrong with a site's legal texts, worst first, each with a remedy.
- **Version history** (Pro) — publishing freezes a document into an immutable version, with a line
  diff between any two.
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
