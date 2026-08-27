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

## Validation

- `php artisan route:list`
- `php artisan test`
- `curl http://127.0.0.1:8000/health`

## Project status

The Laravel application includes the public property catalog and booking flow, payment integrations, reservation emails, message notifications, property media and calendar management, and a role-protected admin workspace.

Queued new-message email notifications can be delivered with `php artisan messages:send-email-notifications`. Run this command from a scheduler or deployment cron using the configured Laravel mailer.

The admin workspace supports dashboard metrics, reservation filtering and CRUD, user management, property and establishment management, reviews, site parameters, and user preferences. Run the full test suite with `php artisan test` before deploying changes.
