# Public UAT 03: Checkout access, payment methods, expiry, pay-on-arrival and cancellation

Scope: `/reservations/access`, `/reservations/{reservation}/checkout` and its POST actions (`checkout.start`, `checkout.cancel`, `checkout.password-setup`, `checkout.skip-password-setup`, `checkout.complete`). All cases are **Not Run**.

**Shared synthetic fixtures:**
- Reservation R1: status `pending`, created now, belongs to guest `guest.new1@example.test` with a valid `checkout_token` (taken from the link `/reservations/{id}/checkout?token=<token>`), total XOF, check_in D+30. Establishment E1 has `payment_methods` configured per each case (JSON on the establishment; `pay_later` is enabled by default).
- Customer `customer.uat1@example.test` (verified, has logged in at least once) owns R2 (status `pending`); `customer.uat2@example.test` is a different customer.
- Unverified/never-logged-in guest account for R3 (`guest.unverified1@example.test`).
- UAT environment name recorded (`APP_ENV`); payment methods set in sandbox mode unless stated.
- Payment-related statuses used by code: payable = `pending`, `pending_payment`, `payment_failed`. Other statuses seen: `confirmed`, `cancelled`, `pending_validation`.
- Link validity: 48 hours after `created_at` (`LINK_VALID_HOURS`).

---

## UAT-PAY-001: Open checkout through the tokenized link
- **Feature / journey:** Checkout summary
- **Priority:** P0
- **Role / permissions:** Anonymous guest holding a checkout link; customer with verified identity
- **References:** `GET /reservations/{reservation}/checkout?token=` (route `checkout.show`), `CheckoutController@show`, resources/views/checkout/show.blade.php, tests/Feature/PaymentWorkflowTest.php (`test_checkout_and_verified_payment_flow_work`)
- **Preconditions / setup:** R1 pending; E1 payment methods: `pay_later` only (default). The identity step has been completed or skipped for this browser session (see PAY-004/005), or the guest account is verified and has a `last_login_at`.
- **Test data:** Link `/reservations/<R1 id>/checkout?token=<R1 token>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the link in a clean browser. | HTTP 200 (or the identity step, see PAY-004). Heading "Complete your reservation" (FR "Finaliser la réservation"), badge "Payment". |
  | 2 | Read the summary. | Reference `AFK-...`, establishment, property, dates formatted `dd/mm/yyyy → dd/mm/yyyy`, total formatted with space thousands separator and currency, guest name, and a price-line list that sums to the total. |
  | 3 | Check the status label. | Label for `pending` (translation `admin.status_pending`). |
  | 4 | View on a 390 px viewport. | A sticky "mobile summary" with total and "Continue to payment" button (anchor `#checkout-payment`) is shown while status is not cancelled/confirmed. |
  | 5 | View page source. | Forms contain CSRF tokens and the `token` query value only in action URLs. |
- **Acceptance criteria:** Link works without logging in; summary is accurate.
- **Evidence to capture:** Screenshots desktop/mobile; reservation row.
- **Notes / assumptions / open questions:** The checkout token travels in the URL (query string) and in form action URLs; it can leak through browser history and referrer headers. SecurityHeaders middleware sets `Referrer-Policy: strict-origin-when-cross-origin`, so the token is not sent to other origins in the Referer header, but is sent to same-origin pages (informational; verify the header on the checkout response).

---

