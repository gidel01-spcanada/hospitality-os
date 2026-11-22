# Admin back-office: emails triggered by admin actions and scheduled jobs/commands

Areas: UAT-MAIL and UAT-JOB. Source of truth: `ReservationEmailService`, `MessageEmailService`, `AccountInvitationService`, `app/Console/Commands/SendMessageEmailNotifications.php`, `app/Jobs/CompletePastReservations.php`, `routes/console.php`, `resources/views/emails/*.blade.php`, `config/platform.php`, README (scheduler and lifecycle sections), tests `ReservationWorkflowAndEmailTest`, `AdminReservationCalendarOverrideTest`, `MessagingTest`.

How emails work (from code): application actions only insert rows into `email_outbox` with `status=queued`. Delivery happens when `php artisan messages:send-email-notifications [--limit=50]` runs (scheduler: every minute). Supported templates: `message_received`, `payment_link`, `reservation_created`, `reservation_received`, `reservation_status_updated`, `receipt_issued`, `payment_proof_submitted`. After sending the status becomes `sent`; on exception `failed` with `payload.error`.
Test setup: use a mail capture (log mailer, Mailpit or similar) in the test environment. Do not use real addresses; all recipients are `*@example.test`.

---

## UAT-MAIL-001: Outbox processing command: delivery, ordering, limit and failure status
- **Feature / journey:** `messages:send-email-notifications`
- **Priority:** P0
- **Role / permissions:** Operator (shell) / scheduler; admin observes results
- **References:** `SendMessageEmailNotifications::handle`; README "Queued new-message and reservation email notifications..."; `AdminReservationCalendarOverrideTest`
- **Preconditions / setup:** Clean `email_outbox`. Create 3 reservations from admin (3 `reservation_created` rows queued at different times) and 1 extra row with an unsupported template value inserted by a developer (e.g. `staff_account_invitation` from ADM-004).
- **Test data:** Recipients `uat-guest-1@example.test`, `uat-guest-2@example.test`, `uat-guest-3@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan messages:send-email-notifications --limit=2` | Output "Sent reservation_created notification to <email>." twice, then "Processed 2 email notification(s)." The two oldest rows are `sent`; third still `queued`. |
  | 2 | Run again without limit option | The remaining row is sent; "Processed 1 email notification(s)." |
  | 3 | Run once more | "Processed 0 email notification(s)." No duplicate sends. |
  | 4 | Check unsupported `staff_account_invitation` row | Remains `queued` (not in the template whitelist). Record. |
  | 5 | `--limit=0` or `--limit=-5` | Treated as 1 (`max(1, limit)`). |
  | 6 | Check the mail capture | One message per sent row with the subject from `payload.subject` and recipients matching. |
  | 7 | Admin UI after delivery | No UI shows outbox status; verify via DB or capture only. |
- **Acceptance criteria:** Rows are processed oldest first, at most `limit` per run, each exactly once.
- **Evidence to capture:** Console output, `email_outbox` rows before/after, mail capture list.
- **Notes / assumptions / open questions:** No retry for `failed` rows (see MAIL-007).

## UAT-MAIL-002: Reservation created email (admin-created reservation)
- **Feature / journey:** Customer confirmation email content and language
- **Priority:** P0
- **Role / permissions:** Admin, Host, Concierge (trigger); guest (recipient)
- **References:** `ReservationEmailService::queueForReservation`; `resources/views/emails/reservation-created.blade.php`; lang `reservation_created.email_subject` ("Your reservation confirmation: :reference"), `reservation_created.email_heading` ("Your reservation is confirmed"), `reservation_created.email_intro`, `reservation_created.email_action` ("Track my reservation and choose payment"); `ReservationWorkflowAndEmailTest::test_admin_can_set_guest_preferred_locale_and_email_is_queued_in_that_language`
- **Preconditions / setup:** Property `PA`; mail capture running.
- **Test data:** Two reservations: guest `uat-mail-en@example.test` locale `en`, guest `uat-mail-fr@example.test` locale `fr`; one with guest name `<b>UAT</b> Guest` and property name containing `&`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create the EN reservation, run the send command | Email subject "Your reservation confirmation: AFK-XXXXXX-yymmdd", heading and intro in English, with reference and property name, price lines table and total. |
  | 2 | Create the FR reservation | Subject, heading and body in French (strings from `resources/lang/fr/messages.php`). |
  | 3 | Create a reservation with no locale chosen | Email uses the app default locale (`config('app.locale')`, `fr` by default). |
  | 4 | Click the action link | Opens `/reservations/{id}/checkout?token=...&lang=<locale>` in the right language without login. |
  | 5 | Check the guest name/property rendering | HTML in names is escaped, not interpreted. |
  | 6 | Dates and totals in the email vs reservation page | Equal (check_in, check_out, total, currency). |
  | 7 | Edit an existing reservation through the admin edit form | No email is sent on edit. |
