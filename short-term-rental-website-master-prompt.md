# Master Prompt — Short-Term Rental Website for Bluehost

Copy this entire prompt into a capable coding agent. Attach the sample customer-facing HTML pages, design assets, and the four property photo folders in the same session.

---
<!-- End of prompt -->
## Role and objective

You are a senior full-stack product engineer, database architect, UX engineer, security reviewer, and deployment engineer. Build a complete, production-ready but deliberately practical short-term rental website that can be committed to GitHub and deployed to Bluehost shared hosting through SFTP.

This is an implementation assignment, not a request for a high-level plan or isolated code snippets. Create the working repository, database scripts, application code, tests, documentation, seed data, deployment scripts, and operational instructions. Do not claim a feature is complete unless it is implemented and tested.

The website will let visitors browse and book rental properties, either with an account or as a guest. Staff will use a secure administration area to manage establishments, properties, photos, prices, availability, external calendars, reservations, users, and limited concierge access.

## Inputs I will provide

Inspect all supplied files before implementing anything:

1. Sample customer-facing pages, likely including a home page and property-details page. Use them as the visual source of truth for layout, typography, colors, spacing, navigation, cards, buttons, and responsive behavior.
2. Four folders of real property photos.
3. Property information when available: establishment, property names, locations, descriptions, occupancy, room counts, amenities, prices, fees, policies, and contact information.
4. Brand assets such as the logo, favicon, and preferred colors, if available.
5. Initial external iCalendar URLs from Airbnb, Booking.com, or other channels, if available.

First produce a short input inventory that identifies what was supplied, what can be safely derived from it, and what information is missing. Do not invent property facts from photographs. When metadata is missing, use clearly labelled draft values in seed configuration and record every item requiring replacement in `docs/content-todo.md`.

## Project configuration

Use these values if I have not provided replacements:

- Site name: `Afrik Appart` (confirm or make configurable)
- Application code name: `afrikappart`
- Primary domain: `[TO BE PROVIDED]`
- Default locale: `fr`
- Secondary locale: `en`
- Default timezone: `Africa/Porto-Novo` unless property data specifies another timezone
- Default currency: `XOF` unless property data specifies another currency
- Booking mode: instant confirmation when all availability checks pass
- Payment mode for version 1: `pay_later`; do not collect or store card information
- External-calendar freshness threshold before final booking: 15 minutes
- External-calendar sync schedule: every 15 minutes
- Admin notification address: `[TO BE PROVIDED]`
- Customer support address: `[TO BE PROVIDED]`
- SMTP provider and credentials: environment variables, `[TO BE PROVIDED]`
- Bluehost cPanel username, SFTP host, remote paths, and database credentials: secrets/configuration only, never committed

Make locale, currency, timezone, booking mode, calendar freshness, admin recipients, fees, taxes, minimum stays, and payment mode configurable rather than hard-coded.

## Fixed technical constraints

Use the following implementation unless an attached hosting specification proves it is incompatible:

- PHP 8.3.
- Laravel 12 with Blade templates.
- MySQL 8.x using InnoDB, `utf8mb4`, foreign keys, indexes, and strict SQL behavior.
- Composer 2 with locked dependencies.
- Bootstrap 5 or a lightweight, locally compiled CSS layer that accurately reproduces the supplied sample pages. Avoid a large client-side framework.
- Small, progressive JavaScript modules for calendars, image galleries, forms, and admin interactions. The production site must not require a Node.js server.
- Vite/Node may be used during development and CI to compile static assets; deploy the compiled assets to Bluehost.
- Use Laravel authentication and authorization primitives. Do not hand-roll cryptography.
- Use a mature Composer library such as `sabre/vobject` for iCalendar parsing and generation rather than implementing the iCalendar standard with fragile string parsing.
- Use SMTP for transactional email. Do not rely on unconfigured PHP `mail()` behavior.
- Store photos as files, not database blobs.
- Store secrets only in `.env` or GitHub/hosting secrets. Commit `.env.example`, never `.env`.

If a package choice changes, explain the compatibility reason in an Architecture Decision Record and retain all required behavior.

## Hosting-aware repository and runtime layout

Create a conventional Laravel repository for local development, plus build scripts that produce a Bluehost-safe release split:

