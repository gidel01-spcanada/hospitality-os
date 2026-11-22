# Requirements coverage gaps and product-owner questions

Prepared for the UAT suite of Afrik Appart (Laravel 12, multi-tenant, FR/EN). Read-only analysis. Nothing in this file states that a feature passed; "evidence" means what the repository shows when read, not what was run.

## 1. Sources and method

Documents read: `docs/requirements-baseline.md`, `docs/input-inventory.md`, `docs/content-todo.md`, `docs/architecture.md`, `docs/database.md`, `docs/M01-audit-proposal.md` (headings and route inventory), `docs/M02-COMPLETION-SUMMARY.md` (headings), `project-state.json`, `CHANGELOG.md` (M00 to M26), `README.md`, and the three master prompts at the repository root:
- `short-term-rental-website-master-prompt.md` (base, 649 lines)
- `short-term-rental-website-master-prompt (1).md` (adds shared payment workflow, FedaPay and PayPal sections, 747 lines)
- `short-term-rental-website-master-prompt (2).md` (adds recovery protocol and milestone plan M00 to M12, 1224 lines)

Code searched for evidence: `app/`, `routes/`, `config/`, `bootstrap/`, `database/seeders`, migration file names, `scripts/`, `.github/`, `resources/js/app.js`, tests names in `tests/Feature`. "Not found" means a targeted search of those folders found nothing. It does not prove absence; a developer should confirm each item marked "Not found".

Status values: **Implemented** (code and at least one test or UI observed), **Partial**, **Not found**, **Conflict** (code differs from the stated requirement), **Doc only** (documentation statement, no behavior).

## 2. Source documents disagree with each other

| ID | Conflict | Sources | Recommended UAT handling |
|----|----------|---------|--------------------------|
| S-01 | Payment model. Base prompt: `pay_later` is the production default and an optional hosted provider (Stripe) is an extension point. Prompt (1) and (2) and `docs/requirements-baseline.md`: online payment (Mobile Money XOF via FedaPay, PayPal EUR) is required to confirm a booking. The code offers `pay_later` as enabled by default (`CheckoutController::paymentMethods`) plus FedaPay, PayPal, CinetPay, M-Pesa, Interac, Wise, Revolut. | master prompts (base vs 1/2), `docs/requirements-baseline.md`, `CheckoutController.php` | Treat the code and CHANGELOG M09 as current behavior. Ask Q1. Cases UAT-INT-004, 008, 017 record actual behavior. |
| S-02 | Which master prompt is authoritative is not stated. Only prompt (2) contains the milestone plan and the recovery protocol; project-state follows (2). | the three prompts | Use prompt (2) as the baseline for gap listing; ask Q2. |
| S-03 | Milestone naming. `docs/M01-audit-proposal.md` and `docs/M02-COMPLETION-SUMMARY.md` describe a UI/UX redesign (M01 audit, M02 design foundation, M03 admin pilot to M07 QA). `project-state.json` and CHANGELOG use M01 = architecture and M02 = database foundation. | docs vs project-state, CHANGELOG | Documentation consistency only; no functional case. Ask Q3. |
| S-04 | `project-state.json` says current milestone M14 completed; CHANGELOG lists work up to M26. `docs/architecture.md` says no authentication, reservations, payments or calendar sync exist yet. `docs/database.md` omits later tables (tenants, platform_feedback, mobile_access_tokens, messages, amenity categories). | project-state.json, architecture.md, database.md, CHANGELOG.md | Doc staleness. UAT-OPS-005 step 5 compares tables. Ask Q3. |
| S-05 | Multi-tenant "Hospitality OS" cloud mode, platform admin, host signup, mobile API and cleaning schedule appear in the code and CHANGELOG but in none of the three master prompts' main requirement sections (prompt (2) mentions "tenant" once). Source of truth for their acceptance criteria is `docs/adr/0002-hospitality-os-multi-tenant-architecture.md` (not reviewed in depth). | CHANGELOG, `routes/web.php`, ADR 0002 | Acceptance criteria for platform cases are derived from code behavior. Ask Q4. |

## 3. Stated requirements versus implementation evidence

### 3.1 Payments and webhooks