## UAT-PAY-002: Invalid or missing checkout token redirects to the access-recovery page
- **Feature / journey:** Checkout access control (GET)
- **Priority:** P0
- **Role / permissions:** Anonymous guest without a valid token; other customer
- **References:** `CheckoutController@show` / `canAccessCheckout`, `GET /reservations/access` (route `reservation.access`), test `test_invalid_checkout_link_redirects_to_account_access_recovery_page`, resources/views/checkout/access.blade.php
- **Preconditions / setup:** R1 with a known token; R2 owned by customer 1.
- **Test data:** Wrong token `abc`; empty token; R1 URL without token; R2 URL while logged in as customer 2.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/reservations/<R1 id>/checkout?token=abc`. | Redirect to `/reservations/access`. Page title "This link is no longer valid", intro text, buttons "Sign in" and "Create account". |
  | 2 | Open `/reservations/<R1 id>/checkout` with no token. | Same redirect. |
  | 3 | Open `/reservations/999999/checkout?token=abc`. | HTTP 404 (route model binding). |
  | 4 | Log in as customer 2 and open R2's URL without token. | Redirect to `/reservations/access` (owner check by user id/email fails). |
  | 5 | Click "Sign in" on the access page. | `/login` opens. |
  | 6 | Open R1's URL with the valid token while logged in as customer 2. | Page opens (token alone is enough per code). Record as observation. |
- **Acceptance criteria:** No reservation data is displayed without a valid token or ownership.
- **Evidence to capture:** Screenshots of the access page and redirects (network tab).
- **Notes / assumptions / open questions:** The token comparison uses `hash_equals`. A token grants access irrespective of the logged-in account (step 6); confirm this is acceptable.

---

## UAT-PAY-003: State-changing checkout actions reject a bad token with 403
- **Feature / journey:** Checkout access control (POST)
- **Priority:** P0
- **Role / permissions:** Anonymous guest without a valid token
- **References:** `CheckoutController@authorizeCheckout` (`abort 403 'Checkout access denied.'`), routes `checkout.start`, `checkout.cancel`, `checkout.offline-proof`, `checkout.paypal.create`, `checkout.paypal.capture`, `checkout.complete`
- **Preconditions / setup:** R1 pending; session cookie with a valid CSRF token (obtain from any page); API client or browser dev tools.
- **Test data:** token `wrong`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST `/reservations/<R1>/checkout?token=wrong` with `provider=pay_later`. | HTTP 403 (localized "Access denied" page). No payment attempt recorded. |
  | 2 | POST `/reservations/<R1>/checkout/cancel?token=wrong`. | 403; R1 stays `pending`. |
  | 3 | POST `/reservations/<R1>/checkout/offline-proof?token=wrong` with a file. | 403; no file stored. |
  | 4 | POST `/reservations/<R1>/checkout/paypal/create?token=wrong` and `/paypal/capture`. | 403. |
  | 5 | POST `/reservations/<R1>/checkout/complete?token=wrong`. | 403. |
  | 6 | Repeat step 2 without the CSRF token. | 419 "Session expired" page. |
- **Acceptance criteria:** Every mutating endpoint requires either ownership or the token; failures are 403/419 and make no changes.
- **Evidence to capture:** Status codes, DB check of reservation status and attempts.
- **Notes / assumptions / open questions:** The webhook route (`POST /webhooks/{provider}`) is intentionally outside token protection and uses signatures (see PAY-027).

---

## UAT-PAY-004: Identity setup screen for guests without a confirmed account
- **Feature / journey:** Password setup before payment
- **Priority:** P1
- **Role / permissions:** Anonymous guest with a checkout link
- **References:** `CheckoutController@show` (`requiresIdentitySetup`), `requestIdentitySetup` (`POST /reservations/{reservation}/checkout/password-setup`), resources/views/checkout/identity.blade.php, test `test_checkout_password_setup_confirms_email_and_returns_to_payment`, lang `checkout.identity_title|set_password|password_setup_link_sent`
- **Preconditions / setup:** R3 belongs to an account that is unverified or never logged in; browser not logged in.
- **Test data:** `guest.unverified1@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open R3's checkout link. | The identity page "Secure your customer account" is shown instead of payment options; two actions: "Set or reset my password" and "Skip and continue to payment". |
  | 2 | Click "Set or reset my password". | Redirect back with "We sent a password setup link to the reservation email address." A `GuestAccountSetupNotification` email is sent. |
  | 3 | Follow the email link and set a password (min rules per auth area). | Message "Your email is confirmed and your password has been updated." (`checkout.password_setup_complete`) and return to the checkout page with the payment options visible. |
  | 4 | Repeat on a reservation whose account does not yet exist. | An account (role customer, tenant of the establishment) is created with a random password and linked to the reservation (`user_id`). |
  | 5 | Click the button twice quickly. | Two emails may be sent; record (no throttle on this route). |
