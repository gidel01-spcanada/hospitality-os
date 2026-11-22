# Ops acceptance: health endpoints, install, seed, media sync, database export/import, schema (UAT-OPS-001 to UAT-OPS-010)

Scope: `/health` (`routes/web.php`), `/up` (`bootstrap/app.php` `health:`), `README.md` install and seed steps, `database/seeders/*`, `config/services.php` `seed`, `php artisan property:sync-media`, `scripts/export-local-db.(ps1|sh)`, `scripts/import-mysql-seed.ps1`, `database/schema.sql`, `database/seed.sql`, `database/sample-data.sql`, `database/bluehost-import.sql`, `database/migrations/*` (51 files), `docs/database.md`, `docs/architecture.md`, `tests/Feature/HealthTest.php`, `tests/Feature/DatabaseSchemaTest.php`, `tests/Feature/PropertyMediaImportTest.php`.

Rules for all OPS cases: never write real secrets (database passwords, `APP_KEY`, seed passwords, provider keys) in evidence. Record only variable names and whether they are set. Run destructive commands (`migrate:fresh`, deletes, imports) only on disposable databases.

Common test data: seeded users `admin@afrikappart.test`, `concierge@afrikappart.test`, `guest@afrikappart.test`; passwords come from `SEED_ADMIN_PASSWORD` and `SEED_GUEST_PASSWORD` provided out of band; expected seeded properties slugs `appartement-401` to `appartement-404`.

---

## UAT-OPS-001: Public health endpoint returns safe status JSON
- **Feature / journey:** Health check (`/health`)
- **Priority:** P0
- **Role / permissions:** Anonymous caller (monitoring, deploy script)
- **References:** `GET /health` (`health` route); `tests/Feature/HealthTest.php`; `docs/architecture.md` section 6; `scripts/post-deploy-validate.sh`
- **Preconditions / setup:** Application deployed or running on a test host.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `curl -i https://<host>/health` with no cookies. | HTTP 200, `Content-Type: application/json`. |
  | 2 | Inspect the body. | Contains exactly: `status` = `ok`, `application` = `afrikappart`, `environment` (string such as `production`), `php_version`, `timestamp_utc` (ISO 8601 UTC). No database name, host, paths, version of Laravel, keys, user data. |
  | 3 | Call it twice, a few seconds apart. | `timestamp_utc` differs; nothing is cached. |
  | 4 | Call with `Accept-Language: en` and `fr`, and with a logged-in session. | Same JSON, no localization or user data. |
  | 5 | `curl -i -X POST https://<host>/health`. | HTTP 405 (or 419 if CSRF applies first); record which. |
  | 6 | Stop the database service on a test host and call `/health`. | Record: the endpoint does not touch the database, so it still returns 200 (it is a process check, not a dependency check). |
- **Acceptance criteria:** The endpoint is public, fast and leaks no configuration. Note that step 6 shows it does not prove database health.
- **Evidence to capture:** curl output (headers and body).
- **Notes / assumptions / open questions:** The `environment` and `php_version` values are visible to anyone; confirm with the product owner that disclosing the PHP version and the environment name is acceptable. The master prompt asks for an authenticated admin system-health screen (database, writable paths, scheduler heartbeat, failed emails, stale feeds); none exists (see requirements-gaps.md R-OPS-01).

---

## UAT-OPS-002: Framework health route `/up` and error behavior on failure
- **Feature / journey:** Liveness (`/up`)
- **Priority:** P1
- **Role / permissions:** Anonymous caller
- **References:** `bootstrap/app.php` (`health: '/up'`); `scripts/post-deploy-validate.sh` ("Health endpoint check" tries `/up` first, then `/health`)
- **Preconditions / setup:** Test host.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `curl -i https://<host>/up`. | HTTP 200 with a small HTML page (framework default). |
  | 2 | Put the application in maintenance mode (`php artisan down`) on a test host and call `/up`, `/health` and `/`. | Record: framework maintenance returns HTTP 503 for normal pages. `/up` is typically still served by the framework; record the actual status for each URL. Then run `php artisan up`. |
  | 3 | Break `.env` (remove `APP_KEY`) on a test copy and call `/up`, `/health`. | Record: expected 500 with a generic error page; no stack trace when `APP_DEBUG=false`. Restore. |
