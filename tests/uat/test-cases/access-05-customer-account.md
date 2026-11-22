# Access 05: Customer account area (dashboard, reservations, profile, favorites)

Source of truth: `routes/web.php` (auth group), `AuthController` (`dashboard`, `reservationDetail`, `updateReservation`, `accountProfile`, `updateProfile`, `accountPreferences`, `updatePreferences`, `accountSecurity`, `updatePassword`), `CheckoutController::cancel`, `FavoriteController`, `ReceiptController::download`, views `resources/views/dashboard.blade.php`, `dashboard-reservation.blade.php`, `account/{profile,preferences,security}.blade.php`, lang `resources/lang/{en,fr}/messages.php`, tests `CustomerDashboardTest`, `CustomerSelfServiceTest`.

Common setup: a customer `uat-acct@example.test` (registered via `/register`) and a second customer `uat-other@example.test`. Reservations are created by the tester through the public booking flow or by an admin on behalf of the customer (`/admin/reservations/create`); statuses are changed by staff on `/admin/reservations/{id}`. A published property `P1` has `minimum_stay` known, `max_guests` known, at least one active property feature ("option"). Passwords: `<tester-chosen password, 8+ chars>`.

---

## UAT-ACCT-001: Customer dashboard overview and empty states
- **Feature / journey:** `/dashboard` landing
- **Priority:** P0
- **Role / permissions:** Customer
- **References:** `GET /dashboard` (route `dashboard`), `AuthController::dashboard`, lang `dashboard.title` "My account", `dashboard.welcome` "Hello, :name.", `dashboard.empty_history`, `favorites.empty`
- **Preconditions / setup:** New customer with no reservations or favorites; later the same customer with reservations.
- **Test data:** `uat-acct@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in and open `/dashboard` | Title "My account", greeting with the user's name, "Customer account" label, status filter, empty reservation state and empty favorites state with "Discover stays" link to `/properties`. |
  | 2 | Create two reservations (one `pending_payment`, one `confirmed`) for this user and reload | Both listed newest first with property name, dates, guests, status; "Details" link to `/dashboard/reservations/{id}`. |
  | 3 | Confirm a `completed` and a `cancelled` reservation exist and reload | Default filter ("Active reservations") hides `cancelled` and `completed`. |
  | 4 | Switch locale to French in preferences (UAT-ACCT-014) | Labels shown in French. |
- **Acceptance criteria:** Dashboard lists the signed-in user's reservations and favorites only.
- **Evidence to capture:** Screenshots of empty and populated dashboard.
- **Notes / assumptions / open questions:** The dashboard has no "write a review" shortcut; reviews are reached through the emailed signed link (UAT-REV-001).

## UAT-ACCT-002: Dashboard status filter, including invalid values
- **Feature / journey:** Filter reservations by status
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** `AuthController::dashboard` (valid values: `pending`, `pending_payment`, `pending_validation`, `payment_failed`, `confirmed`, `checked_in`, `completed`, `cancelled`, `all`; anything else falls back to `active`)
- **Preconditions / setup:** Customer with reservations in at least four distinct statuses.
- **Test data:** URLs `/dashboard?status=completed`, `?status=all`, `?status=bogus`, `?status[]=x`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Choose "All reservations" in the dropdown (auto-submits) | All statuses listed. |
  | 2 | Choose each single status | Only reservations in that status. Option labels are the admin status names (`admin.status_*`). |
  | 3 | Open `/dashboard?status=bogus` | Falls back to "Active reservations"; no error. |
  | 4 | Open `/dashboard?status[]=x` | No server error (record result). |
  | 5 | Open `/dashboard?status=cancelled` | Cancelled reservations shown (no cancel/pay buttons). |
- **Acceptance criteria:** Filter results match the status; invalid values degrade gracefully.
- **Evidence to capture:** Screenshots per filter.
- **Notes / assumptions / open questions:** Step 4 is an unusual input; the code calls `$request->string('status')`.

## UAT-ACCT-003: Reservations linked by account id or by matching email (case-insensitive)
- **Feature / journey:** Guest bookings appear after sign-up
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** `AuthController::dashboard` (`user_id = id OR LOWER(email) = lower(user.email)`), `reservationDetail`/`updateReservation` use the same ownership rule
- **Preconditions / setup:** Staff creates reservation `R-EMAIL` with `email` `UAT-Acct@Example.test` and no linked user. Customer `uat-acct@example.test` exists.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as `uat-acct@example.test`; open `/dashboard?status=all` | `R-EMAIL` is listed. |
  | 2 | Open its detail page | Page loads (ownership via email match). |
  | 3 | Log in as `uat-other@example.test` and open `/dashboard/reservations/{R-EMAIL id}` | HTTP 403 "You do not have access to this reservation." |
  | 4 | Compare with the mobile API list `GET /api/v1/customer/reservations` | API list is by `user_id` only; `R-EMAIL` is NOT returned (UAT-API-008). |
- **Acceptance criteria:** Web access follows the documented rule; unrelated users are denied.
- **Evidence to capture:** Screens, 403 page.
- **Notes / assumptions / open questions:** OPEN QUESTION: web matches by email as well as `user_id`, the mobile API only by `user_id`. Product to confirm this difference is intended. Email-based ownership means anyone who registers an email that appears on a past booking sees that booking (no verification gate).

## UAT-ACCT-004: Reservation detail page content and status-dependent actions
- **Feature / journey:** `/dashboard/reservations/{reservation}`
- **Priority:** P0
- **Role / permissions:** Customer (owner)
- **References:** `AuthController::reservationDetail`, view `dashboard-reservation.blade.php` (pay/cancel for `pending`, `pending_payment`, `payment_failed`; modify form unless `cancelled`/`completed`; receipts list; payment proofs; message form for role customer), `CustomerDashboardTest::test_customer_dashboard_lists_and_shows_their_reservations`
- **Preconditions / setup:** Reservations in statuses `pending_payment`, `confirmed` (with a receipt issued by staff via offline payment confirmation), `completed`, `cancelled`.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the `pending_payment` reservation | Shows reference, dates, travelers summary, amounts/price lines, "Pay now" (link to `/reservations/{id}/checkout?token=...`), "Cancel", modification form, message form to the establishment. |
  | 2 | Open the `confirmed` reservation | No pay/cancel buttons; modification form still present; receipt download button "Receipt" when a receipt exists. |
  | 3 | Open the `completed` reservation | No pay/cancel, no modification form. |
  | 4 | Open the `cancelled` reservation | No pay/cancel, no modification form; cancellation fee price line visible if one was charged. |
  | 5 | Open `/dashboard/reservations/999999999` | HTTP 404. |
  | 6 | Open a reservation id of another customer | HTTP 403 "You do not have access to this reservation." |
- **Acceptance criteria:** Actions offered match status; non-owners are denied; unknown ids give 404.
- **Evidence to capture:** Screenshot per status.
- **Notes / assumptions / open questions:** Payment flows themselves are covered elsewhere.

## UAT-ACCT-005: Modify dates and guests, price unchanged vs price changed
- **Feature / journey:** Self-service reservation modification (success paths)
- **Priority:** P0
- **Role / permissions:** Customer (owner)
- **References:** `PUT /dashboard/reservations/{reservation}` (`dashboard.reservations.update`), `AuthController::updateReservation`, lang `reservation.modified` "Your reservation has been updated.", `reservation.modification_payment_required` "Your reservation has been updated. The new amount must be confirmed from the checkout page.", `CustomerDashboardTest::test_customer_can_modify_reservation_dates_and_guests`
- **Preconditions / setup:** Confirmed reservation on property P1 with total T; future dates; free nights available around it.
- **Test data:** adults 2, children 0, infants 0.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the modification form without changing any value | Redirect back to the detail page with "Your reservation has been updated."; status unchanged; total unchanged. |
  | 2 | Move dates by one night later (available) | Redirect with "Your reservation has been updated. The new amount must be confirmed from the checkout page." when the total changed; status becomes `pending_payment`; totals and price lines recalculated; notes gain "Reservation modified by customer." |
  | 3 | Tick/untick an optional feature (`selected_features[]`) and save | Price lines include/exclude "Option: <name>"; total recalculated. |
  | 4 | Change adults/children/infants within limits | Saved; summary line updated. |
  | 5 | Open the reservation as staff | New dates/status visible; modification note present. |
- **Acceptance criteria:** Valid modifications are saved; a changed total moves the reservation to `pending_payment` with the payment-required message.
- **Evidence to capture:** Before/after screenshots; staff view.
- **Notes / assumptions / open questions:** A confirmed reservation moves back to `pending_payment` when the amount changes; any previous payment treatment is outside this suite. Record how the confirmed-then-modified case is handled in checkout.

## UAT-ACCT-006: Modification field validation and boundaries
- **Feature / journey:** Modify reservation form validation
- **Priority:** P0
- **Role / permissions:** Customer (owner)
- **References:** `updateReservation` rules: `check_in` required date after_or_equal today; `check_out` required date after check_in; `adults` int 1..8; `children` nullable int 0..8; `infants` nullable int 0..4; `selected_features.*` must be active features of this property
- **Preconditions / setup:** A modifiable reservation. Use dev tools to remove HTML `min`/`max` limits.
- **Test data:** Values below.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | check_in = yesterday | Validation error on `check_in`; reservation unchanged. |
  | 2 | check_in = today (with availability) | Accepted (boundary). |
  | 3 | check_out = check_in | Error on `check_out`. |
  | 4 | adults = 0 | Error. adults = 9 | Error. adults = 8 (if capacity allows) | Passes the field rule. |
  | 5 | children = -1, children = 9, infants = 5 | Error each. infants = 4 | Passes the field rule. |
  | 6 | adults = `abc` | Integer error. |
  | 7 | `selected_features[]` = id of a feature belonging to another property or an inactive one | Error on `selected_features.0`. |
  | 8 | Empty dates | Required errors. |
  | 9 | Submit errors in French UI | Messages in French. |
- **Acceptance criteria:** Invalid values are rejected before any change; boundaries behave as listed.
- **Evidence to capture:** Screenshots per error; DB check of unchanged reservation.
- **Notes / assumptions / open questions:** Form values are re-filled using `old()`; verify that the entered values survive the error redirect.

## UAT-ACCT-007: Modification business rules (minimum stay, capacity, availability, status)
- **Feature / journey:** Modify reservation business-rule failures
- **Priority:** P0
- **Role / permissions:** Customer (owner)
- **References:** `updateReservation` aborts with HTTP 422: `reservation.modification_minimum_stay` "The minimum stay requirement is not met.", `reservation.modification_capacity` "The number of guests exceeds the property capacity.", `reservation.modification_unavailable_dates` "These new dates are not available.", `reservation.modification_unavailable` "This reservation can no longer be modified." (status cancelled/completed)
- **Preconditions / setup:** Property with `minimum_stay` >= 2, `max_guests` known (say N), another reservation (or an availability block) on target dates, and a cancelled reservation of the same customer.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Choose a stay shorter than `minimum_stay` | HTTP 422 page with "The minimum stay requirement is not met."; reservation unchanged. |
  | 2 | Set adults + children = N + 1 | HTTP 422 "The number of guests exceeds the property capacity." (infants not counted). |
  | 3 | Choose dates overlapping another reservation or a block | HTTP 422 "These new dates are not available." |
  | 4 | Choose dates overlapping the reservation's own current dates | Accepted (own reservation excluded from the check). |
  | 5 | Submit a PUT for a `cancelled` reservation (replay with tool; form is hidden) | HTTP 422 "This reservation can no longer be modified." Same for `completed`. |
  | 6 | Switch to French and repeat step 1 | Message in French. |
- **Acceptance criteria:** Rules are enforced server-side with the quoted messages; no partial update occurs.
- **Evidence to capture:** Screenshots of 422 pages, DB state.
- **Notes / assumptions / open questions:** Failures are raised with `abort_unless(..., 422)` so the user sees an error page, not an inline form error. Confirm this is the intended UX. External calendar feeds (iCal events) and availability blocks are included in `AvailabilityService`; not reviewed here.

## UAT-ACCT-008: Another customer's reservation cannot be modified or viewed
- **Feature / journey:** Ownership enforcement on reservation routes
- **Priority:** P0
- **Role / permissions:** Customer (non-owner)
- **References:** `reservationDetail` / `updateReservation` ownership rule; `ReceiptController::authorizeReservation`; `errors.reservation_access`
- **Preconditions / setup:** Reservation `R-OWN` belongs to `uat-acct@example.test`. Logged in as `uat-other@example.test`.
- **Test data:** `R-OWN` id.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/dashboard/reservations/{R-OWN}` | HTTP 403 "You do not have access to this reservation." |
  | 2 | PUT `/dashboard/reservations/{R-OWN}` with valid data (replay with tool) | HTTP 403; reservation unchanged. |
  | 3 | GET `/reservations/{R-OWN}/receipt` | HTTP 403. |
  | 4 | POST `/reservations/{R-OWN}/checkout/cancel` without a token | HTTP 403 "Checkout access denied."; status unchanged. |
  | 5 | Same POST with `?token=<wrong>` | HTTP 403. |
  | 6 | Dashboard list of the other customer | `R-OWN` not listed. |