| ID | Requirement (source) | Implementation evidence | Status | Recommended UAT handling |
|----|----------------------|-------------------------|--------|--------------------------|
| R-PAY-01 | Gateway-neutral interface with create, verify, webhook, cancel, refund (prompt (2) "Shared payment workflow") | `app/Contracts/PaymentGateway.php`; sandbox and live gateways per provider | Implemented | UAT-INT-004, 010 to 013 |
| R-PAY-02 | Online checkout creates a configurable 20-minute pending-payment hold that blocks inventory and is released by scheduled cleanup (prompt (1)/(2), baseline "Payment hold duration: 20 minutes") | Setting `payment_hold_minutes` and env `PAYMENT_HOLD_MINUTES` exist; `PayLaterGateway` stores `hold_minutes` in a payload. No expiry job in `routes/console.php`; no `expired` status found; checkout link validity is a fixed 48 hours (`LINK_VALID_HOURS`). | Not found | UAT-INT-017 (records 48 h behavior); exploratory. Ask Q5 |
| R-PAY-03 | Reservation statuses `pending_payment`, `confirmed`, `cancelled`, `completed`, `payment_failed`, `expired` (prompt (2)) | Code uses `pending`, `pending_payment`, `payment_failed`, `confirmed`, `cancelled`, `completed`, `checked_in`, `pending_validation`. `expired` and `manual_review` not found. | Partial | Covered by status assertions in UAT-INT-004, 008, 019 |
| R-PAY-04 | Verify payment through authenticated webhook and/or server lookup; return URL is navigation only (prompt (2) steps 5-6) | PayPal live: verification API; CinetPay and M-Pesa live: server lookup; FedaPay live webhook trusts payload `status` after HMAC; sandbox gateways simulate. Return URL calls `verifyStatus`. | Partial | UAT-INT-001, 002, 010 to 016 |
| R-PAY-05 | Webhook deduplication by provider event or reference ID (prompt (2) step 6) | No event table. Receipt creation is `firstOrCreate` per attempt; status emails can repeat on replay. | Partial | UAT-INT-005, 006 |
| R-PAY-06 | Webhook endpoints exempt from browser CSRF but protected by payload limits, rate controls, safe logging (prompt (2)) | `/webhooks/{provider}` is in the `web` stack; no CSRF exclusion in `bootstrap/app.php`; no throttle on the route. Tests disable CSRF. | Unknown (risk) | UAT-INT-014 (P0). Ask Q6 |
| R-PAY-07 | Compare merchant, internal reference, amount and currency before confirming; reject and alert on mismatch (prompt (2) step 7) | No amount or currency comparison in any gateway `handleWebhook`. | Not found | UAT-INT-015 (exploratory) |
| R-PAY-08 | Late payment after hold expiry or release goes to `manual_review`; no silent overbooking (prompt (2) step 10) | Paid webhook sets `confirmed` unconditionally; no `manual_review` status. | Not found | UAT-INT-019 (exploratory, P1) |
| R-PAY-09 | Scheduled reconciliation job for old pending attempts with alerts (prompt (2)) | No reconciliation schedule in `routes/console.php`. | Not found | No case possible; list as launch risk. Ask Q5 |
| R-PAY-10 | Refunds with confirmation, reason, idempotency, audit and notification; document automated vs manual refunds (prompt (2) "Refunds and administration") | Live gateways throw "Refunds must be initiated from the provider dashboard"; no refund route or screen found; sandbox `refund` sets status `refunded`. | Not found (UI) | Not testable in the UI. Ask Q7 |
| R-PAY-11 | Admins can view payment attempts, provider references, normalized status, safe errors, reconciliation state (prompt (2)) | Admin reservation page shows payment proofs only; `provider_reference` is not displayed (UI shows provider name and date). | Partial | UAT suite uses read-only SQL for references (UAT-INT header). Ask Q7 |
| R-PAY-12 | FedaPay is the confirmed Mobile Money provider; PayPal charges EUR; whole-unit XOF (prompt (2), baseline) | `FedaPayLiveGateway` and sandbox exist; FedaPay is offered only for XOF reservations; PayPal amount uses secondary currency when the reservation currency is unsupported. | Implemented (needs provider sandbox verification) | UAT-INT-011, 017 |
| R-PAY-13 | Supported customer currencies XOF and EUR; canonical XOF; fixed rate 655.957 (baseline) | Seed stores `eur_to_xof_rate = 655.957`; code also supports KES (M-Pesa), XAF (CinetPay), CAD, GBP, USD, AUD, CHF (PayPal), `secondary_currency` per establishment, `InternationalCurrencyConverter`. | Conflict (scope wider than baseline) | UAT-INT-013, 017. Ask Q8 |
| R-PAY-14 | Payment providers beyond the baseline: CinetPay, M-Pesa, Interac, Wise, Revolut, cancellation guarantee hold | Present in code; CinetPay and M-Pesa live files contain orphaned code lines after `createIntent` (see section 5). | Partial | UAT-INT-012, 013, 040 |
| R-PAY-15 | Never store tokens, secrets, PINs or card data in payment attempts (prompt (2)) | `payload` stores raw provider responses (`transaction`, `capture`, `check_response`, request body for sandbox webhooks). Contents not inspected. | Unknown | Add a check to UAT-INT-004: inspect `payment_attempts.payload` (SELECT) for sensitive keys. Ask Q9 |

