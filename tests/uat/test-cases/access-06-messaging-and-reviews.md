# Access 06: Messaging with the host and guest reviews

Source of truth: `MessageController`, `AdminMessageController`, `MessageEmailService`, `ReviewController`, `ReservationEmailService` (review link), `routes/web.php`, views `resources/views/messages/{index,thread}.blade.php` and `reviews/submit.blade.php`, lang `messages.messages.*` and `messages.review_request.*`, tests `MessagingTest`, `ReviewRequestTest`.

Common setup: customers `uat-cust@example.test` and `uat-cust2@example.test`; tenant admin `admin@afrikappart.test`, concierge `concierge@afrikappart.test`, host `host@afrikappart.test` (assigned to establishment E1); establishments E1 and E2 in the default tenant. Passwords: `<PLACEHOLDER from SEED_*_PASSWORD env / tester-provided>` for seed users, `<tester-chosen password, 8+ chars>` for new users. Mail goes to the outbox (`email_outbox` table / mail log); the tester needs read access to it.

---

## UAT-MSG-001: Customer starts a conversation with an establishment
- **Feature / journey:** New message thread
- **Priority:** P0
- **Role / permissions:** Customer
- **References:** `GET /messages` (`messages.index`), `POST /messages` (`messages.store`), `MessageController::store`; lang `messages.title` "Messages", `messages.start_thread` "Start a conversation", `messages.sent` "Message sent.", `messages.no_threads` "No conversations yet."
- **Preconditions / setup:** Logged in as `uat-cust@example.test` with no threads.
- **Test data:** establishment E1, body `Hello, is early check-in possible? (UAT)`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/messages` | Title "Messages", text "No conversations yet.", "Start a conversation" panel open with select `establishment_id` ("Select an establishment") and textarea `body` (maxlength 5000). |
  | 2 | Select E1, enter the body, click "Send message" | Redirect to `/messages` with "Message sent." Thread with E1 listed with the latest message preview. |
  | 3 | Open the thread | Page "Conversation with <E1 name>" shows the message with sender name; related reservation reference shown if the customer has a reservation at E1. |
  | 4 | Check outbox | `message_received` emails to E1's tenant staff (admin, concierge, host) who have message notifications on (not the sender). |
- **Acceptance criteria:** A thread and a message are created; the flash is shown; staff are notified.
- **Evidence to capture:** Screens; outbox rows.
- **Notes / assumptions / open questions:** Notification recipients are all staff of the establishment's tenant with role admin/concierge/host, not only hosts assigned to E1 (see `MessageEmailService`); record who received emails.

## UAT-MSG-002: Second message to the same establishment reuses the thread
- **Feature / journey:** One thread per customer and establishment
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** `MessageController::store` uses `firstOrCreate(customer_id, establishment_id)`; `MessagingTest::test_customer_and_staff_share_one_establishment_thread`
- **Preconditions / setup:** Thread with E1 exists (UAT-MSG-001).
- **Test data:** Second body `Another question (UAT)`; establishment E2 for step 3.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | In `/messages` choose E1 again and send | "Message sent."; still ONE thread with E1 containing both messages in order. |
  | 2 | Open the thread | Two messages visible; thread moves to the top of the list (ordered by last update). |
  | 3 | Send a message to E2 | A second, separate thread. |
- **Acceptance criteria:** No duplicate threads per establishment.
- **Evidence to capture:** Thread list screenshot; DB thread/message counts.
- **Notes / assumptions / open questions:** None.

## UAT-MSG-003: Message validation and boundaries
- **Feature / journey:** Message form validation
- **Priority:** P0
- **Role / permissions:** Customer
- **References:** `MessageController::store` rules: `establishment_id` required integer exists:establishments,id; `body` required string max 5000; `reply` rule `body` required max 5000
- **Preconditions / setup:** Logged in customer. Remove HTML `required`/`maxlength` with dev tools where needed.
- **Test data:** Bodies: empty; 5000 characters of `a`; 5001 characters; whitespace only; `<b>bold</b> & <script>alert(1)</script>`; emoji and French accents `Bonjour, pouvez-vous m'aider ? éàü 😀`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with empty body | Required error; no message stored. |
  | 2 | Submit with body of exactly 5000 characters | Accepted. 5001 characters: max-length error; nothing stored. |
  | 3 | Submit whitespace-only body | Rejected (Laravel trims input and treats empty string as null). Record actual. |
  | 4 | No establishment selected / `establishment_id=0` / `establishment_id=abc` / `establishment_id=999999` | Validation error on `establishment_id`; no thread created. |
  | 5 | Send the HTML/script body | Message stored and shown as literal text (escaped) in the customer thread and in the staff admin thread; no script runs. |
  | 6 | Send the accent/emoji body | Displayed correctly (UTF-8) in both views. |
  | 7 | Submit the same message twice quickly | Two messages are stored (no de-duplication in code); record. |
