# Public UAT 02: Property detail, availability, pricing and reservation request

Scope: `GET /properties/{slug}`, `POST /properties/{slug}/availability`, `POST /properties/{slug}/reserve`, `GET /reservation/resume`. All cases are **Not Run**.

**Shared synthetic fixtures:**
- Property P-A (slug `uat-apt-a`, establishment E1, XOF 40 000/night, max_guests 4, `minimum_stay` 2, published, active, with images, amenities, two active extras F1 "Airport pickup" and F2 "Breakfast", and one inactive extra F3). P-D (draft, slug `uat-draft`). P-I (published but `is_active` = false). P-J (published, establishment inactive).
- E1 pricing settings: record `service_fee_percent`, VAT percent/mode, city tax type/amount (PricingCalculator defaults when null: service fee 10 %, city tax 5 % `percent`).
- New guest email `guest.new1@example.test`; existing verified customer `customer.uat1@example.test`; existing unverified account `guest.unverified1@example.test` (email_verified_at null).
- Mail is captured (mail log or catcher); queue worker running or `QUEUE_CONNECTION=sync` per the UAT environment.
- reCAPTCHA disabled unless a case states otherwise (`RECAPTCHA_SITE_KEY`/`RECAPTCHA_SECRET_KEY` unset).

---

## UAT-PUB-020: Property detail page renders for a published property
- **Feature / journey:** Property details
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** `GET /properties/{property:slug}` (route `properties.show`), `PublicPropertyController@show`, resources/views/properties/show.blade.php, tests `test_property_detail_displays_reviews_map_and_social_preview_data`, `test_property_detail_renders_translated_labels_for_english_guests`
- **Preconditions / setup:** P-A with 8 active reviews, images, amenities, coordinates on E1.
- **Test data:** Slug `uat-apt-a`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties/uat-apt-a`. | HTTP 200. Name, summary, city, price per night, guest summary ("4 guests · 2 bedroom(s) · ..."), characteristics, amenities and included services shown in the active locale. |
  | 2 | Use the gallery next/previous controls. | Photos change in `sort_order` order; controls are labelled "Previous photo" / "Next photo". |
  | 3 | Scroll to reviews. | At most 6 reviews, newest first; average rating and total count reflect all active reviews (8), not only the 6 shown. Link to `/reviews?property=uat-apt-a`. |
  | 4 | Check the location section. | Address/"Location provided on request" depending on data; "Open in Google Maps" link; map iframe titled "Location". |
  | 5 | Check the share menu. | WhatsApp, Facebook, X, LinkedIn, Instagram, TikTok, Copy link; copy shows "Link copied! Paste it into your story or post." |
  | 6 | View page source. | `<title>`, meta description, canonical, `og:*`, `twitter:*` tags carry property data. |
  | 7 | Note the booking panel when logged out. | Fields: `full_name`, `email`, dates picker, `adults`, `country` (default "Bénin"), `children`, `infants`, `phone`, `customer_note`, extras checkboxes, "Check availability" and "Request reservation" buttons. No concierge message form is shown (it only shows for role customer). |
- **Acceptance criteria:** All public content present and localized; no raw translation keys; draft placeholder copy absent.
- **Evidence to capture:** Screenshots desktop and mobile, page source excerpt.
- **Notes / assumptions / open questions:** `properties.beds` is defined twice in lang files (last definition wins): "Beds or sofa beds". Informational only.

---

## UAT-PUB-021: Unpublished, inactive and inactive-establishment properties return 404
- **Feature / journey:** Visibility rules
- **Priority:** P0
- **Role / permissions:** Anonymous guest and customer
- **References:** `PublicPropertyController@show`, `@availability` (`abort_unless(status published && is_active && establishment active)`), test `test_unpublished_properties_are_not_customer_visible`
- **Preconditions / setup:** P-D, P-I, P-J as defined; P-A as control.
- **Test data:** Slugs `uat-draft`, `uat-inactive`, `uat-estab-inactive`; unknown slug `no-such-slug`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open each of the three slugs. | HTTP 404 with the localized 404 page ("Page not found" / "Page introuvable") and "Back to home" link. |
  | 2 | Open `/properties/no-such-slug`. | HTTP 404 (same page). |
  | 3 | POST `/properties/uat-draft/availability` with valid JSON body (dates D+5, D+7) and CSRF token. | HTTP 404. |
  | 4 | POST `/properties/uat-draft/reserve` with a valid body. | HTTP 404; no reservation, user or guest record created. |
  | 5 | Open `/properties` and `/sitemap.xml`. | None of the three slugs listed. |
  | 6 | Open P-A (control). | HTTP 200. |
- **Acceptance criteria:** Hidden properties are not reachable by direct URL, availability or reservation endpoints.
- **Evidence to capture:** Status codes, screenshot of the 404 page.
- **Notes / assumptions / open questions:** Properties are looked up by `slug`; confirm slug uniqueness across tenants (not verified in this area).

---

## UAT-PUB-022: Detail page query prefill and its validation
- **Feature / journey:** Deep link from search to booking panel
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `PublicPropertyController@show` validation (`check_in` after_or_equal:today, `check_out` after:check_in, `guests` 1..100), resources/views/properties/show.blade.php (`data-date-range-start/end`, `adults`)
- **Preconditions / setup:** P-A.
- **Test data:** check_in D+10, check_out D+13, guests 3; invalid: check_in D-2; guests 0; guests 101.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties/uat-apt-a?check_in=<D+10>&check_out=<D+13>&guests=3`. | Hidden inputs `check_in`, `check_out` are prefilled; `adults` = 3. |
  | 2 | Open with `check_in=<D-2>`. | Validation error (redirect back / error); no 500. |
  | 3 | Open with `guests=0` and `guests=101`. | Validation error for each. |
  | 4 | Open with `check_out` only. | Page renders; `check_out` prefilled, no `check_in`. Record behavior. |