- **Acceptance criteria:** `/up` returns 200 when healthy; failure modes return generic errors without secrets.
- **Evidence to capture:** curl output.
- **Notes / assumptions / open questions:** The post-deploy script treats any 2xx/3xx as healthy (`curl -L` follows redirects), so an HTTP-to-HTTPS redirect passes even if the target is down; verify in UAT-OPS-014.

---

## UAT-OPS-003: Clean install and seed from the README
- **Feature / journey:** First install on an empty database
- **Priority:** P0
- **Role / permissions:** Operator
- **References:** `README.md` (Local development, seed passwords); `database/seeders/DatabaseSeeder.php` (ReferenceDataSeeder, SamplePropertySeeder, AuthSeeder); `AuthSeeder` guard; `.env.example`; `docs/database.md`
- **Preconditions / setup:** Clean checkout; PHP and Composer available; empty SQLite or MySQL database; `cp .env.example .env`.
- **Test data:** Local throwaway seed passwords (never reused elsewhere; do not record them).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `composer install`, `php artisan key:generate`, `npm install`, `npm run build`. | Completes; `public/build/manifest.json` exists. |
  | 2 | `php artisan migrate --force`. | All migrations run without error (51 migration files); `php artisan migrate:status` shows all `Ran`. |
  | 3 | With `SEED_ADMIN_PASSWORD` and `SEED_GUEST_PASSWORD` unset or empty, run `php artisan db:seed`. | Fails with `SEED_ADMIN_PASSWORD and SEED_GUEST_PASSWORD must be configured before running the auth seeder.`; no users created with default passwords. Note whether reference data and sample properties were already seeded before the failure (seeders run in order). |
  | 4 | Set both variables to local values and run `php artisan db:seed`. | Success. |
  | 5 | Read-only checks: `SELECT email, role, is_admin, is_platform_admin FROM users`. | Three users: admin (`role=admin`, platform admin), concierge, customer. |
  | 6 | Open `/` and `/properties`. | Home renders; four apartments listed; FR default locale. |
  | 7 | Log in as admin; open the dashboard. | Succeeds. |
  | 8 | `php artisan serve` or the host's web server; `GET /health`. | 200 `ok`. |
  | 9 | Repeat steps 2-8 on MySQL 8 (`DB_CONNECTION=mysql`) if MySQL is the production target. | Same result; no SQL errors (utf8mb4). |
- **Acceptance criteria:** A clean checkout reaches a working app with the documented commands; seeding refuses to run without explicit passwords.
- **Evidence to capture:** Command output (secrets masked); `migrate:status` output; screenshots of home and admin dashboard.
- **Notes / assumptions / open questions:** README shows `php artisan config:cache` before `serve`; local runs after that need `config:clear` before tests (README note). Baseline requires PHP 8.3 + MySQL 8.x (`docs/requirements-baseline.md`) while the README mentions Scoop PHP 8.5; record the PHP version used.

---

## UAT-OPS-004: Seeding is idempotent
- **Feature / journey:** Re-running the seed
- **Priority:** P0
- **Role / permissions:** Operator
- **References:** `DatabaseSeeder`; `ReferenceDataSeeder` (`updateOrInsert`, `firstOrCreate`), `AuthSeeder` (`updateOrCreate`), `SamplePropertySeeder`; master prompt acceptance "imported idempotently"
- **Preconditions / setup:** Database seeded once (UAT-OPS-003). Record counts: tenants, users, establishments, properties, amenities, amenity categories, settings, property_images.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create a reservation and edit the site name setting through Admin > Settings (`Test Site`). | Data exists. |
  | 2 | Run `php artisan db:seed` again. | Success. |
  | 3 | Compare counts. | Tenants 1, users 3 (no duplicates), properties unchanged, amenities unchanged, images unchanged. |
  | 4 | Check the reservation and the edited setting. | Reservation untouched. Record the setting: the seeder writes `site_name = Afrik Appart` by `updateOrInsert`, which overwrites an admin edit. |
  | 5 | Change the admin user's password through the UI, then re-run the seed. | Record: `AuthSeeder` resets the three seeded accounts to the seed passwords and resets their name, role and locale. |