- **Acceptance criteria:** Invalid input is rejected; 5000 is the maximum; content is escaped.
- **Evidence to capture:** Screens; DB rows.
- **Notes / assumptions / open questions:** The `exists:establishments,id` rule is not tenant-scoped, so customers can write to any establishment of any tenant (expected for a shared customer base).

## UAT-MSG-004: Customer replies inside an existing thread
- **Feature / journey:** Thread reply
- **Priority:** P0
- **Role / permissions:** Customer (thread owner)
- **References:** `GET /messages/{thread}` (`messages.show`), `POST /messages/{thread}` (`messages.reply`), `MessageController::reply`
- **Preconditions / setup:** Thread with E1 containing a staff reply (UAT-MSG-006).
- **Test data:** Body `Thank you (UAT)`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the thread from the list | Conversation shows both customer and staff messages with sender names; reply form with `body`. |
  | 2 | Send a reply | Redirect to `/messages` (list) with "Message sent."; thread listed first. |
  | 3 | Re-open the thread | New message at the end. |
  | 4 | Reply with an empty body or 5001 characters | Validation error; nothing stored; user stays on the thread page. |
  | 5 | Staff (with notifications on) | Receives `message_received` email with link `/admin/messages/{thread}`. |
- **Acceptance criteria:** Reply is appended to the same thread; staff is notified.
- **Evidence to capture:** Screens; outbox.
- **Notes / assumptions / open questions:** After a reply the user is redirected to the list, not the thread (code behaviour).

