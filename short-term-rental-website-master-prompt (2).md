# Master Prompt — Short-Term Rental Website for Bluehost

**Execution mode:** incremental, checkpointed, and resumable
**Current payment scope:** FedaPay sandbox for XOF Mobile Money and PayPal sandbox for EUR

Copy this entire prompt into a capable coding agent. Attach the sample customer-facing HTML pages, design assets, and the four property photo folders in the same session. On the first run, ask the agent to execute **Milestone M00 only**. On later runs, use the resume command near the end of this prompt.

---

## Role and objective

You are a senior full-stack product engineer, database architect, UX engineer, security reviewer, and deployment engineer. Build a complete, production-ready but deliberately practical short-term rental website that can be committed to GitHub and deployed to Bluehost shared hosting through SFTP.

This is an implementation assignment, not a request for a high-level plan or isolated code snippets. Create the working repository, database scripts, application code, tests, documentation, seed data, deployment scripts, and operational instructions. Do not claim a feature is complete unless it is implemented and tested.

The website will let visitors browse and book rental properties, either with an account or as a guest. Staff will use a secure administration area to manage establishments, properties, photos, prices, availability, external calendars, reservations, users, and limited concierge access.

## Mandatory incremental execution and recovery protocol

This section overrides any instruction elsewhere that could be interpreted as permission to build the entire project in one uninterrupted run.

### Core execution rules

1. Execute **exactly one numbered milestone per run or response**. Do not begin the next milestone in the same run, even if time remains, unless I explicitly say `CONTINUE AUTOMATICALLY`. Even in automatic mode, finish and package a complete checkpoint before starting another milestone.
2. Keep each milestone bounded and independently verifiable. Prefer a smaller completed checkpoint over a large partially completed feature.
3. At the beginning of every run, inspect the existing repository before creating or modifying files. Read these recovery files first when they exist:
   - `project-state.json`
   - `CHECKPOINT.md`
   - `artifacts/checkpoints/manifest.json`
   - `CHANGELOG.md`
4. Resume from the last successfully completed milestone. Never restart the repository, regenerate completed work, overwrite user changes, or repeat expensive photo processing unless validation proves that the prior output is missing or corrupt.
5. A milestone is not complete until its acceptance checks pass and its recovery artifacts have been written. If a test cannot run, mark the milestone `blocked` or `in_progress`; do not report it as complete.
6. Save all local files and update recovery state **before** any network-dependent operation such as package download, payment API test, GitHub push, or remote deployment. A failed network operation must not destroy local progress.
7. Do not make live charges or use production payment credentials during these milestones. Use FedaPay sandbox and PayPal sandbox only.
8. Do not perform destructive cleanup of prior checkpoints, uploads, database content, or user changes. Use additive migrations and versioned artifacts.
9. Never make a GitHub push, SFTP upload, or remote API call a prerequisite for preserving a checkpoint. Local downloadable artifacts are the recovery source of truth.
10. Keep progress updates short. Avoid streaming large source files or test logs into chat. Put detailed output in files and provide a concise result plus download links.

### Required recovery files

Create the recovery files in M00 and maintain them after every milestone.

`project-state.json` must remain machine-readable and use this minimum structure:

```json
{
  "project": "Afrik Appart",
  "schema_version": 1,
  "current_milestone": "M00",
  "last_completed_milestone": null,
  "status": "not_started",
  "completed_milestones": [],
  "artifacts": [],
  "validation": [],
  "blocking_issue": null,
  "next_action": "Execute M00",
  "updated_at_utc": null
}
```

For each artifact, record at least its relative path, byte size, SHA-256 checksum, milestone, and creation time. Use only these state values: `not_started`, `in_progress`, `blocked`, and `completed`.

`CHECKPOINT.md` must be a human-readable recovery note containing:

- last completed milestone and current status;
- exactly what was completed;
- validation commands and their results;
- files or assumptions that changed;
- unresolved issues;
- the next safe action;
- the exact resume command;
- the names and checksums of downloadable checkpoint archives.

`artifacts/checkpoints/manifest.json` must list every immutable checkpoint package. Do not silently replace an existing package. If a milestone is rebuilt, increment the artifact revision, for example `v2`.

### Checkpoint packaging standard

At the end of every successful milestone:

1. Update `project-state.json`, `CHECKPOINT.md`, `CHANGELOG.md`, and the checkpoint manifest.
2. Run the milestone-specific validation commands and store concise evidence in `artifacts/checkpoints/M##_validation.txt`. Redact secrets and personal data.
3. Create a versioned archive named `afrik-appart-M##-<short-name>-v1.zip`. Include all source and documentation required to resume, but exclude `.env`, credentials, caches, logs, dependencies that can be reinstalled, and raw duplicate media.
4. Create a companion `afrik-appart-M##-<short-name>-v1.sha256` file.
5. If Git is available, create a local commit and tag `checkpoint-M##-v1`. A failed remote push must not fail the milestone. Do not rewrite existing Git history.
6. Verify that the archive can be listed/opened and that its checksum matches.
7. Return a brief handoff using this exact structure:

```text
Milestone: M## — <name>
Status: COMPLETED | BLOCKED | IN PROGRESS
Implemented: <short summary>
Validation: <passed checks or exact blocker>
Download: <checkpoint ZIP link/path>
Checksum: <SHA-256 file link/path>
State: <project-state.json link/path>
Resume with: Resume Afrik Appart from the latest checkpoint. Execute M## only.
```

Then stop and wait for me. Do not start the next milestone.

### Failure and interruption behavior

If a network error, tool limit, dependency failure, timeout, or other interruption occurs:

1. Preserve every coherent file already produced.
2. Set `status` to `blocked` or `in_progress` and leave `last_completed_milestone` unchanged.
3. Record the exact failed action, safe retry command, and whether repeating it is idempotent in both `project-state.json` and `CHECKPOINT.md`.
4. If the current partial work is internally coherent, create a clearly named recovery archive such as `afrik-appart-M05-auth-recovery-v1.zip`; do not call it a completed checkpoint.
5. On the next run, validate the last completed archive, inspect the partial files, and retry only the failed or unfinished action.
6. Never discard the project and start again merely because the chat or network connection failed.

