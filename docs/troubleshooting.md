---
title: Troubleshooting
slug: troubleshooting
order: 50
summary: Changes that don't reach the site, out-of-date documents, empty pages, missing details, 404s, undeclared services, Pro features that vanished, and failing console commands.
---

## Start with the audit

Open **Jack → Audit**. It lists everything wrong with the site's legal texts, worst first, each
with a **Fix:**. Most of the problems on this page show up there before anyone notices them on the
site. From the command line on Pro, `php craft jack/documents/check` prints the same list.

## My change isn't on the site

You changed the profile or the inventory, the edit screen shows the new text, and the live page
still shows the old one. The document has been compiled but not published.

Compiling updates the draft, which is what the control panel shows. Only publishing changes what
visitors see. This is deliberate: a half-typed address saved on the profile mustn't appear on a live
imprint.

1. Open the document. In the sidebar, compare **Compiled** with **Published**. A later
   **Compiled** date means the site is showing an older text.
2. Read the draft.
3. Click **Publish** beside **Save** and confirm.

If there is no **Publish** button, you don't have the **Publish documents** permission.
Ask whoever signs off your legal texts, or an admin.

On a multi-site install, each site's document is published separately. Publishing the German
privacy policy doesn't publish the English one.

## The document is out of date

The edit screen says **Out of date.** and the audit lists *“…” is out of date*. The draft no longer
matches what Jack would write from the current profile, inventory and library.

- **After a `composer update`.** The update brought reworded clauses. Nothing recompiles on an
  update, so every affected document shows as out of date. Press **Recompile now**, or run
  `php craft jack/documents/compile` on Pro.
- **With Recompile automatically off.** Profile and inventory changes don't touch the drafts.
  That's what the setting is for. Press **Recompile now** once you've reviewed the change.
- **With Recompile automatically on.** A save should have recompiled already. If it didn't, check
  Craft's logs under the `jack` category. A document that fails to compile is logged and skipped
  so the others still update.

On Pro, the notice lists each clause that's **Changed**, **New** or **No longer applies**. On Lite,
you're told the document is out of date but not which paragraphs.

Recompiling only updates the draft. Publish afterwards to put the new text on the site.

## The page is empty

The document's URL loads, but there's nothing under the heading, or `craft.jack.render()` prints
nothing.

- **It's never been published.** A new document has a draft and no published text. Publish it.
- **It's disabled.** Check **Enabled** in the sidebar. A disabled document renders nothing.
- **It compiles to nothing.** The audit says *“…” compiles to nothing*. No clause applies with the
  facts Jack has. The usual cause is a terms document on a site whose profile says it doesn't sell
  online. Answer the profile questions the document depends on, or delete it.
- **On Lite, it's not on the primary site.** Lite compiles for the primary site only.

Jack logs a warning under the `jack` category each time it's asked to render a document that has no
published text or is disabled.

## The document has gaps

The edit screen says **This document is incomplete.** and lists the facts it needs. The clauses
that need them are still in the document, with the detail missing.

Press **Fill these in**, fill in the listed fields on the profile, then recompile and publish. On a
multi-site install, check the site's profile overrides as well as the global profile.

## The document's URL returns a 404

- **Give documents their own URLs** is off in the settings.
- The document is disabled.
- The slug isn't what you expected. Titles and slugs default to the site's language, so an English
  site's privacy policy is at `/privacy-policy`, not `/datenschutz`. Check **Slug** on the edit
  screen.

## The URL field won't save

URL facts on the profile, and **Privacy policy URL** and **Opt-out URL** on an inventory entry,
accept only `http://` and `https://` links. Paste the full address, including the scheme.

## I can't remove a service from the inventory

**Server log files** and **Session and security cookies** are always on. They describe something
every Craft site does whether or not it's written down, so they can't be removed or switched off.

## The privacy policy is missing a service

The site uses something the inventory doesn't list. On Pro, press **Find undeclared services** on
**Jack → Inventory**. It reads your templates, plugins and environment variable names and shows
the file and line where each service was found.

If it finds nothing but you know a service is there, it's probably loaded by a tag manager or a
third-party script at runtime. Add your build folder to **Directories to scan**, or add the service
by hand.

## A Pro feature disappeared

The edition is stored in project config. If a deploy applied a `project.yaml` that still says Lite,
Pro features switch off without an error. Commit the edition change with the rest of your config.

Already published documents keep rendering whatever the edition, including document types Lite
can't create. Only creating and editing are gated by edition.

## Console commands fail on Lite

On Lite, every `jack/*` command prints *The console commands are a Jack Pro feature.* and exits
non-zero. That's deliberate: a CI check that silently passed on Lite would be worse than one that
fails. Switch to Pro, or take the step out of your pipeline.

## `jack/documents/check` fails the build

It exits non-zero on any critical finding, and on to-fix findings with `--strict`. The output names
each finding and its fix. Fix them, or run without `--strict` while you work through warnings.

## The page shows an ODR platform link

It doesn't come from Jack. The EU's online dispute resolution platform shut down on 20 July 2025,
and Jack's imprint and terms clauses don't link it. If you see one, it's in a section you rewrote or
added, or in your own template. Remove it: a stale mandatory link is itself a liability.