## UAT-MSG-005: A customer cannot read or write another customer's thread
- **Feature / journey:** Thread ownership
- **Priority:** P0
- **Role / permissions:** Customer (non-owner)
- **References:** `MessageController::show/reply` (`abort_unless($thread->customer_id === user id, 403)`), `MessagingTest::test_customer_cannot_read_another_customers_thread`
- **Preconditions / setup:** Thread T1 belongs to `uat-cust@example.test`. Logged in as `uat-cust2@example.test`.
- **Test data:** T1 id.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/messages/{T1}` | HTTP 403. |
  | 2 | POST `/messages/{T1}` with `body=intrusion` (replay with tool) | HTTP 403; no message stored. |
  | 3 | GET `/messages/999999999` | HTTP 404. |
  | 4 | Open `/messages` | Only own threads listed. |
- **Acceptance criteria:** Other customers' conversations are neither visible nor writable.
- **Evidence to capture:** Responses; DB message count.
- **Notes / assumptions / open questions:** None.

## UAT-MSG-006: Staff inbox, reply and linked reservation
- **Feature / journey:** Admin messaging
- **Priority:** P0
- **Role / permissions:** Admin, concierge, host (assigned to E1)
- **References:** `GET /admin/messages`, `GET /admin/messages/{thread}`, `POST /admin/messages/{thread}` (`admin.messages.*`), `AdminMessageController`; view `messages/thread.blade.php` with `$isStaff=true`
- **Preconditions / setup:** Thread T1 (customer -> E1) and customer has a reservation at E1.
- **Test data:** Reply `Hello, early check-in is possible (UAT)`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Staff opens `/admin/messages` | T1 listed with customer name, establishment and preview. No "start conversation" panel for staff. |
  | 2 | Open T1 | Messages shown; the latest reservation reference of this customer at E1 is shown as a link to `/admin/reservations/{id}`. |
  | 3 | Reply | Redirect to `/admin/messages` with "Message sent."; message stored with the staff user as sender. |
  | 4 | Check outbox | `message_received` to the customer if the customer's "New message notifications" is on; link `/messages/{thread}`; subject "New message received" in the customer's locale. |
  | 5 | Customer opens `/messages/{T1}` | Staff reply visible. |
  | 6 | Staff submits empty / 5001-char reply | Validation error; nothing stored. |
  | 7 | Repeat step 1 as the host: threads of E2 | Not listed (UAT-RBAC-006). |
- **Acceptance criteria:** Staff can read and answer threads of their scope; customer sees the answer.
- **Evidence to capture:** Screens; outbox rows.
- **Notes / assumptions / open questions:** Staff cannot start a conversation themselves (no staff create route).

## UAT-MSG-007: Message email notifications honour the recipient's preference
- **Feature / journey:** Notification opt-out
- **Priority:** P1
- **Role / permissions:** Customer; staff
- **References:** `MessageEmailService::queueForMessage` (customer-sent: staff of the establishment's tenant with `email_message_updates=true`; staff-sent: customer with `email_message_updates=true`; sender excluded), preference checkbox "New message notifications" in `/account/preferences`
- **Preconditions / setup:** Thread T1. Customer preference on, then off.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Customer preference ON; staff replies | One `message_received` outbox row to the customer with sender name, establishment name and message text. |
  | 2 | Customer turns the preference OFF; staff replies again | No new outbox row for the customer. |
  | 3 | A staff user with `email_message_updates` off (set in admin user edit) receives a customer message | No row for that staff user; others still receive. |
  | 4 | Customer sends a message | The customer never receives a mail for their own message. |
- **Acceptance criteria:** Opt-outs are respected; sender is excluded.
- **Evidence to capture:** Outbox listing per step.
- **Notes / assumptions / open questions:** The email body includes the full message text (`payload.message`).

## UAT-MSG-008: Only customers may use the customer messaging area
- **Feature / journey:** Messaging role restriction
- **Priority:** P1
- **Role / permissions:** Admin, concierge, host
- **References:** `MessageController::authorizeCustomer` (role must equal `customer`), lang `errors.customer_messaging_required`
- **Preconditions / setup:** Seeded staff accounts.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As each staff role open `/messages` | HTTP 403 "Customer messaging access is required." |
  | 2 | As staff open `/messages/{any thread}` and POST `/messages` | HTTP 403. |
  | 3 | As staff look for a "Messages" link on `/dashboard` | Record whether a link is shown (staff are directed to `/admin/messages`). |
  | 4 | As guest open `/messages` | Redirect to `/login`. |
- **Acceptance criteria:** Staff use `/admin/messages` only.
- **Evidence to capture:** Screens of 403.
- **Notes / assumptions / open questions:** None.

## UAT-MSG-009: Contact the establishment from a reservation page
- **Feature / journey:** Message form on reservation detail
- **Priority:** P1
- **Role / permissions:** Customer (owner)
- **References:** `dashboard-reservation.blade.php` form posting to `messages.store` with hidden `establishment_id`, textarea `reservation-message-body`
- **Preconditions / setup:** Customer reservation at E1.
- **Test data:** Body `Question about my stay (UAT)`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/dashboard/reservations/{id}` and send the message form | Redirect to `/messages` with "Message sent."; message in the E1 thread. |
  | 2 | Staff opens the thread | Message with link to this reservation's reference. |
  | 3 | Tamper hidden `establishment_id` to E2 | Message goes to E2's thread (no check that the reservation belongs to E2). Record actual. |
  | 4 | View as a staff user | Message form is not displayed (only for role customer). |
- **Acceptance criteria:** The reservation page offers a working contact path.
- **Evidence to capture:** Screens.
- **Notes / assumptions / open questions:** Step 3 is an observation: the tampered id is accepted by the validation rule (any existing establishment).

---

# Reviews (signed review requests)

Review link behaviour (from code): the link is `URL::temporarySignedRoute('reviews.submit', now()->addDays(30), ['reservation' => id])`, produced when a reservation status update email for `completed` is queued (`ReservationEmailService::queueStatusUpdate`). The routes `GET|POST /review/{reservation}` and `GET /review/{reservation}/google` are under the `signed` middleware, need no login, and require the reservation to have status `completed`.

## UAT-REV-001: Review request email is created when a stay is completed
- **Feature / journey:** Review request trigger
- **Priority:** P1
- **Role / permissions:** Staff (actor); guest (recipient)
- **References:** `ReservationEmailService::queueStatusUpdate` (payload `review_url`, `google_review_url`, `review_channel`), `App\Jobs\CompletePastReservations`, lang `review_request.email_internal_action` "Share your experience on our website", `review_request.email_google_action`, `ReviewRequestTest`
- **Preconditions / setup:** Confirmed/checked-in reservation `RV-1` (email `uat-review@example.test`) on E1; establishment review channel `internal` (default).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Staff changes `RV-1` status to `completed` in `/admin/reservations/{id}` | Status saved; outbox email for `uat-review@example.test` queued. |
  | 2 | Inspect the payload | Contains a signed `review_url` of the form `/review/{id}?expires=...&signature=...` valid for 30 days; `review_channel` `internal`. |
  | 3 | Set the establishment channel to `both` with a Google URL and complete another reservation | Payload also has `google_review_url`; email shows the Google action. |
  | 4 | Move a reservation to a status other than `completed` | No review link in that email. |