- **Acceptance criteria:** One localized email per created reservation with correct data and a working link.
- **Evidence to capture:** Mail captures (screenshots of both languages), outbox rows.
- **Notes / assumptions / open questions:** The HTML heading says "Your reservation is confirmed" even though the reservation is created in status `pending`. Raise wording with the product owner.

## UAT-MAIL-003: Status update email, including completion review request
- **Feature / journey:** Reservation status email
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge (trigger)
- **References:** `queueForReservation(..., 'reservation_status_updated')`, `queueStatusUpdate`; `resources/views/emails/reservation-status-updated.blade.php`; lang `reservation_status.email_subject` ("Reservation status update"), `reservation_status.email_intro`, `reservation_status.email_status`, `reservation_status.email_action`, `review_request.email_internal_action`; `routes/web.php` signed routes `reviews.submit`
- **Preconditions / setup:** Reservations `M1` (establishment `review_channel=internal`), `M2` (`google` with `google_review_url` set), `M3` (`both`).
- **Test data:** Status progression `confirmed` then `completed` on each.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Set `M1` to `confirmed` and send | Email intro with reference/property, line "New status: confirmed" (raw status code, not the label). Link "View my reservation". |
  | 2 | Set `M1` to `completed` and send | Email additionally has a review link (signed route `/review/{id}` with signature, valid 30 days). |
  | 3 | Open the review link in a private window | Review form loads. Tamper with the signature: 403. After 30 days (developer changes system date or expiry) link returns 403. |
  | 4 | `M2` completed | Only the Google review link is shown (no internal link). |
  | 5 | `M3` completed | Both internal and Google links shown. |
  | 6 | Set `M1` to `cancelled` | Status email sent with status `cancelled`; no review links. |
  | 7 | Save the status form without changing status | No email. |
- **Acceptance criteria:** Email content follows status and review channel.
- **Evidence to capture:** Mail captures, signed link behavior.
- **Notes / assumptions / open questions:** The status line prints the raw status code (e.g. `pending_validation`) rather than the localized label; record if untranslated codes are visible to customers.

## UAT-MAIL-004: Payment link and receipt emails (content and links)
- **Feature / journey:** Payment link and receipt delivery
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge (trigger)
- **References:** `queuePaymentLink`, `queueReceipt`; `resources/views/emails/payment-link.blade.php`, `receipt-issued.blade.php`; lang `payment_link.email_subject` ("Payment link for reservation :reference"), `payment_link.email_heading` ("Complete your payment"), `receipts.email_subject` ("Your receipt for reservation :reference"), `receipts.email_amount` ("Amount paid: :amount :currency.")
- **Preconditions / setup:** Reservation `M4` pending (send payment link, RES-015); reservation `M5` with offline payment confirmed (RES-013).
- **Test data:** Amounts in XOF and EUR reservation (two cases) to check formatting.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the payment link and deliver the email | Subject "Payment link for reservation <ref>", heading "Complete your payment", price lines with amounts formatted `1 234` style (thousand separator is a space), total, link to checkout with the token and `lang`, plus a registration link with the email prefilled. |
  | 2 | Open the checkout link while logged out | Checkout works for the reservation. |
  | 3 | Deliver the receipt email of `M5` | Subject "Your receipt for reservation <ref>", amount line with currency, "Download my PDF receipt" link to `/reservations/{id}/receipt?lang=..`. |
  | 4 | Click the receipt link while logged out | Redirect to login (receipt download needs authentication; no token) - record. After login as the owner: PDF downloads. |
  | 5 | Check amounts in decimal currencies (e.g. EUR 199.50) | Email displays with 0 decimals (`number_format(..., 0, ',', ' ')`): record rounding shown (199.50 appears as 200). |