```text
repository/
  app/
  bootstrap/
  config/
  database/
    migrations/
    seeders/
    schema.sql
    seed.sql
    sample-data.sql
  docs/
  public/
    build/
    uploads/
      properties/
  resources/
  routes/
  scripts/
  seed-assets/
    properties/
  storage/
  tests/
  .github/workflows/
  .env.example
  .gitignore
  composer.json
  composer.lock
  package.json
  package-lock.json
  README.md
```

The production release must support this secure hosting layout:

```text
/home/<CPANEL_USER>/apps/afrikappart/       # application, vendor, config, storage, .env
/home/<CPANEL_USER>/public_html/            # only public web files
/home/<CPANEL_USER>/public_html/uploads/
  properties/                               # persistent user-uploaded property media
```

Generate the production `public_html/index.php` with correct paths to the application outside the public web root. Treat `<CPANEL_USER>`, application path, and public web root as deployment variables. If the Bluehost plan or domain setup forces a different document root, document an alternate layout that still prevents `.env`, application code, logs, database exports, and private files from being web-accessible.

## Customer-facing experience

Recreate the visual language of the attached sample pages using reusable layouts and components. Do not merely paste static HTML. Preserve the samples' identity while replacing static data with database content.

Implement these public pages and flows:

1. Home page
   - Hero section and search form.
   - Featured establishments and/or properties.
   - Location or destination highlights if supplied.
   - Trust, amenity, or service highlights.
   - Clear calls to browse and book.
   - Responsive header, navigation, and footer.

2. Browse/listings page
   - Grid/list of published properties.
   - Filters for destination/location, check-in, check-out, guest count, bedrooms, amenities, and price range.
   - Sort by recommended, price low-to-high, and price high-to-low.
   - Only return properties that can accommodate the guests and are available for the entire requested stay.
   - Pagination with filters preserved.
   - Useful empty and error states.

3. Property-details page
   - Image gallery/lightbox with optimized responsive images and meaningful alt text.
   - Property name, establishment, location, description, capacity, rooms/beds/baths, amenities, house rules, check-in/out times, and map/coordinates when configured.
   - Availability date picker.
   - Price summary for selected dates, including nightly charges, fees, taxes, discounts, and total.
   - Minimum-stay and maximum-occupancy validation.
   - Booking call to action.
   - Do not expose exact private access instructions before a reservation is appropriately confirmed.

4. Booking/checkout flow
   - Accept authenticated and guest bookings.
   - Capture lead guest name, email, phone, guest count, optional message, check-in/out dates, and acceptance of terms/privacy/cancellation policy.
   - Recalculate price and revalidate occupancy and availability on the server; never trust browser totals or availability.
   - Display an itemized total and configured currency.
   - Use idempotency protection so refreshing or resubmitting does not create duplicate reservations.
   - After a successful transaction, show a confirmation page with a non-sequential public booking reference.
   - For `pay_later`, label the payment status clearly and never imply that payment was captured.

5. Customer authentication and self-service
   - Registration, login, logout, email verification, forgotten password, and password reset.
   - Customer dashboard with upcoming and past reservations.
   - Reservation detail with status, dates, property, amount, and contact information.
   - A verified user may claim/link prior guest reservations that use the same verified email address.
   - Guest confirmations use a signed, expiring access link or reservation reference plus email verification; never expose a booking by a guessable ID.

6. Supporting pages
   - Contact page.
   - Privacy policy, terms, cancellation policy, cookie notice as applicable, and house-rule content driven by configuration.
   - Custom 403, 404, 419, 422, 429, and 500 states.

## Establishments and properties

Model an establishment as the parent organization, building, residence, hotel, or rental location. One establishment can contain multiple separately bookable properties/units.

Each establishment must support:

- Name, slug, status, public description, address, city, region, country, postal code, latitude/longitude, timezone, contact email, contact phone, check-in/out defaults, policies, logo/cover image, and admin notification recipients.
- Draft/published/archived state.
- Assigned administrators and concierges.

Each property must support:

- Parent establishment.
- Name, slug, internal code, status, short and long description.
- Property type.
- Maximum guests, adults/children rules if configured, bedrooms, beds, bathrooms, and size/unit.
- Address override when different from the establishment.
- Property timezone and currency, inherited from establishment/site when absent.
- Base nightly rate, cleaning fee, other fixed/percentage fees, tax rate(s), refundable deposit metadata, minimum/maximum nights, check-in/out times, and booking mode.
- Amenities/features through a reusable amenity catalog.
- House rules and cancellation text.
- Search/SEO title and description.
- Ordered photo gallery with cover image and alt text.
- Featured/published flags.