- **Acceptance criteria:** Valid values prefill; invalid values rejected with validation, not a server error.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** HTML `max` on `adults` equals `max_guests` of the property (4); with `guests=6` for a 4-guest property the field may start above its own max. Record.

---

## UAT-PUB-023: Check availability for free dates shows estimated total
- **Feature / journey:** Availability and price estimate (AJAX)
- **Priority:** P0
- **Role / permissions:** Anonymous guest (no name/email needed)
- **References:** `POST /properties/{slug}/availability` (route `properties.availability`), `PublicPropertyController@availability`, `AvailabilityService`, `PricingCalculator`, test `test_guest_can_check_availability_without_name_or_email`, resources/lang `properties.availability_available_amount`
- **Preconditions / setup:** P-A free for D+30 to D+33 (3 nights). E1 pricing recorded.
- **Test data:** check_in D+30, check_out D+33, adults 2, children 0.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Select the dates in the date picker, adults 2, click "Check availability". | Request `POST /properties/uat-apt-a/availability` returns JSON `available: true`, `estimated_total`, `currency: "XOF"`, `message`. |
  | 2 | Read the result line (`role="status"`). | EN: "These dates are available. Estimated total: <amount> XOF." (value shown with space thousands separator, computed from E1 settings). FR: localized equivalent. |
  | 3 | Hand-calculate the total: nights x nightly rate = 120 000 subtotal, plus service fee percent, VAT, city tax per E1 settings. | The estimate equals the hand calculation (rounded to 2 decimals in code, displayed with 0 decimals). |
  | 4 | Change adults/children and re-check. | Total only changes if E1 city tax type is `per_guest_night`; otherwise unchanged. |
  | 5 | Inspect the response for personal data. | No customer data is returned. |
- **Acceptance criteria:** Availability and estimate match PricingCalculator rules; no account required.
- **Evidence to capture:** Screenshot of the result line, response JSON from dev tools, calculation sheet.
- **Notes / assumptions / open questions:** The estimate mixes `service_fee`, VAT mode (included vs excluded) and city tax; exact expected values must be derived from the E1 fixture. Record the fixture values in the evidence.

---