### 3.2 Availability, calendars and reservations

| ID | Requirement (source) | Implementation evidence | Status | Recommended UAT handling |
|----|----------------------|-------------------------|--------|--------------------------|
| R-AVL-01 | Reservation-night rows with a unique `(property_id, stay_date)` constraint or equivalent; locking transaction; concurrent booking tests (prompt (2) "Availability") | No `reservation_nights` table or `lockForUpdate` found in `app/` or migration names. Availability is a read query (`AvailabilityService`). | Not found | Concurrency cannot be checked through the UI. Recommend a developer-run test; UAT may attempt two near-simultaneous bookings (exploratory, not written as a case). Ask Q10 |
| R-AVL-02 | Check-in inclusive, check-out exclusive; minimum stay | `AvailabilityService::isAvailable` uses strict inequalities and `minimum_stay`. | Implemented | UAT-INT-021 (boundaries) |
| R-AVL-03 | Admin blocks have reason, internal notes, creator, audit | `AdminAvailabilityBlock`; audit via model events. | Implemented (other UAT areas) | Not in this suite |
| R-CAL-01 | HTTPS-only feed fetch, redirect limit, size limit, SSRF protection (prompt (2) "External calendar imports") | `Http::timeout(15)->get($feed->url)`; validation is `url` only. | Not found | UAT-INT-030 (exploratory security) |
| R-CAL-02 | Encrypted feed URL; ETag and Last-Modified; consecutive failure count; sync history screen; recurring events via a maintained library (prompt (2)) | No `encrypted` cast; no ETag/Last-Modified columns; no failure counter; status and last error shown on the property page only; custom parser without `RRULE` expansion. | Not found | UAT-INT-020, 023, 024, 030 |
| R-CAL-03 | Normal sync every 15 minutes (baseline, prompt (2)) | Scheduler runs hourly; per-feed interval default 60. CHANGELOG M21: "Kept hourly scheduling". | Conflict | UAT-INT-029. Ask Q11 |
| R-CAL-04 | Before booking every enabled feed must be fresh (15 minutes); stale feed triggers refresh; otherwise fail closed (prompt (2) "Final booking validation") | Setting `calendar_freshness_minutes = 15` seeded but not read by code searched. | Not found | UAT-INT-031 (exploratory) |
| R-CAL-05 | Never erase previous data after partial or failed fetch; safe handling of empty/malformed feeds | Failed fetch keeps data. A successful HTTP 200 with no events (empty or non-calendar body) deletes all events and marks the feed fresh. | Partial | UAT-INT-023, 024 |
| R-CAL-06 | Revocable high-entropy export URL with copy button and regenerate/revoke action; stable UIDs; cache headers; no re-export of imported events (prompt (2) "Calendar exports") | Token generated at property creation (48 hex), shown with copy button; export excludes imported events; UIDs stable; no regenerate or revoke route; no cache headers; no `DTSTAMP`. Route path is `/calendar/{slug}/{token}.ics`, not `/calendars/{token}.ics` (prompt example only). | Partial | UAT-INT-026, 027 |
| R-CAL-07 | Setup instructions for Airbnb/Booking.com and statement that sync is periodic (prompt (2)) | One-line help texts only: `admin.add_calendar_feed_help` ("Import a calendar from another platform ... to block dates") and `admin.external_calendar_help` ("Use this URL in Booking.com, Airbnb, or another calendar service."). No step-by-step instructions and no statement that sync is periodic and not real time were found. | Partial | UAT-INT-020 and 027 record the help text shown. Ask Q12 |

### 3.3 Email

| ID | Requirement (source) | Implementation evidence | Status | Recommended UAT handling |
|----|----------------------|-------------------------|--------|--------------------------|
| R-EML-01 | Database outbox written with the state change; scheduled worker with retries; attempts, last error, sent time, final failure (prompt (2) "Transactional email") | `email_outbox` rows created with state changes; command marks `sent` or `failed` with the error in `payload`; no attempts counter, no retry of `failed` rows. | Partial | UAT-INT-037, 038 |
| R-EML-02 | Lifecycle messages: pending-payment, confirmed (customer and admin), failure, expiry, refund, manual review, cancellation (guest and admin), verification, password reset, invitation | Present: reservation created/received, status updated (including failure and completed), payment link, receipt, payment proof submitted, message received. Not found: expiry, refund, manual-review messages. Invitation and reset use Laravel's synchronous mail. | Partial | UAT-INT-004, 008, 037, 047; UAT-PLAT-009 |
| R-EML-03 | Responsive HTML and plain-text templates in French and English; mail capture for development | HTML views in `resources/views/emails`; plain-text versions not found; `MAIL_MAILER=log` default. | Partial | UAT-INT-037 step 5 records content type (HTML only vs multipart) |
| R-EML-04 | Env variables for SMTP host, port, encryption, user, password, sender, reply-to, admin fallback recipient | `.env.example` has MAIL_* (host, port, username, password, scheme, from). `MAIL_REPLY_TO` and an admin fallback recipient are not present; `SPRINTPAY_FEEDBACK_EMAIL` is a separate platform-feedback recipient. | Partial | UAT-OPS-020 |