- **Acceptance criteria:** Guests with unconfirmed identity are offered the setup, and completing it returns them to checkout.
- **Evidence to capture:** Screenshots, email, DB `users`/`reservations.user_id`.
- **Notes / assumptions / open questions:** The identity screen condition: not staff and (no customer or unverified or (anonymous and never logged in)).

---

## UAT-PAY-005: Guest skips identity setup and continues to payment
- **Feature / journey:** Skip password setup
- **Priority:** P1
- **Role / permissions:** Anonymous guest with a checkout link
- **References:** `skipIdentitySetup` (`POST /reservations/{reservation}/checkout/skip-password-setup`), test `test_customer_can_skip_checkout_password_setup_and_continue_to_payment`
- **Preconditions / setup:** R3 as in PAY-004.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On the identity page click "Skip and continue to payment". | Redirect to the checkout page with the payment options. |
  | 2 | Refresh the page. | Still shows payment options (skip stored in the session `checkout_identity_skipped`). |
  | 3 | Open the same link in a new private window. | Identity page again (session-scoped). |
  | 4 | Skip on R3 and open a different unverified reservation R4. | R4 still shows the identity page (skip is per reservation id). |
- **Acceptance criteria:** Skip is optional, per reservation and per session.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** None.

---

## UAT-PAY-006: Verified owner reaches checkout from the dashboard without a token
- **Feature / journey:** Owner access
- **Priority:** P1
- **Role / permissions:** Customer owner (customer 1)
- **References:** `canAccessCheckout` (owner by `user_id` or case-insensitive email), `GET /dashboard`, `GET /dashboard/reservations/{reservation}`
- **Preconditions / setup:** R2 pending, owned by customer 1; logged in.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/reservations/<R2>/checkout` (no token). | Checkout page opens; "Back to my account" link is shown. |
  | 2 | Start a pay-on-arrival action (see PAY-013). | Works; redirect URLs get `token=<R2 checkout_token>` appended automatically (`authorizeCheckout` falls back to the stored token). |
  | 3 | Change the email case of the account in the DB (`CUSTOMER.UAT1@example.test`) and reload. | Still accessible (comparison is case-insensitive). |
  | 4 | Log out and open the same URL. | Redirect to `/reservations/access`. |
- **Acceptance criteria:** Owner access works with or without the token; strangers are redirected.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** Step 3 requires DB access; optional.

---

## UAT-PAY-007: Payment methods offered follow establishment configuration and currency rules
- **Feature / journey:** Method availability
- **Priority:** P1
- **Role / permissions:** Customer / guest
- **References:** `CheckoutController@paymentMethods`, resources/views/checkout/show.blade.php (order: paypal, fedapay, cinetpay, mpesa, pay_later, interac, wise, revolut; accordion when more than one), partial checkout/partials/payment-method.blade.php, tests `test_checkout_uses_establishment_payment_methods`, `test_checkout_keeps_last_selected_payment_method_accordion_open_by_default`, `test_checkout_shows_wise_procedure_details_even_as_sole_payment_method`
- **Preconditions / setup:** Run the matrix below by editing E1's payment methods (admin) and creating reservations in XOF, EUR or KES as available:
  (a) only `pay_later`; (b) `fedapay` enabled with XOF reservation; (c) `fedapay` enabled with EUR reservation; (d) `mpesa` enabled with XOF; (e) `mpesa` with KES; (f) `interac`/`wise`/`revolut` enabled without `secondary_currency`; (g) same with secondary currency and rate set; (h) paypal + fedapay + pay_later all enabled.
- **Test data:** Synthetic instructions text "UAT instructions".
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | (a) Open checkout. | Single method card "Pay on arrival" (no accordion) with the default instructions "Please bring cash in the local currency: the full reservation amount is payable upon your arrival." or the configured text. |
  | 2 | (b) vs (c) | FedaPay shown only when reservation currency is XOF. |
  | 3 | (d) vs (e) | "M-Pesa (KES)" shown only when currency is KES. |
  | 4 | (f) vs (g) | Interac (needs CAD conversion available), Wise, Revolut shown only when the establishment has `secondary_currency` and rate. |
  | 5 | (h) Open checkout. | Accordion with paypal first; the first (or last used) method is open; one `details` open at a time (`name="payment-method-accordion"`). |
  | 6 | Disabled methods (enabled = false). | Never shown, and POSTing `provider=<disabled>` returns HTTP 422 "This payment method is not available for this establishment." |
- **Acceptance criteria:** Offered methods exactly match configuration/currency rules; order as specified.
- **Evidence to capture:** Screenshot per configuration.
- **Notes / assumptions / open questions:** Baseline says "online payment required to confirm a booking" and lists Mobile Money (XOF) and PayPal (EUR); code defaults enable `pay_later` for every establishment, which contradicts "online payment required" (open question).

---

## UAT-PAY-008: Expired checkout link (older than 48 hours)
- **Feature / journey:** Payment link expiry
- **Priority:** P0
- **Role / permissions:** Guest/customer
- **References:** `CheckoutController::isLinkExpired` (created_at + 48 h), `guardPaymentIsAllowed` (410), view var `$linkExpired`, `checkout.link_expired`, docs/requirements-baseline.md ("Payment hold duration: 20 minutes, configurable"), `PAYMENT_HOLD_MINUTES` in PayLaterGateway payload
- **Preconditions / setup:** R5 pending with `created_at` set to 49 hours ago (DB edit or time travel on the UAT environment); R6 pending created 47 hours ago; payment methods: pay_later + one online method.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open R5's checkout link. | Page opens with a red banner: "This payment link has expired. Please contact us to complete your reservation." (FR: "Ce lien de paiement a expiré. Merci de nous contacter pour finaliser votre réservation."). Payment options and the cancel button are hidden. |
  | 2 | POST `checkout.start` with `provider=pay_later` for R5 (dev tools). | HTTP 410 with the same message. |
  | 3 | POST `checkout/offline-proof` and `paypal/create` for R5. | HTTP 410 / expired message. |
  | 4 | Open R6's link. | Normal payment options (still valid). |
  | 5 | Check whether R5 still blocks dates on the public availability (PUB-024 step with R5 dates). | Record: the code does not change R5's status when the link expires, so dates remain blocked. |
- **Acceptance criteria:** After 48 h no new payment can be started; the guest is told to contact the host.
- **Evidence to capture:** Screenshots and responses; availability result for R5 dates.
- **Notes / assumptions / open questions:** Baseline says a 20-minute hold; code uses 48 hours for link validity and stores `hold_minutes` (20) only in the pay-later attempt payload. Neither releases dates automatically in the files reviewed (`CompletePastReservations` job handles past stays, not expiry). Record the observed behavior and raise a defect/decision if the hold must release dates.

---

## UAT-PAY-009: Payment cannot be started for non-payable statuses
- **Feature / journey:** Status guard
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `guardPaymentIsAllowed` (422 `checkout.payment_no_longer_available`), `PAYABLE_STATUSES`
- **Preconditions / setup:** Reservations in each status: `confirmed`, `cancelled`, `pending_validation`, `completed`, and, for comparison, `payment_failed`.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the checkout link for the `confirmed` reservation. | No payment options or cancel button; status label shown; "Cancel/Pay" actions absent. Simulate button absent (status confirmed). |
  | 2 | POST `checkout.start` provider=`pay_later` for it. | HTTP 422 "This reservation's status has changed. This payment link is no longer valid." |
  | 3 | Same for `cancelled` and `completed`. | 422. |
  | 4 | Open the `pending_validation` reservation. | Amber notice "Your payment proof has been received. Payment is being validated by the host, you will receive a confirmation shortly." Payment options hidden. |
  | 5 | For `payment_failed`. | Payment options visible again (retry allowed). |
- **Acceptance criteria:** Only `pending`, `pending_payment`, `payment_failed` can start payments.
- **Evidence to capture:** Screenshots, responses.
- **Notes / assumptions / open questions:** None.

---

## UAT-PAY-010: Guest cancels an unpaid reservation without a fee
- **Feature / journey:** Cancel from checkout
- **Priority:** P1
- **Role / permissions:** Guest with token / owner
- **References:** `CheckoutController@cancel` (`POST /reservations/{reservation}/checkout/cancel`), test `test_customer_can_cancel_an_unfinalized_reservation_from_checkout`, lang `checkout.cancel_confirmation|cancelled`
- **Preconditions / setup:** R1 pending; E1 `cancellation_fee_percent` = 0; check_in D+30.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click "Cancel" at the bottom of the payment panel. | A confirmation dialog: "Are you sure you want to cancel this reservation?" |
  | 2 | Dismiss it. | Nothing changes. |
  | 3 | Click again and confirm. | Redirect back to checkout, flash "Your reservation has been cancelled."; status label shows cancelled; payment options hidden. |
  | 4 | Check mailbox. | A "cancelled" status email is queued to the guest. |
  | 5 | Open the property page and check the same dates. | Dates available again. |
  | 6 | Re-POST cancel for the same reservation. | HTTP 422 "This reservation can no longer be cancelled." |
- **Acceptance criteria:** Reservation status becomes `cancelled`, no price line is added, dates are released.
- **Evidence to capture:** Screenshots, email, DB status.
- **Notes / assumptions / open questions:** Cancel is a POST form; no confirmation is enforced server-side (the dialog is client-side).

---

## UAT-PAY-011: Cancellation inside the fee window adds a non-refundable fee line
- **Feature / journey:** Cancellation fee
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `Establishment::cancellationFeeFor` (percent of total when days to check-in < `cancellation_fee_days`), `checkout.cancellation_fee_warning|cancelled_with_fee|cancellation_fee`, `properties.cancellation_fee_note`, test `test_cancellation_fee_applies_inside_the_establishment_window`
- **Preconditions / setup:** E2 `cancellation_fee_percent` = 20 and `cancellation_fee_days` = 7. R7 pending with check_in = today + 3 days (inside window), total 100 000 XOF; R8 pending with check_in = today + 10 days (outside).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the property page of E2's property. | Note: "A non-refundable cancellation fee of 20% of the stay applies when cancelling less than 7 days before check-in." |
  | 2 | Open R7 checkout. | Warning: "Warning: cancelling now incurs a non-refundable fee of 20 000 XOF." |
  | 3 | Cancel R7. | Flash "Your reservation has been cancelled. A non-refundable cancellation fee of 20 000 XOF applies." A price line "Cancellation fee (non-refundable)" 20 000 XOF is added to the reservation. |
  | 4 | Open R8 checkout. | No fee warning; cancelling gives the plain "cancelled" message with no new price line. |
  | 5 | Boundary: R9 with check_in exactly today + 7 days. | No fee (code charges only when days < `cancellation_fee_days`). |
- **Acceptance criteria:** Fee equals percent x total, applied only inside the window.
- **Evidence to capture:** Screenshots, price line DB rows.
- **Notes / assumptions / open questions:** The fee is recorded as a price line only; no collection occurs at cancellation. For `pay_later` with a guarantee hold see PAY-014. Whether the fee line changes `total_amount` is not specified by the code (it only adds a `priceLines` row).

---

## UAT-PAY-012: Cancel is rejected after confirmation or proof submission
- **Feature / journey:** Cancellation guard
- **Priority:** P2
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@cancel` (`abort_unless status in pending, pending_payment, payment_failed` -> 422 `checkout.cancellation_unavailable`)
- **Preconditions / setup:** Reservations confirmed, pending_validation, cancelled.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open each reservation's checkout page. | No cancel button. |
  | 2 | POST `checkout.cancel` directly for each. | HTTP 422 "This reservation can no longer be cancelled." |