Use slugs for public URLs and immutable numeric or UUID/ULID identifiers internally. Enforce unique slugs and stable public booking references.

## Pricing rules

Build an understandable pricing engine with:

- Base nightly price per property.
- Date-range/seasonal rules with optional day-of-week applicability, priority, nightly amount, minimum stay, and optional label.
- Specific-date overrides.
- Cleaning and other configurable fixed or percentage fees.
- Configurable taxes.
- Optional long-stay discount rules, disabled by default.
- A stored booking price snapshot so historical reservations never change when future rates are edited.
- Decimal arithmetic and integer minor units or appropriately precise `DECIMAL` columns; never use binary floating-point for money.
- No automatic exchange-rate conversion in version 1. Display and transact in the property's configured currency.

Document the exact order of operations and rounding policy. Use the same pricing service for search estimates, property quotes, checkout validation, reservation creation, admin views, and emails.

## Availability and double-booking prevention

Availability is a core correctness requirement. Check-in is inclusive and check-out is exclusive. A stay from June 1 to June 3 occupies the nights of June 1 and June 2.

A property is unavailable if any requested night conflicts with:

- An active internal reservation or unexpired booking hold.
- An administrator-created availability block.
- An imported active event from any enabled external calendar.
- Any other configured inventory restriction.

Implement all of the following:

1. Availability service shared by public search, property details, checkout, reservation creation, and admin tools.
2. Database-backed reservation-night rows with a unique constraint on `(property_id, stay_date)` for active internal bookings/holds, or an equivalently strong database-level mechanism.
3. A booking transaction that locks the relevant property/inventory row, rechecks every conflict source and rate, inserts the reservation and occupied nights atomically, then commits.
4. Every process that mutates imported/admin availability must follow the same per-property locking protocol.
5. Pending holds, if enabled later for online payments, need an expiry time and cron cleanup. Expired holds must release inventory safely.
6. Admin blocks have check-in/check-out-style ranges, reason, internal notes, creator, and audit trail.
7. Cancelled/expired reservations no longer block nights, while their historical records remain intact.

Add automated tests that attempt simultaneous bookings for the same property and dates and prove that only one can succeed.

## Airbnb, Booking.com, and third-party calendar synchronization

Use standard iCalendar (`.ics`) import/export. Do not claim to implement proprietary Airbnb or Booking.com APIs.

### External calendar imports

- Allow an authorized admin to add multiple calendar feed URLs to each property.
- Fields: channel/provider, display name, encrypted feed URL, enabled state, sync interval, last attempted sync, last successful sync, ETag, Last-Modified, last error, and consecutive failure count.
- Fetch enabled calendars with strict timeouts, response-size limits, HTTPS by default, redirect limits, and SSRF protection. Reject localhost, link-local, private/reserved IP destinations, unsupported schemes, and URLs that resolve to unsafe networks.
- Parse recurring and all-day events correctly using a maintained library. Preserve external UID and source calendar identity.
- Store normalized imported events with start date inclusive and end date exclusive, source checksum, status, and last-seen timestamp.
- Synchronization must upsert changed events and safely remove or deactivate events no longer present after a complete successful fetch. Never erase prior data after a partial/failed fetch.
- Use conditional requests with ETag/Last-Modified when supported.
- Provide a manual “Sync now” action and a sync history/status screen.
- Schedule normal synchronization every 15 minutes through Laravel's scheduler and a Bluehost cron entry.

### Final booking validation

- Before a booking is committed, every enabled external calendar for that property must have a successful sync within the configurable freshness threshold.
- If a feed is stale, attempt an on-demand refresh before opening the final database transaction.
- If any required feed cannot be brought within the freshness threshold, fail closed: do not auto-confirm the booking. Show a helpful message and log the operational error without exposing feed URLs or credentials.
- After the refresh, acquire the property/inventory lock, confirm all calendars remain fresh, recheck availability, and create the booking atomically.

### Calendar exports

- Give each property a revocable, high-entropy public iCalendar export URL, for example `/calendars/{random_token}.ics`.
- Export confirmed/internal reservations and admin blocks needed to block inventory in external channels.
- Use neutral event summaries such as `Unavailable`; never export guest names, emails, phone numbers, prices, or private notes.
- Do not re-export events imported from third-party feeds, to reduce synchronization loops.
- Generate stable UIDs, timestamps, correct all-day start/end values, and appropriate cache headers.
- Let an admin regenerate/revoke an export token and copy the URL.
- Include exact setup instructions showing where to paste the site's export URL in Airbnb/Booking.com and where to paste their export URLs into this site. Clarify that iCalendar synchronization is periodic and cannot guarantee real-time channel-manager behavior.