## UAT-PUB-024: Availability reports unavailable for reserved, blocked and external-calendar dates
- **Feature / journey:** Availability conflicts
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** `AvailabilityService::isAvailable`, `hasExternalCalendarConflict`, tests `test_availability_check_rejects_enabled_external_calendar_conflicts`
- **Preconditions / setup:** P-A has (a) a `confirmed` reservation D+40 to D+43, (b) an admin block D+50 to D+53, (c) an enabled external feed event D+60 to D+63, (d) a disabled feed event D+70 to D+73, (e) a `cancelled` reservation D+80 to D+83.
- **Test data:** The date ranges above plus D+43 to D+45 (turnover day).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Check D+40 to D+43. | `available: false`; message "These dates are not available." (JSON from this endpoint contains `available` only, and the page script displays the `data-availability-unavailable` text). |
  | 2 | Check D+41 to D+42 (inside the booked range). | Not available. |
  | 3 | Check D+50 to D+53. | Not available (admin block). |
  | 4 | Check D+60 to D+63. | Not available (enabled external feed). |
  | 5 | Check D+70 to D+73. | Available (feed disabled). |
  | 6 | Check D+80 to D+83. | Available (cancelled reservation does not block). |
  | 7 | Check D+43 to D+45. | Available (check-out day = next check-in day). |
  | 8 | Open the "Show availability" calendar. | Booked, blocked ranges are visibly marked; external ranges are marked per `data-external`. |
- **Acceptance criteria:** Result matches the data in each case.
- **Evidence to capture:** Screenshots and JSON responses.
- **Notes / assumptions / open questions:** In the view, `data-external="[]"` is initialised empty and filled by script; the test `test_admin_can_sync_...` asserts `data-external` content on admin pages only. Verify that the public calendar also hides external events (privacy: should only show "blocked", not event titles). Statuses `pending`, `pending_payment`, `pending_validation`, `payment_failed` count as blocking (anything not `cancelled`).

---

## UAT-PUB-025: Minimum stay enforced on availability check
- **Feature / journey:** Minimum stay rule
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `PublicPropertyController@availability` (returns `reason: minimum_stay`), `properties.minimum_stay_error`
- **Preconditions / setup:** P-A `minimum_stay` = 2.
- **Test data:** D+30 to D+31 (1 night); D+30 to D+32 (2 nights).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Check 1 night. | JSON `available: false, reason: "minimum_stay", minimum_stay: 2`. UI shows "This property requires a minimum stay of 2 night(s)." (FR: "Ce logement nécessite un séjour minimum de 2 nuit(s)."). |
  | 2 | Check 2 nights. | Available with estimate. |
  | 3 | Set P-A `minimum_stay` = 0 (admin) and check 1 night. | Treated as minimum 1: available. |
- **Acceptance criteria:** Minimum stay applied at the boundary; message localized.
- **Evidence to capture:** Screenshot and JSON.
- **Notes / assumptions / open questions:** Step 3 requires admin data change; can be skipped if not permitted in UAT.

---

## UAT-PUB-026: Availability endpoint input validation
- **Feature / journey:** Availability validation
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `PublicPropertyController@availability` rules
- **Preconditions / setup:** P-A, extra IDs: F1 (belongs to P-A, active), F3 (inactive), F9 (belongs to another property).
- **Test data:** See steps. Send as AJAX (`Accept: application/json`) to see 422 JSON.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST with no dates. | 422, errors on `check_in` and `check_out` (required). |
  | 2 | POST `check_in` = D-1. | 422 on `check_in` (after_or_equal:today). |
  | 3 | POST `check_in` = D+5, `check_out` = D+5, then D+4. | 422 on `check_out` (after). |
  | 4 | POST `adults` = 0, then 9. | 422 on `adults` (1..8). |
  | 5 | POST `children` = -1, then 9. | 422 on `children` (0..8). |
  | 6 | POST `selected_features[]` = F3, then F9, then "abc". | 422 on `selected_features.0`. |
  | 7 | POST without CSRF token (browser fetch, no `X-CSRF-TOKEN`). | HTTP 419, and the localized "Session expired" page for HTML requests. |
  | 8 | POST with `adults` omitted. | Accepted; 1 adult assumed. |
- **Acceptance criteria:** Each invalid input is rejected with the right field; defaults apply for optional values.
- **Evidence to capture:** Responses (status and JSON).
- **Notes / assumptions / open questions:** The availability endpoint caps `adults` at 8 and `children` at 8 regardless of the property's `max_guests` (capacity is not validated server-side in this endpoint or in `reserve`; see PUB-037).

---