- **Acceptance criteria:** No duplicate rows after re-seeding. Overwrites in steps 4 and 5 are recorded for the product owner: re-running the seed on a production database resets site settings and seeded account credentials.
- **Evidence to capture:** Count queries before and after; screenshots of settings.
- **Notes / assumptions / open questions:** Running `db:seed` on production is dangerous for the reasons in steps 4-5; the deploy docs should state "seed only on first install" (see requirements-gaps.md).

---

## UAT-OPS-005: Seeded reference data matches the baseline and the documentation
- **Feature / journey:** Seed content acceptance
- **Priority:** P1
- **Role / permissions:** Tester with read-only SQL and admin access
- **References:** `database/seeders/ReferenceDataSeeder.php` (settings); `docs/requirements-baseline.md`; `docs/database.md`; `docs/content-todo.md`; `config/app.php`
- **Preconditions / setup:** Seeded database.
- **Test data:** Baseline values: site name `Afrik Appart`, default locale `fr`, secondary `en`, default currency `XOF`, `1 EUR = 655.957 XOF`, payment hold 20 minutes, calendar freshness 15 minutes, timezone `Africa/Porto-Novo`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `SELECT key, value FROM settings WHERE tenant_id = <default tenant id>`. | Contains `site_name=Afrik Appart`, `default_locale=fr`, `secondary_locale=en`, `default_currency=XOF`, `eur_to_xof_rate=655.957`, `payment_hold_minutes=20`, `calendar_freshness_minutes=15`, `booking_mode=instant_confirmation`. |
  | 2 | Check `config('app.timezone')` via `php artisan about` or the code (`config/app.php`). | Value is `UTC` and is hard-coded. The baseline timezone `Africa/Porto-Novo` is not configured at application level. Record. |
  | 3 | Open Admin > Settings. | Settings editable; values from step 1 reflected where the screen exposes them. |
  | 4 | Compare placeholders (`support@afrikappart.example`, `+229 00 00 00 00`, "Draft" property texts) with `docs/content-todo.md`. | Placeholders still present in seed; list each one still visible on the public site (footer, contact, property pages) for the product owner to replace before launch. |
  | 5 | Compare `docs/database.md` table names with the actual schema. | Every listed table exists (`currency_configs` included, per `DatabaseSchemaTest`). Record tables in the schema that are missing from the doc (for example `tenants`, `mobile_access_tokens`, `platform_feedback`, `message_*`). |
- **Acceptance criteria:** Seeded values equal the baseline; differences are listed.
- **Evidence to capture:** SQL output; screenshots.
- **Notes / assumptions / open questions:** Whether `payment_hold_minutes` and `calendar_freshness_minutes` are used anywhere in code: searched `app/`, `routes/`, `config/`, no read found (only the seeder and the `PAYMENT_HOLD_MINUTES` env in `PayLaterGateway`). See requirements-gaps.md.

---

