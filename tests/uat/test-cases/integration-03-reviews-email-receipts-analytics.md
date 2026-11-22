# Integrations: review imports, email delivery, receipts and proofs, analytics consent, third-party services (UAT-INT-032 to UAT-INT-047)

Scope: `ReviewImportService`, `GoogleReviewSyncService`, `reviews:sync-google` and `reviews:import-booking-csv` commands, `EmailOutbox` and `messages:send-email-notifications`, `ReceiptController`, `PaymentProofService`, analytics consent (`resources/js/app.js`, `resources/views/partials/analytics-consent.blade.php`, `resources/views/layouts/app.blade.php`), reCAPTCHA, Groq-backed help chat and property copy assistant.

Common test data: admin `admin@afrikappart.test`; host `host-a@example.test` (assigned to one establishment E1); host `host-other@example.test` (assigned to E2 only); customer `guest@afrikappart.test`; reviewer names `UAT Reviewer One` and similar. All CSV and JSON fixtures below are synthetic and are created by the tester in a text editor.

CSV fixtures:
- `generic.csv`
  ```
  reviewer_name,source,rating,review_text,source_url
  UAT Reviewer One,google,5,Great stay,https://example.test/r/1
  UAT Reviewer Two,booking,9,Rating above range,
  ,google,4,No reviewer name,
  UAT Reviewer Three,unknown-source,0,Zero rating,
  UAT Reviewer Four,google,4,Missing last column
  ```
- `booking.csv` (positional columns: date, reviewer, ignored, title, positive, negative, score)
  ```
  date,reviewer_name,x,title,positive,negative,score
  2026-01-10,UAT Booker One,x,Lovely,Clean and quiet,Noisy street,9.2
  2026-01-12,UAT Booker Two,x,OK,Fine,,7
  2026-01-15,,x,No name,Fine,,8
  2026-01-16,UAT Booker Three,x,Bad score,Fine,,0
  ```
- `google.csv`
  ```
  Review Date,Reviewer Name,Star Rating,Comment,Review Link
  2026-02-01,UAT Googler One,5,Superb,https://example.test/g/1
  2026-02-02,UAT Googler Two,3,Average,
  ```
- `airbnb.csv`
  ```
  Date,Reviewer,Overall Rating,Public Review,Listing URL
  2026-03-01,UAT Airbnber One,4,Good host,https://example.test/a/1
  ```

---

## UAT-INT-032: Global generic CSV review import (admin reviews page)
- **Feature / journey:** Review import (paste CSV)
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `POST /admin/reviews/import` (`admin.reviews.import`); `AdminController::importReviews`; `ReviewImportService::importGenericCsv`; `resources/views/admin/reviews.blade.php`
- **Preconditions / setup:** Admin logged in; reviews table known (record count).
- **Test data:** Contents of `generic.csv` pasted into the CSV text area.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with an empty text area. | Validation error ("CSV content required"); nothing created. |
  | 2 | Paste `generic.csv` and submit. | Redirect to Admin > Reviews with a "reviews imported" message and a count. By the code: 2 rows import (`UAT Reviewer One`, `UAT Reviewer Two`), the nameless row and the 4-column row are skipped, and `UAT Reviewer Three` (rating 0) is imported with rating clamped to 1 (record the actual count). |
  | 3 | Open the review list. | `UAT Reviewer Two` has rating 5 (clamped from 9) and source `booking`; `UAT Reviewer Three` source `google` (unknown source mapped to google). All imported reviews are active. |
  | 4 | Submit the same CSV again. | Record the count: generic import has no duplicate detection, so rows are created again. |
  | 5 | As host or concierge, open `/admin/reviews`. | Access denied (admin-only route group). |
- **Acceptance criteria:** Valid rows import with clamped ratings; invalid rows are skipped; permissions hold. Duplicate rows on re-import (step 4) are recorded for the product owner.
- **Evidence to capture:** Screenshots of the result message and list; SQL count of `site_reviews`.
- **Notes / assumptions / open questions:** Imported reviews carry `reviewed_at = now()` regardless of any date in the file (generic format has no date column). Reviews appear on the public reviews page (`/reviews`) and home page averages; verify one imported review is shown publicly.