### 3.4 Security, privacy and reliability

| ID | Requirement (source) | Implementation evidence | Status | Recommended UAT handling |
|----|----------------------|-------------------------|--------|--------------------------|
| R-SEC-01 | Rate limits for login, reset, booking, availability, contact, calendar export (prompt (2)) | `throttle` only on `/feedback` (5/min), `/help/chat` (10/min) and the cleaning-share status update (30/min). Login, register, reset, booking, availability, public export and webhooks have no throttle in `routes/web.php`. | Not found | UAT-PLAT-011 note, UAT-INT-027 note, UAT-INT-011 note; recommend a dedicated security UAT. Ask Q13 |
| R-SEC-02 | Email verification (prompt (2)) | `MustVerifyEmail` is commented out in `User`; platform-created admins are pre-verified; checkout flow has its own identity step. | Partial | UAT-PLAT-011 step observation |
| R-SEC-03 | CSP appropriate to assets; frame restrictions; HSTS only after HTTPS verified (prompt (2)) | `SecurityHeaders` sets nosniff, frame options, referrer policy, X-XSS-Protection, HSTS; no CSP or Permissions-Policy. | Partial | UAT-OPS-016 |
| R-SEC-04 | Secure, HTTP-only, SameSite cookies; production session settings (prompt (2)) | `config/session.php` defaults; `SESSION_SECURE_COOKIE` absent from `.env.example`. | Unknown | UAT-OPS-016 step 4 |
| R-SEC-05 | Honeypot or equivalent spam control on public forms | reCAPTCHA v3 (login, register, host signup, booking) only when keys are set; feedback and contact forms have none. | Partial | UAT-INT-043 |
| R-SEC-06 | Encryption at rest for external calendar URLs and sensitive configuration | Not found for calendar URLs. | Not found | UAT-INT-030 step 7 |
| R-SEC-07 | Audit trail for staff actions affecting access, availability, rates, properties and reservations | Generic Eloquent event audit for every model; explicit entries for amenity sync and host assignment (CHANGELOG M25/M26). | Implemented | UAT-PLAT-010 |
| R-SEC-08 | Structured logs with correlation IDs; user-friendly failures with internal correlation IDs | Errors logged to a daily `errors` channel; no correlation ID found. | Partial | UAT-OPS-018 step 8 |
| R-SEC-09 | Data-retention and anonymization guidance; privacy and cancellation policies labelled for legal review | `/privacy`, `/terms`, `/cookies` pages exist (content not reviewed here); retention guidance not found. | Unknown | Owner review; Ask Q14 |
| R-SEC-10 | Tenant isolation | `BelongsToTenant` trait on 4 models only; other models rely on controller scoping; `managesEstablishment` returns true for any admin on any establishment id. | Partial | UAT-PLAT-008, UAT-INT-028 step 6, UAT-INT-039 step 9 |

### 3.5 Operations, deployment and documentation

