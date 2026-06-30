# Gallery API Reference

Internal HTTP API for the self-hosted media gallery. All endpoints are served
under the **`/api`** base path and return **JSON**.

This document is a quick reference. The authoritative contract lives in the
controller docblocks under [`api/Routes/Internal/`](api/Routes/Internal/) and the
route table in [`api/index.php`](api/index.php).

---

## Conventions

### Response envelope

| Outcome | Shape | Status |
|---------|-------|--------|
| Success | The payload directly — a resource object, an array of resources, or a bare `true` | `2xx` |
| Error   | `{ "error": "<MachineCode>", "message": "<human text>" }` | `4xx` / `5xx` |

- `error` is a stable PascalCase code (e.g. `MediaNotFound`) safe to branch on.
- `message` is for display and may change.
- Cached `GET`s also return an **`X-Cache: HIT|MISS`** header.

### Authentication

Authentication is a **Bearer token** in the `Authorization` header:

```
Authorization: Bearer <token>
```

Obtain a token from [`POST /auth/login`](#auth) (valid for **24 hours**).

The rule (enforced by middleware in `index.php`):

- **All `GET` requests are public.**
- **State-changing methods (`POST`/`PUT`/`PATCH`/`DELETE`) require a token**, with three intentionally-public exceptions on the allowlist: `POST /media/by-ids`, `PATCH /tags/media/add`, `PATCH /tags/media/remove` (plus `POST /auth/login` itself).

In the tables below: 🌐 = public, 🔒 = token required.

### Rate limiting, CORS & CSRF

- **Rate limit:** 120 requests / 60 s per IP globally; `POST /auth/login` is throttled separately at 10 attempts / 5 min. A `429` carries `Retry-After` and `X-RateLimit-Remaining` headers.
- **CORS:** responses echo an allowed `Origin` (configured via `GALLERY_ALLOWED_ORIGINS`); credentials are allowed.
- **CSRF:** state-changing requests must carry an `Origin` or `Referer` matching an allowed origin, or they are rejected with `403 ForbiddenOrigin`.

---

## Auth

| Method | Path | Auth | Body | Returns |
|--------|------|------|------|---------|
| POST | `/auth/login` | 🌐 | `{ password }` | `{ token }` |

---

## Media

| Method | Path | Auth | Body / Params | Returns |
|--------|------|------|---------------|---------|
| GET | `/media/{media_id}` | 🌐 | — | `Media` |
| GET | `/media/random` | 🌐 | — | `Media` |
| GET | `/media/total` | 🌐 | — | `int` |
| GET | `/media/page/{page}[/{items_per_page}]` | 🌐 | `items_per_page` clamped 1–200 | `{ items, total_pages, current_page }` |
| GET | `/media/untagged/{page}[/{items_per_page}]` | 🌐 | — | `{ items, total_pages, current_page }` |
| GET | `/media/with-tags/{tag_list}/{page}[/{items_per_page}]` | 🌐 | `tag_list` is comma-separated; a leading `-` excludes (e.g. `cat,-dog`) | `{ items, total_pages, current_page }` |
| POST | `/media/by-ids` | 🌐 | `{ ids: int[] }` (max 200) | `Media[]` |
| DELETE | `/media/{media_id}` | 🔒 | — | `{ deleted }` |

---

## Tags

| Method | Path | Auth | Body / Params | Returns |
|--------|------|------|---------------|---------|
| GET | `/tags/all` | 🌐 | — | `Tag[]` |
| GET | `/tags/display` | 🌐 | — | tags + category & usage/implication counts |
| GET | `/tags/tag/{tag_id}` | 🌐 | — | `Tag` |
| GET | `/tags/for/media/{media_id}` | 🌐 | — | `Tag[]` |
| POST | `/tags/add` | 🔒 | `{ tag_name, category_id? }` | `true` |
| PUT | `/tags/edit/{tag_id}` | 🔒 | `{ tag_name, category_id? }` | `true` |
| PATCH | `/tags/media/add` | 🌐 | `{ item_id, tag_ids: int[] }` | updated `Tag[]` |
| PATCH | `/tags/media/remove` | 🌐 | `{ item_id, tag_id }` | updated `Tag[]` |
| POST | `/tags/migrate` | 🔒 | `{ source_tag_id, target_tag_id }` | `true` |
| DELETE | `/tags/delete` | 🔒 | `{ tag_id, migrate_to_tag_id? }` | `true` |
| POST | `/tags/danbooru-fetch` | 🔒 | `{ media_id, danbooru_post_id? }` | `{ tags, all_tags, method, tags_applied, tags_created }` |

### Tag categories

| Method | Path | Auth | Body / Params | Returns |
|--------|------|------|---------------|---------|
| GET | `/tags/categories` | 🌐 | — | `TagCategory[]` |
| POST | `/tags/categories/add` | 🔒 | `{ category_name, category_short, color?, description?, sort_order? }` | `TagCategory[]` |
| PUT | `/tags/categories/edit/{category_id}` | 🔒 | same fields as add | `TagCategory[]` |
| DELETE | `/tags/categories/delete` | 🔒 | `{ category_id }` (must have no tags) | `TagCategory[]` |

### Tag implications

| Method | Path | Auth | Body | Returns |
|--------|------|------|------|---------|
| GET | `/tags/implications` | 🌐 | — | implications list |
| POST | `/tags/implications/add` | 🔒 | `{ tag_id, implied_tag_id }` (cycles rejected) | implications list |
| DELETE | `/tags/implications/remove` | 🔒 | `{ tag_id, implied_tag_id }` | implications list |

---

## Danbooru import rules

| Method | Path | Auth | Body / Params | Returns |
|--------|------|------|---------------|---------|
| GET | `/danbooru/rules` | 🌐 | — | `{ category_mappings, tag_mappings }` |
| POST | `/danbooru/category-map/add` | 🔒 | `{ danbooru_category_id, danbooru_category_name, gallery_category_id }` | category mappings |
| DELETE | `/danbooru/category-map/delete` | 🔒 | `{ danbooru_category_id }` | category mappings |
| POST | `/danbooru/tag-map/add` | 🔒 | `{ danbooru_tag, gallery_tag }` | tag mappings |
| PUT | `/danbooru/tag-map/edit/{id}` | 🔒 | `{ danbooru_tag, gallery_tag }` | tag mappings |
| DELETE | `/danbooru/tag-map/delete` | 🔒 | `{ id }` | tag mappings |

---

## Upload

| Method | Path | Auth | Body | Returns |
|--------|------|------|------|---------|
| POST | `/upload/media` | 🔒 | `multipart/form-data` with `files[]` (≤ 500 MB each); optional `fetch_tags` | `{ results, total_uploaded, total_duplicates, total_failed, total_tags_applied? }` |

Media type is auto-detected from the file extension (animated GIFs are treated
as video). Content type is verified server-side, not just the extension.

---

## Duplicates

Duplicate detection is image-only, based on perceptual fingerprinting.

| Method | Path | Auth | Body | Returns |
|--------|------|------|------|---------|
| GET | `/duplicates/report` | 🌐 | — | latest report with dismissed pairs filtered out |
| POST | `/duplicates/scan` | 🔒 | — | `{ images_compared, lsh_candidates, duplicates_found, execution_time }` |
| POST | `/duplicates/dismiss` | 🔒 | `{ media_id_1, media_id_2 }` | `{ dismissed, media_id_1, media_id_2 }` |
| DELETE | `/duplicates/media` | 🔒 | `{ media_ids: int[] }` | `{ deleted, failed, total_deleted }` |

---

## Resource shapes

`Media`, `Tag`, and `TagCategory` are serialized from their
[structure classes](includes/Gallery/Structure/) (all properties, snake_case):

- **Media** — `media_id, media_type, file_name, file_time, hash, bits_fingerprint, width, height, duration, file_size`
- **Tag** — `tag_id, category_id, tag_name`
- **TagCategory** — `category_id, category_name, category_short, color, description, sort_order`