## Reservations and payments

Reservation statuses must include at least `pending`, `confirmed`, `cancelled`, `completed`, and `expired`. Payment statuses must include at least `not_required`, `unpaid`, `pending`, `paid`, `failed`, `partially_refunded`, and `refunded`, even though version 1 defaults to `pay_later`.

Persist:

- Public booking reference and internal ID.
- Property and establishment.
- Optional user account and guest contact snapshot.
- Check-in/out, guest counts, locale, currency, source, status, payment status, and timestamps.
- Price snapshot: nightly line items/rules, subtotal, discounts, fees, taxes, deposit, total, amount paid, and balance.
- Terms/cancellation version accepted, consent timestamp, IP metadata only when legally appropriate, and internal/admin notes separate from guest-visible notes.
- Cancellation metadata and audit history.

Create a payment-provider interface but make `pay_later` the production default. Do not store card data. Include a documented extension point for a hosted provider such as Stripe Checkout, with verified webhooks and idempotency, but do not present that integration as complete unless it is actually implemented and tested. Keep payment choice separate from booking availability logic.

## Transactional email

Send email notifications for every successfully created reservation:

- Customer confirmation with booking reference, property, dates, guests, price breakdown, payment status, policies, and a secure reservation link.
- Admin notification to the property's/establishment's configured recipients with reservation details and an authenticated admin link.
- Guest and admin cancellation messages when a reservation is cancelled.
- Account verification, password-reset, and staff-invitation messages.

Write email records to a database outbox in the same transaction as important state changes, then send them through a scheduled worker with retries. An SMTP outage must not roll back a valid reservation. Track attempts, last error, sent time, and final failure without storing unnecessary sensitive content. Provide responsive HTML and plain-text templates in French and English, falling back to the configured default language.

Use environment variables for SMTP host, port, encryption, username, password, sender name, sender address, reply-to, and admin fallback recipient. Add a local mail-capture option for development.

## Admin and concierge portal

Create a secure `/admin` area with a practical responsive interface. There is no public staff registration.

### Roles and scoping

Implement least-privilege authorization, scoped to assigned establishments/properties:

- `super_admin`: complete system access, settings, staff invitations, role assignments, audit logs, and all establishments.
- `establishment_admin`: manage assigned establishments, properties, photos, amenities, rates, fees, taxes, calendar feeds, blocks, reservations, and staff within the allowed scope.
- `concierge`: read assigned upcoming/current reservation details and guest contact information, record a contact note or status, and use approved contact actions. Cannot change prices, property content, calendars, availability blocks, permissions, system settings, or delete data.
- `customer`: public account and own reservations only.

Protect every route and query on the server. Hiding a button is not authorization. Add tests for cross-establishment data isolation and privilege escalation.

### Admin features

Implement:

1. Dashboard with upcoming arrivals/departures, current stays, recent bookings, stale/failed calendar feeds, and simple occupancy/booking counts.
2. Establishment CRUD with draft/published/archive flow.
3. Property CRUD with all fields listed above.
4. Photo upload, preview, reordering, cover selection, alt text editing, and deletion.
5. Amenity catalog and property amenity assignment.
6. Base price, rate rules, date overrides, fees, taxes, minimum stay, and booking-mode management.
7. Month/week availability calendar combining reservations, admin blocks, and imported events with clear source labels.
8. Add/edit/remove admin blocks.
9. External-calendar setup, manual sync, export link, last status, errors, and sync history.
10. Reservation list, filters, detail, status changes, cancellation, internal notes, and contact history. Destructive/status actions require confirmation and audit entries.
11. Staff invitation by email with expiring single-use token, role, and scope. Allow deactivation and access revocation.
12. Site settings and establishment-specific notification recipients.
13. Audit log visible to super admins, with actor, action, target, timestamp, and safe before/after metadata.

Do not implement hard deletion for financial/reservation history. Archive or deactivate records unless a privacy-compliant deletion/anonymization workflow explicitly applies.

## Photo storage and four-property seed

All public property images must live under this persistent web-root subfolder:

```text
/public_html/uploads/properties/<property-id-or-slug>/
```

The repository-equivalent local path is:

```text
public/uploads/properties/<property-id-or-slug>/
```