## UAT-OPS-006: Property media import is idempotent and matches 14/12/19/13 photos
- **Feature / journey:** Media sync command and seed
- **Priority:** P1
- **Role / permissions:** Operator
- **References:** `php artisan property:sync-media [--source=] [--target=] [--manifest=] [--dry-run]`; `app/Console/Commands/SyncPropertyMedia.php`; `seed-assets/properties/manifest.json`; `docs/input-inventory.md` section 1; `tests/Feature/PropertyMediaImportTest.php`; `scripts/build-initial-media.(ps1|sh)`; `PUBLIC_UPLOAD_PATH`
- **Preconditions / setup:** Repository `photo/` folder with `appartement 401` (14), `402` (12), `403` (19), `404` (13). Local machine with at least 1 GB memory limit (command sets `memory_limit` to 1G). Disposable `public/uploads` target.
- **Test data:** The supplied photo folders only.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan property:sync-media --dry-run`. | Reports each folder and expected counts; writes nothing to the target or database. |
  | 2 | `php artisan property:sync-media`. | Variants written under `public/uploads/properties/...`; manifest updated; `property_images` rows per property. Exit code 0. |
  | 3 | SQL: `SELECT property_id, COUNT(*) FROM property_images GROUP BY property_id`. | 14, 12, 19, 13 (total 58) mapped to `appartement-401` ... `404` respectively. |
  | 4 | Run the command again. | Same counts; no duplicates; files not duplicated. |
  | 5 | Rename one source folder and run. | Error `Missing expected source folder: <path>`; exit code non-zero. Restore. |
  | 6 | Remove one photo from a folder and run. | Record the result (expected count check should fail or warn; the master prompt requires exact mapping). |
  | 7 | Open a property page. | Gallery shows the images in order; the cover is the first; images are WebP/JPEG variants; no broken images. |
  | 8 | Inspect uploaded files for EXIF location data (use any metadata viewer on three sample files). | Record whether GPS metadata remains in public images. |
- **Acceptance criteria:** 58 images mapped correctly and the process is repeatable.
- **Evidence to capture:** Command output; SQL counts; gallery screenshots.
- **Notes / assumptions / open questions:** `photo/` is excluded from the release bundle (`scripts/build-release.ps1`), so production relies on `scripts/upload-seed-media-bluehost.ps1`. The media upload scripts (`scripts/upload-seed-media-bluehost.ps1`) are not covered by a dedicated case; run them against staging after UAT-OPS-013 and record the result under that case. Test duration is long (58 images).

---

## UAT-OPS-007: MySQL seed export and import scripts
- **Feature / journey:** Database export/import tooling
- **Priority:** P1
- **Role / permissions:** Operator on a workstation with MySQL client tools
- **References:** `scripts/export-local-db.ps1` (`-Mode sqlite|mysql|mysql-seed`, `-SkipSeedRefresh`, `-PromptForSeedPasswords`), `scripts/export-local-db.sh` (`--mysql-seed`, `--skip-seed-refresh`), `scripts/import-mysql-seed.ps1` (`-InputPath`), README "MySQL export", `.gitignore` entries for `afrik-appart-mysql-export.sql` and `afrik-appart-mysql-seed-export.sql`
- **Preconditions / setup:** Disposable empty MySQL 8 database and credentials supplied by the owner; `mysql` client on PATH.
- **Test data:** Local throwaway seed passwords.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run `./scripts/export-local-db.ps1 -Mode mysql-seed -PromptForSeedPasswords`. | Creates `afrik-appart-mysql-seed-export.sql` from a temporary SQLite seed; no unrelated local data. Exit code 0. |
  | 2 | `git status`. | The `.sql` file is not listed as untracked (it is git-ignored). |
  | 3 | Open the file in an editor (do not share it). Search for the `users` inserts. | Contains hashed passwords only (`$2y$...`), no plain text. Treat the file as sensitive. |
  | 4 | With `DB_CONNECTION=sqlite` in `.env`, run `./scripts/import-mysql-seed.ps1`. | Fails with `MySQL import requires DB_CONNECTION=mysql; current connection is 'sqlite'.` |
  | 5 | Point `.env` to the empty MySQL database and run `./scripts/import-mysql-seed.ps1`. | Runs `php artisan migrate --force`, then imports the file; prints `MySQL migrations and seed import completed for database '<name>'.` |
  | 6 | Verify the app against that database (UAT-OPS-003 steps 5-8). | Works; 4 properties, 3 users. |
  | 7 | Run the import a second time. | Record the result (duplicate key errors vs idempotent). |
  | 8 | Run with a missing file (`-InputPath nofile.sql`). | Error `MySQL seed export not found at <path>`. |
  | 9 | Verify that no password is left in the PowerShell history or logs. | `MYSQL_PWD` is set only for the import and restored afterwards. |
- **Acceptance criteria:** Export and import work on a blank MySQL database; guards stop wrong-database use; the dump never lands in git.
- **Evidence to capture:** Command output with credentials removed; `git status` output.
- **Notes / assumptions / open questions:** The repository working tree contains `afrik-appart-mysql-seed-export.sql` locally; confirm it is not in any release artifact (UAT-OPS-011 step 3). Step 7 behavior is unknown from the scripts.

---

## UAT-OPS-008: `database/schema.sql` and migrations describe the same schema
- **Feature / journey:** Schema equivalence acceptance
- **Priority:** P1
- **Role / permissions:** Operator with two empty MySQL 8 databases
- **References:** `database/schema.sql`, `database/seed.sql`, `database/sample-data.sql`, `database/bluehost-import.sql`, `database/migrations/*`, `docs/database.md` ("Schema installation"), master prompt acceptance "Migrations and SQL scripts describe the same effective schema"
- **Preconditions / setup:** Two empty databases DB-A and DB-B.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Import `database/schema.sql` into DB-A with the MySQL client. | Imports without errors. |
  | 2 | Run `php artisan migrate --force` against DB-B. | Completes. |
  | 3 | Compare table names (`SHOW TABLES`) of A and B. | Record every table present in only one of them. The migrations include tenants, platform_feedback, mobile_access_tokens, messages, cleaning, amenity categories and later changes that `schema.sql` may not contain. |
  | 4 | Compare columns for shared tables (`information_schema.columns`). | Record differences. |
  | 5 | Import `database/seed.sql` then `database/sample-data.sql` into DB-A. | Record errors (for example missing columns or tenant ids). |
  | 6 | Open `database/bluehost-import.sql` and compare to `schema.sql`. | Record whether it is a separate, third variant of the schema. |
  | 7 | Check which one the README tells operators to use. | README documents migrations and the seed-export import; `schema.sql` is named only in `docs/database.md`. Record. |
- **Acceptance criteria:** Equal effective schema, or a documented statement of which source is authoritative and which files are obsolete.
- **Evidence to capture:** Diff of table and column lists.
- **Notes / assumptions / open questions:** The product owner decides whether `schema.sql` must stay in sync; if not, mark it deprecated in the docs.

---

## UAT-OPS-009: Migrations on an existing database are safe and repeatable
- **Feature / journey:** Upgrade path
- **Priority:** P0
- **Role / permissions:** Operator
- **References:** `php artisan migrate --force`; `scripts/post-deploy-validate.sh` (schema state detection, "Database has existing tables but no Laravel migrations table"); `docs/adr/0001-laravel-bluehost.md`; README note on schema-changing releases
- **Preconditions / setup:** Staging database restored from a recent backup of production-like data (synthetic if production data is not allowed). Database backup taken before the run and restore tested (UAT-OPS-010).
- **Test data:** A staging database with reservations, reviews, users.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan migrate:status`. | Lists ran and pending migrations. |
  | 2 | `php artisan migrate --force`. | Pending migrations apply; output shows no error. |
  | 3 | Compare row counts for reservations, users, reviews, payment_attempts before and after. | Identical. |
  | 4 | Run `php artisan migrate --force` again. | `Nothing to migrate.` |
  | 5 | On a copy, drop the `migrations` table but keep other tables, run `scripts/post-deploy-validate.sh` (reads `.env` DB settings). | Script stops with "Database has existing tables but no Laravel migrations table" and tells the operator not to run migrate. |
  | 6 | Check the application after upgrade: log in, view reservations, receipts, calendar. | Works. |
- **Acceptance criteria:** Upgrades keep data intact and are repeatable.
- **Evidence to capture:** Migration output; counts.
- **Notes / assumptions / open questions:** No `migrate:rollback` expectations are documented; rollback of schema changes is a gap (see UAT-OPS-019).

---

## UAT-OPS-010: Backup and restore drill (database and uploads)
- **Feature / journey:** Backup and restore
- **Priority:** P1
- **Role / permissions:** Operator with hosting access
- **References:** Master prompt "Backup/restore procedures for both MySQL and persistent uploads"; `PUBLIC_UPLOAD_PATH`; `public/uploads/*`; `docs/` (no runbook present)
- **Preconditions / setup:** Staging environment; hosting backup tool or `mysqldump` access; a documented procedure is required. If no procedure exists the tester records "documentation missing" and the case is Blocked.
- **Test data:** Staging data with at least one uploaded property photo and one payment proof (synthetic).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Locate the backup procedure in the repository docs. | Expected `docs/runbook.md` and `docs/bluehost-deployment.md` per the master prompt. Record found / not found (neither file exists in the repository at review time). |
  | 2 | Create a database dump and an archive of the uploads folder. | Both created and non-empty. |
  | 3 | Restore both into a fresh staging copy. | App works; photos and payment proofs display; counts match. |
  | 4 | Time the procedure. | Record the duration for the owner's recovery objective. |
- **Acceptance criteria:** A documented procedure restores the system with data and files.
- **Evidence to capture:** The document location or "missing"; timing; screenshots.
- **Notes / assumptions / open questions:** Recovery objectives (RPO/RTO) are not defined anywhere reviewed; ask the product owner.