### Media packaging rule

The supplied 58 photos are large. Do not copy the original image archive into every checkpoint. Preserve the original attachment separately, create the optimized web-media package once in M03, and refer to its manifest and checksum from later checkpoints. Later source archives should contain only small fixtures or changed media unless I explicitly request a complete bundle.

## Supplied inputs and required use

The archive `Afrik Appart.zip` has been supplied and must be inspected before implementation. It contains:

1. `Afrik Appart/home_preview.html`: static customer-facing home-page prototype.
2. `Afrik Appart/details_preview.html`: static property-detail prototype with a visual booking panel.
3. `Afrik Appart/photo/appartement 401/`: 14 property photos.
4. `Afrik Appart/photo/appartement 402/`: 12 property photos.
5. `Afrik Appart/photo/appartement 403/`: 19 property photos.
6. `Afrik Appart/photo/appartement 404/`: 13 property photos.

Treat the two HTML files as design examples to adapt to the functional requirements, not finished pages to copy literally. Convert their static layout into reusable database-driven templates. The prototypes use placeholder Unsplash images, hard-coded sample properties, fake platform statistics, example reviews/ratings, prototype commentary, and internal citation markers. Remove all of that from production unless verified owner-supplied data exists. Replace property imagery with the supplied apartment photos. Do not show unsupported claims such as “verified stays,” country counts, ratings, or reviews.

The observed visual direction should remain recognizable:

- Inter/system sans-serif typography.
- Cream background around `#f6f3ec`.
- Deep emerald primary around `#0f5b4c`.
- Warm gold accent around `#c89b3c`.
- White/cream surfaces, soft shadows, large rounded cards, pill badges, and restrained gradients.
- Large photographic hero treatment, responsive search panel, premium listing cards, and a prominent property booking panel.

The current details prototype does not contain a functioning date-range calendar, live availability check, guest selector, price calculation, currency selector, payment selection, or payment flow. Build those features while retaining and improving the visual hierarchy. Remove prototype-only sections and text such as “ready for integration,” “receive the next version,” replacement-photo notices, and design-explanation copy. Do not implement public “List your property” onboarding unless it is requested separately; repurpose or remove that prototype button.

Also inspect any later-supplied property metadata: establishment, property names, addresses, descriptions, occupancy, room counts, amenities, prices, fees, policies, and contact information. First produce a short input inventory that identifies what was supplied, what can be safely derived, and what remains missing. Do not invent property facts from photographs. When metadata is missing, use clearly labelled draft values in seed configuration and record every item requiring replacement in `docs/content-todo.md`.

## Project configuration

Use these values if I have not provided replacements:

- Site name: `Afrik Appart` (confirm or make configurable)
- Application code name: `afrikappart`
- Primary domain: `[TO BE PROVIDED]`
- Default locale: `fr`
- Secondary locale: `en`
- Default timezone: `Africa/Porto-Novo` unless property data specifies another timezone
- Supported customer currencies: `XOF` and `EUR`
- Default display and canonical property-pricing currency: `XOF`
- XOF/EUR reference conversion: `1 EUR = 655.957 XOF`, stored in configuration with source/effective date and used consistently
- Booking mode: instant confirmation when all availability checks pass
- Customer payment mode: online payment required to confirm a booking
- Payment methods: Mobile Money in `XOF` and PayPal in `EUR`
- Amount collected for version 1: 100% of the booking total at checkout; keep a future deposit configuration possible but disabled
- Confirmed Mobile Money provider: FedaPay, with sandbox and live environments; keep the internal gateway interface replaceable for maintainability
- Payment hold duration: 20 minutes by default, configurable
- External-calendar freshness threshold before final booking: 15 minutes
- External-calendar sync schedule: every 15 minutes
- Admin notification address: `[TO BE PROVIDED]`
- Customer support address: `[TO BE PROVIDED]`
- SMTP provider and credentials: environment variables, `[TO BE PROVIDED]`
- Bluehost cPanel username, SFTP host, remote paths, and database credentials: secrets/configuration only, never committed

Make locale, display currency, property/base currency, conversion parity, timezone, booking mode, calendar freshness, payment-hold duration, enabled payment gateways, admin recipients, fees, taxes, and minimum stays configurable rather than hard-coded.

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
- Use a payment-gateway interface with concrete PayPal and FedaPay adapters. Use PayPal Orders/Checkout for `EUR` and FedaPay for Mobile Money in `XOF`. Keep provider-specific logic isolated behind the gateway interface.
- Payment APIs and SDKs are time-sensitive. Before coding each adapter, verify the current official PayPal and selected Mobile Money/FedaPay documentation, supported merchant country/account behavior, currencies, authentication, webhook verification, sandbox procedure, and SDK compatibility. Record the reviewed URLs/date/version in `docs/currency-and-payments.md`; do not rely on blog tutorials.
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
   - A real accessible date-range calendar that marks unavailable dates from internal reservations, admin blocks, and imported calendars.
   - Guest selector and immediate server-backed availability/quote refresh.
   - XOF/EUR display-currency selector that preserves the selected dates and guests.
   - Price summary for selected dates, including nightly charges, fees, taxes, discounts, conversion information when applicable, and total.
   - Minimum-stay and maximum-occupancy validation.
   - Booking call to action.
   - Do not expose exact private access instructions before a reservation is appropriately confirmed.