Requirements:

- Store only relative file paths and metadata in MySQL; never store image blobs or machine-specific absolute paths.
- Validate actual MIME type, extension, file size, and image dimensions.
- Accept JPEG, PNG, and WebP; reject executable/polyglot or malformed uploads.
- Generate collision-resistant filenames and responsive sizes/thumbnails. Preserve good source quality and orientation; strip unsafe/unnecessary metadata.
- Add `.htaccess` rules that disable PHP/script execution and directory listing in uploads.
- Prevent path traversal and unauthorized file replacement/deletion.
- Exclude `public/uploads/**` from destructive code deployment while retaining `.gitkeep` as needed.
- Provide configurable upload size/dimension limits and clear errors.

For the supplied four properties:

1. Build `seed-assets/properties/manifest.example.json` and a real manifest populated from supplied metadata.
2. Create an idempotent import command/script that copies each folder's photos into the correct public upload directory, creates optimized variants, and inserts/updates `property_images` rows in deterministic order.
3. Seed the establishments, four properties, amenities, initial rates, and image metadata.
4. If names/descriptions/prices are missing, use clearly marked draft values and record them in `docs/content-todo.md` rather than guessing.
5. The importer must be safe to rerun without duplicating image rows.

## Database deliverables

Provide both Laravel migrations/seeders and standalone SQL that can be imported with Bluehost phpMyAdmin.

Required files:

- `database/schema.sql`: complete clean-install schema, no hard-coded database name, no production `DROP DATABASE`, and no credentials.
- `database/seed.sql`: essential roles, settings, amenities, and other reference data; safe/idempotent where practical.
- `database/sample-data.sql`: initial establishment/four-property records when the supplied metadata makes this possible.
- `database/migrations/*`: versioned application migrations matching the SQL schema.
- `database/seeders/*`: framework seeders matching the seed scripts.
- `docs/database.md`: entity descriptions, data types, constraints, indexes, date-range conventions, money conventions, import order, backup, restore, and upgrade steps.
- `docs/data-model.mmd`: Mermaid ER diagram source.

At minimum model the following concepts, normalized where practical:

- Users, password resets/session/authentication support, roles, staff invitations, and establishment/property assignments.
- Establishments and properties.
- Amenities and property amenities.
- Property images.
- Rate rules, date overrides, fees, and taxes.
- Reservations, reservation guests/contact snapshot, reservation nights, reservation price lines, and reservation status history.
- Admin availability blocks.
- External calendars, imported events, imported event occurrence/nights if required, and sync runs.
- Email outbox/delivery attempts.
- Settings, contact notes, and audit logs.

Use appropriate foreign-key actions. Add indexes for all foreign keys and common searches, including slugs, published status/location, reservation reference/email/date/status, property/date availability checks, external event UID/source, calendar freshness, and queued email status. Protect personally identifiable information and avoid duplicating it unnecessarily.

The SQL schema and Laravel migrations must not drift. Add a test or documented verification that compares a clean migration result with the intended schema.

## Security, privacy, and reliability

Implement and document:

- HTTPS-only production behavior after the Bluehost SSL certificate is active.
- Secure, HTTP-only, SameSite cookies; production session/domain settings.
- CSRF protection on all state changes.
- Server-side validation, prepared/query-builder database access, contextual output escaping, and safe rich-text handling.
- Strong password hashing through Laravel defaults, email verification, reset-token expiration, login throttling, and session regeneration.
- Rate limits for login, reset, booking, availability, contact, calendar export, and other abuse-prone routes.
- Honeypot and/or equivalent low-friction spam control for public forms.
- Authorization policies for every admin/customer resource.
- Secure random tokens for booking references, invitations, guest links, and calendar exports.
- Encryption at rest through the application key for external calendar URLs and similarly sensitive configuration.
- Safe file uploads as described above.
- No secrets or personal data in repository, client-side code, public logs, calendar exports, or error pages.
- Production logging with rotation/retention suitable for shared hosting.
- User-friendly failures and internal correlation IDs.
- An audit trail for staff actions affecting access, availability, rates, properties, and reservations.
- Configurable data-retention/anonymization guidance. Do not fabricate legal compliance; label policies requiring owner/legal review.
- Security headers including CSP appropriate to actual assets, HSTS only after HTTPS is verified, frame restrictions, referrer policy, and MIME-sniffing protection.
- Backup/restore procedures for both MySQL and persistent uploads.

