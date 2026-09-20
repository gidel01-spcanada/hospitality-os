## M26 - Audit coverage and consent-gated analytics

- Added explicit audit records for property amenity synchronization and host assignment/removal actions.
- Added consent-gated GA4/GTM configuration, disabled by default and excluded from private pages.
- Added privacy-safe public events for property selection, comparison, and booking intent.
- Prevented direct GA4 loading when GTM is configured to avoid duplicate tracking.

## M25 - Administrator audit journal

- Added automatic audit records for model creation, modification, and deletion events.
- Captured actor, object type and identifier, before/after values, URL, IP address, and user agent while excluding passwords and tokens.
- Added a paginated, filterable administrator-only journal at `/admin/audit-logs`.
- Reused the existing `audit_logs` schema for Bluehost compatibility.

## M24 — Search and operations reliability

- Canonicalized homepage property searches to redirect to `/properties` with establishment, destination, guest, and date filters preserved.
- Added an admin-only `/admin/logs` viewer for the latest bounded tail of application log files under `storage/logs`.
- Kept log paths server-controlled and denied log access to customers and non-admin staff.

## M23 — Post-stay review requests

- Added configurable post-stay review destinations per establishment: internal website reviews, Google, or both.
- Added a temporary signed review link to completed-stay status emails, with one internal review per reservation and property-level attribution.
- Added a configurable Google review URL redirect; Google publication remains user-driven because Google does not expose a backend API for posting reviews through this application.

## M22 — Marketplace property cards

- Reworked homepage and property-list cards with clearer marketplace hierarchy: property type, name, location, guests, bedrooms, beds, bathrooms, rating, and concise amenities.
- Added a `New listing` state when no active establishment reviews exist and contextual total-stay pricing when search dates are selected.
- Kept ratings explicitly based on active establishment reviews because current reviews are not linked to individual properties.
- Added a conditional flexible-cancellation signal to cards only when the establishment has no configured cancellation fee.
- Harmonized homepage cards with the property list by adding the same cover selection, photo counter, room tags, and gallery navigation.
- Added a date-aware `Available for your dates` signal to filtered property cards, backed by the existing availability query.
- Added optional property-level review attribution with establishment-level fallback for legacy reviews, so cards and property pages no longer imply a property-specific score when only establishment reviews exist.
- Added property context to the global admin review manager so property-level reviews can be audited and reassigned safely within the current tenant.
- Added public catalog filters for property type, minimum beds, and flexible cancellation, all compatible with existing date, guest, price, favorite, and sort filters.
- Added multi-select amenity filtering from the existing catalog, with selected options preserved in the search form.
- Replaced the multi-select amenity list with responsive accessible checkboxes for easier mobile filtering.
- Added a localized active-filter count to make narrowed catalog results easier to understand.
- Active-filter counts now include each selected amenity instead of treating the multi-select group as one criterion.
- Added a no-account property comparison flow for two or three published homes, covering price, capacity, rooms, beds, bathrooms, ratings, amenities, and cancellation policy.
- Added live comparison selection feedback with a two-property minimum and three-property maximum before submission.
- Comparison links now carry selected dates and guests, showing checkout-aligned total stay pricing when that context is available.
- Comparison now rejects mixed-currency property sets to avoid misleading price comparisons.
- Added direct detail actions and explicit fees/taxes-included context to comparison results.
- Added copy-link and WhatsApp sharing actions to comparison results.
- Added localized live feedback when the comparison link is copied or cannot be copied.
- Added a visible dates-and-guests context banner to comparison results so shared total prices are self-explanatory.
- Comparison links now reject unpublished or archived properties instead of exposing stale listing data.
- Comparison ratings now identify whether they come from the property or the wider establishment.
- Added a responsive summary card for each compared property before the detailed comparison table, improving mobile scanning.
- Made review counts explicit on cards and added a localized location fallback when property location data is incomplete.
- Aligned card pricing and the detail action on desktop, with a full-width mobile action for easier thumb use.
- Stabilized card heights by clamping long property titles and capacity summaries without truncating property-detail content.
- Added validated catalog sorting by recommended order, lowest price, or highest price while preserving existing filters and favorites priority.
- Replaced the effective price-only default ordering with a recommendation score using favorites, active establishment review signals, and price; homepage cards now show checkout-aligned total stay pricing when dates are selected.
- Centralized recommendation scoring so homepage and catalog use the same favorite, review, and price ranking logic.
- Standardized public card gallery heights so mixed source image dimensions no longer change grid rhythm.
- Corrected homepage gallery markup so photo controls are not nested inside the property link, improving keyboard and pointer interaction.
- Added a visible non-shifting focus state to property-card links for keyboard and assistive-device navigation.
- Removed the redundant visible details link from cards; image and content areas now link independently while gallery and favorite controls remain separate.
- Normalized guest, bedroom, bed, and bathroom metadata to the same secondary-card typography with a differentiated muted color.
## M21 — Channel calendar interoperability