---

## UAT-INT-033: Establishment review import: Booking.com CSV, update on re-import
- **Feature / journey:** Review import (file upload per establishment)
- **Priority:** P1
- **Role / permissions:** Admin or host of the establishment
- **References:** `POST /admin/establishments/{establishment}/reviews/import` (`admin.establishments.reviews.import`); `AdminEstablishmentController::importReviews`; `ReviewImportService::importBookingCsv`; command `php artisan reviews:import-booking-csv <path> [--dry-run]`
- **Preconditions / setup:** Establishment E1 reviews tab open (`#reviews`).
- **Test data:** `booking.csv`; format `booking`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Upload `booking.csv` with format "booking". | Flash with detailed counts: inserted 2, updated 0, skipped 2. |
  | 2 | Check ratings and text. | `UAT Booker One`: score 9.2 becomes rating 5 (score divided by 2, rounded); text contains title, positive text and a negative note; `UAT Booker Two`: 7 becomes rating 4 (3.5 rounds to 4). |
  | 3 | Upload the same file again. | inserted 0, updated 2, skipped 2; no duplicate rows. |
  | 4 | CLI check on a test server: `php artisan reviews:import-booking-csv tests/uat/booking.csv --dry-run` (path to the same fixture). | Prints a summary without saving; exit code 0. A missing path prints "CSV file not found" and exits non-zero. |
- **Acceptance criteria:** Booking import is idempotent (identity: establishment, reviewer, date) and reports inserted/updated/skipped.
- **Evidence to capture:** Flash screenshots; SQL of rows and counts; CLI output.
- **Notes / assumptions / open questions:** The CLI command uses the first establishment's tenant (`Establishment::query()->value('tenant_id')`) and does not take an establishment argument. Confirm with the product owner whether that is intended for multi-tenant installs.

---

## UAT-INT-034: Airbnb and Google header-mapped imports, name collisions and missing columns
- **Feature / journey:** Review import (header-mapped formats)
- **Priority:** P1
- **Role / permissions:** Admin or host of the establishment
- **References:** `ReviewImportService::importAirbnbCsv/importGoogleCsv/importHeaderMappedCsv`, `HEADER_ALIASES`
- **Preconditions / setup:** Establishment E1.
- **Test data:** `google.csv`, `airbnb.csv`; a variant `google-bad-header.csv` where the header row is `Foo,Bar,Baz` followed by one data row; a variant `google-same-name.csv` with two rows for `UAT Googler One` with different texts.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Upload `google.csv` with format "google". | Message "2 reviews imported"; reviews have source `google`, ratings 5 and 3, link on the first. |
  | 2 | Upload `airbnb.csv` with format "airbnb". | 1 review, source `airbnb`, rating 4. Record whether a source value of `airbnb` displays correctly in the list and on the public page (the generic import only maps booking/google). |
  | 3 | Re-upload `google.csv`. | Count reported again as 2 but no new rows (match by establishment + source + reviewer name); data updated. |
  | 4 | Upload `google-same-name.csv`. | Record: only one review per reviewer name can exist; the second row overwrites the first. |
  | 5 | Upload `google-bad-header.csv`. | Record the outcome (expected: 0 imported, no error message, because unmapped columns are skipped silently). |
- **Acceptance criteria:** Mapped imports are repeatable without duplicates. Step 4 and 5 results are recorded as usability/data findings.
- **Evidence to capture:** Flash messages; SQL of rows by source.
- **Notes / assumptions / open questions:** Reviews with the same reviewer name but different stays collapse into one record, which may lose data.

---

## UAT-INT-035: Review import validation and permissions
- **Feature / journey:** Review import guard rails
- **Priority:** P1
- **Role / permissions:** Admin; host of E1; host of E2; concierge; customer; anonymous
- **References:** `AdminEstablishmentController::importReviews` (`managesEstablishment`, file `mimes:csv,txt`, `max:5120`); route group `establishment-manager`
- **Preconditions / setup:** E1 and E2 exist; hosts assigned as above.
- **Test data:** `google.csv` (valid), `evil.exe` renamed `evil.csv`, a 6 MB text file, a PDF, an empty file.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with no file and no CSV text. | Error `review_import_file_required`; no rows. |
  | 2 | Upload a PDF. | Validation error (mime). |
  | 3 | Upload the 6 MB file. | Validation error (max 5 MB). |
  | 4 | Upload `evil.csv` (binary content, csv extension). | Record the result (accepted by extension/mime guess; parsing yields no rows; no error page). |
  | 5 | Host of E2 POSTs an import for E1. | HTTP 403; nothing imported. |
  | 6 | Concierge, customer and anonymous POST the import. | 403 / redirect to login. |
  | 7 | Upload an invalid format value (`format=pdf`) via crafted request. | Validation error. |