Do not use `chmod 777`. Document minimal writable permissions for Laravel storage/cache and public uploads.

## Accessibility, responsiveness, performance, and SEO

- Mobile-first behavior and support for current desktop/tablet/mobile browsers.
- WCAG 2.2 AA-oriented implementation: semantic landmarks, labels, keyboard operation, visible focus, adequate contrast, accessible validation, meaningful alt text, and reduced-motion support.
- Avoid layout shifts; lazy-load noncritical images and set dimensions.
- Generate responsive WebP/JPEG variants and use `srcset` where useful.
- Cache public property pages and reference data without caching user/private responses. Invalidate relevant caches when admins publish changes.
- Minify/version static assets and use production-optimized Composer autoloading.
- Human-readable canonical URLs, unique metadata, Open Graph tags, XML sitemap, robots.txt, and appropriate schema.org structured data.
- Do not index admin, account, checkout, confirmation, or guest-reservation pages.

## Testing requirements

Use PHPUnit/Pest and browser-level tests where appropriate. Tests must use a dedicated test database and must never point to production.

At minimum test:

1. Authentication, email verification, password reset, guest booking, and account booking.
2. Role/scope authorization, including cross-establishment denial and concierge restrictions.
3. Pricing rule precedence, fees, taxes, rounding, minimum stays, and immutable booking snapshots.
4. Check-in-inclusive/check-out-exclusive date behavior.
5. Internal reservation conflicts, admin blocks, imported calendar conflicts, and cancelled reservation release.
6. Simultaneous requests for the same property/dates proving no double booking.
7. Fresh, stale, failed, malformed, empty, changed, and removed external iCalendar feeds.
8. Correct iCalendar export, stable UID, no imported-event echo, and no PII leakage.
9. Email outbox creation, retry, customer/admin recipients, localization, and SMTP failure behavior.
10. Image upload authorization, MIME/size validation, processing, safe paths, reordering, and deletion.
11. Idempotent checkout submission, idempotent seed/photo import, and idempotent calendar sync.
12. Core public pages on mobile and desktop, form accessibility, and critical error states.

Provide fixtures for iCalendar tests without depending on live Airbnb/Booking.com feeds. Mock remote HTTP and SMTP services in automated tests.

## Local development deliverables

Provide exact setup for Windows PowerShell and macOS/Linux:

- Required PHP extensions and version checks.
- Composer and Node/npm install commands.
- `.env` creation and key generation.
- MySQL database creation/import/migration.
- Local storage/upload directory setup.
- Asset build commands.
- Local server start.
- Queue/scheduler commands.
- Test and lint/static-analysis commands.
- Optional Docker Compose for MySQL and a mail-capture service, while keeping native local setup documented.

Add `scripts/setup-local.ps1` and `scripts/setup-local.sh` where safe and practical. Scripts must stop on errors and must not silently destroy an existing database.

## GitHub repository requirements

Create:

- A clean `.gitignore` excluding `.env`, credentials, logs, caches, local databases, generated archives, uploaded customer/property media, and IDE files.
- `.env.example` with safe placeholders and comments for every setting.
- Locked PHP and JavaScript dependencies.
- A branch-neutral README with setup, test, build, release, deploy, rollback, cron, backup, and troubleshooting instructions.
- GitHub Actions CI on pull requests/pushes for dependency install, lint/static analysis, tests, and frontend build.
- A separate deployment workflow for controlled deployment from `main`, preferably with manual approval/environment protection for production.
- No secrets printed to logs.
- Dependabot or equivalent optional dependency-update configuration.

## Bluehost deployment by SFTP

Provide both a manual deployment path and a GitHub Actions deployment path. SFTP transfers files but cannot itself execute database migrations or clear server caches; document those as separate controlled steps rather than pretending SFTP performs them.

### Bluehost preparation checklist

Document these exact actions:

1. Confirm the hosting plan, domain document root, PHP 8.3 availability/extensions, SSH/SFTP access, cron support, MySQL version, storage, and email/SMTP choice.
2. Activate SSL and confirm HTTPS before enabling strict redirects/HSTS.
3. Create a MySQL database and least-privilege database user in Bluehost/cPanel; assign required privileges.
4. Set the domain PHP version and required PHP extensions.
5. Enable SSH access so SFTP can be used on shared hosting; use port 22 unless Bluehost/account configuration says otherwise.
6. Create the application directory outside `public_html`, the web-root directory, persistent upload directory, storage directories, and safe permissions.
7. Create the production `.env` outside the web root, including `APP_ENV=production`, `APP_DEBUG=false`, a unique application key, production URL, database, session, SMTP, calendar, and notification settings.
8. Configure Bluehost cron to invoke Laravel's scheduler every minute with the account's actual PHP binary and absolute path. The application scheduler should run calendar sync and email-outbox processing at their configured intervals, with overlap prevention.

