# Public UAT 06: Help chat widget, platform feedback form, public iCal feed

All cases are **Not Run**. Help chat: `POST /help/chat` (route `help-chat.ask`, `throttle:10,1`). Feedback: `GET|POST /feedback` (routes `feedback.create`, `feedback.store`, `throttle:5,1`). iCal: `GET /calendar/{property:slug}/{token}.ics` (route `calendar.public-export`).

**Shared environment notes:**
- Help chat needs `services.help_chat.api_key` (env `GROQ_API_KEY`, set in UAT only with a sandbox key), `base_url` (env `PROPERTY_COPY_BASE_URL`, default Groq OpenAI-compatible URL) and `model` (`HELP_CHAT_MODEL`); the chat completion provider should be stubbed or a sandbox. No real customer data is typed into the widget.
- Feedback needs `SPRINTPAY_FEEDBACK_EMAIL` set to a UAT mailbox `feedback.uat@example.test` (config key `services.sprintpay.feedback_email`).
- Mail is captured.

---

## UAT-HELP-001: Help chat widget opens, closes and shows privacy notice
- **Feature / journey:** Help chat widget
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** resources/views/partials/help-chat.blade.php (included in layouts/app.blade.php), resources/js/app.js (`[data-help-chat]`), lang `help_chat.open|title|privacy|greeting|message|send|close`
- **Preconditions / setup:** Help chat API key configured (widget is always rendered; it is not hidden when the key is missing).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/`. | A launcher labelled "Ask for help" (FR "Besoin d'aide ?") is visible. |
  | 2 | Click it. | Panel "Afrik Appart help" opens with: "Questions are processed by an AI service. Do not include booking or payment details." and the greeting "What can I help you with?". |
  | 3 | Click the "x" button (aria-label "Close help chat"). | Panel closes (`details` open = false). |
  | 4 | Open the panel on `/properties/<slug>`, `/faq`, `/privacy`, `/feedback`. | Widget present on all layout pages. |
  | 5 | Open checkout. | Widget is present (the layout includes it for private pages too); record. |
  | 6 | Switch the language and reopen. | Labels localized. |
- **Acceptance criteria:** Widget opens/closes; privacy notice shown before the first message.
- **Evidence to capture:** Screenshots EN/FR, desktop and mobile.
- **Notes / assumptions / open questions:** Privacy notice warns against entering booking or payment details; on the checkout page the widget is available, which may encourage doing so (observation). The close button glyph is a literal "×" in the template.

---

## UAT-HELP-002: Help chat answers from the public FAQ
- **Feature / journey:** Ask a question
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `HelpChatController@ask` (system prompt built from `messages.faq.{booking,payment,cancellation,checkin,amenities,support}_{question,answer}`; model parameters temperature 0.2, max_tokens 500), test `test_help_chat_answers_from_public_faq_context`, resources/views/faq.blade.php
- **Preconditions / setup:** Chat provider configured and reachable (or stubbed).
- **Test data:** Questions: "How do I cancel a reservation?" (EN); "Comment puis-je payer ?" (FR); an off-topic question "Who won the world cup?".
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Type the cancellation question and click "Send". | The question appears in the log; the status line (`role="status"`) shows progress; then an answer appears, consistent with the FAQ cancellation answer. Response JSON `{ "answer": "..." }`. |
  | 2 | Ask the French payment question while the site is in English. | Answer in French (system prompt says answer in the user's language). |
  | 3 | Ask the off-topic question. | The answer states the FAQ does not cover it and refers to the Contact page (prompt instruction). Record the actual wording; AI output varies. |
  | 4 | Send 6 questions in a row. | The client sends the last up to 10 history messages (5 exchanges) with each request; follow-up questions use context. |
  | 5 | Ask for private information ("What is the password of customer X?"). | No private data is returned (the prompt only has FAQ text). |
  | 6 | Check the request in dev tools. | `POST /help/chat` with JSON `message` and `history`; includes CSRF token. |
  | 7 | Compare the answer with the FAQ page text. | No contradiction with facts on `/faq`; record discrepancies. |
- **Acceptance criteria:** Answers are relevant to FAQ topics; unknown questions are redirected to Contact; no sensitive data involved.
- **Evidence to capture:** Screenshots of conversations, request/response.
- **Notes / assumptions / open questions:** AI answers are non-deterministic; judge using a rubric (relevance, language, no invention of policy). Acceptable accuracy threshold is not defined in the repository.

---

## UAT-HELP-003: Help chat reports unavailable when the API key is missing
- **Feature / journey:** Help chat disabled configuration
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `HelpChatController@ask` (503 `help_chat.unavailable` when `services.help_chat.api_key` is blank), test `test_help_chat_reports_unavailable_without_an_api_key`
- **Preconditions / setup:** Environment without `GROQ_API_KEY`.
- **Test data:** "Hello".
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the widget and send a message. | HTTP 503 JSON `{"message":"Help chat is temporarily unavailable."}`; the widget shows an error text (the data attribute `data-error-message` = "I could not get an answer just now. Please try again or use the Contact page."). Record which text the user sees. |
  | 2 | Repeat in French. | "Le chat d'aide est temporairement indisponible." (API) / FR fallback text in the widget. |
  | 3 | Check that no HTML error page or console crash is produced. | Widget remains usable; Send button re-enabled. |
- **Acceptance criteria:** Graceful failure; no stack trace.
- **Evidence to capture:** Screenshot, response JSON.
- **Notes / assumptions / open questions:** The widget is displayed even when the feature is not configured; users find a launcher that can never answer (UX gap).

---

## UAT-HELP-004: Help chat request validation
- **Feature / journey:** Input validation and limits
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `HelpChatController@ask` rules (`message` required string max 1200; `history` array max 10; `history.*.role` in user,assistant; `history.*.content` required max 1200)
- **Preconditions / setup:** API key configured; API client with session cookie + CSRF (or browser dev tools `fetch`).
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send an empty message in the UI. | Browser `required` blocks submit. |
  | 2 | POST `message` = "" via fetch. | HTTP 422 with `errors.message`. |
  | 3 | POST a 1200-character message, then 1201 characters. | 1200 accepted; 1201 rejected (422). |
  | 4 | POST `history` with 11 entries. | 422 (max 10). |
  | 5 | POST `history[0].role` = "system". | 422 (role must be user or assistant). |
  | 6 | POST `history[0].content` of 1201 characters. | 422. |
  | 7 | POST a message with HTML/script text. | Answer displayed as text (no HTML injection into the chat log). |
  | 8 | POST without CSRF. | HTTP 419. |
- **Acceptance criteria:** Invalid payloads rejected with 422; no prompt-injection through the system role.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** Whether the UI displays the 422 message is part of the recorded observation.

---

## UAT-HELP-005: Help chat provider failure, timeout and empty answer
- **Feature / journey:** Integration failure
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `HelpChatController@ask` (exception, non-2xx, empty content: HTTP 502 `help_chat.failed`; provider timeout 25 s)
- **Preconditions / setup:** Ability to point `PROPERTY_COPY_BASE_URL` to a stub returning: 500; an invalid JSON; HTTP 200 with empty content; a slow response (>25 s).
- **Test data:** "How can I contact support?"
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Provider returns 500. | HTTP 502 `{"message":"I could not get an answer just now. Please try again or use the Contact page."}`; widget shows that message. |
  | 2 | Provider returns 200 with empty content. | Same 502. |
  | 3 | Provider hangs more than 25 s. | Request times out; 502 error message; UI recovers. |
  | 4 | Provider returns 401 (bad key). | 502 (no key details leak to the UI). |
  | 5 | After errors, send a normal question. | Works again. |
  | 6 | Review the application error log. | A warning "Help chat provider ..." entry exists; the API key is not logged. |
- **Acceptance criteria:** Failures give the generic message; no secrets or stack traces to the client.
- **Evidence to capture:** Screenshots, log excerpt (redacted).
- **Notes / assumptions / open questions:** The Send button state during the 25 s wait must prevent double submit; record.

---

## UAT-HELP-006: Help chat rate limit (10 requests per minute)
- **Feature / journey:** Throttling
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `Route::post('/help/chat')->middleware('throttle:10,1')`, resources/views/errors/429.blade.php
- **Preconditions / setup:** API key configured or missing (throttle applies before the controller).
- **Test data:** 11 short messages sent within one minute from one IP/session.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send 10 messages quickly. | All processed (200 or the configured failure). |
  | 2 | Send the 11th within the same minute. | HTTP 429 with a `Retry-After` header. The widget shows its generic failure text (the UI treats non-OK as failure). |
  | 3 | Wait 60 seconds and send again. | Works. |
- **Acceptance criteria:** The 11th request in a minute is throttled and recovers.
- **Evidence to capture:** Network log with status codes and `Retry-After`.
- **Notes / assumptions / open questions:** The limit is keyed by IP (and user when logged in); shared NAT networks share a quota.

---

## UAT-FEED-001: Feedback form renders with and without delivery configured
- **Feature / journey:** Platform feedback form
- **Priority:** P1
- **Role / permissions:** Anonymous guest; customer
- **References:** `GET /feedback` (route `feedback.create`), `PlatformFeedbackController@create`, resources/views/feedback/create.blade.php, lang `feedback.*`, footer link `footer-feedback-link`
- **Preconditions / setup:** Two environments or toggles: `SPRINTPAY_FEEDBACK_EMAIL` set; unset.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click "Send feedback to Sprint Pay Canada" in the footer. | `/feedback` opens: badge "Sprint Pay Canada", heading "Platform feedback", fields Name, Email, Feedback type (Suggestion, Issue, Other), Your feedback (textarea, 6 rows), button "Send feedback". |
  | 2 | With delivery unset. | A visible help text "Feedback delivery is not configured yet." appears. |
  | 3 | As a logged-in customer. | Name and email prefilled from the account. |
  | 4 | As anonymous. | Fields empty. |
  | 5 | Check accessibility. | Each field has a label tied via `for`; required attributes present; inputs have `maxlength` 255 (name/email) and 5000 (message). |
- **Acceptance criteria:** Form complete; state message correct for config.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** The form is shown even if delivery is not configured, and the submit button stays enabled (see FEED-004).

---

## UAT-FEED-002: Submit feedback successfully
- **Feature / journey:** Send feedback
- **Priority:** P0
- **Role / permissions:** Anonymous guest and customer
- **References:** `POST /feedback` (`PlatformFeedbackController@store`: creates `PlatformFeedback` with `user_id` when logged in, sends `PlatformFeedbackReceived` mail), test `test_feedback_is_sent_to_the_configured_sprint_pay_address`, lang `feedback.sent`
- **Preconditions / setup:** Delivery configured to `feedback.uat@example.test`.
- **Test data:** name "UAT Tester", email `tester.uat@example.test`, category `issue`, message "UAT message: the date picker is hard to use on mobile."
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Fill and submit as anonymous. | Redirect to `/feedback` with the flash "Your feedback was sent to Sprint Pay Canada." (`role="status"`). |
  | 2 | Check the mailbox of the feedback recipient. | One email with name, email, category and message. |
  | 3 | Check the database table `platform_feedback`. | One row with the submitted values; `user_id` NULL. |
  | 4 | Repeat as customer `customer.uat1@example.test`. | Row has `user_id` of the customer. |
  | 5 | Submit with category `suggestion` and `other`. | Both accepted. |
  | 6 | Submit a message containing `<script>alert(1)</script>` and accents. | Stored and emailed escaped/as text; no script runs when the staff view or email is opened. |
  | 7 | Submit a 5000-character message. | Accepted. |
- **Acceptance criteria:** Feedback persisted and delivered; success message shown.
- **Evidence to capture:** Screenshot, email, DB row.
- **Notes / assumptions / open questions:** The mail is sent synchronously (`Mail::to()->send`); if SMTP fails the user sees a server error after the row was saved (see FEED-004).

---

## UAT-FEED-003: Feedback validation
- **Feature / journey:** Feedback validation
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `PlatformFeedbackController@store` rules (`name` required max 255, `email` required email max 255, `category` in suggestion,issue,other, `message` required max 5000)
- **Preconditions / setup:** Delivery configured.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit an empty form (remove `required` attributes in dev tools). | Errors on name, email, category (message "required"), message; fields keep nothing. |
  | 2 | Email `not-an-email`. | Error on email. |
  | 3 | Category `bug` (tampered). | Error on category. |
  | 4 | Name of 256 characters; message of 5001 characters. | Errors (max). |
  | 5 | Whitespace-only message `"   "`. | Record: Laravel's `required` rejects strings that are empty after trimming (middleware `TrimStrings`); expect an error. |
  | 6 | Verify no email is sent and no row stored for each failing case. | None. |
  | 7 | In French, repeat step 2. | The error message is in French (resources/lang/fr/validation.php). |
- **Acceptance criteria:** Invalid input is rejected with field errors and no side effects.
- **Evidence to capture:** Screenshots, DB check.
- **Notes / assumptions / open questions:** The view does not show per-field error elements in the lines reviewed; verify errors are visible to the user (A11Y).

---

## UAT-FEED-004: Feedback when delivery is not configured or mail fails
- **Feature / journey:** Integration failure
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `store` (`back()->withErrors(['feedback' => 'Feedback delivery is not configured yet.'])` when recipient blank), test `test_feedback_form_reports_when_sprint_pay_delivery_is_not_configured`, `Mail::to(...)->send(...)`
- **Preconditions / setup:** (a) `SPRINTPAY_FEEDBACK_EMAIL` empty. (b) configured, but SMTP host unreachable/invalid.
- **Test data:** Valid feedback data.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | (a) Submit valid data. | Redirect back with error "Feedback delivery is not configured yet." and no row is stored. The error is shown in the alert `div.form-alert-error[role=alert]` (`$errors->first()`), in addition to the `form-help` text. |
  | 2 | (b) Submit valid data. | Observe: code stores the row first and then sends the email. A mail transport exception would produce HTTP 500 ("Something went wrong") with the row already saved. Record the real behavior. |
  | 3 | Retry after SMTP is fixed. | Another row; a duplicate of the feedback may exist. |
- **Acceptance criteria:** Case (a) shows the message; case (b) outcome documented, with duplicates noted.
- **Evidence to capture:** Screenshots, DB rows, app error log.
- **Notes / assumptions / open questions:** If (b) yields a 500, raise as a defect (no graceful message).

---

## UAT-FEED-005: Feedback rate limit (5 submissions per minute)
- **Feature / journey:** Throttling
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `Route::post('/feedback')->middleware('throttle:5,1')`, errors/429.blade.php
- **Preconditions / setup:** Delivery configured.
- **Test data:** Six valid submissions within a minute.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit 5 valid feedback forms quickly. | All accepted (5 emails). |
  | 2 | Submit the 6th. | HTTP 429 page "Too many requests" / "You sent too many requests. Please wait before trying again." |
  | 3 | Wait a minute and submit again. | Accepted. |
  | 4 | Verify only 5 emails/rows exist before the wait. | Yes. |
- **Acceptance criteria:** Rate limit enforced and recovers.
- **Evidence to capture:** Screenshots, mail count.
- **Notes / assumptions / open questions:** Invalid submissions also count toward the limit (middleware runs before validation).

---

## UAT-CAL-001: Public iCal feed opens with the valid token
- **Feature / journey:** Calendar export feed
- **Priority:** P0
- **Role / permissions:** Anonymous calendar client (Google Calendar, Airbnb, Booking.com importer)
- **References:** `GET /calendar/{property:slug}/{token}.ics` (route `calendar.public-export`), `AdminCalendarController@publicExport` and `buildCalendarResponse`, test `test_public_calendar_url_returns_ics_only_with_valid_token`
- **Preconditions / setup:** P-A with `calendar_export_token` (copy the URL from the admin property calendar tab "copy" control, `data-copy-text`); P-A has one confirmed reservation D+40 to D+43 and one admin block D+50 to D+52.
- **Test data:** URL `/calendar/uat-apt-a/<token>.ics`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the URL in a browser. | The browser downloads a file `uat-apt-a-calendar.ics` (`Content-Disposition: attachment`); `Content-Type: text/calendar; charset=UTF-8`; HTTP 200; no login needed. |
  | 2 | Open the file in a text editor. | Starts with `BEGIN:VCALENDAR`, `VERSION:2.0`, `PRODID:-//Afrik Appart//Calendar//EN`, `CALSCALE:GREGORIAN`; ends with `END:VCALENDAR`; CRLF line endings. |
  | 3 | Count events. | Two `VEVENT`s: `UID:reservation-<id>` with DTSTART `yyyymmdd` of check-in, DTEND check-out; `UID:block-<id>`. `SUMMARY:Unavailable` for both. |
  | 4 | Subscribe to the URL in a calendar app (Google Calendar "From URL" or Thunderbird). | Events are imported as all-day or time events without errors; record how they display. |
  | 5 | Add a new confirmed reservation and re-fetch. | The new event appears (feed is generated on demand, no caching). |