- **Acceptance criteria:** No read or write on another customer's reservation.
- **Evidence to capture:** Responses and DB state.
- **Notes / assumptions / open questions:** The reservation checkout token is a bearer secret in the URL: anyone holding the link `/reservations/{id}/checkout?token=...` can view/cancel without logging in (by design for guest checkout); not a defect for this case.

## UAT-ACCT-009: Cancel an unpaid reservation from the dashboard
- **Feature / journey:** Customer cancellation
- **Priority:** P0
- **Role / permissions:** Customer (owner)
- **References:** Buttons in `dashboard.blade.php` and `dashboard-reservation.blade.php` posting to `POST /reservations/{reservation}/checkout/cancel` (`checkout.cancel`); `CheckoutController::cancel`; lang `checkout.cancel_confirmation` "Are you sure you want to cancel this reservation?", `checkout.cancelled` "Your reservation has been cancelled.", `checkout.cancelled_with_fee`, `checkout.cancellation_fee_warning`, `checkout.cancellation_fee`
- **Preconditions / setup:** Reservations in `pending`, `pending_payment`, `payment_failed`. One establishment with a cancellation fee policy (fee > 0) and one without.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open a reservation of an establishment without fee, click "Cancel" | Browser confirmation "Are you sure you want to cancel this reservation?". Declining does nothing. |
  | 2 | Accept | Redirect to the checkout page of the reservation with "Your reservation has been cancelled."; status `cancelled`; a cancellation email is queued. |
  | 3 | Open a reservation with a fee policy | A warning "Warning: cancelling now incurs a non-refundable fee of :amount :currency." is shown before cancelling. |
  | 4 | Accept the cancellation | Message "Your reservation has been cancelled. A non-refundable cancellation fee of ... applies."; price line "Cancellation fee (non-refundable)" added. |
  | 5 | Reload `/dashboard` (default filter) | Cancelled reservation no longer in the active list; visible with filter `cancelled`. |
  | 6 | Staff opens the reservation | Status cancelled; email in outbox. |