### Build/release scripts

Create `scripts/build-release.sh` and `scripts/build-release.ps1` that:

- Start from a clean workspace or clearly report uncommitted files.
- Install production Composer dependencies with optimized autoloading.
- Install locked npm dependencies and compile production assets.
- Run tests before packaging unless explicitly overridden.
- Produce two staging folders: `release/app` and `release/public`.
- Include `vendor` and compiled assets so the Bluehost server does not need Node and can be deployed even when Composer is unavailable remotely.
- Exclude `.env`, tests, source maps unless intended, development dependencies, logs, caches, seed photo sources, local tools, and secrets.
- Never package persistent uploads or overwrite the production `.env`.
- Generate checksums and a release manifest containing commit SHA/build time without secrets.

### Manual first deployment

Document:

1. How to connect with FileZilla or another SFTP client using the Bluehost host/domain, port 22, cPanel username, and credentials/key.
2. Upload `release/app` into `/home/<CPANEL_USER>/apps/afrikappart/` while preserving the production `.env` and writable storage.
3. Upload `release/public` into the domain's web root while preserving `/uploads`.
4. Import `database/schema.sql` then seed files through phpMyAdmin for a clean installation, or run `php artisan migrate --force` and the approved production seeder through SSH.
5. Create the first super admin using a safe CLI command. If SSH is unavailable, provide a documented one-time setup procedure with a high-entropy setup token, automatic disabling after success, expiration, and a prominent instruction to remove/disable it. Never ship a default admin password or password hash.
6. Clear/build Laravel caches using SSH where available. If unavailable, ensure the uploaded release already contains compatible production caches or provide a safe admin maintenance action that cannot execute arbitrary commands.
7. Verify writable paths, homepage, health endpoint, database access, login, uploads, cron, SMTP, calendars, booking, emails, SSL, and logs.
8. Remove any temporary setup/import files from the web root.

### GitHub Actions SFTP deployment

Create `.github/workflows/deploy-bluehost-sftp.yml` with a clear, reviewed implementation. It must:

- Trigger through manual dispatch and optionally after CI succeeds on `main`; use a protected GitHub environment named `production`.
- Build and test the same release artifact as the local scripts.
- Use SFTP on port 22 by default. An `lftp` SFTP mirror or an equivalently maintained approach is acceptable.
- Use GitHub environment secrets such as `BLUEHOST_SFTP_HOST`, `BLUEHOST_SFTP_PORT`, `BLUEHOST_SFTP_USERNAME`, `BLUEHOST_SFTP_PASSWORD` or a key-based equivalent, `BLUEHOST_APP_PATH`, and `BLUEHOST_PUBLIC_PATH`.
- Validate that remote target paths are nonempty, explicit, expected account paths before any mirror/delete behavior.
- Upload application and public release folders separately.
- Exclude/preserve `.env`, storage logs/cache as appropriate, `public/uploads`, backups, and other persistent content.
- Avoid `--delete` by default. If stale-file deletion is necessary, require a reviewed allowlist/exclusion strategy and dry-run instructions so persistent files cannot be erased.
- Mask credentials and avoid verbose commands that expose secret URLs such as external calendar feeds.
- Upload a release manifest.
- Leave database migration and cache activation as a clearly displayed post-deployment step unless a separately authenticated SSH job is explicitly configured.
- Include a rollback procedure based on a prior release backup/artifact. Back up the database before schema changes.

Do not commit real Bluehost credentials. Do not use plain FTP when SFTP is available.

## Operations and observability

Create:

- An authenticated admin system-health screen showing database connectivity, writable paths, scheduler heartbeat, last successful email job, failed email count, and stale/failed calendar feeds. Never reveal secrets.
- A minimal public health endpoint that exposes no sensitive details.
- Structured application logs with correlation IDs.
- Admin alerts or visible dashboard warnings for repeated calendar/email failures.
- `docs/runbook.md` covering backup, restore, expired SSL, failed calendar feeds, failed email, stuck queue, full disk, application error, compromised credentials, staff offboarding, and rollback.
- `docs/bluehost-deployment.md` with copy/paste-ready commands containing placeholders.