| ID | Requirement (source) | Implementation evidence | Status | Recommended UAT handling |
|----|----------------------|-------------------------|--------|--------------------------|
| R-OPS-01 | Authenticated admin system-health screen (database, writable paths, scheduler heartbeat, last email job, failed email count, stale feeds, gateway mode, reconciliation, manual-review counts) (prompt (2) "Operations") | Not found. `/health` is minimal; `/admin/logs` shows log files. | Not found | UAT-OPS-001 note |
| R-OPS-02 | Public health endpoint with no sensitive details | `GET /health` returns status, application, environment, PHP version, UTC time. | Implemented | UAT-OPS-001, 002 |
| R-OPS-03 | `docs/runbook.md` and `docs/bluehost-deployment.md` | Neither file exists in `docs/`. | Not found | UAT-OPS-019 |
| R-OPS-04 | README includes safe first-deploy, migration, cron, backup, verification and rollback steps | README has deployment options, cron and verification; no backup/restore or rollback steps. | Partial | UAT-OPS-010, 019 |
| R-OPS-05 | Admin alerts or dashboard warnings for repeated calendar, email and payment failures | Not found (feed status only on the property page). | Not found | UAT-INT-023, 037 observations |
| R-OPS-06 | Deploy by SFTP; preserve `.env`, uploads and runtime data; no Node service | Script uses FTP/FTPS (`FtpWebRequest`); workflow uses SSH/SCP; `.env` skipped; uploads untouched. | Partial (channel mismatch) | UAT-OPS-013, 015 |
| R-OPS-07 | Clean MySQL database from `schema.sql` plus seeds; migrations and SQL describe the same schema | Both exist plus `bluehost-import.sql`; equivalence not shown. | Unknown | UAT-OPS-008 |
| R-OPS-08 | Images imported idempotently with 14/12/19/13 mapping | `property:sync-media` and seeder; test `PropertyMediaImportTest`. | Implemented (needs run) | UAT-OPS-006 |
| R-OPS-09 | Scheduled jobs from one cron entry | README and `routes/console.php`; reservation completion is a queued job needing a worker while `.env.example` sets `QUEUE_CONNECTION=database`. | Partial | UAT-OPS-012 |
| R-OPS-10 | Default timezone `Africa/Porto-Novo` (baseline) | `config/app.php` timezone is hard-coded `UTC`. | Conflict | UAT-OPS-005 step 2. Ask Q15 |
| R-OPS-11 | PHP 8.3 and MySQL 8.x (baseline) | CI uses PHP 8.3; README mentions PHP 8.5 locally; default `.env.example` uses SQLite. | Partial | UAT-OPS-003, 015 |
| R-OPS-12 | Queue/outbox backlog monitoring | Not found. | Not found | Mention in UAT-OPS-012 |
| R-OPS-13 | `robots.txt` disallows non-public areas; sitemap generated for canonical URLs (prompt (2) SEO) | `robots.txt` has empty `Disallow:`; static `sitemap.xml` for `afrikappart.com`; `noindex` meta tags on private pages. | Partial | UAT-OPS-016 step 8 |

### 3.6 Content, inputs and prototype claims

| ID | Requirement (source) | Implementation evidence | Status | Recommended UAT handling |
|----|----------------------|-------------------------|--------|--------------------------|
| R-CNT-01 | Replace draft placeholders before production (support email, phone, addresses, "Draft" names, nightly rates, domain) (`docs/content-todo.md`, `docs/input-inventory.md` section 3) | Seed still contains `support@afrikappart.example`, `+229 00 00 00 00`; property seed content not reviewed here. | Open | UAT-OPS-005 step 4 lists visible placeholders; owner sign-off required |
| R-CNT-02 | No unsupported prototype claims (verified stays, country counts, review quotes not from the database, "ready for integration" text, designer commentary, Unsplash photos) (`docs/input-inventory.md` section 4) | Home page review block is database-driven (`site_reviews`); other pages not scanned. | Unknown | Manual content scan on all public pages; UAT-INT-046 covers ratings |
| R-CNT-03 | 58 photos mapped 14/12/19/13 | `docs/input-inventory.md`, command and manifest. | Implemented | UAT-OPS-006 |
| R-CNT-04 | Admin notification email address, support email, SMTP provider, payment live credentials, calendar URLs (content-todo "Required operational configuration") | Owner-supplied values are outside the repository. | Open | Environment owner provides before UAT; record presence only |
| R-CNT-05 | Locale: French default, English secondary, timezone, currency display | `APP_LOCALE=fr` in `.env.example`, `default_locale=fr` seed; user/cookie/session precedence in `SetLocale`. | Implemented | Covered by other UAT areas; locale checks inside this suite's cases |

### 3.7 Platform administration

| ID | Requirement | Implementation evidence | Status | Recommended UAT handling |
|----|-------------|-------------------------|--------|--------------------------|
| R-PLT-01 | Platform tenant list, create (cloud only), suspend, activate | `PlatformAdminController`; `PlatformAdminTest` covers create, deny and mode gate; suspend/activate have no automated test found. | Implemented (partly tested) | UAT-PLAT-001 to 007 |
| R-PLT-02 | Platform feedback triage | Form, `platform_feedback` table, email to `SPRINTPAY_FEEDBACK_EMAIL`. No list, status or assignment screen. | Partial | UAT-PLAT-016 to 019. Ask Q16 |
| R-PLT-03 | Suspension of a tenant also blocks API and background work | Only the web middleware checks status; mobile API, scheduler (calendar sync, email) and webhooks do not. | Not found | UAT-PLAT-012 (exploratory) |
| R-PLT-04 | Tenant deletion, editing and billing | No routes found. | Not found | Out of scope; Ask Q4 |
| R-PLT-05 | Platform audit and visibility of cross-tenant actions | Generic audit log; scope across tenants unknown. | Unknown | UAT-PLAT-008 step 6, UAT-PLAT-010 |