- **Acceptance criteria:** Links work and amounts match the reservation, noting any rounding.
- **Evidence to capture:** Mail captures, PDF.
- **Notes / assumptions / open questions:** DEFECT CANDIDATE: email amounts drop decimals, which can misstate non-XOF totals.

## UAT-MAIL-005: Payment proof submitted: notification to staff
- **Feature / journey:** Staff notification after customer uploads offline proof
- **Priority:** P1
- **Role / permissions:** Customer (trigger); Admin, Concierge, Host of the tenant (recipients)
- **References:** `ReservationEmailService::queueOfflineProofNotification`; `CheckoutController::submitOfflineProof`; `resources/views/emails/payment-proof-submitted.blade.php`; lang `receipts.proof_notification_subject` ("New payment proof for :reference"), `receipts.proof_notification_heading` ("Payment proof received"), `receipts.proof_notification_action` ("View reservation")
- **Preconditions / setup:** Tenant has 1 admin (locale `en`), 1 concierge (locale `fr`), 2 hosts (one assigned to `E1`, one to `E2`). Establishment `E1` has Interac enabled. Pending reservation `M6` on `E1`.
- **Test data:** Proof file `proof.jpg` (under 5 MB).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Customer opens checkout for `M6`, selects Interac, uploads the proof | Proof accepted; reservation shows validation pending (see checkout cases). |
  | 2 | Check `email_outbox` | One `payment_proof_submitted` row per staff user of the tenant with role admin, concierge or host. |
  | 3 | Deliver the emails | Subject "New payment proof for <ref>" (FR for the concierge in French); body shows provider, amount and the link to `/admin/reservations/{id}`. |
  | 4 | Click the link as the admin | Reservation detail opens with the proof available. |
  | 5 | Check the host of `E2` | Also receives the notification (not scoped by establishment). Record. Link opens 403 for that host. |
- **Acceptance criteria:** Staff are notified once per user; link goes to the reservation.
- **Evidence to capture:** Outbox rows; mail captures.
- **Notes / assumptions / open questions:** OPEN QUESTION: hosts unrelated to the establishment receive the notification and get 403 on the link. Not scoped in code. Also `email_booking_updates` flags are not checked here.

## UAT-MAIL-006: Message reply email to the customer (and back to staff)
- **Feature / journey:** `message_received` email
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge (sender); Customer (recipient)
- **References:** `MessageEmailService::queueForMessage`; `AdminMessageController::reply`; `resources/views/emails/message-received.blade.php`; lang `messages.email_subject`, `messages.email_heading`, `messages.email_intro`, `messages.email_action`; `tests/Feature/MessagingTest.php`
- **Preconditions / setup:** Thread between customer `uat-guest-msg@example.test` (locale `en`, `email_message_updates=true`) and `E1`. A second customer with `email_message_updates=false`.
- **Test data:** Reply body `Your key is at reception <b>today</b>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Staff replies in `/admin/messages/{thread}` | Outbox row `message_received` for the customer only (not for the sender). |
  | 2 | Deliver | Email subject from `messages.email_subject` in the customer's locale, sender name, establishment name, quoted message (HTML escaped, line breaks kept), link to `/messages/{thread}`. |
  | 3 | Reply in a thread whose customer disabled message emails | No outbox row. |
  | 4 | Customer replies from `/messages/{thread}` | Outbox rows for all tenant admin/concierge/host users with `email_message_updates=true` (except sender), link to `/admin/messages/{thread}`. |
  | 5 | Staff user with `email_message_updates=false` | Not notified. |
- **Acceptance criteria:** Recipients respect opt-out flags; the message is delivered with a working link.
- **Evidence to capture:** Outbox rows; mail captures.
- **Notes / assumptions / open questions:** All tenant staff are notified for any thread, not only those of their establishment (same as MAIL-005).

## UAT-MAIL-007: Mail transport failure handling
- **Feature / journey:** Integration failure on delivery
- **Priority:** P1
- **Role / permissions:** Operator
- **References:** `SendMessageEmailNotifications::handle` catch block (`status=failed`, `payload.error`)
- **Preconditions / setup:** Ability to break the mail configuration in a test environment (wrong SMTP host/port) then restore it. Two queued rows.
- **Test data:** Rows for `uat-fail-1@example.test`, `uat-fail-2@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Break SMTP settings; run the command | For each row an error line "Could not send <template> notification to <email>: <reason>". Rows get `status=failed` with `payload.error`. Command exit code 0, final line "Processed 2 email notification(s)." |
  | 2 | Restore SMTP; run the command again | Failed rows are not retried (status no longer `queued`). Record. |
  | 3 | Re-queue by creating a new admin action (e.g. send payment link again) | New row delivered. |
  | 4 | Check the app locale after a failure | Subsequent rows in the same run still use their own locale (previous locale restored). |
  | 5 | Check admin UI at the time of failure | The admin action itself (e.g. creating the reservation) succeeded and showed the "queued" flash even though delivery later failed. |
