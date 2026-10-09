# Jack — Craft CMS 5 Plugin

## Project Overview

Jack generates legal texts — imprint, privacy policy, cookie policy, terms — for Craft sites, doing
eRecht24's job without eRecht24's API. Distributed as `justinholtweb/craft-jack`. Lite (free) +
Pro editions. German-first, English throughout, with a CCPA layer.

## Tech Stack

- **PHP 8.2+**, **Craft CMS 5.3+**, Yii2, Twig
- No build step, no JavaScript runtime on the front end: a legal document is text. The CP edit
  screens carry a little inline `{% js %}`.
- No dependencies beyond Craft. HTML Purifier comes via Craft's own.

## Architecture

### Namespace & package

- Namespace: `justinholtweb\jack`
- Package: `justinholtweb/craft-jack`
- Handle: `jack`

### The load-bearing idea: a document is a projection

Nobody writes a privacy policy. They answer two questions — *who are you* (`services\Profiles`) and
*what do you use* (`services\Inventory`) — and `services\Compiler` projects those, through
`services\Library`, into a document. The compiler is **pure**: same profile, same inventory, same
library, same bytes.

Purity is not tidiness here, it is the feature. Because output is a function of input:

- **Drift is computable.** `Document::isStale()` compares the stored text with a fresh
  compilation. That single comparison catches all three causes at once — a reworded clause, a
  service added to the inventory, a changed company address — where a clause-version check would
  catch only the first.
- **A diff means something.** Two published versions differ only because an input differed.
- **CI can assert.** `jack/documents/check` fails a build over a policy that stopped being true.

### The second idea: a processor is data, not prose

`src/library/services.php` describes forty-one third parties (and five first-party entries) as *fields* — provider, address,
purpose, data, legal basis, transfer mechanism, cookies. `src/library/templates.php` holds one
templated paragraph per legal basis and per transfer mechanism. The compiler renders the same
template over every service.

Consequences worth keeping: adding Klarna is a data edit; forty services read in one voice; and
when Meta moves its Irish entity, one file changes and every site is right after a `composer
update`.

### Data model

- `elements\Document` — localized, `hasUris()`, `hasTitles()`. Split across two tables on purpose:
  `jack_documents` holds what a document **is** (type, handle, jurisdiction — same on every site),
  `jack_documentsites` holds what it currently **says** (compiled HTML, stamp, overrides — not).
  Conflating them is how you get a German privacy policy on an English page.
- `jack_profiles` — one row with `siteId = null` is the global profile; rows with a `siteId` are
  **override maps holding only the keys that differ**. Shaped for the common case rather than the
  tidy one: a German site and its English translation are one company, and making somebody type
  the address twice guarantees one copy is eventually wrong.
- `jack_services` — the inventory. Global, not per-site: the processors a business uses are a
  property of the business; the *language* differs between sites, not the recipients.
- `jack_versions` — append-only. The question a legal team eventually asks is "what did our privacy
  policy say on the day this customer signed up", and no amount of current-state modelling answers
  it.

### The two little languages

Clause bodies are not Twig — they are edited by people who are not developers, and a template
language in that position is an execution surface. Instead:

- `{{ dotted.key }}` interpolates a fact. Missing keys render as nothing and are **collected as
  warnings**, never left as literal braces in a published legal text.
- `[[ … ]]` is an optional run that vanishes whole if any placeholder inside it is empty. Nesting
  works, resolved innermost-first, which is how an address block carries an optional country line
  inside an optional paragraph.

Everything interpolated is escaped except three derived facts that are already HTML
(`Compiler::RAW_FACTS` — closed list, deliberately).

### Conditions

Declarative arrays, not expressions: `factTruthy`, `factFalsy`, `factFilled`, `factIn`,
`jurisdiction`, `language`, `service`, `category`, `anyTransfer`. Named conditions are ANDed, values
within one are ORed. That covers every clause in the library without giving a legal text an
expression evaluator.

### Services

- `library` — clauses, processor registry, sentence templates, fact schema. Read-only and
  date-stamped; `composer update` is how a site's texts get better.
- `profiles` — the facts, plus the **derived** ones (`controller.display`, `company.address`,
  `withdrawal.address`). Deriving in one place is what stops the imprint and the privacy policy
  disagreeing about the address.