## Required documentation and final handoff

Deliver at least:

- `README.md`
- `docs/architecture.md`
- `docs/database.md`
- `docs/data-model.mmd`
- `docs/availability-and-ical.md`
- `docs/roles-and-permissions.md`
- `docs/security.md`
- `docs/bluehost-deployment.md`
- `docs/runbook.md`
- `docs/content-todo.md`
- `docs/acceptance-test-checklist.md`
- `CHANGELOG.md`

At the end, provide:

1. A concise summary of what is implemented and what is deliberately deferred.
2. Final repository tree.
3. Exact local setup and test commands.
4. Exact database installation choices: phpMyAdmin SQL import and Laravel migration.
5. Exact manual SFTP deployment steps.
6. Exact GitHub Actions secret names and deployment trigger.
7. Exact Bluehost cron entry template.
8. The first-admin setup procedure.
9. A deployment verification checklist.
10. Known limitations, security considerations, and remaining content decisions.

## Acceptance criteria

The project is acceptable only when all of the following are true:

- A clean checkout can be configured locally from the README and the test suite passes.
- A clean MySQL database can be created from `schema.sql` and populated from the seed scripts.
- Migrations and SQL scripts describe the same effective schema.
- The supplied four properties and their real photos can be imported idempotently into the required upload subfolders and displayed on the site.
- The public home, listing, details, checkout, confirmation, login, registration, and account-history pages are responsive and visually consistent with the samples.
- A guest and a registered customer can each create a reservation.
- The server independently verifies occupancy, pricing, policies, and availability.
- Internal bookings, admin blocks, and every enabled imported iCalendar feed participate in availability.
- A stale or failed required calendar prevents automatic confirmation.
- Concurrent booking tests prove no double booking.
- Each property exposes a revocable, privacy-safe iCalendar feed that does not echo imported events.
- Customer and admin emails are queued and sent, and SMTP failure does not invalidate the booking.
- Super-admin, establishment-admin, concierge, and customer access are correctly scoped and tested.
- Property photos can be securely uploaded, ordered, optimized, and removed without being stored in MySQL.
- Production secrets, uploads, logs, and application code are not publicly exposed or committed.
- The release can be uploaded to Bluehost by SFTP without requiring a running Node service.
- Deployment preserves `.env`, uploads, and other persistent runtime data.
- The README contains safe first-deploy, migration, cron, backup, verification, and rollback steps.

## Working method

Proceed in this order:

1. Inspect inputs and create the content/requirements inventory.
2. Record assumptions and Architecture Decision Records.
3. Scaffold the repository and local environment.
4. Implement and verify database/migrations/seed import.
5. Implement authentication and authorization.
6. Implement establishment/property/media/admin features.
7. Implement pricing and availability with concurrency protection.
8. Implement iCalendar imports, exports, cron, and failure handling.
9. Implement reservations, guest/account flows, and email outbox.
10. Recreate and finish the customer-facing UI from the samples.
11. Add tests, security hardening, accessibility, and performance work.
12. Build and validate the Bluehost release/deployment workflow.
13. Run the full test/build suite and complete the acceptance checklist.

Do not stop after scaffolding. If a noncritical business value is unknown, choose the documented default, make it configurable, and add it to `docs/content-todo.md`. Ask me only when a missing decision would create a security problem, make data incompatible, or fundamentally change the booking/payment workflow.

---

## Owner decisions to revisit before production

Use defaults so development can begin, but make these decisions visible in the final handoff:

1. Final site/domain name, logo, brand colors, and whether the attached samples are authoritative at all breakpoints.
2. French only, English only, or both; final translated property/policy content.
3. Default and per-property timezone/currency.
4. Instant booking versus admin approval per property.
5. Pay later versus online payment; if online, provider, deposit percentage, currencies, refund/cancellation behavior, and webhook account.
6. Taxes, fees, security deposit, minimum stays, discounts, and rounding rules.
7. Cancellation/refund and privacy/retention policies for legal review.
8. SMTP provider, sender domain authentication, admin recipients, and reply-to behavior.
9. Initial super admin and concierge users and their establishment scopes.
10. Property metadata and the exact mapping/order/alt text for all four photo folders.
11. Initial Airbnb/Booking.com/other calendar URLs and desired sync threshold.
12. Bluehost plan, cPanel username, domain document root, available PHP/MySQL versions, and whether SSH is enabled.

---

