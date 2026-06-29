# Contributing to Gallery

Thanks for your interest in improving Gallery! This guide covers local setup, the
coding standards CI enforces, and the pull-request flow. For the deeper architecture,
data model, and conventions, read **[AGENTS.md](AGENTS.md)** first.

## Project layout

```
backend/    PHP 8.5 + Slim 4 API, SQLite, Phinx migrations, CLI scripts
frontend/   Vue 3 + TypeScript SPA (Vite, Pinia, Vue Router, Bulma)
scripts/    Dev-only deploy script (not part of the app; gitignored)
```

The droplet webroot is flat; the deploy flattens `backend/` + `frontend/dist` onto
it. See [AGENTS.md §2](AGENTS.md) for the full tree.

## Prerequisites

- **PHP 8.5+** with `pdo_sqlite`, `curl`, and **GD** (WebP, ideally AVIF).
- **Composer** and **Node.js** (with npm).
- **`ffmpeg`** on your `PATH` (video/animated thumbnails).

## Local setup

**Backend** (run from `backend/`):

```bash
cd backend
composer install
cp .env.example .env     # then fill in real values — see AGENTS.md §3 for what each key does
php db/setup.php         # creates db/gallery.db and runs migrations
```

Serve `backend/` with Apache + `mod_rewrite` (it is the app root). At minimum set
`GALLERY_ADMIN_PASSWORD` in `backend/.env`, or admin login stays disabled.

**Frontend** (run from `frontend/`):

```bash
cd frontend
npm install
npm run dev             # Vite dev server on :5173, proxies /api and /media to http://localhost
```

## Coding standards

CI runs these checks on every push and pull request — please run them locally first.

**PHP — PSR-12** (enforced by `phpcs`; config in `backend/phpcs.xml.dist`):

```bash
cd backend
composer lint           # phpcs: must report 0 errors (line-length warnings are allowed)
composer lint:fix       # phpcbf: auto-fix most violations
```

Keep SQL in the `Storage` layer, return responses via `success()` / `error()`, and
follow the `Controller → Collection → Storage` flow described in AGENTS.md.

**Frontend — TypeScript + Vue 3** (`<script setup lang="ts">`, Composition API only):

```bash
cd frontend
npm run build           # vue-tsc type-check + vite build; must succeed with no type errors
```

Route API calls through `composables/useApi.ts` (never raw `fetch`), and keep the
frontend/backend mirror lists (tag colors, `MediaItem` shape) in sync.

## Tests

Backend (PHPUnit 13, from `backend/`):

```bash
composer test            # run the suite
composer test:coverage   # with a coverage report (needs pcov or xdebug)
```

Tests live in `backend/tests/Unit/` (organized by layer) and run against an in-memory
SQLite database built from `backend/tests/Support/schema.sql` — a flattened snapshot of
the Phinx schema. **If you add or change a migration, regenerate that snapshot** (migrate a
scratch SQLite file and dump `SELECT sql FROM sqlite_master`), preserving the manual edits
documented at the top of the file.

Code is wired for testability via **constructor dependency injection**: Storage/Collection
classes (and `RateLimiter`) receive their `PDO`/dependencies through the constructor, and the
PHP-DI container in `backend/api/dependencies.php` supplies them in production. Keep new
DB-bound classes injectable rather than calling `DatabaseConnection::getInstance()` directly.

Frontend (Vitest, from `frontend/`):

```bash
npm run test             # run once
npm run test:watch       # watch mode
npm run test:coverage    # with a coverage report
```

Specs live in `frontend/src/__tests__/*.spec.ts` (happy-dom).

## Database changes

The schema lives in Phinx migrations (`backend/db/migrations/`) — the single source
of truth. **Never hand-edit `db/setup.php` to change the schema.** Add a migration:

```bash
cd backend
php vendor/bin/phinx create AddSomethingUseful
php vendor/bin/phinx migrate
```

## Pull-request flow

1. Branch off `master`.
2. Make your change; run the lint, test, and build checks above until they pass.
3. Add a bullet under the **`## [Unreleased]`** heading in
   [CHANGELOG.md](CHANGELOG.md), in the right group (Added / Changed / Fixed /
   Removed / Security). We follow [Keep a Changelog](https://keepachangelog.com/)
   and [Semantic Versioning](https://semver.org/).
4. Open a PR against `master`. CI (lint, tests, build) must be green.

## Security & secrets

- **Never commit secrets.** `backend/.env` (admin password, Danbooru API key) and
  `scripts/deploy.ps1` (host IP, SSH key path) are gitignored — keep them that way.
- The SSH private key lives **outside** the repo; only its path is referenced.
- Report a security concern privately to the maintainer rather than opening a public
  issue.

## Releasing (maintainers)

1. Move the `## [Unreleased]` entries under a new `## [x.y.z] - YYYY-MM-DD` heading
   and update the compare links at the bottom of `CHANGELOG.md`.
2. Bump `version` in `frontend/package.json`.
3. Tag the release `vX.Y.Z` and deploy with `scripts/deploy.ps1`.
