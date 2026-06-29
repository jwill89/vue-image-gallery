# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-06-28

### Added
- Automated test suites: **PHPUnit 13** for the backend (`backend/tests/`, run against an
  in-memory SQLite database) and **Vitest** for the frontend (`frontend/src/__tests__/`,
  happy-dom). Both run in CI with coverage reporting.

### Changed
- Refactored the backend Storage/Collection layers, `RateLimiter`, `DanbooruTagger`, and
  `DuplicateScanner` to **constructor dependency injection**. The PHP-DI container
  (`backend/api/dependencies.php`) now supplies `PDO` and autowires the graph for both the API
  and the CLI scripts, making the data layer unit-testable without touching the live database.

### Fixed
- `AbstractStructure` array construction (e.g. `new Media([...])`) now works — properties are
  assigned via reflection, fixing a latent error when the base class set a subclass's private
  properties.

## [2.0.0] - 2026-06-28

A complete rewrite of Gallery as a **Vue 3 + TypeScript single-page app** on a
**Slim 4 / PHP 8.5** API, replacing the original jQuery + server-rendered version.
The data model was unified, a full tagging system was added, and the project was
reorganized into a clean `backend/` + `frontend/` split. The previous jQuery app
was never formally versioned; this is the first release under semantic versioning.

### Added

- **Vue 3 + TypeScript SPA** frontend (Composition API with `<script setup>`,
  Pinia stores, Vue Router, Vite build, Bulma styling).
- **Unified media model** — images, animated GIFs, and videos live in one `media`
  table and one browsable grid, with both pagination and infinite-scroll modes.
- **Tag system** — categories (with colors, shortcodes, descriptions, sort order)
  and **tag implications** (applying tag A transitively auto-applies its implied tags).
- **Tag search** with include/exclude filters (`+tag` / `-tag`).
- **Danbooru integration** — auto-import tags by MD5 hash with an IQDB
  visual-similarity fallback; database-driven, UI-editable import rules
  (category and tag-name mappings).
- **Duplicate detection** — perceptual hashing pipeline (LSH candidate generation →
  Hamming-distance filter → SSIM verification) with a review/dismiss UI.
- **Favorites**, persisted client-side in `localStorage`.
- **Admin authentication** — shared password exchanged for a 24h bearer token;
  gates uploads and deletes.
- **Multi-file uploads** through the SPA.
- **Media metadata** (width, height, duration, file size) extracted at ingest and
  backfillable for existing rows.
- **Open Graph / Twitter Card** meta-tag injection via the `index.php` front
  controller for link previews.
- **Service worker** — cache-first thumbnails/static assets, network-first media
  lists, plus adjacent-page thumbnail prefetch.
- **Server-side response cache** (file-based, group-invalidated) for hot GET endpoints.
- **Rate limiting** — global per-IP sliding window plus a stricter login bucket.
- **Phinx migrations** as the single source of truth for the schema.
- **PowerShell deploy script** — build → tarball → upload → `composer install` →
  migrate, with an automatic rollback snapshot.
- **PSR-12 linting** (`phpcs`) and a **GitHub Actions CI** pipeline (PHP lint +
  frontend type-check/build).
- Project docs: this `CHANGELOG.md`, `CONTRIBUTING.md`, and an expanded `AGENTS.md`.

### Changed

- **Repository layout** reorganized into `backend/` (PHP app) and `frontend/`
  (Vue/Vite), with a dev-only `scripts/` for deployment. The droplet webroot stays
  flat; the deploy flattens `backend/` + `frontend/dist` onto it.
- **Backend architecture** is now layered: `Controller → Collection → Storage →
  DatabaseConnection (PDO)`, with all SQL confined to the Storage layer.
- Upgraded to **PHP 8.5**, **Slim 4** with the PHP-DI bridge, and **SQLite in WAL mode**.
- `composer.lock` and `frontend/package-lock.json` are now committed for
  reproducible installs.

### Removed

- The legacy **jQuery frontend** (`index.html`, `js/gallery.js`, `css/`).
- The split **Image/Video** classes (`ImageCollection`, `VideoCollection`,
  `ImageStorage`, `VideoStorage`, `Image`, `Video`), unified into `Media*`.
- The `php-ffmpeg/php-ffmpeg` dependency — thumbnails now shell out to `ffmpeg` directly.

### Security

- All state-changing API routes require a bearer token, except a deliberate public
  allowlist (anyone may add/remove tags; `POST /media/by-ids` is a batched read).
- Login is refused entirely when `GALLERY_ADMIN_PASSWORD` is unset (the insecure
  `changeme` default can never grant access); the password is compared with
  `hash_equals()` and login attempts are throttled per IP.
- CSRF protection via `Origin`/`Referer` allowlist checks on state-changing requests.
- `.htaccess` blocks direct web access to `.env`, the SQLite database, logs, cache,
  `vendor/`, and raw source.

[Unreleased]: https://github.com/jwill89/simple-image-gallery/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/jwill89/simple-image-gallery/releases/tag/v2.0.0
