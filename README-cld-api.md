# CLD API migration (Laravel)

CLD (Courses API) sync is now available as a Laravel Artisan command and service. This replaces the old cron entry point `generate-add-update-cld-api-cron-append.php`.

## Commands

- **Cron-style sync (no manual list)** – fetches courses updated in the last month, backs up current feed data, fetches each course from CLD API, inserts/updates DB, matches parent/child languages, optionally triggers FeedMe:

  ```bash
  php artisan cld:sync
  ```

  Options:
  - `--no-feedme` – skip calling FeedMe after sync
  - `--feed-id=70` – FeedMe feed ID
  - `--basic-filter` – only use LastUpdate (skip image/locale checks)

  Example for cron:
  ```bash
  0 * * * * cd /path/to/hub.hsi.com && php artisan cld:sync --no-feedme
  ```

- **Singles / one-off IDs** – truncates `course_api_data_singles` and fetches only the IDs you provide:

  ```bash
  php artisan cld:sync-singles 7142 15394 --no-feedme
  ```

  Options:
  - `--no-feedme` – skip calling FeedMe after sync
  - `--feed-id=70` – FeedMe feed ID

- **Manual list via code** – you can still call the service from a custom command or controller:

  ```php
  $cldApi->cronJobGenerateAddUpdateCldApiDataFromList(
      manualList: [7142],
      feedId: 70,
      doSingleBatch: true,  // uses course_api_data_singles, truncates first
      runFeedMe: true
  );
  ```

## JSON feeds (for Craft FeedMe)

These replace the old `apismd.hsi.com/create-api-xml-feed.php` and `apismd.hsi.com/create-api-json-feed-singles.php` endpoints, but keep the same JSON structure.

- **Full feed**: `GET /feeds/cld/courses`
- **Singles feed**: `GET /feeds/cld/courses/singles`

Optional protection:
- If you set `CLD_FEEDS_PASSKEY` in `.env`, FeedMe must request:
  - `/feeds/cld/courses?passkey=...`
  - `/feeds/cld/courses/singles?passkey=...`

## .env variables

| Variable | Description |
|----------|-------------|
| `CLD_API_BASE_URL` | CLD API base URL (default: `https://cldapi.hsiplatform.com`) |
| `CLD_API_ADMIN_ID` | CLD API auth Admin_ID (required for token) |
| `CLD_API_PASSWORD` | CLD API auth Password (required for token) |
| `CLD_FEEDS_PASSKEY` | Optional passkey required to access `/feeds/cld/*` endpoints |
| `CLD_FEEDME_PASSKEY` | Passkey for FeedMe run-task URL (optional; if empty, FeedMe is not triggered) |
| `CLD_FEEDME_PROD_URL` | FeedMe run-task base URL (default: production Craft URL in `config/cld_api.php`) |
| `CLD_FEEDME_PROD_FEED_ID` | FeedMe feed ID (default: 70) |
| `CLD_DO_SPACES_KEY` | DigitalOcean Spaces key (course images CDN) |
| `CLD_DO_SPACES_SECRET` | DigitalOcean Spaces secret |
| `CLD_DO_SPACES_BUCKET` | Bucket name (default: `hsiassetstorage`) |
| `CLD_DO_SPACES_REGION` | Region (default: `sfo2`) |
| `CLD_DO_SPACES_ENDPOINT` | Endpoint (default: `https://sfo2.digitaloceanspaces.com`) |

DB connection uses Laravel’s existing `DB_*` (.env) – no separate CLD DB vars.

## Folder structure

- **Config:** `config/cld_api.php` (paths, URLs, credentials from .env).
- **Service:** `App\Services\CldApiService` – all CLD API and feed logic; uses Laravel `DB` facade (no Medoo/MeekroDB).
- **Command:** `App\Console\Commands\CldSync` → `php artisan cld:sync`.
- **Storage:** Course images and optimized thumbs are stored under Laravel storage:
  - `storage/app/cld-api/course-images/` – downloaded originals
  - `storage/app/cld-api/course-images/optimized/` – large thumbs
  - `storage/app/cld-api/course-images/optimized/small/` – small thumbs  

  Paths are configurable in `config/cld_api.storage`.

## Database tables

The service expects these tables (same as the old app). Create them via migrations or your existing schema (e.g. from `private/old-api-app-files/apis_md_local-*.sql`):

- `cld_api_tokens`
- `course_api_data`
- `course_api_data_backup`
- `course_api_data_singles`
- `course_api_append_data` (used by other flows)
- `course_api_to_delete` / `course_api_to_delete_backup` (used by inactive/delete flow)

## Differences from old app

- No Medoo/MeekroDB: all DB access uses Laravel `DB` facade.
- Credentials and URLs come from `.env` and `config/cld_api.php` (no hardcoded credentials).
- Output directories for course images live under `storage/app/cld-api/` instead of a custom `uploads/` path.
- The old cron script used a global `$cldapi` created at the bottom of `cld-api.class.php`; the Laravel command injects `CldApiService` and calls `cronJobGenerateAddUpdateCldApiDataFromList([])` with no manual list.
