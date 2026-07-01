# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-07-01

A rework of the HTTP API into a **hybrid REST-RPC** design with a
machine-readable **OpenAPI 3.1** contract, an interactive **Scalar** docs page,
and **generated TypeScript types** consumed by the frontend. This is a breaking
release: nearly every endpoint's path, verb, and/or status code changed.

### Added

- **OpenAPI 3.1 specification** generated from PHP attributes with
  [`zircote/swagger-php`](https://github.com/zircote/swagger-php) (`composer docs`
  → committed `backend/openapi.json`). Every controller action and structure class
  is annotated; a `bearerAuth` security scheme documents the token model.
- **Interactive API reference** (Scalar) at **`GET /api/docs`**, with the raw
  document at **`GET /api/openapi.json`**.
- **`GET /api/version`** — reports the running application and API versions.
- **Generated TypeScript types** for the frontend via
  [`openapi-typescript`](https://github.com/openapi-ts/openapi-typescript)
  (`npm run gen:types` → `frontend/src/types/api.generated.ts`), surfaced through
  ergonomic aliases in `frontend/src/types/index.ts` and a central
  `frontend/src/api/endpoints.ts` route registry.
- **App version in the footer** (injected at build time from `package.json`),
  linking to the API docs.
- CI freshness gates: the committed `openapi.json` and `api.generated.ts` must be
  regenerated and in sync (`OpenApiSpecTest` + `git diff --exit-code`).

### Changed

- **REST-RPC route surface.** Reads return `200`, creates `201` (with the created
  resource), updates `200` (with the updated resource), and deletes `204`. Routes stay
  clean and path-based (no query strings). Notable moves:
  - Listings keep path-based pagination: **`GET /media/page/{page}[/{per_page}]`**,
    **`/media/untagged/{page}[/{per_page}]`**, **`/media/with-tags/{tag_list}/{page}[/{per_page}]`**
    (now returning the `MediaPage` envelope with proper status codes).
  - Tags are a proper resource: **`POST/PUT/DELETE /tags[/{id}]`** (was `/tags/add`,
    `/tags/edit/{id}`, `/tags/delete`); migrate-then-delete is
    **`DELETE /tags/{id}/migrate-to/{target_id}`**, and **`POST /tags/{id}/migrate`** moves usages.
  - Categories and implications are top-level resources (**`/tag-categories`**,
    **`/tag-implications`**), and media-scoped tagging nests under media
    (**`GET/PATCH/DELETE /media/{id}/tags[/{tag_id}]`**,
    **`POST /media/{id}/danbooru-tags`**).
  - Upload is the media create: **`POST /media`** (multipart). Bulk delete is
    **`POST /media/bulk-delete`**; Danbooru rules split into
    **`/danbooru/category-mappings`** and **`/danbooru/tag-mappings`**.
  - `GET /media/total` (bare int) → **`GET /media/count`** (`{ count }`).
- Controllers were reorganized for cohesion: dedicated `AuthController`,
  `SystemController`, `TagCategoryController`, and `TagImplicationController`; the
  media–tag relationship and bulk delete moved onto `MediaController`.
- The frontend consumes generated types end-to-end; the hand-written `Tag` /
  `TagCategory` / `MediaItem` interfaces in `stores/gallery.ts` were removed.

### Removed

- The action-in-URL endpoints (`/tags/add`, `/tags/edit/{id}`, `/tags/delete`,
  `/tags/migrate`, `/tags/for/media/{id}`, `/tags/media/add|remove`,
  `/tags/danbooru-fetch`, `/tags/categories/*`, `/tags/implications/*`,
  `/danbooru/*-map/*`, `/upload/media`, `/duplicates/media`, `/duplicates/dismiss`)
  and the paginated `/media/page`, `/media/with-tags`, `/media/untagged` routes.
- The hand-written frontend domain interfaces (now generated).

### Security

- The bearer-token model is unchanged: all `GET`s are public; state-changing
  methods require a token, save for a small public allowlist (media tagging and
  the batched `POST /media/by-ids` read), now matched by an exact
  `(method, path pattern)` rule so a public sub-route can't widen a protected one.

## [2.0.2] - 2026-06-30

### Added
- **PHPStan static analysis** at level 8 (`backend/phpstan.neon`, `composer analyse`),
  with the `phpstan-phpunit` extension and a CI gate. The whole backend is clean at level 8.
- **`backend/API.md`** — a full HTTP API endpoint reference (auth model, response
  envelope, rate limiting, and every endpoint grouped by resource).
- Per-action route docblocks (`VERB /path`) on every controller, and `#[\NoDiscard]`
  (PHP 8.5) on critical repository mutators whose return must be checked.

### Changed
- Collapsed the pass-through `Collection` layer into unified repositories
  (`Gallery\Repository\TagRepository`, `TagCategoryRepository`, `DanbooruRulesRepository`);
  `MediaCollection` keeps its real file/thumbnail behavior.
- Controllers no longer hold the DI container (service-locator removed) — collaborators,
  including a now lazily-initialized `DanbooruTagger`, are constructor-injected directly.
- Structure classes (`Media`, `Tag`, `TagCategory`) now use PHP 8.4 **asymmetric
  visibility** (`public private(set)`), dropping the getter boilerplate while keeping
  reads public and writes encapsulated.
- Cache groups are now a `CacheGroup` enum (was bare strings); request parameters go
  through typed `intParam`/`stringParam`/`parsedBody` helpers; the `sanitizeTagName`
  pipeline uses the PHP 8.5 pipe operator.

### Fixed
- `TagRepository::getOrCreate` no longer relies on an implicitly-defined `$name`
  variable on the no-prefix path (surfaced by static analysis; now always initialized).

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

## Pre-2.0.0 — Legacy (unversioned)

Before the 2.0.0 rewrite the gallery ran for roughly three years (first commit
**2023-01-02**) as a **jQuery + server-rendered PHP** app. It was never tagged
under semantic versioning; this section is a retrospective summary reconstructed
from the git history, not a formal release.

- **Frontend:** static `index.html` + `js/gallery.js` (jQuery), Bulma styling with
  a custom extended color palette, FontAwesome icons (CDN), and a Lightbox for
  full-size viewing. Async `fetch`/AJAX calls to the PHP API drove the grid.
- **Backend:** PHP with **separate Image and Video** class hierarchies
  (`Image`/`Video`, `ImageCollection`/`VideoCollection`, `ImageStorage`/`VideoStorage`)
  over SQLite; thumbnails were generated via `php-ffmpeg/php-ffmpeg`. The API was
  migrated onto **Slim** partway through this era and PSR-4 autoloading was adopted.
- **Features:** a basic browsable image/video gallery, an initial **tagging system**
  with a dedicated tag page, sorting by tag name, a sticky footer with copyright/
  disclaimer, and a **cron** ingest script for importing media (including videos).

[Unreleased]: https://github.com/jwill89/simple-image-gallery/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/jwill89/simple-image-gallery/compare/v2.0.2...v3.0.0
[2.0.2]: https://github.com/jwill89/simple-image-gallery/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/jwill89/simple-image-gallery/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/jwill89/simple-image-gallery/releases/tag/v2.0.0