- **Acceptance criteria:** A signed link is generated only for completed stays.
- **Evidence to capture:** Outbox payload (signature masked in the report).
- **Notes / assumptions / open questions:** The scheduled job `CompletePastReservations` also completes past reservations; verify that it queues the same email (not reviewed here).

## UAT-REV-002: Guest submits one internal review through the signed link
- **Feature / journey:** Leave a review
- **Priority:** P0
- **Role / permissions:** Guest (no login required); also signed-in customer
- **References:** `GET /review/{reservation}` (`reviews.submit`), `POST /review/{reservation}`, `ReviewController::create/store`, view `reviews/submit.blade.php`; lang `review_request.title` "Share your experience", `.rating` "Your rating", `.comment` "Your comment", `.submit` "Submit my review", `.thanks` "Thank you for your review."
- **Preconditions / setup:** Review link from UAT-REV-001 for a `completed` reservation with no review. Not logged in.
- **Test data:** rating 5, text `Great stay (UAT).`
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the signed link as a guest | Page "Share your experience" with description "How was your stay at <property>?", number field `rating` (min 1, max 5, default 5), textarea `review_text`, button "Submit my review". |
  | 2 | Submit rating 5 and the text | Redirect to the same signed URL showing "Thank you for your review." (alert). |
  | 3 | Check admin `/admin/reviews` (admin) | New review: source `website`, rating 5, linked to the property/reservation, active. Reviewer name = guest full name, or "Guest" when absent. |
  | 4 | Reload the signed link | Shows "A review has already been submitted for this stay." and no form. |
  | 5 | Open `/reviews` and the property page | The review appears in the public lists and in rating averages (record where). |
- **Acceptance criteria:** One review is stored and published; the form is gone afterwards.
- **Evidence to capture:** Screens of steps 1-5; DB row.
- **Notes / assumptions / open questions:** Reviews are active immediately (no moderation step in code).

## UAT-REV-003: Duplicate or concurrent submissions are rejected
- **Feature / journey:** One review per reservation
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `ReviewController::store` (`abort_if(exists, 409, review_request.already_submitted)`)
- **Preconditions / setup:** Link of a reservation whose review was already submitted; another unreviewed link.
- **Test data:** rating 4.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST the signed URL again (replay with tool, valid CSRF) | HTTP 409 "A review has already been submitted for this stay."; no second review. |
  | 2 | Open the form in two tabs, submit in both | One review stored; the second request returns 409. |
  | 3 | Browser Back after submission and resubmit | Same 409 outcome. |
- **Acceptance criteria:** Exactly one review per reservation.
- **Evidence to capture:** Responses; review count.
- **Notes / assumptions / open questions:** Race between two simultaneous requests relies on a check-then-insert (no unique constraint reviewed); record any duplicate row.

## UAT-REV-004: Review field validation and boundaries
- **Feature / journey:** Review form validation
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `ReviewController::store` rules: `rating` required integer min 1 max 5; `review_text` nullable string max 2000
- **Preconditions / setup:** A fresh unreviewed signed link (use a new one for each accepted submission). Remove HTML limits with dev tools.
- **Test data:** ratings 0, 1, 5, 6, `abc`, `4.5`; text of 2000 and 2001 characters; text with `<script>alert(1)</script>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | rating 0, rating 6, `abc`, `4.5`, empty | Validation error on `rating`; no review stored. |
  | 2 | rating 1, empty text | Accepted (text optional). |
  | 3 | rating 5, text of 2000 characters | Accepted. |
  | 4 | text of 2001 characters | Error on `review_text`. |
  | 5 | text with script tag | Stored; displayed escaped on public review lists and in admin. |
  | 6 | Errors in French UI | Messages in French. |
- **Acceptance criteria:** Boundaries 1 and 5, 2000 characters pass; others fail.
- **Evidence to capture:** Screens; DB rows.
- **Notes / assumptions / open questions:** Validation failures redirect back to the signed URL; verify the signature stays valid after the redirect (form posts to `request()->fullUrl()`).

## UAT-REV-005: Tampered, expired or unsigned review links are refused
- **Feature / journey:** Signed URL protection
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `signed` middleware on `/review/{reservation}`; link lifetime 30 days
- **Preconditions / setup:** A valid link for a completed reservation. Ability to alter the URL; for expiry, a link older than 30 days (system date shift in test environment or an old email).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/review/{id}` without query string | HTTP 403 (invalid signature). |
  | 2 | Alter one character of `signature` | HTTP 403. |
  | 3 | Change the reservation id in the path, keep the signature | HTTP 403. |
  | 4 | Change `expires` to a later timestamp | HTTP 403. |
  | 5 | Open an expired link | HTTP 403. |
  | 6 | POST to `/review/{id}` without a valid signature | HTTP 403; no review. |
  | 7 | Open `/review/{id}/google` without a signature | HTTP 403. |