4. Booking/checkout flow
   - Accept authenticated and guest bookings.
   - Capture lead guest name, email, phone, guest count, optional message, check-in/out dates, and acceptance of terms/privacy/cancellation policy.
   - Recalculate price and revalidate occupancy and availability on the server; never trust browser totals or availability.
   - Display an itemized total in the customer's selected display currency and the gateway's actual charge currency.
   - Offer Mobile Money and PayPal as the two online payment options. Mobile Money charges in XOF; PayPal charges in EUR.
   - When the customer selects Mobile Money, validate an international-format mobile number and show only payment operators returned/enabled by the configured provider for the country/account.
   - When the customer selects PayPal, create and capture a server-side PayPal order in EUR using the approved checkout flow.
   - Create a time-limited `pending_payment` reservation hold only after the final availability and price validation succeeds.
   - Use idempotency protection so refreshing, returning from a provider, webhook retries, or resubmitting cannot create duplicate reservations or payments.
   - Never mark a reservation confirmed based only on a browser redirect. Confirm it only after a verified provider webhook and/or server-to-server payment-status verification.
   - After successful verified payment, atomically mark the payment paid and reservation confirmed, retain the occupied nights, and show a confirmation page with a non-sequential public booking reference.
   - If payment is cancelled, fails, or the hold expires, release the nights safely and provide a clear retry flow. Prevent capture against an expired hold; route rare late payments to a documented reconciliation/refund workflow.

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
- Property timezone and canonical pricing currency (`XOF` or `EUR`), inherited from establishment/site when absent.
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
- Full support for XOF and EUR. XOF is a zero-decimal currency; EUR uses two decimal places.
- Use the official fixed XOF/EUR parity as a versioned application configuration: `1 EUR = 655.957 XOF`. Do not call a volatile foreign-exchange API for this pair.
- Let the customer switch display between XOF and EUR on public pages. Preserve the preference in the session/account.
- Mobile Money payments must be charged in XOF. PayPal payments must be charged in EUR because XOF is not a PayPal-supported payment currency.
- When display, property, and/or payment currencies differ, calculate the property price once in its canonical currency, convert once at the payment boundary, and store the original amount, converted amount, exact rate, rate source, effective timestamp, display currency, and payment currency in the booking/payment snapshot.
- Convert XOF to EUR by dividing by `655.957` and rounding half-up to EUR cents. Convert EUR to XOF by multiplying by `655.957` and rounding half-up to the nearest whole XOF. Document and test this rule.
- Never reprice an existing booking when the configured conversion record changes.

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
5. Online checkout creates a configurable 20-minute pending-payment hold. Holds block inventory, have an expiry time, and are released safely by scheduled cleanup when unpaid. Payment callbacks and cleanup must use locking/idempotency so they cannot race into an inconsistent state.
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

Customer bookings require successful online payment through Mobile Money or PayPal. Reservation statuses must include at least `pending_payment`, `confirmed`, `cancelled`, `completed`, `payment_failed`, and `expired`. Payment statuses must include at least `created`, `pending`, `authorized`, `paid`, `failed`, `cancelled`, `partially_refunded`, and `refunded`.

Persist:

- Public booking reference and internal ID.
- Property and establishment.
- Optional user account and guest contact snapshot.
- Check-in/out, guest counts, locale, property currency, display currency, payment currency, source, reservation status, payment summary status, hold expiry, and timestamps.
- Price snapshot: nightly line items/rules, subtotal, discounts, fees, taxes, deposit, total, amount paid, balance, XOF/EUR rate, converted amount, rate source, rate effective time, and rounding result.
- Terms/cancellation version accepted, consent timestamp, IP metadata only when legally appropriate, and internal/admin notes separate from guest-visible notes.
- Cancellation metadata and audit history.
- Separate append-oriented payment attempts containing gateway, environment, internal idempotency key, provider order/transaction/reference IDs, requested amount/currency, verified captured amount/currency, normalized and provider statuses, timestamps, safe response metadata, failure code, and refund references. Never store access tokens, raw secrets, Mobile Money PINs, or card data.

### Shared payment workflow

Create a gateway-neutral interface with operations for creating a payment, retrieving/verifying provider status, processing a webhook, cancelling where supported, and refunding where supported. Implement both concrete adapters below; do not provide fake success stubs in production mode.

1. Refresh required external calendars and validate stay, occupancy, policies, and price.
2. In a database transaction, lock the property/inventory, revalidate, create a `pending_payment` reservation, create occupied-night rows, and set a configurable 20-minute expiry.
3. Create the provider payment using a unique idempotency key and the amount/currency stored in the immutable quote snapshot.
4. Redirect/open the provider-approved checkout. Never collect a Mobile Money PIN or PayPal credential in this application.
5. Treat the return/cancel URL only as navigation. Verify payment through an authenticated webhook and/or server-to-server provider lookup.
6. Deduplicate every webhook by provider event/reference ID. Verify authenticity using the provider's documented signature procedure or retrieve the transaction through the authenticated API when signature support is unavailable.
7. Before confirming, compare provider merchant/account, internal reference, amount, currency, and final successful state with the stored attempt. Reject and alert on any mismatch.
8. In one locked/idempotent transaction, mark the attempt paid, mark the reservation confirmed, retain occupied nights, create email-outbox events, and audit the transition.
9. Release inventory on verified failure/cancellation or hold expiry. Handle webhook/expiry races safely.
10. If payment succeeds after the hold expired or after inventory was released, do not silently overbook. Place it in `manual_review`, alert the admin, and follow a documented refund/reconciliation procedure.

Add a scheduled reconciliation job that checks old pending attempts directly with the provider, repairs missed-webhook cases idempotently, and alerts administrators about unresolved discrepancies.