- `inventory` — the single answer to "who are the recipients". Everything downstream is a
  projection of it.
- `compiler` — the pure function.
- `documents` — create, compile, publish, drift, versions, diff (LCS over lines).
- `detector` — reads templates, plugins and env for undeclared services, **with evidence**.
- `audit` — findings, worst first, each with a remedy.
- `renderer` — puts stored text on a page. Never recompiles on the way out. `body()` and
  `payload()` are the same published text for GraphQL and the JSON endpoint.
- `consentExport` — the inventory as a consent manager's config (Pro): `toss`, `klaro`,
  `cookieconsent`, `consent-mode`. Pure like the compiler (no dates, no visitor). See below.

### Consent manager configs (theme 3 consumer of Toss's contract)

Jack is still not a banner; it writes the banner's config from the inventory. Every target files
each service under Toss's canonical categories (`necessary`, `preferences`, `analytics`,
`marketing`): a `consentCategories` key naming the service wins, then **non-consent legal basis →
`necessary`**, then the inventory category via `ConsentExport::DEFAULT_CATEGORY_MAP` (`email` →
null = left out; cookie-free first-party entries are left out too).

- Toss is read through the plugins service only after `isPluginEnabled('toss')`, never by class
  name, so Jack has no dependency on it (`tossConsent()` is untyped on purpose). "Active" = Toss's
  `consent->isActive()`, the contract's test. When active: `inToss`/`tossCategory` per service from
  `consent->scripts($siteId)`, Toss's extra categories are allowed, and `consentModeMap()` (with
  `config/toss.php` overrides) replaces Jack's copy of the map.
- `TOSS_PRESETS` maps Jack keys to Toss preset keys (`meta_pixel` → `meta-pixel`). Update it when
  either registry gains a service.
- Wildcard cookie names become `helpers\CookiePattern`: a regex-source string in JSON, a real regex
  literal in `script()`. Only names matching `[A-Za-z0-9_.\-*]` become patterns, so no `/` can end
  the literal early. `script()` encodes with `JSON_HEX_TAG|AMP|APOS|QUOT`: custom service labels are
  editor-typed.
- Banner wording lives in `src/library/consent.php` (all prose lives under `src/library`).

### Headless delivery

GraphQL (`jackDocuments`/`jackDocument`/`jackDocumentCount`, scope `jackDocuments.all`) and
`/jack/documents[.json|/<handle>.json]` (`controllers\JsonController`, anonymous, CORS `*`,
`serveDocumentsJson`). Both are **published text of enabled documents only**:
`DocumentResolver` forces `status(enabled)->trashed(false)->published()` after applying arguments,
whatever the schema's inactive-elements scope says; `DocumentQuery::published()` is a correlated
`EXISTS` on `jack_documentsites` (not a filter on the outer-only join). No type exposes
`compiledHtml`, warnings, missing facts or overrides. `version`/`stamp` come from the latest
`jack_versions` row (`Documents::publishedVersion()`), not the per-site stamp, which describes the
last *compile*.

## Rules the code does not break

- **Compiling and publishing are separate acts.** Compiling is cheap and automatic; publishing is
  deliberate, writes an immutable row, and is what somebody can be asked to sign off.
- **The renderer never recompiles, and shows only published text.** `jack_documentsites` keeps
  `compiledHtml` (the draft, rewritten by every profile/inventory save) and `publishedHtml` (set
  only by `Documents::publish()`). Every way out — element URL, `craft.jack.render()`, the ref tag,
  `toc()` — reads `publishedHtml`, and a disabled document renders nothing. A half-typed address
  must not appear on a live privacy policy the moment it is saved, and a compile does not belong
  on every page view.
- **A link fact is an http(s) URL or it is missing.** Facts land in `href`s where escaping does
  nothing against `javascript:`. Save paths refuse other schemes and `Compiler::resolve()` drops
  them again before `[[ … ]]` runs are judged.
- **A lapsed licence never blanks a document.** Editions gate *creating* and *editing*; already
  compiled text keeps rendering. A privacy policy that 404s over billing is worse than anything it
  prevents.
- **Always-on inventory entries cannot be removed.** Server logs and session cookies happen whether
  or not they are written down; letting an editor delete them only produces a lie.