- **Acceptance criteria:** Cancellation works for the three unpaid statuses with correct fee messaging.
- **Evidence to capture:** Screens; outbox row; price line.
- **Notes / assumptions / open questions:** The cancel button posts the checkout token from the page, so ownership is proved by session or token. Refund handling for already-paid reservations is not covered by this route.

## UAT-ACCT-010: Cancellation is refused for confirmed, completed or already cancelled reservations
- **Feature / journey:** Cancellation state guard
- **Priority:** P1
- **Role / permissions:** Customer (owner)
- **References:** `CheckoutController::cancel` (`abort_unless(status in pending, pending_payment, payment_failed, 422, checkout.cancellation_unavailable)`)
- **Preconditions / setup:** Reservations in `confirmed`, `completed`, `cancelled`.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open each reservation in the dashboard | No "Cancel" button for these statuses. |
  | 2 | POST `/reservations/{id}/checkout/cancel` for each (replay with tool, valid CSRF) | HTTP 422 "This reservation can no longer be cancelled." (`checkout.cancellation_unavailable`). Status unchanged; no second fee line. |
  | 3 | Double-submit cancel on a pending reservation quickly (two tabs) | Second request returns 422; only one fee line exists. |
- **Acceptance criteria:** Only unpaid statuses can be cancelled by the customer; no duplicate fee.
- **Evidence to capture:** Responses, price lines.
- **Notes / assumptions / open questions:** There is no customer-facing cancellation route for confirmed (paid) reservations in the web routes; confirm how such cancellations are expected to be handled (contact via messages / staff).