Provide safe `.env.example` placeholders at minimum for `PAYMENT_HOLD_MINUTES`, `MOBILE_MONEY_PROVIDER`, `FEDAPAY_ENVIRONMENT`, `FEDAPAY_PUBLIC_KEY`, `FEDAPAY_SECRET_KEY`, `PAYPAL_ENVIRONMENT`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID`, and the application return/cancel/webhook base URLs required by the selected SDKs. Confirm the exact current provider variable requirements against official documentation during implementation. Webhook endpoints such as `/webhooks/paypal` and `/webhooks/fedapay` may be exempt from browser CSRF middleware, but must use provider authentication/verification, payload limits, rate controls, event deduplication, and safe logging.

### Mobile Money in XOF

- Implement Mobile Money payment in XOF through the confirmed FedaPay adapter. FedaPay is the selected production provider because its API/official PHP SDK supports XOF transactions and Benin Mobile Money flows; keep the internal gateway contract replaceable for future maintainability, but implement and test FedaPay completely.
- Support FedaPay sandbox and live modes through environment variables. Do not enable live mode until merchant onboarding, callback/webhook configuration, settlement, fees, and supported Benin operators have been verified.
- Use the provider-hosted or provider-approved flow and official server-side API/SDK. Validate Benin/international phone format, but do not hard-code an operator list that can become stale; use configured/provider-supported methods.
- Store Mobile Money amounts as whole XOF units. The provider transaction amount/reference and the internal snapshot must match exactly before confirmation.
- Implement provider callback/webhook handling, server-to-server status verification, retry/reconciliation, cancellation/failure handling, and refund/admin-review behavior supported by the merchant account.

### PayPal in EUR

- Implement PayPal Checkout/Orders in EUR using the current supported PayPal REST/JavaScript SDK integration and server-side order creation/capture.
- Support sandbox and live modes through separate environment credentials and webhook IDs.
- Use EUR for all PayPal orders because XOF is not a supported PayPal payment currency. Show the exact EUR amount before the buyer launches PayPal.
- Verify PayPal webhook authenticity using the documented verification API/mechanism, deduplicate events, and handle at least completed, pending, denied/failed, reversed/refunded, and dispute-relevant states that apply to the selected API.
- A browser-side `onApprove` callback alone is not proof of payment. Confirm only after authenticated server-side capture/status verification and exact amount/currency/reference matching.
- Configure the PayPal Business account to receive/hold EUR or document the expected settlement conversion/fees before go-live.

### Refunds and administration

- Let authorized admins view payment attempts, provider references, normalized status, safe error details, reconciliation state, and refund history.
- Refunds require explicit confirmation, a reason, authorization, idempotency, provider result tracking, email notification, and audit logging.
- Never automatically refund or cancel solely because the browser closed. Follow the verified provider state.
- Document which refund paths are automated per provider and which require manual merchant-dashboard action.
- Keep payment state separate from reservation state while enforcing documented transition rules.

## Transactional email

Send email notifications for the complete booking/payment lifecycle:

- Optional pending-payment/continue-payment message when a provider remains pending beyond the initial browser session.
- Customer paid/confirmed message with booking reference, property, dates, guests, original and payment currencies where different, price breakdown, verified payment status, policies, and a secure reservation link.
- Admin paid/confirmed notification to the property's/establishment's configured recipients with reservation/payment details and an authenticated admin link.
- Customer/admin failure, expiry, refund, and manual-review messages where appropriate.
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
11. Payment view with method, gateway, XOF/EUR amounts and conversion snapshot, provider reference, attempts, webhook/reconciliation status, safe failure reason, and controlled refund/manual-review actions. Concierges may see whether a reservation is paid but cannot see gateway diagnostics or issue refunds.
12. Payment configuration/status for PayPal and FedaPay without ever displaying stored secret values. Provide sandbox/live indicators and a safe connectivity/credential-validation action.
13. Staff invitation by email with expiring single-use token, role, and scope. Allow deactivation and access revocation.
14. Site settings and establishment-specific notification recipients.
15. Audit log visible to super admins, with actor, action, target, timestamp, and safe before/after metadata.

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
- The supplied 58 PNG sources total roughly 309 MiB, and several apartment 402 files are around 17–19 MiB each. Do not serve these raw files directly as normal page assets.
- Validate actual MIME type, extension, file size, and image dimensions.
- Accept JPEG, PNG, and WebP; reject executable/polyglot or malformed uploads.
- Generate collision-resistant filenames and responsive sizes/thumbnails, including practical full/large/card/thumbnail variants in WebP plus a compatible JPEG/PNG fallback. Preserve good visual quality and orientation, strip unsafe/unnecessary metadata, and never upscale a source.
- Keep the original high-resolution ZIP/source photos outside the public web root. Do not commit the 309 MiB source archive to a normal Git repository. If original preservation in Git is explicitly desired later, use a private repository plus Git LFS and document its storage/bandwidth consequences.
- Add `.htaccess` rules that disable PHP/script execution and directory listing in uploads.
- Prevent path traversal and unauthorized file replacement/deletion.
- Exclude `public/uploads/**` from destructive code deployment while retaining `.gitkeep` as needed.
- Provide configurable upload size/dimension limits and clear errors.

For the supplied four properties:

1. Preserve the explicit source mapping: apartment 401 has 14 photos, 402 has 12, 403 has 19, and 404 has 13, for 58 supplied images total.
2. Build `seed-assets/properties/manifest.example.json` and a real manifest populated from supplied metadata. Use normalized safe target filenames; retain the original filename in private import metadata only when useful.
3. Create an idempotent import command/script that copies each folder's photos into the correct public upload directory, creates optimized variants, and inserts/updates `property_images` rows in deterministic order. Do not upscale smaller sources merely to meet a target size.
4. Seed the establishments, four properties, amenities, initial XOF/EUR pricing configuration, and image metadata.
5. If names/descriptions/prices are missing, use clearly marked draft values and record them in `docs/content-todo.md` rather than guessing.
6. The importer must be safe to rerun without duplicating image rows.
7. Generate a separate one-time `release/initial-media` deployment package containing only optimized web variants and the import manifest. It must mirror the final `/public_html/uploads/properties/<property>/` layout, have checksums, and be deployable by SFTP without being included in later code mirrors.
8. Document the initial media deployment separately from routine application deployment. Routine GitHub/SFTP deployments must always preserve and exclude `/uploads`; a deliberate media-sync workflow must never use remote deletion.

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
- Currency/parity configuration and immutable booking conversion snapshots.
- Payment attempts, provider references, idempotency keys, normalized status history, processed webhook/event deduplication, reconciliation runs, and refunds.
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
3. Pricing rule precedence, fees, taxes, minimum stays, XOF zero-decimal behavior, EUR cents, `655.957` conversion in both directions, rounding, display/payment currency differences, and immutable booking snapshots.
4. Check-in-inclusive/check-out-exclusive date behavior.
5. Internal reservation conflicts, admin blocks, imported calendar conflicts, and cancelled reservation release.
6. Simultaneous requests for the same property/dates proving no double booking.
7. Fresh, stale, failed, malformed, empty, changed, and removed external iCalendar feeds.
8. Correct iCalendar export, stable UID, no imported-event echo, and no PII leakage.
9. Payment-hold creation/expiry, inventory release, payment/expiry races, provider retry, late payment, and manual-review behavior.
10. PayPal EUR order/capture and Mobile Money XOF flows in sandbox/mocked mode, including exact amount/currency matching, cancelled/failed/pending/successful payments, duplicated and out-of-order webhooks, invalid signatures, missed-webhook reconciliation, refunds, and idempotency.
11. Email outbox creation, retry, customer/admin recipients, localization, payment lifecycle messages, and SMTP failure behavior.
12. Image upload authorization, MIME/size validation, processing, safe paths, reordering, and deletion.
13. Idempotent checkout submission, idempotent seed/photo import, and idempotent calendar sync.
14. Core public pages on mobile and desktop, real date/guest/currency/payment controls, form accessibility, and critical error states.

Provide fixtures for iCalendar tests without depending on live Airbnb/Booking.com feeds. Mock remote HTTP, SMTP, PayPal, and Mobile Money services in automated tests. Never make live financial transactions from the test suite.

## Local development deliverables

Provide exact setup for Windows PowerShell and macOS/Linux:

- Required PHP extensions and version checks.
- Composer and Node/npm install commands.
- `.env` creation and key generation.
- PayPal sandbox and Mobile Money/FedaPay sandbox configuration, webhook forwarding/testing, and safe test credentials supplied only through `.env`.
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

1. Confirm the hosting plan, domain document root, PHP 8.3 availability/extensions, outbound HTTPS/API access to the configured payment providers, SSH/SFTP access, cron support, MySQL version, storage, and email/SMTP choice.
2. Activate SSL and confirm HTTPS before enabling strict redirects/HSTS.
3. Create a MySQL database and least-privilege database user in Bluehost/cPanel; assign required privileges.
4. Set the domain PHP version and required PHP extensions.
5. Enable SSH access so SFTP can be used on shared hosting; use port 22 unless Bluehost/account configuration says otherwise.
6. Create the application directory outside `public_html`, the web-root directory, persistent upload directory, storage directories, and safe permissions.
7. Create the production `.env` outside the web root, including `APP_ENV=production`, `APP_DEBUG=false`, a unique application key, production URL, database, session, SMTP, calendar, currency/parity, payment-hold, PayPal, Mobile Money/FedaPay, webhook, and notification settings.
8. Configure Bluehost cron to invoke Laravel's scheduler every minute with the account's actual PHP binary and absolute path. The application scheduler should run calendar sync, email-outbox processing, expired-payment-hold cleanup, and payment reconciliation at their configured intervals, with overlap prevention.

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

Also create `scripts/build-initial-media.sh` and `scripts/build-initial-media.ps1`. They must accept the extracted `Afrik Appart/photo` source directory, verify the expected 401/402/403/404 counts, create the optimized `release/initial-media/uploads/properties/...` tree and manifest/checksums, and never modify or delete the originals. This is a one-time/explicit media build, not part of routine CI code deployment.

### Manual first deployment

Document:

1. How to connect with FileZilla or another SFTP client using the Bluehost host/domain, port 22, cPanel username, and credentials/key.
2. Upload `release/app` into `/home/<CPANEL_USER>/apps/afrikappart/` while preserving the production `.env` and writable storage.
3. Upload `release/public` into the domain's web root while preserving `/uploads`.
4. Upload `release/initial-media/uploads` once into `/public_html/uploads` using an additive SFTP transfer with no deletion, then verify checksums and all 58 mapped image records/variants.
5. Import `database/schema.sql` then seed files through phpMyAdmin for a clean installation, or run `php artisan migrate --force` and the approved production seeder through SSH.
6. Create the first super admin using a safe CLI command. If SSH is unavailable, provide a documented one-time setup procedure with a high-entropy setup token, automatic disabling after success, expiration, and a prominent instruction to remove/disable it. Never ship a default admin password or password hash.
7. Clear/build Laravel caches using SSH where available. If unavailable, ensure the uploaded release already contains compatible production caches or provide a safe admin maintenance action that cannot execute arbitrary commands.
8. Verify writable paths, homepage, health endpoint, database access, login, uploads, cron, SMTP, calendars, XOF/EUR pricing, sandbox payment flows, verified callbacks/webhooks, booking confirmation, emails, SSL, and logs. Switch payment gateways to live mode only after the merchant accounts and live webhook endpoints have passed a controlled low-value test.
9. Remove any temporary setup/import files from the web root.

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

- An authenticated admin system-health screen showing database connectivity, writable paths, scheduler heartbeat, last successful email job, failed email count, stale/failed calendar feeds, payment gateway sandbox/live state, last successful provider reconciliation, and unresolved payment/manual-review counts. Never reveal credentials, tokens, full payloads, or secrets.
- A minimal public health endpoint that exposes no sensitive details.
- Structured application logs with correlation IDs.
- Admin alerts or visible dashboard warnings for repeated calendar/email/payment failures and unresolved late payments.
- `docs/runbook.md` covering backup, restore, expired SSL, failed calendar feeds, failed email, stuck queue, payment-provider outage, invalid/missed webhook, late payment, manual reconciliation/refund, full disk, application error, compromised credentials, staff offboarding, and rollback.
- `docs/bluehost-deployment.md` with copy/paste-ready commands containing placeholders.

## Required documentation and final handoff

Deliver at least:

- `README.md`
- `docs/architecture.md`
- `docs/database.md`
- `docs/data-model.mmd`
- `docs/availability-and-ical.md`
- `docs/currency-and-payments.md`
- `docs/roles-and-permissions.md`
- `docs/security.md`
- `docs/bluehost-deployment.md`
- `docs/runbook.md`
- `docs/content-todo.md`
- `docs/acceptance-test-checklist.md`
- `CHANGELOG.md`

At final-project completion, in addition to every milestone checkpoint, provide:

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
- The supplied 58 real photos are imported idempotently with the correct mapping: 401/14, 402/12, 403/19, and 404/13.
- The public home, listing, details, checkout, confirmation, login, registration, and account-history pages are responsive and visually consistent with the samples while containing no prototype commentary, internal citation markers, fake ratings/statistics, or placeholder Unsplash property photos.
- A guest and a registered customer can each complete a paid reservation through Mobile Money or PayPal sandbox/test mode.
- The server independently verifies occupancy, pricing, XOF/EUR conversion, policies, availability, payment reference, provider status, amount, and currency.
- Mobile Money charges whole-unit XOF and PayPal charges EUR; the exact conversion snapshot is retained with the booking.
- Pending-payment holds block inventory and expire/release safely; successful verified payment confirms the reservation; duplicated callbacks do not duplicate bookings or charges.
- Internal bookings, admin blocks, and every enabled imported iCalendar feed participate in availability.
- A stale or failed required calendar prevents automatic confirmation.
- Concurrent booking tests prove no double booking.
- Each property exposes a revocable, privacy-safe iCalendar feed that does not echo imported events.
- Customer and admin emails are queued and sent, and SMTP failure does not invalidate the booking.
- Payment failures, invalid webhooks, missed callbacks, expired holds, late payments, and refunds have tested and documented recovery paths.
- Super-admin, establishment-admin, concierge, and customer access are correctly scoped and tested.
- Property photos can be securely uploaded, ordered, optimized, and removed without being stored in MySQL.
- Production secrets, uploads, logs, and application code are not publicly exposed or committed.
- The release can be uploaded to Bluehost by SFTP without requiring a running Node service.
- Deployment preserves `.env`, uploads, and other persistent runtime data.
- The README contains safe first-deploy, migration, cron, backup, verification, and rollback steps.

## Checkpointed milestone plan

Follow these milestones in order. Execute only one milestone per run. A later milestone may refine earlier code through additive, tested changes, but must not invalidate or erase prior recovery artifacts.

### M00 — Input inventory and recovery baseline

**Scope**

- Inspect `Afrik Appart.zip` without altering it.
- Verify the two preview files and the four photo folders, including photo counts and file types.
- Inspect any existing repository and preserve prior work.
- Record supplied requirements, configurable defaults, missing owner content, and implementation assumptions.
- Initialize Git locally if appropriate and establish the recovery/checkpoint structure.

**Required outputs**

- `docs/input-inventory.md`
- `docs/requirements-baseline.md`
- `docs/content-todo.md`
- initial `README.md`
- `project-state.json`
- `CHECKPOINT.md`
- `artifacts/checkpoints/manifest.json`
- M00 validation record, ZIP, and SHA-256 file

**Validation**

- Confirm the archive is readable.
- Confirm photo mapping is 401/14, 402/12, 403/19, and 404/13, for 58 total.
- Confirm neither previews nor original photos were modified.
- Confirm recovery JSON files parse and the checkpoint checksum verifies.

**Stop condition:** package M00 and return its links. Do not scaffold the application yet.

### M01 — Architecture and Laravel scaffold

**Prerequisite:** completed M00.

**Scope**

- Record architecture decisions for Laravel/MySQL, Bluehost public/private split, media storage, queues/cron, locale, currency, calendars, and payment adapters.
- Create the Laravel repository skeleton, locked dependencies, environment example, local setup, baseline routes, Blade layout, asset build configuration, and test configuration.
- Add a health endpoint that reveals no secrets.

**Required outputs**

- runnable Laravel scaffold
- `docs/architecture.md`
- Architecture Decision Records
- `.env.example`, safe `.gitignore`, and initial automated checks
- M01 validation record, ZIP, and SHA-256 file

**Validation**

- Dependency manifest and lock file are valid.
- Application boots in the supported local environment.
- health endpoint and baseline automated test pass.
- no secret or real credential is committed.

**Stop condition:** package M01 and return its links. Do not build the database domain yet.

### M02 — Database foundation and seed framework

**Prerequisite:** completed M01.

**Scope**

- Implement migrations and models for establishments, properties, amenities/features, photos, users/roles/scopes, rates, blocks, reservations, guests, payments, calendar feeds/events/sync logs, emails, settings, and audit logs.
- Add constraints, foreign keys, indexes, soft-delete strategy where useful, normalized monetary fields, timezones, and timestamps.
- Create idempotent seeders for reference data and clearly marked draft property records.
- Generate `schema.sql`, `seed.sql`, `sample-data.sql`, and the Mermaid data model.

**Required outputs**

- migrations, models/factories, and seed framework
- SQL installation scripts without credentials or destructive database commands
- `docs/database.md` and `docs/data-model.mmd`
- M02 validation record, ZIP, and SHA-256 file

**Validation**

- Fresh migration succeeds on MySQL 8.
- rollback/re-run succeeds in a disposable test database.
- clean `schema.sql` import produces the same effective schema.
- database/model tests and SQL lint/sanity checks pass.

**Stop condition:** package M02 and return its links. Do not process all media yet.

### M03 — Optimized media and four-property seed

**Prerequisite:** completed M02.

**Scope**

- Import the supplied apartment 401–404 photos with deterministic property mapping.
- Produce web-optimized responsive variants, safe filenames, dimensions, ordering, checksums, and descriptive draft alt-text markers.
- Store web assets under the configured site subfolder and metadata in MySQL; never store image blobs.
- Make media import idempotent and document original-versus-generated files.

**Required outputs**

- media import command/script and tests
- seeded establishment plus four clearly labelled draft properties
- optimized media beneath the required web-accessible subfolder
- `seed-assets/properties/manifest.json`
- separate `afrik-appart-M03-optimized-media-v1.zip` and checksum
- M03 source checkpoint ZIP and checksum

**Validation**

- Exactly 58 source photos map as 401/14, 402/12, 403/19, and 404/13.
- Re-running the importer creates no duplicates.
- generated files open successfully, meet configured size/dimension rules, and contain no unsafe paths.
- property photo foreign keys, cover images, and ordering are valid.

**Stop condition:** package source and optimized media separately and return both links. Later checkpoints must not duplicate the full media package.

### M04 — Public browsing experience

**Prerequisite:** completed M03.

**Scope**

- Convert `home_preview.html` and `details_preview.html` into responsive database-driven Blade views.
- Implement home, property listing/search, and property detail/gallery pages.
- Preserve the approved visual direction while removing prototype notes, fake statistics/reviews, citations, and placeholder Unsplash images.
- Add locale and XOF/EUR display controls with accessible, semantic markup.
- Display a booking panel shell, but do not yet claim live booking completion.

**Required outputs**

- reusable public UI components and pages
- responsive styles/scripts and accessibility notes
- screenshot or render evidence at representative desktop/mobile sizes
- M04 validation record, ZIP, and SHA-256 file

**Validation**

- public feature tests pass.
- no prototype-only copy or external placeholder property image remains.
- pages use seeded property/photo data and work without a Node server at runtime.
- basic keyboard, form-label, contrast, and responsive checks pass.

**Stop condition:** package M04 and return its links. Do not implement payment yet.

### M05 — Authentication, authorization, and admin shell

**Prerequisite:** completed M04.

**Scope**

- Implement registration, login, logout, password reset, profile, and guest-contact behavior.
- Implement `super_admin`, `establishment_admin`, `concierge`, and `customer` authorization with establishment scoping.
- Create the secured admin navigation/dashboard shell and audit-sensitive access controls.
- Add a safe first-admin creation procedure that does not seed a known production password.

**Required outputs**

- authentication/account pages and authorization policies
- admin/concierge shell
- `docs/roles-and-permissions.md`
- access-control test matrix
- M05 validation record, ZIP, and SHA-256 file

**Validation**

- role and tenant-scope tests prove forbidden cross-establishment access is rejected.
- customer and guest paths cannot access administration.
- password/token/session security tests pass.

**Stop condition:** package M05 and return its links.

### M06 — Property administration, pricing, and core availability

**Prerequisite:** completed M05.

**Scope**

- Implement practical admin CRUD for establishments, properties, descriptions, amenities, occupancy, policies, photo ordering, base/seasonal pricing, fees, taxes, minimum stays, and manual availability blocks.
- Implement XOF canonical pricing, fixed/configurable EUR conversion, whole-unit XOF rounding, quote snapshots, and server-side quote validation.
- Implement date-range and occupancy validation, pending holds, overlap queries, database concurrency protection, expiry, and no-double-booking rules.
- Connect the public detail calendar and quote panel to server-side availability.

**Required outputs**

- working admin property/pricing/block interfaces
- quote and availability services/APIs
- concurrency and boundary-date tests
- updated currency/availability documentation
- M06 validation record, ZIP, and SHA-256 file

**Validation**

- invalid occupancy/date/price submissions are rejected server-side.
- XOF/EUR conversions and stored snapshots match configured rules.
- concurrent reservation-hold tests demonstrate no double booking.
- manual blocks and active holds prevent conflicting quotes/reservations.

**Stop condition:** package M06 and return its links.

### M07 — iCalendar import, export, and freshness controls

**Prerequisite:** completed M06.

**Scope**

- Implement property-specific external iCalendar feed configuration for Airbnb, Booking.com, and generic ICS URLs.
- Parse safely with a maintained library; support recurring/revised/cancelled events needed for interoperability.
- Implement idempotent sync, logs, timeouts, last-success state, stale-calendar policy, and a Bluehost cron command.
- Expose revocable, tokenized, privacy-safe property availability feeds without re-exporting imported events.

**Required outputs**

- calendar administration, sync command/job, and export route
- fixture-based import/export tests
- `docs/availability-and-ical.md`
- M07 validation record, ZIP, and SHA-256 file

**Validation**

- duplicate syncs do not duplicate blocks.
- changed/cancelled events reconcile safely; a failed partial fetch does not erase prior data.
- every enabled feed participates in availability.
- stale required feeds prevent automatic confirmation as configured.
- exported feed contains no customer personal data and uses revocable access.

**Stop condition:** package M07 and return its links.

### M08 — Reservation workflow and transactional email

**Prerequisite:** completed M07.

**Scope**

- Implement guest and authenticated-customer checkout up to the payment handoff.
- Create/reuse pending-payment holds safely, capture guest contact details, consent, policies, quote snapshot, and reservation identifiers.
- Implement confirmation/account-history views in the correct pre-payment states.
- Implement a database-backed email outbox and templates for customer/admin lifecycle notifications, with retry and failure visibility.

**Required outputs**

- reservation/checkout workflow excluding live provider completion
- email templates, queue/outbox command, and admin visibility
- redacted local mail-capture evidence
- M08 validation record, ZIP, and SHA-256 file

**Validation**

- guest and account checkout tests reach `pending_payment` with a valid hold.
- duplicate submissions are idempotent.
- expired holds release inventory safely.
- email failure does not roll back a valid reservation state and is retryable.

**Stop condition:** package M08 and return its links. Do not use live payment credentials.

### M09 — FedaPay and PayPal sandbox payments

**Prerequisite:** completed M08.

**Scope**

- Verify current official FedaPay and PayPal sandbox documentation before coding; record source URLs, review date, API/SDK version, supported currencies, and webhook verification behavior.
- Implement the payment gateway interface, FedaPay sandbox adapter for XOF Mobile Money, and PayPal sandbox Orders/Checkout adapter for EUR.
- Implement server-created payment sessions/orders, return/cancel pages, authenticated webhook processing, idempotency, provider/amount/currency verification, reconciliation, expiry, late-payment/manual-review, refund state handling, and sanitized logs.
- Keep all sandbox credentials in environment variables. Include no real secret in code or checkpoints.

**Required outputs**

- sandbox payment adapters and webhook endpoints
- gateway fakes/fixtures plus integration instructions
- updated `docs/currency-and-payments.md`
- sandbox test evidence that does not contain credentials or personal data
- M09 validation record, ZIP, and SHA-256 file

**Validation**

- FedaPay XOF and PayPal EUR happy paths confirm exactly one reservation after verified payment.
- tampered amount/currency/reference, invalid signature, duplicate callback, cancellation, failure, expiry, and late callback tests pass.
- browser redirects alone cannot mark a reservation paid.
- no live transaction is attempted.

**Stop condition:** package M09 and return its links.

### M10 — Complete admin and concierge operations

**Prerequisite:** completed M09.

**Scope**

- Finish establishment/property/media/rate/calendar/reservation/payment/customer/staff administration.
- Allow super admins to add scoped administrators and concierges.
- Limit concierge access to permitted reservation/contact operations; hide secrets, settings, and unrelated establishments.
- Add reservation search/filter/export where safe, customer contact actions, payment/calendar/email health summaries, and audit logging.

**Required outputs**

- complete practical admin and limited concierge UI
- audit and authorization tests
- operational notes for common staff tasks
- M10 validation record, ZIP, and SHA-256 file

**Validation**

- CRUD, scoping, validation, upload authorization, and audit tests pass.
- concierge restrictions are enforced server-side, not merely hidden in UI.
- sensitive credentials and full payment details are never displayed.

**Stop condition:** package M10 and return its links.

### M11 — Security, quality, and integrated acceptance

**Prerequisite:** completed M10.

**Scope**

- Complete unit, feature, integration, concurrency, authorization, calendar, payment-webhook, email, and smoke tests.
- Harden CSRF, validation, output escaping, rate limits, file uploads, headers, sessions, logs, error pages, and sensitive-data handling.
- Complete accessibility, responsive, browser, performance, image, and dependency checks.
- Run the full acceptance criteria in this prompt and clearly record any unresolved failure.

**Required outputs**

- test suite and quality scripts
- `docs/security.md`
- `docs/acceptance-test-checklist.md`
- concise reports/evidence
- M11 validation record, ZIP, and SHA-256 file

**Validation**

- all automated suites and production asset builds pass.
- dependency/security scan results are documented with actionable exceptions.
- every acceptance item is marked passed, failed, deferred with approval, or blocked with reason.

**Stop condition:** package M11 and return its links. Do not deploy remotely yet.

### M12 — Bluehost release and deployment package

**Prerequisite:** completed M11.

**Scope**

- Build the production release with compiled assets and a Bluehost-compatible public/private directory split.
- Include safe initial database scripts, optimized-media installation instructions/package reference, writable-directory handling, maintenance mode, atomic-enough release switching, persistent `.env`/uploads preservation, backup, rollback, and health verification.
- Create local/manual SFTP tooling and an optional GitHub Actions deployment workflow with documented secrets. A GitHub push or Bluehost connection is not required to complete the local release artifact.
- Document cPanel PHP/MySQL configuration, phpMyAdmin import, Composer alternatives, cron entries for scheduler/calendar/email/reconciliation, TLS, SMTP, sandbox configuration, and the later production cutover checklist.

**Required outputs**

- `scripts/build-release.*`, manual SFTP deployment helper/config template, and optional GitHub Actions workflow
- versioned `afrik-appart-bluehost-release-v1.zip` plus checksum
- separate database and media package references as appropriate
- `docs/bluehost-deployment.md` and `docs/runbook.md`
- final `README.md`, repository tree, release manifest, and `CHANGELOG.md`
- M12 checkpoint ZIP and checksum

**Validation**

- inspect the release archive and prove `.env`, secrets, development caches, tests not intended for production, and unsafe files are excluded.
- verify public/private layout, writable paths, compiled assets, SQL files, health check, cron commands, and rollback instructions.
- rehearse installation in a clean local/staging environment when available.
- confirm payment configuration remains sandbox by default; production activation requires a deliberate environment change and checklist.

**Stop condition:** return the source checkpoint, Bluehost release, database/media references, checksums, and exact deployment order. Do not perform a real SFTP deployment unless I separately authorize it and provide the destination details.

## Resume commands

Use one of these short commands in a new session if a chat or network connection fails. Attach the latest checkpoint ZIP plus `project-state.json` and `CHECKPOINT.md` when the previous workspace is not available.

**Normal resume**

```text
Resume Afrik Appart from the latest checkpoint. First read project-state.json,
CHECKPOINT.md, and artifacts/checkpoints/manifest.json. Validate the most recent
completed checkpoint, preserve all existing work, and execute only the next
incomplete milestone. Create its ZIP, checksum, validation record, updated state,
and resume instruction, then stop.
```

**Resume a specific interrupted milestone**

```text
Resume Afrik Appart milestone M## from the attached recovery files. Do not redo
completed milestones. Read the recorded blocker and retry only the unfinished
idempotent action. If M## passes, package its completed checkpoint and stop.
```

**Verify without changing anything**

```text
Audit the latest Afrik Appart checkpoint in read-only mode. Verify its checksum,
state files, archive contents, and validation evidence. Report the next milestone,
but do not modify files or begin implementation.
```

If a noncritical business value is unknown, choose the documented default, make it configurable, and add it to `docs/content-todo.md`. Ask me only when a missing decision would create a security problem, make data incompatible, or fundamentally change the booking/payment workflow.

---

## Owner decisions to revisit before production

Use defaults so development can begin, but make these decisions visible in the final handoff:

1. Final site/domain name, logo, brand colors, and whether the attached samples are authoritative at all breakpoints.
2. French only, English only, or both; final translated property/policy content.
3. Default and per-property timezone and canonical pricing currency; both XOF and EUR remain supported for customers.
4. Instant paid booking versus admin approval after payment per property.
5. Complete FedaPay merchant onboarding for Benin/XOF and confirm the enabled operators, fees, settlement account, refund capabilities, API credentials, live callback/webhook configuration, and low-value production verification.
6. Whether the customer pays 100% online or a configurable deposit; PayPal Business EUR account, Mobile Money settlement account, refund/cancellation behavior, and dispute ownership.
7. Taxes, fees, security deposit, minimum stays, discounts, and rounding rules.
8. Cancellation/refund and privacy/retention policies for legal review.
9. SMTP provider, sender domain authentication, admin recipients, and reply-to behavior.
10. Initial super admin and concierge users and their establishment scopes.
11. Property metadata and the exact cover/order/alt text for apartment 401 (14 photos), 402 (12), 403 (19), and 404 (13).
12. Initial Airbnb/Booking.com/other calendar URLs and desired sync threshold.
13. Bluehost plan, cPanel username, domain document root, available PHP/MySQL versions, outbound API access, and whether SSH is enabled.