- **Acceptance criteria:** Only the exact emailed URL, within 30 days, works.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** The framework's 403 page is rendered through `resources/views/errors/403.blade.php`.

## UAT-REV-006: Review link for a reservation that is not completed
- **Feature / journey:** Status guard
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `ReviewController::authorizeCompletedReservation` (HTTP 404 unless `completed`)
- **Preconditions / setup:** Obtain a valid signed link for a completed reservation, then staff changes the status to `cancelled` (or generate a signed link with a tool for a `confirmed` reservation if the tester can sign URLs).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the signed link after the status moved away from `completed` | HTTP 404. |
  | 2 | POST to the same URL | HTTP 404; no review. |
  | 3 | Restore the status to `completed` | Link works again. |
- **Acceptance criteria:** Reviews are possible only for completed stays.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** None.

## UAT-REV-007: Review channel settings: internal, Google, both
- **Feature / journey:** Google review redirect
- **Priority:** P1
- **Role / permissions:** Guest; admin (configures)
- **References:** `ReviewController::create/google`, establishment fields `review_channel` and `google_review_url` (`/admin/establishments/{id}/edit`, tab online), `ReviewRequestTest::test_google_review_destination_can_be_configured`, lang `review_request.google_title` "Also share on Google", `.google_action` "Review us on Google"
- **Preconditions / setup:** Three completed reservations on three establishments (or one establishment whose channel is changed between runs).
- **Test data:** Google URL `https://g.page/r/example/review` (placeholder).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Channel `internal`, no Google URL | Form shown; no Google block, before and after submit. |
  | 2 | Channel `both` with URL | Form shown first, Google block hidden. After submitting (or when already reviewed) block "Also share on Google" with link "Review us on Google" (`target="_blank" rel="noopener noreferrer"`). |
  | 3 | Click the Google link | Signed `/review/{id}/google` redirects to the configured Google URL. |
  | 4 | Channel `google` | The internal form is not displayed; Google block is shown only when a review exists or was just submitted (record what a first-time visitor sees). |
  | 5 | Open `/review/{id}/google` (signed) for an establishment without URL | HTTP 404. |
- **Acceptance criteria:** Each channel setting changes the page as described; the redirect uses the configured URL.
- **Evidence to capture:** Screens; redirect location.
- **Notes / assumptions / open questions:** OPEN QUESTION (step 4): with channel `google` the form is hidden and the Google block requires an existing internal review, so a first-time visitor may see an empty page. Record what appears.

## UAT-REV-008: A submitted review belongs to the establishment's tenant only
- **Feature / journey:** Review tenant stamping
- **Priority:** P2
- **Role / permissions:** Admin A, admin B (cloud mode)
- **References:** `ReviewController::store` (`tenant_id` from the establishment), `SiteReview` uses `BelongsToTenant`
- **Preconditions / setup:** Two-tenant fixture (see `access-04`), completed reservation at tenant B's property with a review submitted by a guest.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin B opens `/admin/reviews` | The review is listed. |
  | 2 | Admin A opens `/admin/reviews` | The review is not listed. |
  | 3 | Public property page of B's property | Review displayed (public catalog). |
- **Acceptance criteria:** Admin review lists are tenant-scoped; public view is unified.
- **Evidence to capture:** Screens.
- **Notes / assumptions / open questions:** The guest has no session, so the review is created without a current tenant; the code stamps `tenant_id` explicitly.