## 4. Defect candidates and risks found during reading (for the product owner and developers)

These were seen in the code, not run. Each is turned into an exploratory case where possible.

1. `app/Services/Payments/CinetPayLiveGateway.php` (from line 61) and `app/Services/Payments/MpesaLiveGateway.php` (from line 68) contain orphaned array-literal lines after the end of `createIntent`. They look like PHP syntax errors that would break production-mode CinetPay and M-Pesa. A developer should run a static `php -l` on both files. UAT-INT-012 and 013 (Part B) are blocked until this is confirmed.
2. `/webhooks/{provider}` has no CSRF exclusion in `bootstrap/app.php`, so provider callbacks may receive HTTP 419 in a real deployment. The test suite does not catch this (UAT-INT-014).
3. A signed sandbox webhook without a `status` field marks the attempt paid (default `paid`). Late "paid" webhooks confirm cancelled reservations without overlap checks (UAT-INT-009, 019).
4. A successful fetch of an empty or non-calendar page deletes all external events of the feed and marks it fresh (UAT-INT-024).
5. Calendar feed URLs are not restricted to public HTTPS destinations and are stored in clear text (UAT-INT-030).
6. `CompletePastReservations` is queued but `.env.example` sets `QUEUE_CONNECTION=database` without a documented worker; reservations may never complete and review-request emails may never be sent (UAT-OPS-012).
7. `User::managesEstablishment` returns true for any admin and any establishment id, and `Property`, `Reservation`, `User` models have no tenant scope; cross-tenant access to calendar and receipts may be possible (UAT-INT-028, 039; UAT-PLAT-008).
8. Tracked file `afrikappart_db.txt` contains a short token-like string. If it is a database credential it must be rotated and removed from history (UAT-OPS-020). The value is intentionally not reproduced here.
9. `scripts/build-release.ps1` does not exclude `afrik-appart-mysql-seed-export.sql` or `afrik-appart-mysql-export.sql` (they hold seeded password hashes); it also runs `git clean -fd -- public/uploads` (UAT-OPS-011).
10. `public/.user.ini` caps uploads at 3 MB (`upload_max_filesize`) and 4 MB (`post_max_size`) while the forms and validation allow 5 MB (UAT-OPS-016).
11. Payment proofs are stored under `public/uploads/reservations/<id>/` and may be fetchable by direct URL without authentication (UAT-OPS-017 step 5, UAT-INT-040).
12. `test-failure-full.txt` at the root records a failing `PaymentWorkflowTest` case (checkout accordion). The current status is unknown (UAT-OPS-015 step 3).
13. Re-running `php artisan db:seed` resets the three seeded accounts and the site name setting (UAT-OPS-004).

## 5. Recommended handling of items that cannot be tested

| Item | Why it cannot be UAT-tested now | Proposed handling |
|------|--------------------------------|-------------------|
| Live payment providers (real merchant accounts) | Financial risk and missing onboarding (master prompt owner decision 5) | Test only with provider sandboxes (UAT-INT-011 to 013); real-money verification stays an owner-run, low-value production smoke test after go-live |
| Concurrent bookings, race conditions | Not reproducible from the UI | Developer-level automated test (master prompt testing item 6) |
| Reconciliation, refunds, hold expiry | Not implemented | Mark as known gaps; add cases when implemented |
| Stale feed fail-closed behavior | Not implemented | UAT-INT-031 records current behavior |
| System-health screen, runbook | Missing | Add after owner decision (Q5, Q17) |
| Hosting-specific limits (PHP version, PHP execution in uploads, outbound access to payment APIs) | Depends on the hosting plan (owner decision 13) | Verify on staging with UAT-OPS-013, 016, 017 |

## 6. Product-owner questions