- **Acceptance criteria:** Only managers of the establishment can import; invalid files never create reviews or cause a server error.
- **Evidence to capture:** Status codes and screenshots; SQL counts before/after.
- **Notes / assumptions / open questions:** None.

---

## UAT-INT-036: Google reviews sync from a configured CSV/JSON source
- **Feature / journey:** Scheduled and manual review sync
- **Priority:** P1
- **Role / permissions:** Admin or host of the establishment; operator for CLI
- **References:** `POST /admin/establishments/{establishment}/reviews/sync-google`; `GoogleReviewSyncService`; command `php artisan reviews:sync-google [--establishment=ID]`; schedule every six hours in `routes/console.php`; `tests/Feature/GoogleReviewSyncTest.php`, `GoogleReviewSyncCommandTest.php`
- **Preconditions / setup:** Fixture host serving `google.csv` as `text/csv` at `https://<fixture-host>/reviews.csv`, and a JSON variant at `/reviews.json` (`{"reviews":[{"review_date":"2026-02-01","reviewer_name":"UAT Googler One","rating":5,"review_text":"Superb","review_url":"https://example.test/g/1"}]}`) served as `application/json`. Establishment E1 "Google reviews import URL" field set to the CSV URL.
- **Test data:** As above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click "Sync Google reviews" on E1. | Success flash with the count; reviews appear; "last sync" time stored. |
  | 2 | Click again. | Same count message, no duplicate rows. |
  | 3 | Switch the URL to the JSON endpoint and sync. | Reviews from JSON imported (converted to CSV internally). |
  | 4 | Serve HTTP 404, then an empty JSON `{"reviews":[]}`, then a non-JSON/HTML page. | Each shows an error under the import URL field (`HTTP 404`, invalid payload message); last-sync error stored; existing reviews untouched. |
  | 5 | Clear the URL and click sync. | Error "Google import URL missing". |
  | 6 | On a test server run `php artisan reviews:sync-google --establishment=<E1 id>`. | Per-establishment line with count; summary `Processed N establishment(s); M failure(s).`; exit code 0, or non-zero when any failure occurred. |
  | 7 | Run `php artisan schedule:list`. | A six-hour review sync entry exists. |
- **Acceptance criteria:** Successful sync is idempotent; failures are visible and non-destructive.
- **Evidence to capture:** Flash/error screenshots; CLI output with exit codes; SQL for `google_reviews_last_sync_at` and `google_reviews_last_sync_error`.
- **Notes / assumptions / open questions:** The URL field accepts any URL (no private network restriction, same concern as UAT-INT-030). The Google source URL is separate from the public Google review link sent to guests (README).

---

