# Afrik Appart

This repository contains the Laravel 12 baseline for the Afrik Appart short-term rental website.

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

Before running `php artisan db:seed`, set `SEED_ADMIN_PASSWORD` and `SEED_GUEST_PASSWORD` in `.env` to local-only values. These credentials are intentionally not provided by the repository. The local concierge account is `concierge@afrikappart.test` and uses `SEED_GUEST_PASSWORD`.

## MySQL export

With `DB_CONNECTION=mysql` configured in `.env`, run `./scripts/export-local-db.ps1` on Windows or `./scripts/export-local-db.sh` on macOS/Linux. The export refreshes every canonical Laravel seed before generating `afrik-appart-mysql-export.sql`. Pass `-SkipSeedRefresh` (PowerShell) or `--skip-seed-refresh` (shell) only when you need to dump the database without updating seed records.

When a MySQL connection is unavailable locally, run `./scripts/export-local-db.ps1 -Mode mysql-seed` on Windows or `./scripts/export-local-db.sh --mysql-seed` on macOS/Linux. It creates a temporary fresh SQLite database with all Laravel migrations and canonical seeders, then writes `afrik-appart-mysql-seed-export.sql`. This avoids exporting unrelated local data. The resulting MySQL data file must be imported after the target database migrations have run. When the seed passwords are not in `.env`, Windows users can add `-PromptForSeedPasswords` and type them directly in the terminal.

To restore the seed-only export on a fresh MySQL database, configure that database in `.env`, then run `./scripts/import-mysql-seed.ps1` on Windows. It runs `php artisan migrate --force` before importing `afrik-appart-mysql-seed-export.sql`, so every required table exists. The generated export is ignored by Git because it includes the seeded local user password hashes.

## Validation

- `php artisan route:list`
- `php artisan test`
- `curl http://127.0.0.1:8000/health`

## Project status

The Laravel application includes the public property catalog and booking flow, payment integrations, reservation emails, message notifications, property media and calendar management, and a role-protected admin workspace.

Queued new-message email notifications can be delivered with `php artisan messages:send-email-notifications`. Run this command from a scheduler or deployment cron using the configured Laravel mailer.

The admin workspace supports dashboard metrics, reservation filtering and CRUD, user management, property and establishment management, reviews, site parameters, and user preferences. Run the full test suite with `php artisan test` before deploying changes.