1. Q1. Is online payment mandatory to confirm a booking (prompt (1)/(2), baseline) or is `pay_later` an acceptable default (base prompt, current code default)? Which payment methods are in scope for launch?
2. Q2. Which master prompt file is authoritative? Should the other two be archived?
3. Q3. Should `docs/M01-audit-proposal.md`, `docs/M02-COMPLETION-SUMMARY.md`, `docs/architecture.md`, `docs/database.md` and `project-state.json` be updated or marked as historical?
4. Q4. Where are the acceptance criteria for the multi-tenant cloud mode (platform admin, tenant signup, suspension, tenant lifecycle: edit, delete, billing)? Is ADR 0002 the reference?
5. Q5. What is the intended handling of unpaid checkouts: a 20-minute hold with expiry (baseline) or a 48-hour payment link (current code)? Should reconciliation and manual review be built before launch?
6. Q6. Confirm that provider webhooks must work without a CSRF token and that the developers will exclude `/webhooks/*` from CSRF protection; should rate limits apply?
7. Q7. Who issues refunds (provider dashboard or the application)? Should admins see provider references and payment attempt history in the reservation screen?
8. Q8. Which currencies are officially supported at launch (baseline says XOF and EUR; the code also handles KES, XAF, CAD and others)?
9. Q9. Is it acceptable that raw provider responses are stored in `payment_attempts.payload`? What data may be stored?
10. Q10. Is a database-level double-booking guarantee (nights table or locking) required before launch?
11. Q11. Should calendar sync run every 15 minutes (baseline) or hourly (current)? What is the booking freshness threshold, and should a stale feed block a booking?
12. Q12. Should hosts be able to regenerate or revoke the public iCalendar URL? Where should channel setup instructions appear?
13. Q13. Which routes need rate limiting (login, registration, reset, booking, availability, signup, export, webhooks)?
14. Q14. Who approves privacy, cookie, terms and retention texts? Are the sub-processors (Google reCAPTCHA, Google Analytics/GTM, Groq AI, payment providers) listed in the privacy policy?
15. Q15. What is the production timezone (baseline `Africa/Porto-Novo`, code `UTC`)?
16. Q16. Who receives platform feedback (`SPRINTPAY_FEEDBACK_EMAIL`: the platform vendor or the operator)? Is mailbox triage enough, or is an in-app list needed?
17. Q17. Is an admin system-health page, a runbook and a rollback procedure required before launch?
18. Q18. Should a platform admin be allowed to suspend their own tenant, and should suspension block mobile API tokens and scheduled jobs?
19. Q19. Which hosting channel is authoritative for deployment: FTP/FTPS (script) or SSH/SFTP (workflow, README)?
20. Q20. Is a queue worker available on the production host, or should `QUEUE_CONNECTION` be `sync`?
21. Q21. What are the recovery objectives (backup frequency, restore time)?
22. Q22. Should the placeholder values listed in `docs/content-todo.md` block go-live? Who supplies the final names, addresses, rates, policies and contact data?

## 7. Route and component to case map (seed for the traceability matrix)

| Route / component | Cases |
|-------------------|-------|
| `GET /platform/tenants` | UAT-PLAT-001, 005, 020 |
| `GET /platform/tenants/create`, `POST /platform/tenants` | UAT-PLAT-002, 003, 004, 005 |
| `POST /platform/tenants/{tenant}/suspend`, `/activate` | UAT-PLAT-005, 006, 007, 012, 020 |
| `ResolveTenant`, `EnsureUserIsPlatformAdmin`, tenant isolation | UAT-PLAT-005, 006, 008, 012 |
| `GET/POST /host/register` | UAT-PLAT-004, 011, INT-043 |
| `php artisan platform:mode`, `config/platform.php`, `PlatformBrand` | UAT-PLAT-013, 014, 015 |
| `GET/POST /feedback` | UAT-PLAT-016, 017, 018, 019 |
| `AccountInvitationService`, password reset | UAT-PLAT-002, 009 |
| Audit log of platform actions | UAT-PLAT-010 |
| `POST /webhooks/{provider}` | UAT-INT-001 to 015, 018, 019 |
| `/reservations/{id}/checkout*` (start, return, complete, cancel, PayPal create/capture) | UAT-INT-004, 008, 010, 016, 017, 019 |
| FedaPay, PayPal, CinetPay, M-Pesa gateways | UAT-INT-004 to 013, 015 |
| Offline proof routes, `confirm-offline-payment` | UAT-INT-040 |
| `/admin/properties/{property}/calendar*` | UAT-INT-020 to 025, 028, 030, 031 |
| `/admin/properties/{property}/calendar/export.ics` | UAT-INT-026, 028 |
| `GET /calendar/{slug}/{token}.ics` | UAT-INT-027 |
| Scheduler (`routes/console.php`) | UAT-INT-029, 036, UAT-OPS-012 |
| `/admin/reviews/import`, `/admin/establishments/{id}/reviews/import`, `reviews/sync-google`, `reviews:*` commands | UAT-INT-032 to 036, 046 |
| `messages:send-email-notifications`, `email_outbox` | UAT-INT-037, 038, 047, UAT-OPS-012 |
| `GET /reservations/{id}/receipt`, `POST /admin/reservations/{id}/receipt`, proof download | UAT-INT-039, 040 |
| Analytics consent (`resources/js/app.js`, partial) | UAT-INT-041, 042 |
| reCAPTCHA, `POST /help/chat`, `POST /admin/properties/{id}/improve-copy` | UAT-INT-043, 044, 045 |
| `POST /admin/reservations/{id}/payment-link` | UAT-INT-047 |
| `GET /health`, `/up` | UAT-OPS-001, 002 |
| Install, seed, `db:seed`, seeders | UAT-OPS-003, 004, 005 |
| `property:sync-media`, `photo/`, `seed-assets` | UAT-OPS-006 |
| `scripts/export-local-db.*`, `scripts/import-mysql-seed.ps1` | UAT-OPS-007 |
| `database/schema.sql`, migrations | UAT-OPS-008, 009 |
| Backup/restore, rollback docs | UAT-OPS-010, 019 |
| `scripts/build-release.*` | UAT-OPS-011 |
| `scripts/deploy-bluehost.ps1`, `bluehost-deploy-script.ps1` | UAT-OPS-013 |
| `scripts/post-deploy-validate.sh`, `post-deploy-bluehost.sh` | UAT-OPS-014 |
| `.github/workflows/*` | UAT-OPS-015 |
| Production config, security headers, error pages, robots/sitemap | UAT-OPS-016 |
| Web exposure of private files | UAT-OPS-017 |
| Error logging, `/admin/logs` | UAT-OPS-018 |
| `.env.example`, config files, repository hygiene | UAT-OPS-020 |

