# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

GJClasses is a small PHP (>= 8.2) Composer library (`chetcuti/gjclasses`) of utility classes Greg uses across his own projects: notifications (email and push), logging, currency conversion, Auth0 login, HTTP header checks, pinging, CSV export, and so on. Consumers install it with `composer require chetcuti/gjclasses` and call `new GJClasses\<Class>()`.

There is no build step, test suite, linter, or CI config in the repo. Changes are checked by running the scripts in `examples/` by hand.

## Commands

```bash
composer install                 # install dependencies into vendor/
php examples/<name>.php          # exercise one class by hand
php -l src/GJClasses/<Class>.php # syntax-check a class
```

The examples need `_includes/config.inc.php` to exist. It's local and git-excluded, and it must `define()` the `GJC_*` constants the classes being exercised read (see Architecture below). `_includes/config.SAMPLE.inc.php` is an empty local placeholder, so it's no help as a template. Many examples send real email or push notifications, or write to a real database, so check what one does before running it.

## Architecture

- **One class per file** in `src/GJClasses/`, PSR-4 autoloaded under the `GJClasses\` namespace. Classes are plain, with no interfaces or base classes, and mostly take everything as method or constructor arguments.
- **Configuration is global constants, not injected.** Classes read `GJC_*` constants that the consuming project must `define()` before using them:
  - `Database` (a PDO singleton via `Database::getInstance()`): `GJC_DB_HOSTNAME`, `GJC_DB_NAME`, `GJC_DB_USERNAME`, `GJC_DB_PASSWORD`.
  - `Mail`: `GJC_USE_SMTP` (`1` sends through `MailSmtp`/PHPMailer, anything else through `MailPhp`/`mail()`), plus the `GJC_SMTP_*` and `GJC_EMAIL_ENCODING_TYPE` constants for `MailSmtp`.
  - `Auth0`: `GJC_AUTH0_*` constants, including `GJC_AUTH0_COOKIE_SECRET` (required by auth0-php 8, which encrypts its session cookies with it).
- **Dispatcher classes route by string:**
  - `Notify` (all work happens in its constructor) sends by `$method` = `all`, `email`, or `push`, using `Mail` and `Push`.
  - `Push` routes by provider name (`join`, `pushbullet`, `pushover`, `telegram`) to the provider class, each with `push()` choosing between `pushNote()` and `pushUrl()` depending on whether a URL was given. Adding a provider means a new class plus a branch in `Push::push()`.
  - `PersonalApi` is a stand-alone push option, deliberately not routed through `Push`.
- **`Log`** writes PSR-3-style levels (`emergency()` through `debug()`) to a MySQL `log` table (schema in `tables/Log.sql`) through `Database`, taking the user ID from `$_SESSION['s_user_id']` and the URL from `$_SERVER['REQUEST_URI']`.
- **`_includes/init.inc.php`** defines `GJC_DIR_ROOT`, `GJC_DIR_INC`, and `GJC_DIR_TEMP` for the examples only; the library itself doesn't depend on it.

## Local-Only Files

`.git/info/exclude` keeps these out of git: `.data/` (notes, including `SETUP.txt`, which lists the projects that use each class), `.dev/` (scratch test script), `.idea/`, `vendor/`, `_includes/config.inc.php`, `_includes/config.SAMPLE.inc.php`, `TODO.md`, `DONE.md`, and the three `email_templates/TemplateName.*` files (`.html`, `.txt`, and `.subject`). Any other file added under `email_templates/` isn't excluded.

Per `.data/SETUP.txt`, `Remote.php` has a copy in another project that is kept in sync by hand. Mention it to Greg when `Remote.php` changes, but don't edit the other project from here.

## Releases and Changelog

- The changelog is `CHANGELOG` (plain text, no `.md`), newest first, in the form `v0.53.0    2024-08-05` followed by `- ` bullets in past tense. Changes that aren't released yet go under an `Unreleased` heading at the top, which becomes the `v0.N.0    YYYY-MM-DD` heading at the next release.
- `CHANGELOG` is for people using the library, so it leaves out changes to Claude's own files (`CLAUDE.md`, `CLAUDE.local.md`, `TODO.md`, `DONE.md`, `ARCHIVE.md`, and `.claude/`). Their commit messages describe them instead (Greg's choice, 2026-10-18).
- Each release bumps the minor version (`v0.N.0`). Usually the code change is one commit, then a separate `Add v0.N.0 to CHANGELOG` commit, with a matching `v0.N.0` git tag (Packagist reads the tags). Tags are GPG-signed annotated tags (`git tag -s -u 87C607E479D40432 -m v0.N.0 v0.N.0`), so GitHub shows them as Verified; commits stay unsigned.
- Work happens on `dev`; `main` is the main branch. The only remote is `public` (`git@github.com:gregdotca/gjclasses.git`), with both `main` and `dev`.