- **Acceptance criteria:** A paid or awaiting-validation reservation cannot be cancelled by the guest from checkout.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** The guest must use the dashboard/contact route for these; see customer-area UAT.

---

## UAT-PAY-013: Pay on arrival without a cancellation guarantee
- **Feature / journey:** Pay later
- **Priority:** P0
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@start` (`provider=pay_later`), `PayLaterGateway::createIntent`, partial payment-method.blade.php, lang `checkout.pay_on_arrival_action|pay_on_arrival_confirmed|payment_started`
- **Preconditions / setup:** E1 `cancellation_fee_percent` = 0; R1 pending.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click "Confirm this payment method". | Redirect to checkout, flash "Payment started via pay_later. A payment attempt has been recorded." (gateway `name()` is shown as the raw code). |
  | 2 | Check the reservation. | Status `pending_payment`; an attempt `pay_later` with status `created`, payload `mode: manual`, `hold_minutes: 20`. |
  | 3 | Reload checkout. | Payment panel shows "This payment method has been recorded. You will pay on site." and the "Attempts" list shows the `pay_later` attempt. |
  | 4 | Verify that the reservation is NOT confirmed. | Status remains `pending_payment` until staff confirm. |
  | 5 | POST `provider=foo`. | Validation error 422 on `provider` (not in the allowed list). |
- **Acceptance criteria:** An attempt is recorded and the reservation moves to `pending_payment`.
- **Evidence to capture:** Screenshot, DB rows.
- **Notes / assumptions / open questions:** The flash text uses the gateway key (`pay_later`) rather than the translated "Pay on arrival" label (wording observation). `confirmed` status for pay-on-arrival is a staff action (admin area).

---

## UAT-PAY-014: Pay on arrival with a cancellation guarantee hold
- **Feature / journey:** Guarantee hold
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@start` (pay_later + guarantee branch), partial (`guarantee_provider` select, `checkout.pay_later_guarantee_title|notice`), test `test_pay_later_requires_and_creates_guarantee_hold_when_cancellation_fee_configured`
- **Preconditions / setup:** E2 `cancellation_fee_percent` = 20; payment methods: `pay_later` and at least `paypal` + `fedapay` (sandbox) enabled; R10 total 100 000 XOF.
- **Test data:** guarantee_provider `fedapay`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open "Pay on arrival". | Notice "Cancellation guarantee required" with text "...a guarantee hold of 20 000 XOF is placed on your card/mobile money..." and a select "Payment method for cancellation guarantee:" listing online methods (only shown when more than one online method). |
  | 2 | Pick FedaPay and click "Confirm this payment method". | A guarantee attempt (provider fedapay, amount 20 000, status `authorized`) and a pay_later attempt are recorded; pay_later payload contains `guarantee_provider`, `guarantee_amount`, `guarantee_attempt_id`, `guarantee_status`; reservation `pending_payment`. Without a live redirect URL: flash "Reservation saved. A guarantee hold of 20 000 XOF has been reserved for cancellation." |
  | 3 | With a live provider (`mode=production`) that returns `redirect_url`. | Browser is redirected to the provider page. Out of scope for sandbox; mark N/A if not available. |
  | 4 | POST `guarantee_provider=cinetpay` when cinetpay is disabled. | HTTP 422 "An online guarantee payment method is required for on-site payment." |
  | 5 | With zero online methods enabled and pay_later only. | No guarantee required: behaves as PAY-013. |
