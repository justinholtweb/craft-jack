# Plugin Store promo images

Marketing images for the Jack listing on the Craft Plugin Store, rendered in the same theme as the
plugin's marketing page at
[justinholt.com/plugins/craft-jack](https://justinholt.com/plugins/craft-jack).

## Building

```bash
./build.sh          # all slides
./build.sh "2 5"    # just slides 2 and 5
```

Output lands in `out/` as `jack-promo-N.jpg`, 1920×1080 (rendered at 2× in headless Chrome, then
downsampled so the type stays crisp). **Promos are JPEG, never PNG**: Chrome can only write PNG, so
`build.sh` converts with `sips` at quality 90 and deletes the intermediate. Chrome sometimes exits
non-zero from its teardown watchdog *after* writing the screenshot, so the script checks for the
file rather than trusting the exit code.

## Slides

| # | Slide | Shows | Strings come from |
|---|-------|-------|-------------------|
| 1 | Cover: name, tagline, app icon, price | — | `README.md` intro |
| 2 | A document is a projection | Profile + Inventory + Library → Compiler → four documents, then Recompile → Publish → Visitors | `CLAUDE.md` "a document is a projection"; fact keys from `src/library/facts.php`; document names from `Document::typeLabels()`; "Recompile" and "Publish" from `documents/_edit.twig`; library date from `Library::VERSION` (`2026-10-03`); published-only rendering from `services/Renderer.php` |
| 3 | This list is the privacy policy | the inventory index | `inventory/_index.twig` ("Inventory", "Find undeclared services", "— always on", Consent / Contract / Legitimate interest, `transfer()\|upper` or "EEA", Edit / Remove, no Remove on always-on rows); pane headings from `Inventory::categories()`; labels and providers from `src/library/services.php` |
| 4 | A processor is data, not prose | the Mailchimp subsection of the privacy policy, German and English | `src/library/templates.php` (`service.intro`, `provider`, `data`, `basis.consent`, `transfer.dpf`, `links`, category headings) filled from the `mailchimp` entry in `services.php`, in the order `Compiler::renderService()` emits them; section heading from `privacy.services` in `clauses/generated.php` |
| 5 | Out of date, and here is which paragraph (Pro) | the documents index with one stale imprint, and the imprint's edit-screen note | columns Title / Type / URI / State / Compiled and "Current" / "Out of date" from `elements/Document.php`; "Legal documents", "New document" from `documents/_index.twig`; "Out of date.", the note text, "Changed:", "Recompile now" from `documents/_edit.twig`; `imprint.dispute` (2.0.0) and its heading "Verbraucherstreitbeilegung" from `clauses/imprint.php` |
| 6 | Somebody embedded a video (Pro) | the Undeclared services screen | `inventory/_detect.twig` (intro, "{n} services are not declared", "Add the ticked services", "Found and already declared", "— {n} places"); raw category keys, `location:line` and the 140-char excerpt cut to 90 from `services/Detector.php`; env evidence prints `set`, never the value |
| 7 | Worst first, each with a remedy | the Audit screen | `audit/_index.twig` (critical / to fix / worth knowing, "Fix:"); finding titles, details, remedies and citations verbatim from `services/Audit.php` |
| 8 | Two editions | Lite and Pro, with prices, and the lapsed-licence rule | `README.md` "Editions" table and the paragraph under it; "in one language" from the README intro; the audit and Twig API are ungated in `models/Edition.php` |

The mocks have to agree with the plugin and with each other. It is one German site throughout: the
inventory on slide 3 (sessions, server logs, contact forms, Stripe, Mailchimp, Google Fonts,
Google Analytics 4) is the inventory the audit on slide 7 counts — three consent services, Google
Fonts warned about by name. The detector on slide 6 finds YouTube (two templates, most evidence
first), Google Tag Manager (`templates/_layouts/base.twig:13`, matched by the `gtm.js` pattern)
and Sentry (`SENTRY_DSN`), and those three are the critical finding on slide 7. The stale
"Impressum" on slide 5 is the warning on slide 7; it predates `imprint.dispute` 2.0.0, the ODR
platform shutdown. Excerpts on slide 6 were computed with the detector's own rule (40 characters
before the match, whitespace collapsed, 90 shown) and are truncated with an ellipsis for width.

Slide 4 uses Mailchimp rather than Stripe on purpose: registry `providerAddress` values are written
in German ("Dublin 2, Irland") and are not localized, so Stripe's English paragraph would print
"Irland". Mailchimp's address ends in "USA", which reads correctly in both languages.

Change a label, a finding, a clause version or the editions table and change the slide.

## Palette

Accent `#2E4374`, the legal-ink navy of the icon tile — about 2:1 against the charcoal background,
so it is only ever a fill (the price badge, the Pro pill, the Pro column). `#9FB4E6` is the light
accent for small things on the dark background (ticks, rules, inline code, the Pro price, pill
rims): 9.2:1 on `#0f0f1a`. Gradient headline text starts at `#6F88C4` (5.4:1) for the same reason.
Plus the family's `#ffd166` gold, used for the compiler and the publish step. Jersey 20 for
display, Inter for body.

## The watermark is the glyph alone

`assets/watermark.svg` is the path from `../src/icon-mask.svg` (two raised hands) filled white,
with no tile. The app icon is never used as a watermark. It is anchored bottom-right, runs off the
edge, and is switched off on the cover, where it would sit behind the icon like a shadow.

`assets/icon.svg` is a straight copy of `../src/icon.svg`. Re-copy it (`cp`) whenever the icon
changes.

`fonts.css` is generated by `build.sh` and gitignored; don't edit it.