## UAT-ACCT-011: Update profile name
- **Feature / journey:** `/account` profile
- **Priority:** P1
- **Role / permissions:** Customer (also staff roles)
- **References:** `GET/POST /account` (`account.profile`, `account.profile.update`), `AuthController::updateProfile` (rule `name` required string max 255), lang `profile.updated` "Your profile has been updated.", `CustomerDashboardTest::test_customer_can_update_profile_name_and_open_preferences_page`
- **Preconditions / setup:** Logged in as `uat-acct@example.test`.
- **Test data:** new name `Amina UAT Renamed`; 256-character name; empty name; name with `<script>alert(1)</script>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/account` | Shows full name field (editable) and read-only account info (email, role, status; email not editable). |
  | 2 | Save `Amina UAT Renamed` | Redirect `/account` with "Your profile has been updated."; dashboard greeting shows the new name. |
  | 3 | Save an empty name | Required error; name unchanged. |
  | 4 | Save a 256-character name | Max-255 error; 255 characters accepted. |
  | 5 | Save the script-tag name | Stored as text and displayed escaped (no alert) on dashboard and in the admin user list. |
  | 6 | Tamper POST with extra `email`, `role=admin` fields | Ignored; email and role unchanged. |
- **Acceptance criteria:** Only the name is editable; output is escaped.
- **Evidence to capture:** Screenshots; DB values.
- **Notes / assumptions / open questions:** There is no customer way to change email in the web UI (open question for the product).