- **An empty document is reported, loudly.** A document with no sections is a heading with nothing
  under it, invisible on any screen that lists what exists. The compiler warns and the audit calls
  it critical.
- **A blank per-site override falls back; it does not erase.** Otherwise saving an English override
  with three fields wipes the address out of the English policy.
- **The detector never prints an environment value.** The report gets pasted into tickets.

## Traps found while building this

- **A phrase cannot live in two grammatical frames in an inflected language.** The service purposes
  are written for the accusative slot after `für`. Reusing the same words after "Unser berechtigtes
  Interesse:" produced *"den technisch fehlerfreien Betrieb"* — wrong, and invisible to an
  English-speaking author. The legitimate-interest clause now refers back to the purpose instead of
  repeating it, and the data sentence uses a colon so it works for singular and plural alike.
- **Craft joins `elements_sites` into the outer query while building it, but into the *subquery*
  after `beforePrepare()` has run.** A per-site join added in `beforePrepare()` therefore works in
  the outer query and dies in the subquery — `exists()` blows up with "Unknown column
  elements_sites.siteId". Join the outer query only, unless you also need to filter on it.
- **`ref` is stored by the base ElementQuery but applied by nobody.** Each element type has to
  handle it, or `{jack:privacy:render}` silently resolves to whichever document sorts first. The
  element also needs `getRef()` returning the handle, or the tag never matches at all.
- **`Plugin::getInstance()` is null in a console script** until something has asked the plugins
  service for the plugins. `Craft::$app->getPlugins()->loadPlugins()` first; the failure otherwise
  reads exactly like the plugin is not installed.
- **`$facts + ['company.city' => 'Hamburg']` changes nothing.** The union operator keeps the
  left-hand value for a key both sides have. `array_merge` — this cost a confusing failing check.
- **Project config writes from a console script are buffered.** A second `switchEdition()` in one
  script throws `StaleResourceException` without `saveModifiedConfigData()` after the first.
- **HTML Purifier will not create its own cache directory** and throws instead, turning the first
  override on a fresh install into a 500. `FileHelper::createDirectory()` first.
- **A registry label may be a language map.** Brands are plain strings ("Stripe" is "Stripe"), but
  the generic first-party entries are not, and every consumer that read `$definition['label']`
  directly broke. Resolution goes through `Library::serviceLabel()` and `ServiceEntry::label()`.
- **Anchors end up in URLs people paste into tickets.** `imprint.liabilityContent` became
  `jack-imprint-liability-content`, lower-cased and hyphenated, rather than a straight copy of the
  clause key.
- **Never nest a `<form>` in a CP template.** The inventory index carries two of its own, so the
  remove button posts with `Craft.sendActionRequest` instead of a `formsubmit` link.

- **Stored datetimes are naive UTC, and `new DateTime($row)` reads them as system time.** Every
  save moved `compiledAt`/`publishedAt` back by the site's offset, and Twig's `|datetime` on the raw
  string is wrong the same way. `Document` normalises them to `DateTime` in `init()` with
  `DateTimeHelper::toDateTime()`; version rows go through `VersionRecord::getCreated()`.