- **Acceptance criteria:** Valid token returns a parseable ICS with all blocked periods.
- **Evidence to capture:** The `.ics` file, response headers, calendar app screenshot.
- **Notes / assumptions / open questions:** Events lack `DTSTAMP`, which RFC 5545 requires; some importers reject such files. Date values are written without `VALUE=DATE` (see CAL-005). The export route does not check that the property is published or active.

---

## UAT-CAL-002: Invalid, missing or wrong-property token returns 404
- **Feature / journey:** Feed access control
- **Priority:** P0
- **Role / permissions:** Anonymous
- **References:** `publicExport` (`abort_unless($property->calendar_export_token && hash_equals(...), 404)`)
- **Preconditions / setup:** P-A with token TA, P-B with token TB; P-X without token (NULL).
- **Test data:** `invalid-token`, TB used on P-A, empty token, token of different length, uppercase variant of TA.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/calendar/uat-apt-a/invalid-token.ics`. | HTTP 404 (localized 404 page). |
  | 2 | GET `/calendar/uat-apt-a/<TB>.ics`. | HTTP 404 (token belongs to another property). |
  | 3 | GET `/calendar/uat-apt-a/.ics` and `/calendar/uat-apt-a/.ics?token=<TA>`. | 404. |
  | 4 | GET `/calendar/<P-X slug>/anything.ics`. | 404 (a NULL token never matches). |
  | 5 | GET `/calendar/no-such-slug/<TA>.ics`. | 404. |
  | 6 | GET with TA in uppercase (if TA has lowercase letters). | 404 (case-sensitive). |
  | 7 | GET `/calendar/uat-apt-a/<TA>.ics?x=1` and with a trailing slash. | Record behavior (query ignored; trailing slash probably 404). |
  | 8 | Send 30 invalid requests in a row. | All 404; note that no throttle middleware is attached to this route (observation). |
- **Acceptance criteria:** Only the exact token for the property returns data; nothing leaks in 404 responses.
- **Evidence to capture:** Status codes.
- **Notes / assumptions / open questions:** Token rotation/regeneration controls were not located in `AdminCalendarController`; if the owner cannot rotate a leaked token, record as a gap (admin UAT owner).

---

## UAT-CAL-003: Feed content privacy and status filtering
- **Feature / journey:** Data minimization in the iCal export
- **Priority:** P0
- **Role / permissions:** Anonymous with valid token
- **References:** `buildCalendarResponse` (reservations with status in `confirmed`, `checked_in`, `completed`; all `availabilityBlocks`; summary always "Unavailable"), test `test_admin_can_sync_external_calendar_feed_and_export_internal_calendar` (asserts no reservation reference or block reason in the admin export)
- **Preconditions / setup:** P-A with reservations in each status: `pending`, `pending_payment`, `pending_validation`, `payment_failed`, `confirmed`, `checked_in`, `completed`, `cancelled`, `no_show` (if exists); a block with reason "Maintenance"; a reservation with ref `AFK-UATCAL-000000`, guest name "Cal Guest"; an external feed event titled "Airbnb booking".
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Fetch the feed. | Events for `confirmed`, `checked_in`, `completed` reservations only, plus all availability blocks. |
  | 2 | Search the file for "AFK-UATCAL", "Cal Guest", "Maintenance", "example.test", phone numbers. | None present (summary is the literal "Unavailable"). |
  | 3 | Check `UID` values. | `reservation-<numeric id>` and `block-<numeric id>`: IDs are guessable but contain no personal data. |
  | 4 | Check whether external feed events (other platforms) are exported. | Not exported (avoids loops); record the actual content. |
  | 5 | Compare against availability on the property page. | `pending`, `pending_payment`, etc. reservations block dates on the website but are **not** in the feed: another platform could accept an overlapping booking (record as a risk). |
  | 6 | Verify cancelled reservation is absent. | Yes. |
- **Acceptance criteria:** Feed contains only blocked dates, no PII or internal notes.
- **Evidence to capture:** The ICS file, search results.
- **Notes / assumptions / open questions:** Gap between statuses blocking the website (all non-cancelled) and statuses exported to the feed is a double-booking risk with OTAs; confirm intent with the product owner.

---

## UAT-CAL-004: External calendar feeds affect public availability only when enabled
- **Feature / journey:** Imported feed impact on guests
- **Priority:** P1
- **Role / permissions:** Anonymous guest (observer); admin used for setup only
- **References:** `AvailabilityService::hasExternalCalendarConflict` (enabled feeds only), `PublicPropertyController@index/availability`, test `test_properties_date_filter_excludes_external_calendar_conflicts`, docs/requirements-baseline.md (sync every 15 minutes; freshness threshold 15 minutes before final booking)
- **Preconditions / setup:** P-A with an external feed whose events include D+60 to D+63; feed enabled; second feed (disabled) with event D+70 to D+73; the feed URL points to a stub `.ics` on an `example.test`-style test host.
- **Test data:** Stub ICS with the above events and one cancelled/`STATUS:CANCELLED` event.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin syncs the feed (sets up `external_calendar_events`). | Events stored; cancelled event not stored (per test `uid cancelled-event` absence). |
  | 2 | Guest checks availability D+60 to D+63 (PUB-024). | Not available. |
  | 3 | Guest searches `/properties?check_in=<D+60>&check_out=<D+63>`. | P-A absent. |
  | 4 | Disable the feed and repeat. | Available. |
  | 5 | Change the stub (remove the event), wait for the scheduled sync (15 min) or run the sync from admin, and re-check. | Dates become available again after the sync. |
  | 6 | Make the stub unreachable and sync. | Admin sees a sync failure flag (see test `test_adding_a_calendar_feed_with_an_unreachable_url_flags_the_sync_failure`); the guest still sees the previously imported blocks (stale data). Record. |
- **Acceptance criteria:** Only enabled feeds affect guests; stale data behavior documented.
- **Evidence to capture:** Screenshots, admin sync status.
- **Notes / assumptions / open questions:** Baseline requires a 15-minute freshness check before a final booking; the controller code reviewed does not trigger a sync before accepting a reservation. Record whether a stale feed is tolerated at `reserve` time.

---

## UAT-CAL-005: ICS format compatibility check
- **Feature / journey:** Standards conformance of the exported feed
- **Priority:** P2
- **Role / permissions:** Tester with valid token
- **References:** `AdminCalendarController::buildCalendarResponse` output format; RFC 5545 (DTSTAMP and UID required in VEVENT; all-day dates use `DTSTART;VALUE=DATE:`)
- **Preconditions / setup:** Feed with at least one reservation event and one block; access to an iCalendar validator (for example icalendar.org validator or `ical-validator` locally) and at least two calendar clients (Google Calendar, Outlook or Apple Calendar).
- **Test data:** The exported file.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Validate the file with an iCalendar validator. | Record warnings. Expected from code: no `DTSTAMP`, no `VALUE=DATE` parameter on `DTSTART`/`DTEND`. |
  | 2 | Import into Google Calendar by URL. | Record whether events appear and whether they are all-day. |
  | 3 | Import into Outlook or Apple Calendar. | Same. |
  | 4 | Check that DTEND (check-out date) is treated as exclusive: a stay D+40 to D+43 blocks nights D+40, D+41, D+42. | Displayed span matches. |
  | 5 | Test an event with a check-in on the first of a month and a block spanning the year end. | Dates correct. |
- **Acceptance criteria:** Calendar clients import the events correctly; validator findings recorded.
- **Evidence to capture:** Validator output, screenshots of imports.
- **Notes / assumptions / open questions:** Failing import in OTA platforms would be a P1 defect.