## UAT-ACCT-012: Change password from account security
- **Feature / journey:** `/account/security`
- **Priority:** P0
- **Role / permissions:** Customer
- **References:** `GET/POST /account/security` (`account.security`, `account.security.update`), rules `current_password` required + `current_password`, `password` min 8 confirmed; lang `security.updated` "Your password has been updated."
- **Preconditions / setup:** `uat-acct@example.test` logged in.
- **Test data:** Current `<tester-chosen password, 8+ chars>`, new `<tester-chosen new password, 8+ chars>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with a wrong current password | Error on `current_password`; no change. |
  | 2 | Correct current, new password of 7 chars | Min-8 error. |
  | 3 | Correct current, mismatched confirmation | Confirmation error. |
  | 4 | Correct current, new password of exactly 8 chars, confirmed | Redirect `/account/security` with "Your password has been updated."; user remains signed in. |
  | 5 | Log out; log in with the old password | Fails. With the new password: succeeds. |
  | 6 | Submit with all fields empty | Required errors. |
  | 7 | Check other browser session of same user | Record whether it remains valid after the change (no "log out other devices" in code). |
- **Acceptance criteria:** Password changes only with the correct current password; rules enforced.
- **Evidence to capture:** Screenshots; login results.
- **Notes / assumptions / open questions:** Existing mobile API tokens are not revoked on password change in code; record in UAT-API-005.

## UAT-ACCT-013: Favorites from the web (add, remove, restrictions)
- **Feature / journey:** Favorite apartments
- **Priority:** P1
- **Role / permissions:** Customer; guest
- **References:** `POST /properties/{property}/favorite` (`properties.favorite.toggle`), `FavoriteController::toggle` (404 if property not `published`), lang `favorites.added` "Apartment added to favorites.", `favorites.removed` "Apartment removed from favorites.", `favorites.login` "Sign in to save this apartment", dashboard "Favorite apartments"
- **Preconditions / setup:** Published property P1 and a draft/unpublished property P2.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As guest, open `/properties` and find the favorite control on the P1 card (also check the property page) | Shows "Sign in to save this apartment" (link to login) on the list card instead of a toggle; record what the property page shows. |
  | 2 | As customer click "Add to favorites" on P1 | Redirect back with "Apartment added to favorites."; button now "Remove from favorites". |
  | 3 | Open `/dashboard` | P1 listed under favorites with "View" link. |
  | 4 | Click remove | "Apartment removed from favorites."; P1 gone from the dashboard. |
  | 5 | Replay POST toggle for P2 (unpublished) | HTTP 404. |
  | 6 | Admin unpublishes a favorited property | Property no longer shown in the customer's favorites list (only published ones listed). |
  | 7 | Property list "Show favorites only" control | Shows only favorited properties for the user. |
- **Acceptance criteria:** Toggle works, is per user, and unpublished properties are excluded.
- **Evidence to capture:** Screenshots of control states and dashboard list.
- **Notes / assumptions / open questions:** Property list favorites filter is part of the catalog feature; check lightly.

## UAT-ACCT-014: Communication preferences and language
- **Feature / journey:** `/account/preferences`
- **Priority:** P0
- **Role / permissions:** Customer
- **References:** `GET /account/preferences`, `POST /dashboard/preferences` (`dashboard.preferences.update`), `AuthController::updatePreferences` (`locale` required in fr,en; four email checkboxes), lang `profile.saved` "Your preferences have been saved."
- **Preconditions / setup:** Logged in customer.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/account/preferences` | Language select with fr/en; checkboxes: Reservation updates, New message notifications, Marketing offers, Newsletter. |
  | 2 | Choose English, tick only "Reservation updates", save | Redirect `/dashboard` with "Your preferences have been saved." UI in English. Other boxes saved as off. |
  | 3 | Choose French, tick all, save | UI in French; all four saved on. |
  | 4 | Untick all, save | All four off (unchecked boxes saved as false). |
  | 5 | Tamper `locale=de` or empty | Validation error; stored locale unchanged. |
  | 6 | Log out and in again | Saved locale applies immediately and overrides cookie/session language. |
  | 7 | With "New message notifications" off, staff replies in a thread | No `message_received` email queued for this customer (UAT-MSG-007). |