- Added Booking.com, Airbnb, Vrbo, and generic iCalendar provider labels to property feeds.
- Hardened iCalendar parsing for folded lines, date parameters, escaped text, and cancelled events.
- Made feed synchronization snapshot-based: missing external events are removed instead of remaining stale, and feed errors/status remain visible.
- Kept hourly scheduling while honoring each feed’s configured sync interval.
- Made outbound iCalendar exports privacy-safe by using only `Unavailable` summaries and excluding reservation references, guest data, prices, and private block reasons.

## M20 — Cleaning visit scheduling

- Added a week/month cleaning schedule for administrators, hosts, and concierges with property, assignee, date/time, duration, status, optional reservation, and instructions.
- Added bulk generation from confirmed departures using an explicit cleaning time, assignee, duration, and instructions while skipping reservations already linked to a visit.
- Prevented overlapping visits for the same assignee and automatically sequenced same-day visits created from departures.
- Let workers use an active shared link to mark only in-scope visits as in progress or completed, without gaining access to schedule editing or other platform data.
- Recorded actual start and completion times for field updates and highlighted overdue scheduled visits across admin, shared, and PDF views.
- Fixed mobile cleaning-schedule overflow by constraining grid tracks and controls, stacking public/share actions, and allowing all action groups to wrap within the viewport.
- Added assignee filtering and frozen assignee-scoped links so each worker can receive only their own visits and cannot update another worker’s status.
- Added inline visit editing and confirmed deletion, with hosts restricted to properties in their assigned establishments.
- Added revocable, expiring public schedule links whose property scope is frozen at creation and which expose no guest, contact, reservation-reference, or payment data.
- Added previous/current/next period navigation, explicit 7/30/90-day link validity, WhatsApp sharing, and direct PDF access from active links.
- Added responsive read-only shared schedules, print support, and downloadable landscape PDFs for housekeepers who do not have platform accounts.

## M19 — AI-assisted property copy

- Added an optional Groq-backed writing assistant for bilingual French and English property summaries and descriptions using `openai/gpt-oss-20b` by default.
- Kept API credentials and calls on the Laravel backend, sending only current listing copy plus verified property capacity, amenity, and active-feature facts.
- Added a review-first workflow: generated copy appears in a bilingual preview and is never persisted until a host explicitly applies it and saves the property form.
- Added 24-hour result caching, a configurable per-user daily limit, graceful provider errors, and host property-scope enforcement.
- Kept provider URL, model, key, and limits configurable through server environment variables for future OpenAI-compatible provider changes.
- Added inline editing and confirmed deletion for existing property features while preserving property and host-scope authorization.
- Added an admin/host reservation option to bypass conflicts from enabled external calendars, while continuing to block internal reservations and manual availability blocks; every used override is recorded in the reservation’s internal notes.

## M18 — Operational and financial reporting