## UAT-PUB-027: Optional extras change the price and appear as price lines
- **Feature / journey:** Extras (property features)
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `selected_features[]` in `availability` and `reserve`, `PricingCalculator::calculate` (`cost_xof` extras), `messages.pricing.extra_option`
- **Preconditions / setup:** F1 cost 10 000 XOF, F2 cost 5 000 XOF (record values).
- **Test data:** D+30 to D+33.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Check availability without extras and note the total. | Baseline total T0. |
  | 2 | Tick F1 and re-check. | Total increases by F1 cost plus the percentage fees/taxes applied to the extras subtotal (service fee and city tax percent apply to the subtotal that includes extras). |
  | 3 | Tick F1 and F2. | Total reflects both. |
  | 4 | Confirm only active extras of this property are listed. | F3 and F9 not shown. |
  | 5 | Submit the reservation (see PUB-031 for a verified customer) with F1 ticked. | On the checkout page the price lines list "Option: Airport pickup" with its amount; reservation `notes` text includes "Extras: Airport pickup" (visible to staff). |
- **Acceptance criteria:** Estimate (availability) equals the total of the created reservation.
- **Evidence to capture:** Screenshot of estimate and checkout price lines.
- **Notes / assumptions / open questions:** Whether extras are taxed by VAT/service fee is determined by PricingCalculator; compare numbers rather than assume.

---