- **Acceptance criteria:** Preferences persist and drive UI language and email opt-ins.
- **Evidence to capture:** Screens, user row, outbox.
- **Notes / assumptions / open questions:** None.

## UAT-ACCT-015: Signed-in users keep their saved language (no switcher, link and cookie ignored)
- **Feature / journey:** Locale precedence for authenticated users
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** `SetLocale` (user locale first), `AuthController::switchLanguage` (for signed-in users returns to previous page with the saved locale), test `test_authenticated_user_uses_saved_locale_and_sees_no_language_switcher`
- **Preconditions / setup:** Customer with saved locale `en`.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in and open `/` | English UI; header has no language switch link. |
  | 2 | Open `/language/fr` | Redirected back; UI remains English. |
  | 3 | Open `/dashboard?lang=fr` | UI remains English. |
  | 4 | Set a `locale=fr` cookie manually and reload | UI remains English. |
  | 5 | Change saved language to French in preferences | Switches to French on all pages. |
- **Acceptance criteria:** Saved account locale always wins.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** Related guest behaviour: UAT-AUTH-014.

## UAT-ACCT-016: Download receipt as owner, staff and non-owner
- **Feature / journey:** Receipt PDF access
- **Priority:** P1
- **Role / permissions:** Customer owner; other customer; staff
- **References:** `GET /reservations/{reservation}/receipt` (`reservations.receipt`), `ReceiptController::download` (404 if no receipt), authorization: owner (by `user_id` or email), staff via `canManageReservations()`, otherwise 403; dashboard label "Receipt"
- **Preconditions / setup:** Reservation with an issued receipt (staff confirms offline payment or a payment is verified); another without receipt.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Owner: dashboard row with receipt shows "Receipt" link; click | PDF downloads, file name = receipt number + `.pdf`; content shows reservation data. |
  | 2 | Owner opens `/reservations/{id-without-receipt}/receipt` | HTTP 404. |
  | 3 | Other customer opens the receipt URL | HTTP 403. |
  | 4 | Guest opens the receipt URL | Redirect to `/login`. |
  | 5 | Staff of the same tenant opens it | Download works. Staff of another tenant: HTTP 404 (reservation binding). |
- **Acceptance criteria:** Receipts are only available to owner and authorized staff.
- **Evidence to capture:** Downloaded PDF; statuses.
- **Notes / assumptions / open questions:** Payment-proof download (`/reservations/{reservation}/payment-proofs/{attempt}`) is owned by the payments suite.