## UAT-INT-037: Email outbox delivery, localization and failure marking
- **Feature / journey:** Transactional email delivery
- **Priority:** P0
- **Role / permissions:** Operator with shell access; guest; staff
- **References:** `app/Console/Commands/SendMessageEmailNotifications.php` (`messages:send-email-notifications --limit=50`); `EmailOutbox`; `ReservationEmailService`; `MessageEmailService`; email views under `resources/views/emails/`; schedule (every minute); README scheduler section
- **Preconditions / setup:** Test environment with `MAIL_MAILER=log` or a capture sandbox; scheduler not running (so outbox rows stay queued until the command is run).
- **Test data:** Reservations in French and in English (`reservations.locale`); guest `mail-guest@example.test`; staff `admin@afrikappart.test` with `email_booking_updates` on.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create a public reservation as `mail-guest@example.test` (locale FR). | Outbox rows queued: `reservation_created` for the guest and `reservation_received` for staff (confirm which templates appear in this environment). |
  | 2 | `SELECT template, recipient_email, status FROM email_outbox ORDER BY id`. | Rows `queued`. |
  | 3 | Run `php artisan messages:send-email-notifications --limit=1`. | One row processed; status `sent`; output `Sent <template> notification to <email>.`; summary line `Processed 1 email notification(s).` |
  | 4 | Run the command without a limit. | Remaining queued rows become `sent`. |
  | 5 | Open the captured emails. | Subject and body in the reservation's locale (FR) with correct reference, dates, totals, working checkout link with `?lang=fr`. Repeat with an EN reservation: EN content. |
  | 6 | Break the mail transport on a test server (invalid host/port), queue a new email and run the command. | Row status becomes `failed` with `payload.error` set; command output shows `Could not send ...`; command exit code 0; other rows are still processed. |
  | 7 | Restore the transport and run the command again. | The failed row stays `failed` (the command only picks `queued`). Record it. |
  | 8 | Insert a row with template `staff_account_invitation` (created by UAT-PLAT-002) and run the command. | That row is not picked up (template not in the command's list); it stays `queued`. |
- **Acceptance criteria:** Queued rows are sent exactly once, in the right language; failures are visible and isolated.
- **Evidence to capture:** SQL result sets before/after; captured emails; command output.
- **Notes / assumptions / open questions:** There is no retry counter or retry of `failed` rows and no admin screen showing failed emails (master prompt asks for both). Step 7 and 8 document that. Without the scheduler's one-minute tick no email leaves the system; confirm the cron entry in production (UAT-OPS-012).

---

## UAT-INT-038: Mail outage does not roll back reservations or payments
- **Feature / journey:** Email failure isolation
- **Priority:** P0
- **Role / permissions:** Guest; operator
- **References:** Outbox pattern in `ReservationEmailService`; `SendMessageEmailNotifications`; master prompt "An SMTP outage must not roll back a valid reservation"
- **Preconditions / setup:** Test environment with an unreachable SMTP host (set through the environment owner). Scheduler off or the command run manually.
- **Test data:** Guest `outage-guest@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create a public reservation. | Reservation created; the guest reaches checkout; no error page. |
  | 2 | Complete the payment simulation (UAT-INT-004 style signed webhook). | Reservation confirmed; receipt created. |
  | 3 | Run the send command. | Rows `failed`; reservation and receipt unaffected. |
  | 4 | Guest opens checkout and downloads the receipt PDF. | Both work. |
  | 5 | Feedback form (UAT-PLAT-018 step 4) and password reset request under the same outage. | Record the behavior: these send synchronously and may return an error page. |
- **Acceptance criteria:** Core booking and payment state is independent of mail availability for outbox-based mail. Synchronous mail paths are recorded.
- **Evidence to capture:** SQL; screenshots.
- **Notes / assumptions / open questions:** The invitation and password reset flows use Laravel's synchronous mail, not the outbox.

---

## UAT-INT-039: Receipt PDF generation, content and access control
- **Feature / journey:** PDF receipts
- **Priority:** P0
- **Role / permissions:** Reservation owner (customer), other customer, reservation staff, anonymous with checkout token
- **References:** `GET /reservations/{reservation}/receipt` (`reservations.receipt`); `ReceiptController::download`, `authorizeReservation`; `POST /admin/reservations/{reservation}/receipt` (`admin.reservations.receipt.generate`); `resources/views/receipts/show.blade.php`; `tests/Feature/PaymentWorkflowTest.php::test_admin_can_generate_receipt_for_confirmed_reservation_without_one`
- **Preconditions / setup:** Confirmed reservation R1 with a receipt (UAT-INT-004). Confirmed reservation R2 without a receipt. Users: owner of R1 (`guest@afrikappart.test` linked by email), other customer `other-customer@example.test`.
- **Test data:** Receipt number from R1.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Owner opens the receipt link (from the receipt email) while logged in. | PDF downloads, file name `<receipt number>.pdf`. |
  | 2 | Open the PDF. | Contains receipt number, reservation reference, property, dates, guest name, price lines, total and currency matching the reservation; French/English per template. Record whether the correct language is used. |
  | 3 | Other customer opens the same URL. | HTTP 403. |
  | 4 | Anonymous opens the same URL. | Redirect to login. |
  | 5 | Anonymous with the checkout token appended (`?token=`) opens the receipt URL. | Redirect to login: the receipt route sits inside the `auth` group and `ReceiptController::download` does not accept the checkout token (token access exists only for payment proofs). |
  | 6 | Staff opens the URL for R1. | PDF downloads. |
  | 7 | Staff opens the receipt URL for R2 (no receipt). | HTTP 404. |
  | 8 | Staff clicks "Generate receipt" for R2. | Receipt created, receipt email queued; the URL now downloads a PDF. Run again: no second receipt for the same payment attempt. |
  | 9 | Staff of another tenant opens R1's receipt URL. | Record: access is granted by `canManageReservations()` with no tenant check in `authorizeReservation`; a 200 is a cross-tenant defect candidate. |
- **Acceptance criteria:** Receipt PDFs are generated only for paid reservations and only owner or staff can download them.
- **Evidence to capture:** Status codes; PDF file; SQL receipts count.
- **Notes / assumptions / open questions:** The email link `receipt_url` requires login; a guest without an account (checkout-token booking) cannot download the receipt from the email without logging in (step 5). Confirm with the product owner.

---

## UAT-INT-040: Offline payment proof (Interac, Wise, Revolut): guest upload and staff confirmation
- **Feature / journey:** Manual transfer proof and receipt issuance
- **Priority:** P1
- **Role / permissions:** Guest with checkout token; reservation staff; other customer
- **References:** `POST /reservations/{reservation}/checkout/offline-proof` (`checkout.offline-proof`); `CheckoutController::submitOfflineProof`; `POST /admin/reservations/{reservation}/confirm-offline-payment`; `GET /reservations/{reservation}/payment-proofs/{attempt}` (`reservations.payment-proof.download`); `PaymentProofService`; `tests/Feature/PaymentWorkflowTest.php::test_guest_can_submit_offline_payment_proof_and_host_is_notified`
- **Preconditions / setup:** Establishment has Wise enabled (mode manual) with a secondary currency and rate configured; reservation `pending_payment`.
- **Test data:** `proof.png` (small synthetic image, 100 KB), `proof.pdf`, `notes.txt`, a 6 MB PNG.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Guest uploads `proof.png` with provider `wise`. | Reservation status becomes `pending_validation`; attempt `awaiting_validation`; staff recipients (admin, concierge, host of the tenant) get a `payment_proof_submitted` outbox row. |
  | 2 | Guest uploads `notes.txt` and the 6 MB PNG. | Validation errors (mime/max 5 MB); state unchanged. |
  | 3 | Guest uploads for provider `interac` that is not enabled. | HTTP 422. |
  | 4 | Staff opens the reservation in Admin and downloads the proof via the link. | File downloads under its original file name. |
  | 5 | Other customer opens the proof URL. | HTTP 403. |
  | 6 | Guest opens the proof URL with `?token=<checkout token>`. | File downloads (token allowed for proofs). |
  | 7 | Staff confirms the offline payment, uploading a proof and optional reference. | Attempt `paid`; reservation `confirmed`; receipt issued once; guest receives status and receipt emails. |
  | 8 | Staff confirm without a proof file. | Validation error (proof required). |
  | 9 | Craft a proof URL with a different reservation id for the same attempt id. | HTTP 404 (attempt must belong to the reservation). |
- **Acceptance criteria:** Proof files are stored under `uploads/reservations/<id>/`, downloadable only by authorized parties, and confirmation issues exactly one receipt.
- **Evidence to capture:** Screenshots; SQL; list of files on disk (file names only).
- **Notes / assumptions / open questions:** Uploaded files live under `public/uploads` (`PUBLIC_UPLOAD_PATH`): check that the direct URL `https://<host>/uploads/reservations/<id>/<file>` is or is not reachable without login; record the result (privacy concern if reachable). File names contain a random 12-character suffix.

---

## UAT-INT-041: Analytics is off by default and absent on every page
- **Feature / journey:** Analytics disabled state
- **Priority:** P0
- **Role / permissions:** Anonymous visitor
- **References:** `config/services.php` `analytics` (`ANALYTICS_ENABLED`, `GA4_MEASUREMENT_ID`, `GTM_CONTAINER_ID`); `resources/views/layouts/app.blade.php` (`window.__ANALYTICS_CONFIG__`); `resources/views/partials/analytics-consent.blade.php`
- **Preconditions / setup:** `ANALYTICS_ENABLED` unset or false (default).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/` with browser dev tools (Network tab) and cleared storage. | No consent banner. `window.__ANALYTICS_CONFIG__` is `null` in the page source. No request to `googletagmanager.com` or `google-analytics.com`. |
  | 2 | Set `ANALYTICS_ENABLED=true` but leave both IDs empty; rebuild the config cache; reload. | Still no banner and no third-party request (banner requires an ID). |
- **Acceptance criteria:** No tracking request or banner without both the switch and at least one ID.
- **Evidence to capture:** Screenshot of the Network tab filtered by `google`; page source excerpt.
- **Notes / assumptions / open questions:** None.

---

## UAT-INT-042: Analytics consent banner: accept, decline, persistence, GTM-only loading
- **Feature / journey:** Consent-gated analytics
- **Priority:** P0
- **Role / permissions:** Anonymous visitor
- **References:** `resources/js/app.js` (localStorage key `afrik_analytics_consent`, `loadAnalytics`, GTM/GA4 loader); analytics partial; README analytics paragraph; CHANGELOG M26
- **Preconditions / setup:** Test environment with `ANALYTICS_ENABLED=true` and a test container `GTM_CONTAINER_ID` (use a dedicated test GTM container, never production). Run a second pass with only `GA4_MEASUREMENT_ID` set, and a third with both set.
- **Test data:** Clean browser profile.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/` before any choice. | Consent dialog is visible with Accept and Decline. Network shows no request to `googletagmanager.com`. |
  | 2 | Click "Decline". | Dialog hides; `localStorage.afrik_analytics_consent = "declined"`; no analytics requests; reload and navigate to a property page: no banner and no requests. |
  | 3 | Clear site data, open `/`, click "Accept". | Dialog hides; value `accepted`; a request to `https://www.googletagmanager.com/gtm.js?id=<id>` (GTM pass) occurs; `dataLayer` exists. |
  | 4 | Navigate to a property page, then use "compare" and "select property". | Events (property selection, comparison, booking intent) are pushed to `dataLayer` without personal fields (no email, name, phone). Inspect the pushed objects. |
  | 5 | Reload after accepting. | Analytics loads automatically without showing the banner. |
  | 6 | GA4-only pass: Accept. | Request to `gtag/js?id=<GA4 id>`; `config` call with `anonymize_ip: true`. |
  | 7 | Both IDs set, Accept. | Only GTM loads; no direct `gtag/js` request (avoids duplicate page views). |
  | 8 | Open private pages: `/admin`, `/dashboard`, `/account/profile`, `/messages`, a checkout URL, after accepting. | `window.__ANALYTICS_CONFIG__` is `null`; no analytics requests; page has `noindex` meta. |
  | 9 | Withdraw consent: clear the localStorage key. | Banner returns on next load; no way to withdraw from the UI itself (record). |
- **Acceptance criteria:** No analytics request before explicit acceptance; declined choice is respected; private pages never load analytics; events carry no personal data.
- **Evidence to capture:** Network screenshots for each step; `dataLayer` dump with synthetic data.
- **Notes / assumptions / open questions:** There is no cookie policy link or "change my choice" control in the banner (check `/cookies` page content). `consent` is stored in localStorage only; no server-side record. Whether GTM consent mode is configured is outside the application (container settings).

---

## UAT-INT-043: reCAPTCHA verification on login, registration, host signup and booking
- **Feature / journey:** Spam protection integration
- **Priority:** P1
- **Role / permissions:** Anonymous visitor
- **References:** `GoogleRecaptchaVerifier`; `AuthController` (login/register); `TenantSignupController`; `PublicPropertyController::reserve`; `config/services.php` `recaptcha` (`RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`, `RECAPTCHA_MINIMUM_SCORE`); `resources/views/partials/recaptcha.blade.php`
- **Preconditions / setup:** Part A: keys unset. Part B: test environment with a reCAPTCHA v3 test site registered for the test domain (or Google's published test keys, noting that test keys do not return a score).
- **Test data:** Synthetic user `captcha-user@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Part A: log in, register and reserve. | Forms work without any captcha element or script. |
  | 2 | Part B: load the login form. | reCAPTCHA script loads from Google; a token is produced on submit. |
  | 3 | Part B: submit the login or reservation form with the hidden `g-recaptcha-response` removed (browser dev tools). | Request rejected with a captcha-failed message; no session/reservation created. |
  | 4 | Part B: block `google.com` for the test browser (or the server's outbound access to `www.google.com`) and submit. | Request rejected (the verifier fails closed on network error). Record the user-facing message. |
  | 5 | Part B: set `RECAPTCHA_MINIMUM_SCORE=0.99` on the test env and submit with a genuine token. | Likely rejected; record. Reset the value. |
- **Acceptance criteria:** With keys configured, every protected form requires a valid token; with no keys, forms keep working.
- **Evidence to capture:** Screenshots; Network log.
- **Notes / assumptions / open questions:** The verifier compares the returned `score` (default 0 if missing) to the minimum, so v2 test keys that return no score fail when keys are configured. Use a v3 site key for UAT.

---

## UAT-INT-045: AI property copy assistant limits and failure handling
- **Feature / journey:** Third-party AI copy suggestions
- **Priority:** P2
- **Role / permissions:** Admin or host of the property
- **References:** `POST /admin/properties/{property}/improve-copy` (`admin.properties.improve-copy`); `AdminPropertyController::improveCopy`; `PropertyCopyImprover`; `PROPERTY_COPY_DAILY_LIMIT`; `tests/Unit/PropertyCopyImproverTest.php`
- **Preconditions / setup:** Property edit page, description tab. Part A: `GROQ_API_KEY` unset. Part B: test key from the environment owner. `PROPERTY_COPY_DAILY_LIMIT=2` on the test environment.
- **Test data:** French summary `Appartement calme près du marché`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click the assistant with all four fields empty. | Validation message "source text required"; no provider call. |
  | 2 | Part A: with text and no key. | Localized "not configured" error. |
  | 3 | Part B: with text. | A suggestion is returned in the assistant panel; the form fields are NOT overwritten until the admin applies the suggestion (record the actual behavior). |
  | 4 | Run it three times as the same user in one day. | Third call returns HTTP 429 with the limit-reached message. |
  | 5 | A host of another establishment POSTs the same URL. | HTTP 403. |
- **Acceptance criteria:** The assistant respects the per-user daily limit and does not save anything without an explicit admin action.
- **Evidence to capture:** Screenshots; statuses.
- **Notes / assumptions / open questions:** The text of the property is sent to a third-party AI provider; confirm the privacy wording with the product owner.

---

## UAT-INT-046: Public pages show only approved review data after an import
- **Feature / journey:** Imported reviews visible to guests
- **Priority:** P2
- **Role / permissions:** Anonymous visitor; admin
- **References:** `/reviews` (`StaticPageController::reviews`); home page review stats (`routes/web.php` `/`); `SiteReview` `is_active`; Admin > Reviews (`admin.reviews`, update/delete routes); `docs/input-inventory.md` section 4 (no fake ratings)
- **Preconditions / setup:** Imports from UAT-INT-032 to UAT-INT-036 done.
- **Test data:** Imported reviews from the fixtures.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/reviews` and the home page. | Imported active reviews show reviewer name, rating, text and source; the average and count on the home page equal the active imported reviews. |
  | 2 | In Admin > Reviews deactivate one imported review. | It disappears from `/reviews` and from the home page average. |
  | 3 | Delete one review. | Removed everywhere. |
  | 4 | With zero active reviews, open home and a property card. | No rating invented; a "New listing"/no-rating state is shown. |
- **Acceptance criteria:** Public rating numbers are computed only from active imported or submitted reviews.
- **Evidence to capture:** Screenshots; SQL average vs UI.
- **Notes / assumptions / open questions:** Whether imported third-party reviews may be reproduced publicly depends on the platforms' terms; flag to the product owner.