## UAT-PUB-028: New guest reservation requires email confirmation before the reservation is created
- **Feature / journey:** Guest booking with account creation
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** `POST /properties/{slug}/reserve` (route `properties.reserve`), `queueReservationAccountSetup`, `GuestAccountSetupNotification`, `GET /reservation/resume` (route `reservation.resume`), test `test_new_guest_email_must_be_confirmed_before_reservation_is_created`, resources/lang `auth.confirm_email_before_reservation`
- **Preconditions / setup:** No user or guest with `guest.new1@example.test`. P-A free D+30 to D+33.
- **Test data:** full_name "Test Guest One", email `guest.new1@example.test`, phone "+22900000000", country "Bénin", adults 2, children 0, infants 0, customer_note "Late arrival".
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Fill the booking form with the data above, dates D+30 to D+33, click "Request reservation". | Redirect back to the property page (inputs preserved) with the status message "An account already exists with this email but was never confirmed. We just sent you a new email: confirm your address and set your password before completing this reservation." (this is the code's message for the new-guest case as well, see Notes). |
  | 2 | Check the database/admin. | A user with role `customer`, tenant of E1, locale equal to the active locale exists and is unverified. **No reservation** and no `reservation_guests` row exists yet. |
  | 3 | Open the mailbox. | A "guest account setup" notification with a password-setup link. |
  | 4 | Follow the link, set a valid password. | Password saved, email confirmed (see auth area). |
  | 5 | Log in and open `/reservation/resume`. | The pending reservation (kept in the session) is replayed: a reservation `AFK-XXXXXX-yymmdd` with status `pending` is created and the browser goes to `/reservations/{id}/checkout?token=...` with the message "Your reservation request has been received." |
  | 6 | Repeat step 5 in a different browser/session (no session data). | HTTP 404 (no pending reservation in session). |
- **Acceptance criteria:** No reservation is created until the email is confirmed; after confirmation the reservation is created with the original data.
- **Evidence to capture:** Screenshots, captured email, DB rows before/after, final checkout URL.
- **Notes / assumptions / open questions:** The message is the same text for new and unverified existing accounts. For a brand new email the wording "already exists... never confirmed" is inaccurate for the user (possible wording defect). The `pending_public_reservation` session data lives in the browser session: if the guest opens the email link on another device the resume path returns 404 (open question on intended UX). Whether the password-setup link redirects to `/reservation/resume` is determined in the auth flow (not traced here).

---

## UAT-PUB-029: Existing verified customer must sign in, then resumes the reservation
- **Feature / journey:** Guest booking with existing account
- **Priority:** P0
- **Role / permissions:** Anonymous guest using the email of a verified customer
- **References:** `PublicPropertyController@reserve`/`resume`, test `test_existing_customer_email_requires_login_before_reservation_checkout`, `auth.existing_account_login`
- **Preconditions / setup:** `customer.uat1@example.test` exists, verified, password known to the tester.
- **Test data:** full_name "Test Customer One", that email, dates D+30 to D+33, adults 2.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the booking form logged out. | Redirect to `/login` with email prefilled and the message "An account already exists with this email. Sign in to continue your reservation." No reservation yet. |
  | 2 | Log in with the correct credentials. | Per the auth flow the user lands on dashboard or resume (record which). |
  | 3 | Open `/reservation/resume` if not redirected there automatically. | Reservation created with `user_id` = the customer, status `pending`; redirect to checkout with the token; the status line "Your reservation request has been received." |
  | 4 | Open `/reservation/resume` again. | HTTP 404 (session data is pulled once). |
  | 5 | Repeat step 1 but log in with wrong password. | Error "Invalid email or password."; no reservation created. |
- **Acceptance criteria:** No reservation without authentication; the resumed reservation uses the data originally typed.
- **Evidence to capture:** Screenshots, URL sequence, reservation reference.
- **Notes / assumptions / open questions:** Whether `/login` automatically redirects to `/reservation/resume` after sign-in is not visible in the files reviewed for this area; record the observed behavior. Resume requires middleware `auth`, `active`, `locale`.

---

## UAT-PUB-030: Existing account with unverified email gets a confirmation mail
- **Feature / journey:** Unverified account booking
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `PublicPropertyController@reserve` branch `$existingAccount && ! email_verified_at`, test `test_unverified_existing_account_email_receives_confirmation_email_instead_of_login_prompt`
- **Preconditions / setup:** `guest.unverified1@example.test` exists with `email_verified_at` NULL.
- **Test data:** That email, dates D+30 to D+33.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the booking form. | Back to the property page with the "...never been confirmed..." message. No redirect to login. |
  | 2 | Check the mailbox. | One setup email to that address. |
  | 3 | Submit the form twice quickly. | Two emails or one; record (no throttle visible on this route). |
  | 4 | Confirm that no reservation row exists. | No reservation created. |
- **Acceptance criteria:** Unverified accounts are never allowed to book before confirming.
- **Evidence to capture:** Mail screenshot, DB check.
- **Notes / assumptions / open questions:** `/properties/{slug}/reserve` has no throttle middleware (compare feedback 5/min and help chat 10/min). Possible email-spam vector; record as observation.

---

## UAT-PUB-031: Logged-in customer reserves without re-entering name and email
- **Feature / journey:** Authenticated booking to checkout
- **Priority:** P0
- **Role / permissions:** Customer (verified)
- **References:** `PublicPropertyController@reserve` (authenticated branch), `ReservationEmailService::queueForReservation('reservation_received')`, test `test_authenticated_customer_can_reserve_without_reentering_name_or_email`, `GET /reservations/{reservation}/checkout`
- **Preconditions / setup:** Logged in as `customer.uat1@example.test` (verified, last login set). P-A free D+30 to D+33.
- **Test data:** adults 2, children 1, infants 0, customer_note "Quiet room please".
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties/uat-apt-a`. | The booking panel does not show `full_name` and `email` inputs; a concierge message form is shown. |
  | 2 | Select dates, click "Check availability" then "Request reservation". | Redirect to `/reservations/{id}/checkout?token=<checkout_token>` with the flash "Your reservation request has been received." |
  | 3 | Read the checkout summary. | Reference `AFK-XXXXXX-yymmdd`, establishment, property, dates `dd/mm/yyyy → dd/mm/yyyy`, total with currency, status label for `pending`, guest name from the account, price lines. |
  | 4 | Check mailbox. | "Reservation received" email queued/sent to the customer. |
  | 5 | Open `/dashboard`. | The reservation appears under the customer. |
- **Acceptance criteria:** Reservation created with status `pending`, correct dates, guests, total equal to the earlier estimate, `source` = website.
- **Evidence to capture:** Screenshot of checkout, reservation reference, email, DB row (status, total_amount, user_id).
- **Notes / assumptions / open questions:** `reservation_ref` format is `AFK-` + 6 random uppercase + `-` + `ymd`. Infants are stored but not priced.

---

## UAT-PUB-032: Reservation form validation (required fields, formats, limits)
- **Feature / journey:** Reservation request validation
- **Priority:** P1
- **Role / permissions:** Anonymous guest (and customer for step 9)
- **References:** `PublicPropertyController@reserve` rules (`full_name` required max 120, `email` required `email:filter` max 255, `phone` max 40, `country` max 80, `adults` 1..8, `children` 0..8, `infants` 0..4, `customer_note` max 2000, dates), custom messages for `check_in.after_or_equal` and `check_out.after`
- **Preconditions / setup:** P-A free D+30 to D+33.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with empty name and email (browser validation disabled via dev tools). | Server errors on `full_name` and `email` ("required"), form values preserved. |
  | 2 | Email `not-an-email`, then `a@b` , then 256-char email. | Rejected by `email:filter`/max. |
  | 3 | `full_name` of 121 characters. | Rejected (max 120). |
  | 4 | `phone` 41 characters; `country` 81 characters. | Rejected. |
  | 5 | `adults` = 0, 9; `children` = 9; `infants` = 5. | Rejected each. |
  | 6 | `customer_note` of 2001 characters (the textarea has `maxlength=2000`). | Rejected by the server when maxlength is bypassed. |
  | 7 | `check_in` = D-1. | Error shows the custom French message "La date d'arrivée doit être aujourd'hui ou plus tard." even when the site is in English (message is hard-coded in the controller, not translated). |
  | 8 | `check_out` earlier than `check_in`. | Error shows "La date de départ doit être après la date d'arrivée." (hard-coded French). |
  | 9 | Feature id not belonging to P-A. | Rejected (`selected_features.*`). |
  | 10 | Submit without CSRF token. | 419 page. |
  | 11 | Submit with HTML tags in name and note, e.g. `<b>x</b>`. | Stored as text; displayed escaped on checkout (no HTML rendering). |
- **Acceptance criteria:** Invalid input rejected with field errors; no reservation, user or guest created; user text is escaped.
- **Evidence to capture:** Screenshots of the errors; DB confirmation of no records.
- **Notes / assumptions / open questions:** Hard-coded French messages for the two date rules are a localization gap for EN users (see I18N-007). The date inputs are hidden fields filled by the picker, so the error is shown on `check_in` only.

---

## UAT-PUB-033: Reserve rejects unavailable dates, minimum-stay violations and double booking
- **Feature / journey:** Server-side re-check on submit
- **Priority:** P0
- **Role / permissions:** Customer (verified)
- **References:** `PublicPropertyController@reserve` (`minimum_stay_error`, `availability_unavailable` errors on `check_in`), `AvailabilityService`
- **Preconditions / setup:** Two browsers A and B, each logged in as a different verified customer (`customer.uat1@example.test`, `customer.uat2@example.test`). P-A free D+30 to D+33; `minimum_stay` 2.
- **Test data:** D+30 to D+33; D+30 to D+31.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | In A and B open P-A, both pick D+30 to D+33 and both click "Check availability". | Both see available. |
  | 2 | A clicks "Request reservation". | A reservation is created; A is on checkout. |
  | 3 | B clicks "Request reservation". | Back to the page with the error on `check_in`: "These dates are not available." No second reservation. |
  | 4 | In B, force a 1-night booking D+40 to D+41 (bypass UI). | Error "This property requires a minimum stay of 2 night(s)." |
  | 5 | A cancels the reservation from checkout (see PAY-010) and B retries the same dates. | B succeeds (cancelled reservations do not block). |
  | 6 | Admin blocks D+50 to D+52 and B submits it. | Error "These dates are not available." |
- **Acceptance criteria:** The server enforces availability regardless of the page state; no double booking.
- **Evidence to capture:** Screenshots and DB rows.
- **Notes / assumptions / open questions:** There is no database-level lock; a truly simultaneous submit race (both pass the check) is possible. Record as an observation if two reservations can be created by submitting both requests at the same instant.

---

## UAT-PUB-034: reCAPTCHA failure blocks anonymous reservation
- **Feature / journey:** Bot protection on reservation form
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `reserve` (`GoogleRecaptchaVerifier`), resources/views/partials/recaptcha.blade.php, config/services.php (`recaptcha.enabled` when both keys set), `security.captcha_failed`
- **Preconditions / setup:** UAT environment configured with reCAPTCHA test keys; run only if reCAPTCHA is enabled there.
- **Test data:** Valid booking data for a new email.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the booking form normally. | Hidden `g-recaptcha-response` is populated by script before submit; reservation flow proceeds. |
  | 2 | Block `google.com/recaptcha` in the browser (dev tools) and submit. | Submission fails: error on `email`: "The security verification failed. Please try again." (FR: "La vérification de sécurité a échoué. Veuillez réessayer."). No user created. |
  | 3 | As a logged-in customer, submit with the script blocked. | Allowed (captcha is only required for anonymous users). |
- **Acceptance criteria:** Captcha failure stops the flow with the listed message.
- **Evidence to capture:** Screenshot, network log.
- **Notes / assumptions / open questions:** The error appears under `email`, not as a dedicated captcha message. If the script is blocked the form still submits without the token (the script fails silently); verify there is no stuck form.

---

## UAT-PUB-035: Pricing consistency between estimate, checkout and price lines
- **Feature / journey:** Price calculation
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** app/Services/PricingCalculator.php (service fee, VAT included/excluded, city tax `percent|per_night|per_guest_night`), `messages.pricing.*`, checkout/show.blade.php
- **Preconditions / setup:** Three establishments (or three settings of E1 across runs) configured: (1) VAT included, city tax `percent`; (2) VAT excluded, city tax `per_night`; (3) city tax `per_guest_night`. Property rate XOF 40 000, stay 3 nights, adults 2, children 1.
- **Test data:** D+30 to D+33.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | For each setting, check availability and note the estimate. | Estimate recorded E. |
  | 2 | Reserve and open checkout. | Checkout total equals E exactly. |
  | 3 | Review price lines. | Lines: "Night(s) x 3", "Service fee (x%)", "VAT (x% incl.)" or "VAT (x%)", "City / Local tax" with amounts that sum to the total. |
  | 4 | Compare with a hand calculation: per_night tax = amount x nights; per_guest_night = amount x (adults + children) x nights; percent = subtotal x amount / 100. | Matches code rules. |
- **Acceptance criteria:** Estimate, reservation total and line sum are equal; amounts follow the formulas.
- **Evidence to capture:** Spreadsheet with calculations; screenshots.
- **Notes / assumptions / open questions:** Infants are not counted in `per_guest_night`. Rounding: amounts rounded to 2 decimals, displayed to 0 decimals; sums of displayed lines can differ by 1 XOF from the displayed total (record).

---

## UAT-PUB-036: Reservation placed by a staff/admin account is not treated as a customer booking
- **Feature / journey:** Role boundary on public booking form
- **Priority:** P2
- **Role / permissions:** Admin or host logged in
- **References:** `reserve` (`$request->user()?->role === 'customer'`), properties/show.blade.php (name/email shown when not customer)
- **Preconditions / setup:** Logged in as a host/admin test user (`host.uat1@example.test`).
- **Test data:** Name and email of a synthetic guest.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open P-A. | Name and email fields are shown (user is not role customer); concierge message form is hidden. |
  | 2 | Submit a booking for `guest.new2@example.test`. | Record the outcome exactly (code path: `$request->user()` is set, user email verification state decides between "confirm email" message and reservation creation). |
- **Acceptance criteria:** No server error; behavior documented.
- **Evidence to capture:** Screenshots, DB rows.
- **Notes / assumptions / open questions:** The code does not define the intended behavior for staff users on the public form. If the staff user's own email is unverified, the confirm-email branch runs against the staff account. Raise with product owner; staff should use `/admin/reservations/create`.

---

## UAT-PUB-037: Guest count above the property's capacity
- **Feature / journey:** Capacity rule
- **Priority:** P1
- **Role / permissions:** Customer (verified)
- **References:** properties/show.blade.php (`adults` input `max = max_guests`), `reserve` (adults max 8, no `max_guests` comparison found), lang `errors.reservation_capacity_exceeded` ("The number of guests exceeds the property capacity.")
- **Preconditions / setup:** P-A max_guests 4.
- **Test data:** adults 5 (above capacity, below 8); adults 3 + children 2 (total 5).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | In the UI try to type adults = 5. | Browser validation prevents submit (`max=4`). |
  | 2 | Bypass the HTML limit (dev tools) and submit adults = 5. | Observe: the controller has no explicit capacity check, so a reservation may be created. Record the actual result; if created this is a gap against `errors.reservation_capacity_exceeded`. |
  | 3 | Submit adults 3 + children 2. | HTML does not limit the sum. Record the outcome. |
  | 4 | Availability check with adults 5. | Returns an estimate (no capacity check). Record. |
- **Acceptance criteria:** Behavior recorded and compared with the business rule (capacity must not be exceeded).
- **Evidence to capture:** Screenshots, DB row.
- **Notes / assumptions / open questions:** Open question for the product owner: is capacity enforced anywhere for public bookings (the lang key exists, likely used by admin reservation creation)? Do children count toward capacity?