## 8. Overlap with cases written by other UAT areas (for de-duplication)

Other agents wrote cases in the same `test-cases` folder while this suite was prepared. The cases below test the same behavior from a different angle. Keep both only if the extra angle in the right column is wanted; otherwise keep the case from the functional area and retain the listed extra steps.

| This suite | Overlaps with | Extra coverage unique to this suite |
|------------|---------------|-------------------------------------|
| UAT-PLAT-013, 014 | UAT-JOB-003 | `.env` rewrite details, missing `.env`, uppercase input |
| UAT-PLAT-016 to 018 | UAT-FEED-001 to 005 | Reply-To header, stored record and `user_id`, mail failure leaves a stored row |
| UAT-INT-001 to 003, 005, 006, 008 | UAT-PAY-027, 028, 029 | Reference lookup by SQL, replay counts of receipts and status emails, concurrent replay |
| UAT-INT-010 | UAT-PAY-020 | Simulated PayPal webhook shape and the `event_type` mismatch |
| UAT-INT-012, 013 | UAT-PAY-025, 026 | Production-path verification against provider sandboxes, orphaned-code blocker |
| UAT-INT-016, 017 | UAT-PAY-024, 033 | Token and role matrix for `complete` and `return`, currency gating |
| UAT-INT-020 to 025 | UAT-CALA-001 to 008 | Fixture file set, `DATETIME` handling, empty/non-calendar feed (INT-024) |
| UAT-INT-026, 027 | UAT-CALA-009, 010, UAT-CAL-001 to 005 | Privacy search terms, UID stability, calendar-client import |
| UAT-INT-028 | UAT-CALA-012 | Cross-tenant admin step |
| UAT-INT-029 | UAT-CALA-011, UAT-JOB-002 | Interval and disabled-feed matrix |
| UAT-INT-032 to 036 | UAT-REVA-003 to 008 | Fixtures, clamping rules, name-collision behavior |
| UAT-INT-037, 038 | UAT-MAIL-001, 007 | SMTP outage does not roll back booking or payment |
| UAT-INT-039, 040 | UAT-PAY-031 to 036 | Cross-tenant receipt access, direct URL exposure of proofs |
| UAT-INT-044 | UAT-HELP-003 to 006 | None beyond the 1200-character limit |
| UAT-INT-047 | UAT-MAIL-004 | Token tampering step |
| UAT-OPS-006 | UAT-JOB-004 | Count mapping, idempotency, EXIF check |
| UAT-OPS-012 | UAT-JOB-001, 002 | Queue worker dependency of the hourly job |

Cases with no overlap found: UAT-PLAT-001 to 012, 015, 019, 020; UAT-INT-004, 007, 009, 011, 014, 015, 018, 019, 030, 031, 041 to 043, 045, 046; UAT-OPS-001 to 005, 007 to 011, 013 to 020.

> Reconciliation note: UAT-PLAT-013, 014, 016 to 018, UAT-INT-044, UAT-INT-047, UAT-CALA-009 and UAT-CALA-010 were removed as duplicates; see traceability.md section 2. Section 8 rows that name them now point to the retained cases.