- **Acceptance criteria:** Failures are recorded, other rows continue, no admin action is blocked.
- **Evidence to capture:** Console output; outbox rows (error text may include server names, do not share externally).
- **Notes / assumptions / open questions:** There is no admin screen or retry for failed emails. Raise to product owner.

## UAT-MAIL-008: Customer notification preferences versus reservation emails
- **Feature / journey:** Opt-out handling
- **Priority:** P2
- **Role / permissions:** Customer (preference), Admin (trigger)
- **References:** `User.email_booking_updates`, `email_marketing`, `email_newsletter`; `ReservationEmailService` (recipient is the reservation email); account preferences page `/account/preferences`
- **Preconditions / setup:** Customer `uat-optout@example.test` with account and `email_booking_updates=false` (set in `/account/preferences`).
- **Test data:** Reservation created by admin for that email.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin creates a reservation for `uat-optout@example.test` and sets status `confirmed` | Record whether `email_outbox` rows are created. |
  | 2 | Run the send command | Record whether emails are delivered despite the opt-out. |
- **Acceptance criteria:** Behavior is documented against the preference label's promise.
- **Evidence to capture:** Outbox rows; screenshot of the preference setting and its wording.
- **Notes / assumptions / open questions:** Code review shows reservation emails are built from `reservations.email` without checking `email_booking_updates` (only message emails check `email_message_updates`). Transactional emails may legitimately ignore this flag; confirm intended scope.