- **Acceptance criteria:** Hold amount = fee percent x total; invalid guarantee provider rejected.
- **Evidence to capture:** Screenshots, DB rows for both attempts.
- **Notes / assumptions / open questions:** Sandbox gateways create an `authorized` guarantee attempt without any real authorization; confirm intent for UAT with real sandbox credentials.

---

## UAT-PAY-015: Start-payment input validation
- **Feature / journey:** Payment start validation
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@start` rules (`provider` required in `pay_later,fedapay,paypal,cinetpay,mpesa,interac,wise,revolut`; `guarantee_provider` in `fedapay,paypal,cinetpay,mpesa`)
- **Preconditions / setup:** R1 pending, E1 with `pay_later` only.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST without `provider`. | 422/redirect with "provider is required". |
  | 2 | POST `provider=stripe`. | Validation error (not in list). |
  | 3 | POST `provider=paypal` while PayPal disabled. | HTTP 422 "This payment method is not available for this establishment." |
  | 4 | POST `provider=interac` without secondary currency configured. | HTTP 422 (method not offered). |
  | 5 | POST `provider=pay_later&guarantee_provider=wise`. | Validation error (not allowed for guarantee). |
  | 6 | Double-submit `pay_later` twice rapidly. | Two attempts may be created (idempotency key includes `time()`); record. |
- **Acceptance criteria:** Invalid provider/method never creates attempts or crashes.
- **Evidence to capture:** Responses and attempt counts.
- **Notes / assumptions / open questions:** Error text for the "not available" case is hard-coded English.

---

## UAT-PAY-016: Payment simulation is available only in local/testing or for staff
- **Feature / journey:** Dev-only "Simulate verified payment"
- **Priority:** P1
- **Role / permissions:** Guest (production-like), staff
- **References:** `CheckoutController@complete` (`POST /reservations/{reservation}/checkout/complete`, 403 outside local/testing unless `canManageReservations()`), checkout/show.blade.php (`$canSimulatePayment`), tests `test_customer_cannot_simulate_offline_payment_verification_outside_dev_environment`, `test_only_staff_can_simulate_payment_in_production`, lang `checkout.simulate_unavailable|simulate`
- **Preconditions / setup:** UAT environment with `APP_ENV=production` or staging (not local/testing); R1 with a recorded attempt (PAY-013).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As a guest open checkout of R1 after an attempt exists. | No "Simulate verified payment" button. The text "This offline payment will be confirmed by an administrator once payment is received." is shown. |
  | 2 | POST `checkout.complete` with the token. | HTTP 403 with that same message. |
  | 3 | Log in as staff (reservation staff) and open the same checkout. | The "Simulate verified payment" button is visible. |
  | 4 | Click it as staff. | If attempt status verifies as `paid`/`completed`: status `confirmed`, flash "Payment verified and reservation confirmed.", receipt issued. For the pay_later attempt the status stays `created`, so expect redirect with error "Payment is still awaiting verification." |
  | 5 | On a `local`/`testing` environment (if available) repeat step 1. | The button is visible to guests. |
- **Acceptance criteria:** Guests cannot self-confirm payments in production.
- **Evidence to capture:** Screenshots, status codes.
- **Notes / assumptions / open questions:** The error "Payment is still awaiting verification." is hard-coded English (see I18N-008). Environment name must be recorded.

---

## UAT-PAY-017: Secondary-currency (EUR) display on checkout
- **Feature / journey:** XOF/EUR display
- **Priority:** P2
- **Role / permissions:** Guest
- **References:** checkout/show.blade.php (`≈ <amount> <secondary_currency>`), `Establishment::secondaryDisplayAmount` (amount x `secondary_currency_rate`, 2 decimals), docs/requirements-baseline.md (1 EUR = 655.957 XOF), `legal.terms_art3_body`
- **Preconditions / setup:** E1 `secondary_currency` = EUR and `secondary_currency_rate` = 0.001524 (approx. 1/655.957); R1 total 100 000 XOF. A second establishment without secondary currency.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open R1 checkout. | Under the total: "≈ 152,40 EUR" (amount x rate rounded to 2 decimals, French number format `1 234,56`). |
  | 2 | Open a reservation of the establishment without secondary currency. | No approximate line. |
  | 3 | Compare with the baseline rate: 100 000 / 655.957 = 152.45 EUR. | Difference is due to the rate stored on the establishment; record the stored rate. |
- **Acceptance criteria:** Display equals total x stored rate; absent when not configured.
- **Evidence to capture:** Screenshot, establishment settings.
- **Notes / assumptions / open questions:** The rate is stored as an establishment field (multiplier from the reservation currency to the secondary one), not the fixed baseline rate. There is no per-visitor currency selector in the views reviewed (see I18N-006).