- Added a reporting workspace for administrators and hosts with date, establishment, and property filters plus print/PDF and UTF-8 CSV output.
- Added operational reporting for occupancy, sellable and blocked nights, reservations, arrivals/departures, average stay, guests, cancellations, statuses, booking sources, guest origins, and upcoming arrivals.
- Added financial reporting for issued-receipt collections, booked value, outstanding amounts, average booking value, taxes, service fees, and payment-provider success rates, with separate currency totals.
- Added property performance and review-quality sections while explicitly identifying unavailable cost, net-margin, OTA-commission, and refund metrics.
- Scoped host reports and CSV exports to assigned establishments and added feature tests for calculations, report rendering, role access, and cross-establishment denial.
- Replaced ambiguous and repeated admin navigation symbols with conventional distinct icons, shortened the amenity menu label, and improved footer-link hover contrast.

## M17 — Interface and usability refinement

- Completed a responsive UX audit across public, customer, checkout, host, admin, and platform pages at mobile, tablet, and desktop widths.
- Completed the public mobile navigation, made admin tables keyboard-scrollable on small screens, and removed account form overflows.
- Reworked the property detail hierarchy with a real page heading and an early mobile price/action summary, then restored checkout branding with an early payment shortcut.
- Unified standalone error pages and contact details with the shared brand styling and configured contact information.
- Added a reusable accessible confirmation dialog for destructive actions and keyboard navigation for editor tabs.
- Reduced admin dashboard duplication, compacted KPI cards, improved the mobile topbar, and standardized localized alert feedback.
- Prevented draft placeholder copy from reaching public cards, property details, and SEO metadata by using factual localized fallbacks.
- Associated shared and authentication form errors with their fields using inline feedback and ARIA validation state.
- Added Messages to the customer account navigation, prioritized existing conversations, and collapsed the new-conversation form when it is secondary.
- Localized unmatched-route error pages through the selected locale and compacted property sharing into a disclosure.
- Added keyboard photo reordering with arrow/Home/End controls while preserving the existing drag-and-drop workflow.
- Aligned the rendered typography with the design token, replaced inline theme previews with focusable semantic cards, and standardized key touch targets at 44px.
- Removed redundant single-provider payment copy while preserving distinct instructions and multi-provider payment details.
- Added localized notes for rating-only reviews and contextual return links from the review archive to either the originating property or home page.
- Collapsed property-detail reviews by default behind an accessible count-aware show/hide control while keeping the full review archive link visible.

## M16 — Host role with scoped admin access

- Added a new `host` user role that can manage establishments, properties, reservations, and messages, but has no access to site-wide settings, user management, the amenity catalog, or the global reviews page.
- Split the `admin` middleware group into `admin` (site configuration) and `establishment-manager` (establishment/property operations, granted to both `admin` and `host`), with matching nav/dashboard visibility changes.
- Added an `establishment_host` assignment table so hosts only see and manage the specific establishment(s) (and their properties, calendars, and price rules) assigned to them by an admin; only admins can create new establishments.
- Let hosts invite and remove additional hosts on the establishments they manage, from a new "Hosts" tab on the establishment editor, without needing user-management access.
- Restricted the reservations and messages admin lists (and individual reservation/thread access) to a host's assigned establishments, while leaving admin and concierge access tenant-wide as before.
- Added a sample seeded host user, scoped to the first establishment, for local testing.
- Verified the new role's access boundaries and per-establishment scoping with feature tests covering allowed, forbidden, and cross-establishment admin routes.

## M15 — Establishment review import

- Added a per-establishment "Avis" tab for managing customer reviews, alongside the existing global reviews admin page.
- Added multi-format CSV review import (Booking.com, Airbnb, Google, and a generic format) via a shared `ReviewImportService`, with header-based column mapping for Airbnb/Google exports.
- Scoped reviews to their establishment via a new `establishment_id` column while keeping the existing global review management flow working.
- Verified the new tab, manual review CRUD, and all four import formats with feature tests.

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