## UAT-MAIL-009: Account invitation email for users created by admin
- **Feature / journey:** Password setup invitation
- **Priority:** P1
- **Role / permissions:** Admin (trigger); new user (recipient)
- **References:** `AccountInvitationService::send`; `Password::sendResetLink`; lang `account_invitation.subject`; routes `password.reset`, `password.update`
- **Preconditions / setup:** Mail capture running; create user `uat-invite@example.test` (ADM-004).
- **Test data:** New password `UatPass#2026`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Check the mail capture right after creating the user | A reset-password email (from Laravel's password broker) exists for the address; it is sent by the broker directly, without waiting for the outbox command. |
  | 2 | Open the reset link, set `UatPass#2026` (with confirmation) | Password updated; login works. |
  | 3 | Reuse the same reset link | Rejected (token consumed). |
  | 4 | Create a user with an address the mail server rejects (simulate transport failure) | Record: `AccountInvitationService` throws a `RuntimeException` if the broker status is not `RESET_LINK_SENT`; the admin may see an error page after the user row was already created. |
  | 5 | Check `email_outbox` | `staff_account_invitation` row `queued`; stays unsent (see MAIL-001 step 4). |
- **Acceptance criteria:** New users can set their own password through the emailed link.
- **Evidence to capture:** Mail capture, screenshots of reset flow.
- **Notes / assumptions / open questions:** Reset link validity follows `config/auth.php` password broker expiry; note the value.

## UAT-JOB-001: `reservations:complete-past` and the hourly `CompletePastReservations` job
- **Feature / journey:** Automatic completion of stays
- **Priority:** P0
- **Role / permissions:** Operator/scheduler; admin observes
- **References:** `app/Jobs/CompletePastReservations.php`; `routes/console.php` (`Schedule::job(...)->hourly()` and command `reservations:complete-past`); `config/platform.php` `checkout_completion_grace_hours` (env `CHECKOUT_COMPLETION_GRACE_HOURS`, default 6); README "Reservation lifecycle automation"; `ReservationWorkflowAndEmailTest::test_past_confirmed_reservations_are_completed_by_job_and_email_status_update`
- **Preconditions / setup:** Grace hours = 6 (default). Reservations (all on `PA`, different guests): `J1` confirmed, check_out two days ago; `J2` checked_in, check_out two days ago; `J3` confirmed, check_out today; `J4` confirmed, check_out tomorrow; `J5` pending, check_out two days ago; `J6` cancelled, check_out two days ago; `J7` confirmed, check_out yesterday.
- **Test data:** Guest emails `uat-job-1@example.test` ... `uat-job-7@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run `php artisan reservations:complete-past` | Prints "Past reservations completed." |
  | 2 | Check `J1`, `J2` | Status `completed`; notes end with "Stay automatically completed after check-out."; `reservation_status_updated` email queued each (with review link). |
  | 3 | Check `J3`, `J4` | Unchanged (check-out not before the cutoff date). |
  | 4 | Check `J5`, `J6` | Unchanged (only confirmed and checked_in are completed). |
  | 5 | `J7` (checkout yesterday) | Completed only when `now - 6h` falls on a date after yesterday, i.e. after 06:00 today. Run the command before and after 06:00 (or change the grace setting) and record both results. |
  | 6 | Run the command a second time | No further changes, no new emails (already completed). |
  | 7 | Set `CHECKOUT_COMPLETION_GRACE_HOURS=0`, clear config, repeat for a reservation with check_out yesterday | Completed immediately at any time today. |
  | 8 | With more than 100 eligible reservations (seed 150) | All completed in one run (chunked). |
  | 9 | Review the audit log | `updated` entries for each reservation with no user. |
  | 10 | Check reports and cleaning generation after completion | `completed` counts as occupied; cleaning generation still includes completed departures (CLN-006). |
- **Acceptance criteria:** Only confirmed/checked-in reservations whose check-out date is before the cutoff date become completed, with one email each, once.
- **Evidence to capture:** Console output, DB status table before/after, outbox rows.
- **Notes / assumptions / open questions:** The comparison uses the date of `now - grace` against check-out date, so "grace" effectively shifts completion to the next day boundary rather than check-out time + N hours. Confirm intended semantics. Timezone is the app timezone (documented default `Africa/Porto-Novo`).

## UAT-JOB-002: Scheduler overview and cadence of registered tasks
- **Feature / journey:** `schedule:list` / `schedule:run`
- **Priority:** P1
- **Role / permissions:** Operator
- **References:** `routes/console.php`; README scheduler cron section (one cron line `php artisan schedule:run` every minute)
- **Preconditions / setup:** Test environment with the scheduler running every minute (or run `schedule:run` manually).
- **Test data:** One queued email, one stale calendar feed, one confirmed past reservation, one Google source establishment.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan schedule:list` | Lists: closure hourly (calendar feed sync), `CompletePastReservations` job hourly, `messages:send-email-notifications --limit=50` every minute, closure every six hours (Google reviews). |
  | 2 | `php artisan schedule:run` at a minute that is not an hour boundary | Only the email command runs; queued email sent. |
  | 3 | `php artisan schedule:run` at the top of the hour | Calendar sync closure, completion job also run (feed events refreshed, past reservations completed). |
  | 4 | At 00:00, 06:00, 12:00, 18:00 | Google review sync closure runs for establishments never synced or synced more than 6 hours ago. |
  | 5 | Stop the scheduler for 2 hours and restart | Queued emails are delivered at next run; no duplicates; hourly tasks run at the next boundary (missed runs are not replayed). |
  | 6 | Run `php artisan schedule:run` twice within the same minute | Tasks are not executed twice by the app logic beyond what the scheduler allows; record any duplicates (e.g. duplicate emails). |
- **Acceptance criteria:** Every documented task is registered and runs at the stated cadence.
- **Evidence to capture:** `schedule:list` output and time-stamped run results.
- **Notes / assumptions / open questions:** The README describes the calendar sync only as part of the "single cron entry"; the baseline document lists 15 minutes (see UAT-CALA-011). The queue worker is not required because the job is dispatched via `Schedule::job` (check `QUEUE_CONNECTION`; with a non-sync driver a queue worker must run for `CompletePastReservations`). Record the environment's queue driver.

## UAT-JOB-003: `platform:mode` command (on-premise vs cloud branding)
- **Feature / journey:** Platform mode switch
- **Priority:** P2
- **Role / permissions:** Operator (local test environment only)
- **References:** `app/Console/Commands/SetPlatformMode.php`; `config/platform.php`; admin settings customer theme rule (`AdminController::updateSettings`)
- **Preconditions / setup:** A disposable copy of `.env` (the command rewrites `.env`). Back it up first.
- **Test data:** Modes `on_premise`, `cloud`, `invalid`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan platform:mode` | "Current mode: <mode>" and usage line. |
  | 2 | `php artisan platform:mode cloud` | `.env` line `PLATFORM_MODE=cloud` updated or appended; config cache cleared; message says public pages show "Hospitality OS" branding. Exit 0. |
  | 3 | Open the public home page | Branding "Hospitality OS" regardless of site name. |
  | 4 | `php artisan platform:mode on_premise` | Operator brand (Settings > Site name) shown again. |
  | 5 | `php artisan platform:mode wrong` | "Invalid mode 'wrong'. Use 'on_premise' or 'cloud'." exit code 1; `.env` unchanged. |
  | 6 | Missing `.env` file | ".env not found at <path>" exit code 1. |
  | 7 | Restore the backed-up `.env` | Original state. |
- **Acceptance criteria:** Mode switches persist in `.env` and affect branding and cloud-only routes (`/platform/tenants/create`, `/host/register`).
- **Evidence to capture:** Console output, screenshots.
- **Notes / assumptions / open questions:** Not for production use.

## UAT-JOB-004: `property:sync-media` image import (dry run behavior and expected counts)
- **Feature / journey:** Bulk media import command
- **Priority:** P2
- **Role / permissions:** Operator (test environment with the supplied photo set)
- **References:** `app/Console/Commands/SyncPropertyMedia.php`; `tests/Feature/PropertyMediaImportTest.php`; `seed-assets/properties/manifest.json`
- **Preconditions / setup:** Source folder with sub-folders `appartement 401` (14 images), `appartement 402` (12), `appartement 403` (19), `appartement 404` (13); PHP GD enabled; test database (the command overwrites `property_images` of the four slugs).
- **Test data:** Options `--source`, `--target`, `--manifest`, `--dry-run`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run with a missing `--source` | "Source photo directory not found: <path>" exit 1. |
  | 2 | Remove one image from a folder, run | "Folder appartement 40X expected N images but found M." exit 1. |
  | 3 | Restore the folder, run with `--dry-run` | Record what is written: per code review `--dry-run` only skips writing the manifest file; resized files and `property_images` rows are still created. |
  | 4 | Run without `--dry-run` | "Processed 4 properties into <target>." Each image yields `-full`, `-large`, `-card`, `-thumb` variants in `.webp` and `.jpg`; DB has 58 images in total; first image per property is cover; manifest file generated. |
  | 5 | Run again | Rows updated (no duplicates); previous rows for the four slugs are deleted and recreated. |
  | 6 | Include a corrupt image | Command throws "Unable to read image: <path>". |
  | 7 | Check the property pages | Images load from `uploads/properties/<slug>/`. |
- **Acceptance criteria:** Counts and variants match; behavior of `--dry-run` is recorded.
- **Evidence to capture:** Console output, directory listing, DB counts.
- **Notes / assumptions / open questions:** DEFECT CANDIDATE: `--dry-run` has side effects (files, database). Command is hard-coded to four apartment folders and creates missing properties as `draft`.
