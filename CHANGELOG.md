## M14 — Customer self-service and support pages

- Added password reset request and reset flows using the Laravel password broker and email outbox pattern.
- Added support and legal pages for contact, privacy, terms, and cookies without inventing unverified owner-specific legal language.
- Verified the self-service flow with feature tests covering password reset and public support pages.

## M13 — Customer dashboard and reservation detail views

- Added authenticated customer reservation summaries and a booking detail page tied to the logged-in user.
- Added account-scoped reservation lookup and dashboard route wiring for a secure post-booking experience.
- Verified the dashboard and detail flow with dedicated customer-facing feature tests.

## M12 — GitHub CI and deployment automation

- Added CI validation for PHP and frontend builds as well as a guarded deployment workflow for the Bluehost/SFTP release path.
- Added Dependabot automation for dependency monitoring.
- Kept provider credentials and deployment environment details in GitHub Actions secrets rather than the app source.

## M11 — Deployment build and local setup automation

- Added production release scripts and local bootstrap automation for Linux/macOS and PowerShell.
- Added build and packaging instructions for a deployment-safe app/public bundle without checking in secrets.
- Verified the release packaging logic and environment-safe deployment defaults.

## M10 — Production hardening and safe error handling

- Added global security headers, custom error pages, and production-safe URL defaults.
- Hardened the app against common browser and deployment issues without introducing live external dependencies.
- Verified custom error rendering and security headers with feature tests.

## M09 — Payment provider abstraction and checkout

- Added a gateway-neutral payment abstraction, checkout flow, and payment-attempt records.
- Added sandbox/offline adapter support for default pay-later, FedaPay, and PayPal patterns without permanent live secrets.
- Verified the checkout and payment workflow with feature tests.

## M08 — Reservation lifecycle and transactional email

- Added reservation lifecycle status handling, admin review actions, and queued email outbox integration.
- Added the admin reservation management flow and transactional email triggers for guest-facing booking events.
- Verified reservation lifecycle and email queue handling with feature tests.

## M07 — iCalendar import, export, and freshness controls

- Added calendar import handling for external ICS feeds with deduplicated event records.
- Added admin-side calendar feed management and a property-level internal calendar export.
- Added stale-feed detection based on the last successful sync and calendar status metadata.
- Verified the calendar sync/export flow with feature tests covering feed import and admin restrictions.

## M06 — Property administration, pricing, and core availability

- Added the admin property management list and edit screens for property metadata and pricing changes.
- Added price rules and date-based availability block creation for staff-managed inventory control.
- Added an availability service that checks reservations and admin blocks before a date range is accepted.
- Verified the property admin, pricing, and availability flow with dedicated feature coverage.

## M05 — Authentication, authorization, and admin shell

- Added customer registration, login, logout, and the personal dashboard flow.
- Secured staff access behind a role-based admin guard and a dedicated admin dashboard shell.
- Seeded default admin and customer accounts for authenticated staff and customer access.
- Verified the auth flow, access controls, and related public app tests with targeted feature coverage.

# Changelog

## M03 — Optimized media and four-property catalog

- Added the media sync command for the supplied apartment folders and generated deterministic optimized WebP/JPEG variants.
- Created the initial media build scripts and the property manifest under `seed-assets/properties/`.
- Imported the four-property gallery into the local Laravel database without duplicating image rows.
- Verified the 58-image inventory, manifest integrity, and app smoke test coverage.

## M02 — Database foundation and seed framework

- Created the Laravel migration set for establishments, properties, amenities, media, pricing, reservations, and admin/system tables.
- Added seed data for reference records and the four draft properties.
- Documented the database schema and entity model in the app docs.
- Verified the schema with migration + seeding tests.

## M01 — Architecture and Laravel scaffold

- Created the Laravel 12 repository under `repository/` with a working local environment and Node asset build.
- Replaced the default Laravel landing page with a premium Afrik Appart home page and health route.
- Documented the architecture baseline and first ADR.
- Validated the scaffold with a feature test and successful production asset build.

## M00 — Input inventory and recovery baseline

- Confirmed the supplied HTML prototypes and photo source inventory.
- Recorded the working visual direction, supported defaults, and missing owner metadata.
- Created the recovery baseline files and package-ready checkpoint metadata.
- No application scaffold or production code was created yet.
