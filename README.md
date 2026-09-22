# Afrik Appart

This repository contains the Laravel 12 baseline for the Afrik Appart short-term rental website.

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate --force
php artisan db:seed
php artisan config:cache
php artisan serve
```

Before running `php artisan db:seed`, set `SEED_ADMIN_PASSWORD` and `SEED_GUEST_PASSWORD` in `.env` to local-only values. These credentials are intentionally not provided by the repository. The local concierge account is `concierge@afrikappart.test` and uses `SEED_GUEST_PASSWORD`.

## MySQL export

With `DB_CONNECTION=mysql` configured in `.env`, run `./scripts/export-local-db.ps1` on Windows or `./scripts/export-local-db.sh` on macOS/Linux. The export refreshes every canonical Laravel seed before generating `afrik-appart-mysql-export.sql`. Pass `-SkipSeedRefresh` (PowerShell) or `--skip-seed-refresh` (shell) only when you need to dump the database without updating seed records.

When a MySQL connection is unavailable locally, run `./scripts/export-local-db.ps1 -Mode mysql-seed` on Windows or `./scripts/export-local-db.sh --mysql-seed` on macOS/Linux. It creates a temporary fresh SQLite database with all Laravel migrations and canonical seeders, then writes `afrik-appart-mysql-seed-export.sql`. This avoids exporting unrelated local data. The resulting MySQL data file must be imported after the target database migrations have run. When the seed passwords are not in `.env`, Windows users can add `-PromptForSeedPasswords` and type them directly in the terminal.

To restore the seed-only export on a fresh MySQL database, configure that database in `.env`, then run `./scripts/import-mysql-seed.ps1` on Windows. It runs `php artisan migrate --force` before importing `afrik-appart-mysql-seed-export.sql`, so every required table exists. The generated export is ignored by Git because it includes the seeded local user password hashes.

## Live Payments

Keep FedaPay, PayPal, CinetPay, and M-Pesa credentials in `.env`, never in the establishment record. Enable a provider in the establishment editor only after its production environment and credentials are configured. Register these HTTPS webhook endpoints with the provider dashboards:

```text
https://your-domain.example/webhooks/fedapay
https://your-domain.example/webhooks/paypal
https://your-domain.example/webhooks/cinetpay
https://your-domain.example/webhooks/mpesa
```

PayPal returns buyers to the checkout return route after approval, where the order is captured. FedaPay sends transaction updates to its webhook endpoint and provides a hosted payment URL for the buyer redirect.

For PayPal production webhooks, set `PAYPAL_WEBHOOK_ID`; the application verifies incoming events through PayPal's `verify-webhook-signature` endpoint before processing them. FedaPay webhooks currently require the `X-Webhook-Signature` HMAC generated with `PAYMENT_WEBHOOK_SECRET`; confirm FedaPay's signing header in your account before enabling live FedaPay webhooks. CinetPay IPNs must target `/webhooks/cinetpay`; each received `transaction_id` is verified against CinetPay's payment-check API before payment status is updated.

M-Pesa uses Daraja STK Push for KES payments. Configure `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, and `MPESA_PASSKEY`, then register `https://your-domain.example/webhooks/mpesa` as the callback URL. The application records the Daraja checkout request ID and processes only callbacks for a known payment attempt.

Set only the values for providers you have contracted and intend to enable:

```env
FEDAPAY_ENVIRONMENT=production
FEDAPAY_PUBLIC_KEY=...
FEDAPAY_SECRET_KEY=...

PAYPAL_ENVIRONMENT=production
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_WEBHOOK_ID=...

CINETPAY_ENVIRONMENT=production
CINETPAY_SITE_ID=...
CINETPAY_API_KEY=...

MPESA_ENVIRONMENT=production
MPESA_CONSUMER_KEY=...
MPESA_CONSUMER_SECRET=...
MPESA_SHORTCODE=...
MPESA_PASSKEY=...
```

The local media import expects apartment source photos in `photo/` at the repository root. When present, `php artisan db:seed` syncs those photos into `public/uploads/properties` and writes `seed-assets/properties/manifest.json`.

On Windows with Scoop PHP 8.5, run `php artisan config:cache` before `php artisan serve` and rerun it after `.env` changes. Clear config first with `php artisan config:clear` when running PHPUnit so `phpunit.xml` testing overrides are applied.

Public GA4/GTM tracking is disabled by default. To enable it in production, set `ANALYTICS_ENABLED=true` and configure either `GA4_MEASUREMENT_ID` or `GTM_CONTAINER_ID` in `.env`. Use GTM as the source of the GA4 tag when both IDs are present to avoid duplicate page views. Tracking loads only after visitor consent and excludes admin/account pages and personal fields.

After deploying a release that changes the database schema, run `php artisan migrate --force` before serving traffic. In particular, the categorized amenity migrations add localized amenity names and ordering; the public catalog has a legacy-column fallback, but production should still apply all pending migrations.

## Validation

- `php artisan route:list`
- `php artisan test`
- `curl http://127.0.0.1:8000/health`

## Bluehost deployment & optimization

Deployments are incremental by default: `scripts/deploy-bluehost.ps1` maintains a SHA256 checksum manifest (`.deploy-manifest.json`) on the remote server and uploads **only changed files**, skipping unchanged vendor dependencies and assets.

- **Fast / Incremental deployment (default)**: `.\scripts\deploy-bluehost.ps1`
- **Ultra-fast deployment (skips `vendor/` check)**: `.\scripts\deploy-bluehost.ps1 -SkipVendor`
- **Force full re-upload**: `.\scripts\deploy-bluehost.ps1 -Force`

## Project status

The Laravel application includes the public property catalog and booking flow, payment integrations, reservation emails, message notifications, property media and calendar management, and a role-protected admin workspace.

Queued new-message and reservation email notifications are sent automatically every minute by the Laravel scheduler (`php artisan messages:send-email-notifications --limit=50`), as long as the single scheduler cron entry below is configured. You do not need a separate cron line per command — `schedule:run` dispatches every command registered in `routes/console.php` whose interval is due. You can also run it manually at any time.

Reservation lifecycle automation is available with `php artisan reservations:complete-past`. It moves confirmed or checked-in reservations to `completed` after their checkout date, using `CHECKOUT_COMPLETION_GRACE_HOURS` (default: 6), and queues a status email.

Google review imports can be synchronized manually with `php artisan reviews:sync-google` or for one establishment with `php artisan reviews:sync-google --establishment=12`. Each establishment can configure a CSV/JSON source URL in its Reviews tab; the Laravel scheduler checks configured sources every six hours and records the last success or error.

This single cron entry drives every scheduled task above (email queue, past-reservation completion, calendar feed sync, Google review sync). On Bluehost (or any host), add exactly one cron line running every minute:

```cron
* * * * * cd /path/to/hospitality-os && php artisan schedule:run >> /dev/null 2>&1
```

Do not add separate cron entries for `messages:send-email-notifications` or the other commands — they are triggered by `schedule:run` based on the intervals set in `routes/console.php`. You can verify what is scheduled and when it will next run with `php artisan schedule:list`.

The Google source URL is separate from the public Google review link sent to guests. The public link opens Google's own review flow; the application does not publish reviews to Google automatically.

The admin workspace supports dashboard metrics, reservation filtering and CRUD, user management, property and establishment management, reviews, site parameters, and user preferences. Run the full test suite with `php artisan test` before deploying changes.