- **The nav badge runs on every CP request.** `Audit::problemCount()` is cached behind the
  `jack:audit` tag and leaves the detector out; anything that changes a document or the facts calls
  `Audit::invalidate()` (via `Documents::onFactsChanged()` and the element's save/delete hooks).
- **`actions/jack/documents/render` must not answer on its own.** It is anonymous, so it only
  renders when the URL manager matched that very document; otherwise it was a second, indexable
  copy that ignored `enableDocumentUrls`.
- **Craft offers `status`/`trashed`/`archived` GraphQL arguments only to a schema that may query
  inactive elements**, and silently drops a nested field a type lacks (`{ jackDocument { compiledHtml } }`
  answers `{}` with no error). Tests assert on absent keys, and test the resolver's overrides with
  an inactive-elements schema in its own process.
- **CP HTTP checks log in with an impersonation token**, not the login form: another builder's
  security plugin (Garrison) locks 127.0.0.1 out of `users/login` for minutes at a time, and the
  harness admin's password stops validating. `consent-export.php` creates throwaway users and an
  `users/impersonate-with-token` token, sent to the **site** URL (the CP bounces an anonymous
  request to its login screen before the token route runs) **with a User-Agent** — PHP's curl sends
  none, and Craft then refuses to log anybody in (`requireUserAgentAndIpForSession`).
- **The harness is shared.** Another session writing project config makes `switchEdition()` throw
  `StaleResourceException` even after `ProjectConfig::reset()`: the config version lives on the
  cached info row, so `Craft::$app->getIsInstalled(true)` first, then reset, then retry. A run that
  dies mid-way (harness stopped) leaves its imprint behind and the next run's "finds a document by
  type" fails until that orphan is deleted.

## Legal-content notes

These are content, not code, and are the things that age:

- `§ 5 TMG` became `§ 5 DDG` in May 2024. `TTDSG` became `TDDDG`.
- The EU ODR platform shut down on **20 July 2025**. Linking it in an imprint is now a stale
  mandatory disclosure, not a safe extra. `imprint.dispute` is at version 2.0.0 for this reason.
- LG München I, 20 January 2022, 3 O 17493/20 — dynamically embedded Google Fonts. The audit warns
  about it by name.
- The withdrawal instructions are statutory text (Anlage 1 and 2 to Art. 246a § 1 Abs. 2 EGBGB).
  Departing from the wording loses the safe harbour, so Jack fills blanks and changes nothing else.

When the library changes, bump the clause's own `version` **and** `Library::VERSION`. The clause
version is what tells a site its published document predates the change.

## Testing

No local PHP on this Mac. Everything runs inside the plugin-testing container:

```sh
cd ~/Sites/plugin-testing
ddev exec php /var/www/craft-jack/tests/integration/checks.php     # 188 checks
ddev exec php /var/www/craft-jack/tests/integration/consent-export.php  # consent configs + CP download security
ddev exec php /var/www/craft-jack/tests/integration/headless.php   # GraphQL (3 processes) + JSON endpoint
ddev exec bash /var/www/craft-jack/tests/integration/cp-smoke.sh   # every CP screen + front end
ddev exec bash -c 'find /var/www/craft-jack/src -name "*.php" -print0 | xargs -0 -n1 php -l'

# phpstan and ECS need PHP 8.4 (the lock file was resolved there); use the runner:
cd ~/Sites/phpstan-runner
ddev exec -d /sites/craft-jack composer phpstan
ddev exec -d /sites/craft-jack composer ecs
```

`checks.php` cleans up from a **shutdown handler**, not off the end of the script, and restores the
profile, the inventory and the edition it found. The CP smoke test seeds one document of every
type, walks its screens and the front-end URL of each, then deletes them; it needs a login —
`admin` / `claudepassword` locally, or `JACK_CP_USER` / `JACK_CP_PASS`.

The checks that matter most are the ones on **generated prose**: that German and English bodies
actually differ, that no bilingual `x / y` string reaches a document, that every legal basis and
transfer mechanism in the registry has a paragraph in both languages, and that every placeholder in
every clause resolves to a fact that exists. Those catch the failures that no assertion about
control flow would.

## Coding conventions

- `Craft::t('jack', '…')` for user-facing strings; `src/translations/en/jack.php` lists all of them
- **All prose lives under `src/library`** — clauses, processor descriptions and the sentence
  templates the compiler assembles. Legal wording in a PHP service is wording nobody who is not a
  developer can review.
- Business logic in services; controllers stay thin
- Never mark plugin settings `required`

## Release

Icon and mask follow the family standard (100×100, 22.44 radius tile, `#FEFEFE` glyph; tile
`#2E4374`; the marketing page uses the lighter `#5A75B8`, because the navy is unreadable as headline text on the dark page). Promos live in `promos/` (`./build.sh`, JPEG only). The marketing site is
`justinholt.com/plugins/craft-jack`, seeded from `~/Sites/justinholt/scripts/seed/plugin-pages/
craft-jack.json`; docs in `docs/*.md` are synced there with `pluginsite/docs/sync craft-jack`.
Pricing: Lite free, Pro $99 then $79/yr — the real prices live at id.craftcms.com.

See also `[[craft-plugin-gotchas]]` in the shared memory for family-wide traps.
