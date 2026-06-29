# AGENTS.md

Operational guide for AI coding agents (and humans) working in this repository.
Read this before making changes. It describes the architecture, conventions,
data model, commands, and the non-obvious gotchas that will bite you.

---

## 1. What this project is

A self-hosted **personal media gallery** (images + videos) with a Danbooru-style
tagging system. It is a single-user/small-trusted-group app, not a multi-tenant
SaaS. Key features:

- Unified media browsing (images, GIFs, videos) with pagination and infinite scroll.
- Hierarchical **tag system** with categories, colors, shortcodes, and **implications**
  (tag A implies tag B → applying A auto-applies B, transitively).
- **Tag-based search** with include/exclude (`+tag`, `-tag`) filters.
- **Danbooru API integration**: auto-import tags by MD5 hash, with IQDB visual-similarity
  fallback. Import rules (category + tag-name mappings) are DB-driven and editable in the UI.
- **Duplicate detection** via perceptual hashing (LSH candidate generation → Hamming
  distance → SSIM verification).
- **Favorites** (client-side, `localStorage`).
- Admin-gated **uploads** and **deletes** via a single shared password → bearer token.
- A PWA-ish **service worker** for thumbnail/API caching and adjacent-page prefetch.

### Tech stack

| Layer | Tech |
|------|------|
| Backend | PHP **8.5** (typed constants, `match`, enums-style consts), [Slim 4](https://www.slimframework.com/) + PHP-DI bridge |
| Database | **SQLite** (single file at `db/gallery.db`), WAL mode |
| Migrations | [Phinx](https://phinx.org/) (`db/migrations/`) — the single source of truth for the schema |
| Image/video tooling | `ffmpeg` (shelled out via `exec`), PHP GD, `jenssegers/imagehash` |
| Logging | Monolog (rotating file, 14 days) |
| Frontend | **Vue 3** (`<script setup>` + Composition API), TypeScript, [Pinia](https://pinia.vuejs.org/), Vue Router |
| Build | **Vite** (outputs to `frontend/dist`) |
| Styling | **Bulma 1.x** + custom `style.css` (extended color palette), FontAwesome (kit script) |
| Tests | **PHPUnit 13** (`backend/tests/`, in-memory SQLite) + **Vitest** (`frontend/src/__tests__/`, happy-dom) |

---

## 2. Repository layout

The repo is split into **`backend/`** (PHP) and **`frontend/`** (Vue), with a dev-only
**`scripts/`** at the root. The droplet's webroot is **flat** (project root ===
DocumentRoot): at deploy time `scripts/deploy.ps1` flattens `backend/*` and the built
`frontend/dist` into one payload that mirrors the live layout. All PHP resolves paths
via `__DIR__`/`chdir(__DIR__ . '/..')`, so `backend/` *is* the app root in both places.

```
/
├── backend/                   # PHP app — flattened onto the droplet webroot at deploy time
│   ├── index.php              # SPA front controller: injects OG/Twitter meta + FA kit, serves dist/index.html
│   ├── .htaccess              # Apache rewrites: dist assets → real files → /api → index.php fallback
│   ├── composer.json          # PSR-4: Gallery\ → includes/Gallery, Routes\ → api/Routes
│   ├── phinx.php              # Phinx config (sqlite, db/gallery.db)
│   ├── phpunit.xml            # PHPUnit 13 config (tests/Unit; coverage source)
│   ├── phpcs.xml.dist         # PSR-12 ruleset for phpcs / phpcbf
│   │
│   ├── api/
│   │   ├── .htaccess          # Routes everything to api/index.php
│   │   ├── index.php          # Slim bootstrap: middleware stack + ALL route definitions
│   │   ├── dependencies.php   # PHP-DI definitions: provides PDO, autowires Storage→Collection→Controller
│   │   └── Routes/Internal/   # Controllers (namespace Routes\Internal)
│   │       ├── AbstractController.php   # success()/error()/cachedSuccess()/invalidateCache()/resolveTagIds()
│   │       ├── MediaController.php      # /media/*  (auth-protected for writes; /by-ids is public)
│   │       ├── TagController.php        # /tags/*   (auth-protected for writes)
│   │       ├── DanbooruController.php   # /danbooru/* import-rule CRUD (auth)
│   │       ├── UploadController.php     # /upload/media (auth)
│   │       └── DuplicatesController.php # /duplicates/* (auth)
│   │
│   ├── includes/Gallery/      # Domain layer (namespace Gallery\…)
│   │   ├── Core/              # Configuration, DatabaseConnection, Logger, RateLimiter,
│   │   │                      # ResponseCache, DanbooruTagger, DuplicateScanner, MediaThumbnail
│   │   ├── Collection/        # "Service"/use-case layer (MediaCollection, TagCollection, …)
│   │   ├── Storage/           # SQL data-access layer (MediaStorage, TagStorage, …)
│   │   └── Structure/         # Plain data objects (Media, Tag, TagCategory) — JsonSerializable
│   │
│   ├── db/
│   │   ├── setup.php          # CLI bootstrap: ensures db/gallery.db exists, then runs Phinx migrations
│   │   ├── migrations/        # Phinx migrations (the schema source of truth)
│   │   └── gallery.db         # SQLite database (gitignored)
│   │
│   ├── scripts/               # CLI cron/maintenance scripts (gitignored). cron.php = ingest pipeline,
│   │                          # dupes.php = duplicate scan, tag_imports.php, regenerate-*.php
│   │
│   ├── tests/                 # PHPUnit 13 suite
│   │   ├── Support/           # DatabaseTestCase + schema.sql (fresh in-memory SQLite per test)
│   │   └── Unit/              # by layer: Structure/, Core/, Storage/
│   │
│   ├── vendor/                # Composer deps (gitignored, regenerated on the host)
│   ├── cache/api/             # File-based response cache (gitignored)
│   ├── dupes/                 # Duplicate scan JSON reports (gitignored)
│   ├── logs/                  # Monolog output (gitignored)
│   ├── .env                   # Config (gitignored); template committed as .env.example
│   └── media/
│       ├── (root)             # cron.php INPUT folder: drop new files here for ingestion
│       ├── full/              # canonical full-size files (served directly)
│       └── thumbs/            # generated WebP thumbnails: <base>.webp (200px) and <base>@2x.webp (400px)
│
├── frontend/                  # Vue 3 + Vite source
│   ├── vite.config.ts         # Vite + Vitest config (build outDir, dev proxy, test block)
│   ├── vitest.setup.ts        # Vitest setup (pins TZ=UTC)
│   ├── src/
│   │   ├── main.ts            # App bootstrap, global error handler, SW registration
│   │   ├── router/index.ts    # Routes (lazy-loaded views)
│   │   ├── stores/            # Pinia: gallery (tags/categories/totals/blur), favorites, toast
│   │   ├── composables/       # useApi (fetch wrapper), useGalleryData, useMediaTags, usePrefetch
│   │   ├── components/        # Reusable UI (GalleryCard, TagBadge, TagMultiSelect, modals…)
│   │   ├── views/             # Route-level pages
│   │   ├── constants/categories.ts  # color → CSS class helpers (reads store)
│   │   ├── __tests__/         # Vitest specs (*.spec.ts): useApi, categories, stores
│   │   └── style.css          # Custom styles + extended Bulma color palette
│   ├── public/sw.js           # Service worker (caching + prefetch)
│   └── dist/                  # Vite build output (gitignored) — index.php serves dist/index.html
│
├── .github/workflows/ci.yml   # CI: PHP lint + PHPUnit, frontend build + Vitest (coverage reported)
├── README.md · CONTRIBUTING.md · CHANGELOG.md · LICENSE
└── scripts/                   # Dev-only: deploy.ps1 (PuTTY pscp/plink → droplet), gitignored
```

### Layering convention (backend)

`Controller → Collection → Storage → DatabaseConnection (PDO)`

- **Structure** (`Media`, `Tag`, `TagCategory`): dumb data holders extending `AbstractStructure`
  (which provides `jsonSerialize()` via reflection). Properties are **private** and hydrated by
  PDO `FETCH_CLASS`. Fluent setters return `$this`.
- **Storage**: the only place raw SQL lives. One class per table-group. Always use PDO prepared
  statements / bound params.
- **Collection**: thin domain/use-case wrapper over Storage; also where filesystem side effects
  live (e.g. `MediaCollection::save()` writes the thumbnail + fingerprint; `delete()` unlinks files).
- **Controller**: HTTP concerns only — parse/validate input, call collections, shape responses
  via `$this->success()` / `$this->error()` / `$this->cachedSuccess()`.

When adding a feature, follow this chain. Do **not** put SQL in controllers or collections.

---

## 3. Running & building

### Backend (Apache + PHP 8.5)
The app expects to be served by Apache with `mod_rewrite`. The document root is the PHP app root —
**`backend/`** in this repo (the deploy flattens it into the droplet webroot); `.htaccess` serves
`dist/*` assets, then real files (`media/full`, `media/thumbs`), routes `/api` to the Slim app, and
falls everything else back to `index.php` (which injects OG tags and serves the SPA shell).

```bash
cd backend
composer install              # install PHP deps into backend/vendor/
composer lint                 # check PSR-12 (phpcs); `composer lint:fix` auto-fixes most issues
```

### Database
**Phinx migrations (`backend/db/migrations/`) are the single source of truth** for schema, indexes,
and seeds. Run from `backend/`:
```bash
php vendor/bin/phinx migrate    # apply migrations
php db/setup.php                # convenience wrapper: ensures db/gallery.db exists, then migrates
```
`db/setup.php` no longer contains hand-written `CREATE TABLE`s — it just creates the SQLite file if
missing and runs Phinx. To change the schema, **add a migration** (`php vendor/bin/phinx create …`).

### Frontend
```bash
cd frontend
npm install
npm run dev        # Vite dev server on :5173, proxies /api and /media to http://localhost
npm run build      # vue-tsc type-check + vite build → outputs to frontend/dist
npm run preview
```
The production app is **the built `dist/`** served via `index.php`; there is no Node server in prod.

### CLI maintenance (in `backend/scripts/`, run from anywhere — they `chdir` to the app root)
```bash
php backend/scripts/cron.php          # ingest new files from media/ → media/full/, build thumbs+fingerprints, prune orphans
php backend/scripts/dupes.php         # run the duplicate scanner → writes dupes/dupes-YYYY-MM-DD.json
php backend/scripts/regenerate-thumbnails.php
php backend/scripts/regenerate-fingerprints.php
php backend/scripts/regenerate-metadata.php   # backfill width/height/duration/file_size on existing rows
php backend/scripts/tag_imports.php   # bulk Danbooru tag import
```
All CLI scripts have a `PHP_SAPI !== 'cli'` guard and refuse to run over HTTP.

### Deploy (to the DigitalOcean droplet)
```powershell
./scripts/deploy.ps1            # build SPA → tar backend/ + frontend/dist → upload → composer install → migrate
./scripts/deploy.ps1 -SkipBuild # redeploy existing frontend/dist
```
`scripts/deploy.ps1` (PuTTY pscp/plink) **flattens `backend/*` + `frontend/dist`** onto the flat
webroot and ships **code only** (never `db/gallery.db`, `.env`, `vendor/`, or `media/`), then on the
host runs `composer install` (with dev deps — Phinx is a dev dependency) and `php db/setup.php`
(which baselines a legacy pre-Phinx DB, then applies pending migrations), restores `www-data`
ownership, and clears `cache/api/`. Keeps a `.deploy-backup/` for rollback. Uses only two SSH
connections to respect the droplet's `ufw limit ssh`. The webroot is `/var/www/gallery.mathdad.me`
and *is* the app root (project root === DocumentRoot).

### Config (`backend/.env` — gitignored; template in `backend/.env.example`)
Keys (resolved by `Gallery\Core\Configuration`):
```
GALLERY_ADMIN_PASSWORD     # admin password; DEFAULTS TO 'changeme' if unset (⚠)
GALLERY_URL                # public base URL, required for Danbooru IQDB fallback
GALLERY_ALLOWED_ORIGINS    # comma-separated CORS allowlist
DANBOORU_LOGIN             # Danbooru API username
DANBOORU_API_KEY           # Danbooru API key
FONTAWESOME_KIT_ID         # FA kit id (injected into <head> by index.php)
```
Explicit OS/Docker env vars take precedence over `.env`. `Configuration` also understands Apache
`REDIRECT_`-prefixed vars from mod_rewrite.

---

## 4. Data model (SQLite)

```
media(media_id PK, media_type['image'|'video'], file_name UNIQUE, file_time, hash[MD5], bits_fingerprint,
      width, height, duration[secs, videos], file_size[bytes])   # metadata extracted at ingest
tag_categories(category_id PK, category_name UNIQUE, category_short UNIQUE, color, description, sort_order)
tags(tag_id PK, category_id FK→tag_categories, tag_name UNIQUE COLLATE NOCASE)
media_tags(media_id FK, tag_id FK)              # junction, PK(media_id, tag_id)
tag_implications(tag_id FK, implied_tag_id FK)  # PK(tag_id, implied_tag_id)
danbooru_category_map(danbooru_category_id PK, danbooru_category_name, gallery_category_id FK)
danbooru_tag_map(id PK, danbooru_tag UNIQUE, gallery_tag)
dismissed_duplicates(media_id_1, media_id_2, dismissed_at)  # PK(id1,id2), stored with id1<id2
auth_tokens(token PK, created_at)               # bearer tokens, valid 24h
rate_limits(ip, requested_at)                   # sliding-window limiter
```

Key indexes (defined in `db/migrations/`): `media(hash)`, `media(file_time DESC, media_id DESC)`,
`media(media_type)`, `media_tags(tag_id, media_id)` (reverse index for tag search),
`tags(category_id)`, `rate_limits(ip, requested_at)`, `auth_tokens(created_at)`.

Notes:
- **Metadata** (`width`, `height`, `duration`, `file_size`) is extracted at ingest by
  `Core/MediaMetadata` (images: `getimagesize`; videos/animated GIFs: `ffprobe`) inside
  `MediaCollection::save()`, so both the upload and cron paths populate it. Backfill existing rows
  with `php backend/scripts/regenerate-metadata.php`. `duration` is 0 for still images.
- **`media_type='video'` includes animated GIFs** (detected by counting Graphic Control Extension
  headers). Static GIFs are `'image'`. See `MediaCollection::detectMediaType()` and `isAnimatedGif()`.
- Only **images** get a `bits_fingerprint` (perceptual hash); videos don't, so duplicate detection
  is image-only.
- `tag_name` and category name/short are **case-insensitive** (`COLLATE NOCASE`).
- Tag deletion can **migrate** usages to another tag first (`migrateTag`, wrapped in a transaction).
- Foreign keys use `ON DELETE CASCADE`, and `PRAGMA foreign_keys=ON` is set per connection — deleting
  a media row removes its `media_tags`, etc.

---

## 5. API surface (all under `/api`, base path set in Slim)

Auth column: 🔓 = no token required, 🔒 = bearer token required (state-changing, via `$authMiddleware`).

### Media (`/media`) — group has `$authMiddleware` (writes need a token; GETs are public)
| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/media/random` | 🔓 | random item |
| POST | `/media/by-ids` | 🔓 | body `{ids:[…]}`, capped at 200 (allowlisted public read) |
| GET | `/media/untagged/{page}[/{perPage}]` | 🔓 | |
| GET | `/media/page/{page}[/{perPage}]` | 🔓 | cached (TTL_SHORT) |
| GET | `/media/with-tags/{tag_list}/{page}[/{perPage}]` | 🔓 | `tag_list` comma-sep, `-tag` to exclude |
| GET | `/media/total` | 🔓 | cached (TTL_MEDIUM) |
| DELETE | `/media/{media_id}` | 🔒 | single-item delete |
| GET | `/media[/{media_id}]` | 🔓 | no id → returns **all** media |

### Tags (`/tags`) — group is 🔒 for writes (GETs pass through; writes need token)
Read: `/tags/all`, `/tags/display`, `/tags/tag/{id}`, `/tags/for/media/{id}`,
`/tags/implications`, `/tags/categories`.
Write 🔒: `/tags/add`, `/tags/edit/{id}`, `/tags/migrate`, `/tags/delete`,
`/tags/implications/add`, `/tags/implications/remove`,
`/tags/categories/add|edit/{id}|delete`, `/tags/danbooru-fetch`.
Write 🔓 (explicitly exempted): **`PATCH /tags/media/add` and `PATCH /tags/media/remove`** —
anyone can add/remove tags on media by design (see §7).

### Other 🔒 groups
- `/danbooru/rules` + category-map/tag-map CRUD
- `/upload/media` (multipart, field name `files[]`)
- `/duplicates/report|scan|dismiss|media`
- `POST /auth/login` (🔓, password → token)

### Response conventions
- Success: raw JSON of the data (controllers use `$this->success()` = `withJson`).
- Error: `{ "error": "PascalCaseCode", "message": "human readable" }` + appropriate HTTP status.
  Use `$this->error($response, 'CodeName', 4xx, 'message')`.
- Cached GETs add `X-Cache: HIT|MISS`. Rate-limit info in `X-RateLimit-Remaining` / `Retry-After`.

---

## 6. Middleware stack (order matters — defined in `api/index.php`)

Outermost → innermost:
1. **Rate limiter** (global): 120 req / 60 s per IP, SQLite-backed sliding window.
2. **CSRF origin check** (global, for POST/PUT/PATCH/DELETE): validates `Origin` against the
   allowlist; falls back to deriving the origin from `Referer`; **rejects when both are absent**
   (blocks non-browser clients like curl from bypassing CORS).
3. **CORS headers** (global) + adds `X-Frame-Options`, `X-Content-Type-Options`.
4. **OPTIONS** catch-all → 204.
5. **`$authMiddleware`** (per-group): requires a valid bearer token for state-changing methods,
   except `/auth/login` and the public allowlist (`/tags/media/add`, `/tags/media/remove`,
   `/media/by-ids`). Attached to **`/media`, `/tags`, `/danbooru`, `/upload`, `/duplicates`**.

Auth = opaque random token (`bin2hex(random_bytes(32))`) stored in `auth_tokens`, valid 24h,
sent as `Authorization: Bearer …`. Frontend stores it in `sessionStorage` (`useApi.ts`).

---

## 7. Security model (and the one intentional exception)

Current, hardened behavior — keep these invariants when editing auth/routes:

- **All state-changing routes require a bearer token**, enforced by `$authMiddleware` on the
  `/media`, `/tags`, `/danbooru`, `/upload`, `/duplicates` groups (it only gates POST/PUT/PATCH/DELETE).
  If you add a new write route, put it in one of those groups (or add the middleware to its group).
- **Public allowlist** (state-changing methods that intentionally skip auth):
  `/tags/media/add`, `/tags/media/remove` (anyone may tag media — *intentional* design choice), and
  `/media/by-ids` (a read that uses POST only to carry a large id list). Don't add to this list lightly.
- **CSRF**: state-changing requests are rejected unless `Origin` (or, failing that, `Referer`) matches
  `GALLERY_ALLOWED_ORIGINS`. Requests with neither header are rejected.
- **Login**: refused entirely if `GALLERY_ADMIN_PASSWORD` is unset (so the `'changeme'` default can't
  grant access); throttled to 10 attempts / 5 min per IP (own bucket) on top of the global limiter;
  password compared with `hash_equals()`.
- Tokens: 256-bit random, stored in `auth_tokens`, valid 24h, sent as `Authorization: Bearer …`.

Remaining low-risk note: the bearer-token lookup is a direct DB equality match (not constant-time),
which is fine given the token's entropy.

---

## 8. Frontend conventions

- **Vue 3 `<script setup lang="ts">` + Composition API only.** No Options API, no class components.
- **State**: Pinia stores (`stores/`). `gallery` holds `allTags`, `categories`, `totalMedia`,
  `blurThumbnails` (persisted to `localStorage`), and `lastViewedItemIds` (used for prev/next
  navigation on the media detail page). `favorites` is a `Set<number>` persisted to `localStorage`.
  `toast` drives the global notification container.
- **API calls** go through `composables/useApi.ts` (`get/post/put/patch/del/upload`). It auto-attaches
  the bearer token, throws a structured `ApiError` (`status`, `code`, `message`), and clears the token
  on 401. Don't call `fetch` directly in components.
- **Data-fetching composables**: `useGalleryData` (paginated lists), `useMediaTags` (detail + tag
  add/remove). Reuse them rather than re-implementing fetch logic.
- **Routing**: lazy-loaded views in `router/index.ts`; page title from `meta.title`. `perPage=0`
  is the magic value that switches `GalleryView` into **infinite-scroll** mode.
- **Styling**: Bulma classes + helpers in `constants/categories.ts` that map a category's
  `color` to `is-<color>` / `has-text-<color>`. Extended colors (teal, purple, …) are defined in
  `style.css`. Keep the `VALID_COLORS` list in sync between `constants/categories.ts` (frontend) and
  `TagController::VALID_COLORS` (backend).
- **Thumbnails**: derive from `file_name` by stripping the extension →
  `/media/thumbs/<base>.webp` (1x) and `/media/thumbs/<base>@2x.webp` (2x, used in `srcset`).
- **Service worker** (`public/sw.js`): cache-first for thumbs/static assets, network-first for
  media list APIs, network-only for full-size media. The app posts `PREFETCH_THUMBNAILS` to warm
  the next page's thumbnails (`usePrefetch.ts`). Bump `CACHE_VERSION` in `sw.js` when changing
  caching behavior.

---

## 9. Caching & performance model

- **Server response cache** (`Core/ResponseCache`, file-based in `cache/api/`): GET endpoints wrap
  their generator in `cachedSuccess($response, $group, $key, $ttl, fn)`. TTLs: `TTL_SHORT=30s`
  (lists), `TTL_MEDIUM=60s` (totals/all-tags/implications).
- **Invalidation is group-based.** After any mutation, call `$this->invalidateCache('media','tags')`
  for the affected groups. **If you add a new cached GET, make sure every mutation that affects it
  invalidates the right group**, or you'll serve stale data for up to the TTL.
- SQLite connection sets WAL, `foreign_keys=ON`, `synchronous=NORMAL`, 20 MB cache, mmap, 5 s busy
  timeout (`DatabaseConnection`).
- Duplicate scan is the heaviest operation: LSH banding → Hamming filter → SSIM on `@2x` thumbnails
  (not originals) to bound memory; it temporarily raises `memory_limit`/`max_execution_time`.

---

## 10. Known traps & drift risks (read before editing)

- **Schema source of truth = Phinx** (`db/migrations/`). `db/setup.php` is now just a bootstrap that
  ensures `db/gallery.db` exists and runs migrations — no hand-written DDL. Change the schema by
  **adding a migration**, never by editing `setup.php`.
- **Upload size limits disagree**: `.htaccess` sets `upload_max_filesize 100M` / `post_max_size 105M`,
  but `UploadController::MAX_FILE_SIZE` is **500 MB** and the UI advertises 500 MB. Files >100 MB are
  rejected by PHP before reaching the controller. Reconcile if you touch uploads. *(Still open.)*
- **`ffmpeg` must be on PATH** for thumbnail generation; **GD** (with WebP, ideally AVIF) is required
  for SSIM. On hosts where `exec()` is disabled, thumbnails silently won't generate. (Note:
  `php-ffmpeg/php-ffmpeg` was removed from `composer.json` — thumbnails shell out to `ffmpeg` directly.)
- **CI runs lint, tests, and build.** GitHub Actions (`.github/workflows/ci.yml`) runs
  `composer lint` (phpcs / PSR-12) + `composer test` (PHPUnit 13) on the backend, and
  `npm run build` (vue-tsc + Vite) + `npm run test` (Vitest) on the frontend, with coverage
  reported (no hard gate). Backend tests build a fresh in-memory SQLite DB from
  `backend/tests/Support/schema.sql` and pass it to Storage/Collection constructors (the app
  uses **constructor DI** — the PHP-DI container in `api/dependencies.php` supplies `PDO` and
  autowires the graph). If you change a migration, regenerate `schema.sql` (see CONTRIBUTING.md).
  Frontend tests are in `src/__tests__/*.spec.ts` (happy-dom). Untested by design: HTTP
  controllers, and the `ffmpeg`/curl/image-pipeline helpers.
- Release history lives in `CHANGELOG.md` (Keep a Changelog format). A historical
  `IMPROVEMENTS.md` also exists (gitignored) — old internal review notes, not a current audit;
  don't trust it as the state of the codebase.

---

## 11. How to add things (quick recipes)

**A new API endpoint**
1. Add SQL to the relevant `*Storage` class (prepared statements only).
2. Expose it via the matching `*Collection` method.
3. Add a controller action; validate input, return via `success()`/`error()`.
4. Register the route in `api/index.php` in the correct group (mind auth + caching).
5. If it's a cached GET, use `cachedSuccess`; if it's a mutation, call `invalidateCache`.

**A new media field**
Update: a **migration**, the `Media` structure (+getter/setter),
`MediaStorage` (`store`/`retrieve*` column lists), and the frontend `MediaItem` interface in
`stores/gallery.ts`.

**A new tag-category color**
Add to `TagController::VALID_COLORS` (backend) **and** `VALID_COLORS` in
`constants/categories.ts` (frontend) **and** define the CSS in `frontend/src/style.css`.

**A new frontend page**
Create a view in `views/`, add a lazy route with `meta.title` in `router/index.ts`, fetch via
`useApi`/a composable, and link it from `AppNavbar.vue`.

---

## 12. Conventions checklist for any change

- [ ] SQL only in `Storage`; bound parameters always.
- [ ] Controllers return `success()`/`error()` with a PascalCase error code + human message.
- [ ] Mutations invalidate the right cache group(s).
- [ ] New state-changing routes are in an auth-protected group (or you consciously decided otherwise).
- [ ] Schema changes land in a **Phinx migration** (never hand-edited into `db/setup.php`).
- [ ] Frontend: typed, `<script setup>`, API via `useApi`, no direct `fetch`.
- [ ] Bump `sw.js` `CACHE_VERSION` if caching/asset behavior changed.
- [ ] Keep frontend/backend mirror lists (colors, extensions, `MediaItem` shape) in sync.
```
