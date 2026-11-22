# Public UAT 04: Online providers, return and webhook outcomes, offline proof, receipts

Scope: PayPal Smart Buttons, FedaPay, CinetPay, M-Pesa return and webhook outcomes as the guest sees them; Interac/Wise/Revolut offline proof; receipt PDF. All cases are **Not Run**. Do not use real card or mobile-money data; use provider sandboxes only.

**Shared synthetic fixtures and environment notes:**
- Sandbox gateways (`PayPalSandboxGateway`, `FedaPaySandboxGateway`, `CinetPaySandboxGateway`, `MpesaSandboxGateway`) create attempts with status `created` and **no** redirect URL; they finalize through the webhook (HMAC-SHA256 of the raw body using `services.payments.webhook_secret`, header `X-Webhook-Signature`). `PayPalSandboxGateway::verifyStatus` marks the attempt `paid` immediately on capture/return. Live gateways (`mode=production`) redirect the guest to the provider and verify with the provider API. The tester records which mode each run used.
- Webhook secret for UAT: a synthetic value stored in the UAT `.env` (never recorded in evidence). Use a script or API client to compute the signature.
- Reservations R1..R9 are `pending` with valid tokens, created within 48 hours, total 100 000 XOF unless stated (R-EUR with currency EUR; R-KES with KES).
- Payment methods are enabled per case on E1 (mode `sandbox` unless stated).
- Mail captured; queue worker running.

---

## UAT-PAY-020: PayPal Smart Buttons create and capture an order (success)
- **Feature / journey:** PayPal payment
- **Priority:** P0
- **Role / permissions:** Guest with token
- **References:** `POST /reservations/{reservation}/checkout/paypal/create` and `/paypal/capture` (routes `checkout.paypal.create`, `checkout.paypal.capture`), `CheckoutController@createPayPalOrder/capturePayPalOrder`, checkout/show.blade.php (PayPal JS SDK with `config('services.paypal.client_id')`), test `test_paypal_smart_buttons_create_and_capture_order`, lang `checkout.paypal_smart_buttons_heading`
- **Preconditions / setup:** E1 `paypal` enabled (sandbox); PayPal sandbox client id configured; R-EUR (currency EUR) and R1 (XOF, E1 `secondary_currency` EUR with rate).
- **Test data:** PayPal sandbox buyer account (synthetic, from the PayPal developer dashboard).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open R-EUR checkout. | Section "Pay via PayPal or Credit Card:" with PayPal buttons loaded. |
  | 2 | Click the PayPal button, approve with the sandbox buyer. | POST `.../paypal/create` returns JSON `orderID`, `attempt_id`, `is_guarantee: false`; reservation becomes `pending_payment`. Then POST `.../paypal/capture` returns `{status: "COMPLETED", redirect_url: <checkout url>}`. |
  | 3 | Land on the checkout page. | Flash/status shows confirmed state; reservation `confirmed`, note "Payment verified via PayPal Smart Buttons." appended. |
  | 4 | Check mailbox. | Status email "confirmed" and a receipt email with a "Download my PDF receipt" link are queued. |
  | 5 | Repeat with R1 (XOF, secondary EUR). | PayPal charge currency is EUR using the establishment's secondary amount (`amount x secondary_currency_rate`); if no secondary currency is set, record the behaviour (PayPal currency falls back to `null`/EUR in code paths). |
  | 6 | Attempt list on the checkout page. | One `paypal` attempt with status `paid` and its `provider_reference`. |
- **Acceptance criteria:** One paid attempt, reservation confirmed once, receipt issued.
- **Evidence to capture:** Screenshots of PayPal flow, JSON of create/capture, DB rows, emails.
- **Notes / assumptions / open questions:** The checkout code defaults the PayPal client id to `test` when not configured; this would load a non-working button. Expected response to a PayPal currency not supported (XOF without secondary currency) is not specified.

---

## UAT-PAY-021: PayPal approval fails or is not yet confirmed
- **Feature / journey:** PayPal failure handling
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `capturePayPalOrder` (HTTP 400 `{"status":"PENDING","message":"Payment awaiting confirmation."}`), `PayPalLiveGateway::verifyStatus`, checkout/show.blade.php PayPal JS handlers
- **Preconditions / setup:** PayPal in `production` mode against the PayPal **sandbox** base URL (live gateway path) so that an uncaptured order stays pending; alternatively simulate by POSTing capture with an unknown `orderID` on a reservation with no PayPal attempt.
- **Test data:** `orderID` = "UAT-NOT-CAPTURED".
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Start the PayPal flow and close the popup before approving. | No capture request; reservation `pending_payment`; guest can retry; no confirmation email. |
  | 2 | POST capture with an uncaptured order. | HTTP 400 JSON `status: PENDING`; the page shows an error state (record the visible message). Reservation not confirmed. |
  | 3 | Try PayPal after R1 became `cancelled`. | HTTP 422 "This reservation's status has changed. This payment link is no longer valid." |
  | 4 | Try PayPal with PayPal disabled. | HTTP 422 "PayPal is not available for this establishment." |
- **Acceptance criteria:** A failed or abandoned PayPal flow never confirms the reservation and leaves the guest able to retry.
- **Evidence to capture:** Network trace, screenshots.
- **Notes / assumptions / open questions:** Messages from the capture endpoint are hard-coded English and only the JSON status is machine-readable; confirm the front-end shows a localized error.

---

## UAT-PAY-022: Provider cancel redirect lands on a POST-only cancel route
- **Feature / journey:** Cancel link from the provider
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@start` builds `cancel_url` = `route('checkout.cancel', ...)`; `routes/web.php` defines `checkout.cancel` only as `POST`; `GET` handling is absent
- **Preconditions / setup:** A provider that actually uses `cancel_url` (live FedaPay/CinetPay/M-Pesa or PayPal redirect flow) in the provider's sandbox; or simulate by opening the cancel URL manually.
- **Test data:** URL `/reservations/<id>/checkout/cancel?provider=fedapay&token=<token>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On the provider's page choose "Cancel / return to merchant" (if offered). | The browser issues a GET to the `cancel_url`. |
  | 2 | Observe the response. | By route definition the GET is not allowed: HTTP 405 (Method Not Allowed) or the fallback 404 page. **This is expected from code but is probably a defect**; record exactly what the guest sees. |
  | 3 | Open the original checkout link. | Reservation still `pending_payment`; payment can be retried; no cancellation happened. |
- **Acceptance criteria:** Recorded behavior; the guest must have a clear path back to checkout. Raise a defect if the guest sees a dead end.
- **Evidence to capture:** Screenshot of the response page and network entry.
- **Notes / assumptions / open questions:** Also check the `provider` query parameter on the cancel URL is ignored by the controller (the cancel action cancels the whole reservation when POSTed; a GET-to-POST fix would then cancel a reservation just because the guest closed the payment page; ask the product owner for intended semantics).

---

## UAT-PAY-023: FedaPay (XOF) start, return and confirmation
- **Feature / journey:** FedaPay mobile money
- **Priority:** P0
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@start`, `@return` (`GET /reservations/{reservation}/checkout/{provider}/return`, route `checkout.return`), `@webhook` (`POST /webhooks/fedapay`), FedaPaySandboxGateway / FedaPayLiveGateway, lang `flash.payment_started|payment_verified`
- **Preconditions / setup:** E1 `fedapay` enabled, reservation currency XOF; FedaPay sandbox account; `FEDAPAY_*` env set for live-gateway testing, otherwise sandbox gateway with webhook simulation.
- **Test data:** FedaPay sandbox test phone number from FedaPay docs (synthetic); webhook body `{"reference":"<provider_reference>","status":"paid"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open R1 checkout; expand FedaPay; click "Pay online now". | Reservation status `pending_payment`; attempt `fedapay` created. Live gateway: redirect to FedaPay page. Sandbox gateway: redirect to checkout with flash "Payment started via fedapay. A payment attempt has been recorded." |
  | 2 | (Live) Complete payment at FedaPay, return to `/reservations/<id>/checkout/fedapay/return?token=...`. | Redirect to checkout; if FedaPay reports `approved`/`completed` the status is `confirmed`, flash "Payment verified and reservation confirmed.", receipt email sent. |
  | 3 | (Sandbox) Return before webhook. | Redirect to checkout with the red message "Payment is awaiting confirmation."; reservation stays `pending_payment`. |
  | 4 | (Sandbox) POST the signed webhook with status `paid`. | Response `{"ok":true,"provider":"fedapay","status":"paid"}`; reservation `confirmed`; receipt issued. |
  | 5 | Reload the checkout page. | Status confirmed; no payment options; attempt shown as `paid`. |
- **Acceptance criteria:** Reservation is confirmed only after provider-verified payment.
- **Evidence to capture:** Screenshots, webhook request/response, DB rows, emails.
- **Notes / assumptions / open questions:** For `provider` values with no existing attempt, the return route returns 404 (`firstOrFail`).

---

## UAT-PAY-024: Return page while the provider payment is still pending or failed
- **Feature / journey:** Payment return, unsuccessful outcome
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@return` (else branch: `with('error', 'Payment is awaiting confirmation.')`), `@webhook` (`failed`/`cancelled` -> `payment_failed`)
- **Preconditions / setup:** R1 with a `fedapay` attempt in status `created`/`pending`.
- **Test data:** Webhook `{"reference":"<ref>","status":"failed"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/reservations/<R1>/checkout/fedapay/return?token=<token>`. | Redirect to the checkout page showing "Payment is awaiting confirmation." Status stays `pending_payment`. |
  | 2 | Send the signed webhook with status `failed`. | Response status `failed`; reservation becomes `payment_failed`; "payment failed" email queued to the guest. |
  | 3 | Reload checkout. | Status label "payment failed"; payment options visible so the guest can retry; the attempts list shows `failed`. |
  | 4 | Retry with FedaPay or pay later. | A new attempt is created and the reservation returns to `pending_payment`. |
  | 5 | Open the return URL with a wrong token. | HTTP 403. |
  | 6 | Open the return URL for a provider without attempts (e.g. `/checkout/paypal/return`). | HTTP 404. |
- **Acceptance criteria:** Failed or pending payments never confirm; the guest can retry.
- **Evidence to capture:** Screenshots, DB status transitions, email.
- **Notes / assumptions / open questions:** The message "Payment is awaiting confirmation." is hard-coded English; for a **failed** (not pending) payment the return page still says "awaiting confirmation" (the GET return does not set `payment_failed`; only the webhook does).

---

## UAT-PAY-025: CinetPay return and webhook
- **Feature / journey:** CinetPay
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@start|return|webhook`, CinetPaySandboxGateway / CinetPayLiveGateway, `validWebhookSignature` (production: requires `transaction_id`; otherwise HMAC secret)
- **Preconditions / setup:** E1 `cinetpay` enabled; XOF reservation; CinetPay sandbox credentials if testing live gateway.
- **Test data:** Synthetic transaction id `cp-uat-0001`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Start CinetPay from checkout. | Attempt created; redirect to CinetPay (live) or flash message (sandbox). |
  | 2 | Pay successfully (live: status `ACCEPTED`). | Return page: reservation `confirmed`, "Payment verified and reservation confirmed." |
  | 3 | Refuse the payment (live: `REFUSED`/`CANCELLED`). | Attempt `failed`; reservation remains `pending_payment` on return and becomes `payment_failed` only when the webhook reports failure. |
  | 4 | POST `/webhooks/cinetpay` without `transaction_id` in production mode. | HTTP 403 "Invalid webhook signature." |
- **Acceptance criteria:** Outcomes follow provider status.
- **Evidence to capture:** Screenshots, DB rows.
- **Notes / assumptions / open questions:** In production mode the CinetPay webhook is accepted whenever `transaction_id` is present (no signature); confirm the live gateway re-verifies with the CinetPay API before trusting it.

---

## UAT-PAY-026: M-Pesa (KES) is offered only for KES reservations and handles callbacks
- **Feature / journey:** M-Pesa
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `CheckoutController@paymentMethods` (`mpesa` only when `currency === 'KES'`), `@webhook` (production: requires `Body.stkCallback.CheckoutRequestID`), MpesaSandboxGateway / MpesaLiveGateway, lang `checkout.mpesa` ("M-Pesa (KES)")
- **Preconditions / setup:** E-KE establishment with `mpesa` enabled and a KES property; R-KES pending; Safaricom sandbox credentials if using the live gateway.
- **Test data:** Synthetic Safaricom sandbox MSISDN from the Daraja docs.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open an XOF reservation checkout of the same establishment. | M-Pesa not shown. |
  | 2 | Open R-KES checkout. | "M-Pesa (KES)" shown. |
  | 3 | Start the payment. | Attempt created; reservation `pending_payment`. |
  | 4 | Deliver a callback with `ResultCode` success (live) or signed webhook `paid` (sandbox). | Reservation `confirmed`; receipt sent. |
  | 5 | Deliver a callback missing `Body.stkCallback.CheckoutRequestID` in production mode. | HTTP 403. |
  | 6 | Deliver a failure callback. | Reservation `payment_failed` and email queued. |
- **Acceptance criteria:** M-Pesa limited to KES and resulting statuses consistent.
- **Evidence to capture:** Screenshots, callback payloads (redacted), DB rows.
- **Notes / assumptions / open questions:** Whether prompting the guest for a phone number (STK push) is part of the UI is not visible in the partial reviewed; record what the guest is asked for.

---

## UAT-PAY-027: Webhook authentication and unsupported providers
- **Feature / journey:** Webhook security
- **Priority:** P0
- **Role / permissions:** External system / attacker without secret
- **References:** `POST /webhooks/{provider}` (route `checkout.webhook`), `CheckoutController@webhook`, `validWebhookSignature`
- **Preconditions / setup:** Sandbox mode (`config('services.<provider>.environment')` = sandbox); webhook secret configured; R1 with attempt reference `<ref>`.
- **Test data:** Body `{"reference":"<ref>","status":"paid"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST `/webhooks/unknown` | HTTP 404 "Unsupported payment provider." |
  | 2 | POST `/webhooks/fedapay` without `X-Webhook-Signature`. | HTTP 403 "Invalid webhook signature." |
  | 3 | POST with a wrong signature. | HTTP 403. |
  | 4 | POST with a valid HMAC-SHA256(secret, raw body) signature. | HTTP 200 JSON `{"ok":true,"provider":"fedapay","status":"paid"}`. |
  | 5 | Remove the configured secret (`PAYMENT_WEBHOOK_SECRET` empty) and POST a body with any signature. | HTTP 403 (secret must be filled). |
  | 6 | POST a valid signed body with an unknown reference. | HTTP 200 with `status: "ignored"`; no change. |
  | 7 | POST a correctly signed body from an API client **without** a session cookie or CSRF token (as a real provider would). | Expected by business need: HTTP 200. By code: `/webhooks/{provider}` is declared in routes/web.php (web middleware group) and no CSRF exclusion was found in bootstrap/app.php or app/, so HTTP 419 is likely. **Treat a 419 as a P0 defect** (provider callbacks would never confirm payments). Automated tests do not detect this because CSRF is disabled under testing. |
  | 8 | PayPal production: send invalid PayPal headers. | HTTP 403 (verification call to PayPal fails). |
- **Acceptance criteria:** Only authenticated webhooks change state; unknown providers rejected.
- **Evidence to capture:** HTTP request/response pairs (secret redacted).
- **Notes / assumptions / open questions:** Step 7 is the most important check in this case: confirm with a real provider sandbox callback if possible. The secret env variable is `PAYMENT_WEBHOOK_SECRET` (config `services.payments.webhook_secret`).

---

## UAT-PAY-028: Webhook state transitions and replay safety
- **Feature / journey:** Webhook idempotency
- **Priority:** P0
- **Role / permissions:** External system
- **References:** `CheckoutController@webhook` (paid/completed -> `confirmed` + status email + `issueReceiptAndQueueEmail`; failed/cancelled and status not confirmed -> `payment_failed`), FedaPaySandboxGateway::handleWebhook (a paid attempt is not downgraded)
- **Preconditions / setup:** Reservations R1 (attempt A1 `created`), R2 (confirmed through A2 `paid`).
- **Test data:** Signed bodies with `status` = `paid`, `failed`, `cancelled`, `pending`, `weird`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send `paid` for A1. | R1 `confirmed`; one confirmation email and one receipt (number `RC-yyyymmdd-XXXXXXXX`) created. |
  | 2 | Send the same `paid` webhook again (provider retry). | HTTP 200; record whether a second confirmation status email is queued (code calls `queueStatusUpdate` on every paid webhook). The receipt is created with `firstOrCreate`, so a second receipt number and receipt email are not expected. |
  | 3 | Send `failed` for A2 (already paid and R2 `confirmed`). | Attempt stays `paid` (not downgraded); R2 stays `confirmed`. |
  | 4 | Send `failed` for a pending attempt of R3. | R3 becomes `payment_failed`; email queued. |
  | 5 | Send `pending`. | Attempt `pending`; reservation unchanged. |
  | 6 | Send `status=weird`. | HTTP 200 with `status: "ignored"`; nothing changes. |
- **Acceptance criteria:** A paid reservation is never moved to `payment_failed`; replays do not corrupt state.
- **Evidence to capture:** DB rows and emails before/after each call.
- **Notes / assumptions / open questions:** Duplicate status emails on replay are a possible gap; receipt duplication is guarded by `firstOrCreate` (verify).

---

## UAT-PAY-029: Failed payment can be retried with another method
- **Feature / journey:** Retry after failure
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `PAYABLE_STATUSES` includes `payment_failed`, checkout/show.blade.php (`$showPaymentOptions`)
- **Preconditions / setup:** R3 in `payment_failed` (from PAY-024); within 48 hours; two methods enabled.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open checkout of R3. | Status label for `payment_failed`; the methods are shown again. |
  | 2 | Choose "Pay on arrival" and confirm. | New `pay_later` attempt; status `pending_payment`. |
  | 3 | Check that the earlier failed attempt remains in the Attempts list. | Both attempts visible, newest last. |
  | 4 | After 48 hours (or set created_at back) open again. | Expired banner (PAY-008). |
- **Acceptance criteria:** Retry works within the validity window.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** The 48 hours are measured from reservation creation, not from the failure.

---

## UAT-PAY-030: Interac, Wise and Revolut instructions and amounts
- **Feature / journey:** Offline transfer instructions
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** checkout/partials/payment-method.blade.php, `CheckoutController@start` (international amounts), `InternationalCurrencyConverter::toCad`, lang `checkout.interac_*`, `checkout.wise_*`, `checkout.revolut_*`, `checkout.copy`
- **Preconditions / setup:** E1 with `secondary_currency` = EUR and rate set; methods `interac` (email `pay.uat@example.test`, security question "Colour?", answer "Blue"), `wise` (email `wise.uat@example.test`), `revolut` (email `rev.uat@example.test`) enabled with custom instructions "UAT instructions". R1 100 000 XOF.
- **Test data:** As above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Expand Interac. | Shows "Reservation amount: <EUR amount> EUR", "Amount to send via Interac: <CAD amount> CAD", email, security question and answer, each with a "Copy" button, and the instructions text. |
  | 2 | Click "Copy" next to the amount/email. | Value copied (`data-copy-text`): amount in `NNN.NN CAD` format; email text. |
  | 3 | Expand Wise and Revolut. | "Amount to send: <EUR amount> EUR", "Wise email: ..." / "Revolut email or ID: ..." and instructions. |
  | 4 | Compare the displayed EUR amount with total x rate. | Equal (2 decimals, French number format). |
  | 5 | If the CAD conversion is unavailable (`toCad` returns null). | Interac is not offered at all. |
  | 6 | Check an upload field. | File input `payment_proof_<provider>` with `accept="image/jpeg,image/png,image/webp,application/pdf"` and button "Send proof". |
- **Acceptance criteria:** Instructions and converted amounts are correct and copyable.
- **Evidence to capture:** Screenshots, copied clipboard value.
- **Notes / assumptions / open questions:** The Interac security answer is shown to the guest in clear text by design. The CAD conversion source is not documented in the files reviewed.

---

## UAT-PAY-031: Guest uploads an offline payment proof
- **Feature / journey:** Offline proof submission
- **Priority:** P0
- **Role / permissions:** Guest with token
- **References:** `POST /reservations/{reservation}/checkout/offline-proof` (route `checkout.offline-proof`), `CheckoutController@submitOfflineProof`, `ReservationEmailService::queueOfflineProofNotification`, test `test_guest_can_submit_offline_payment_proof_and_host_is_notified`, lang `checkout.offline_proof_submitted|proof_already_submitted|awaiting_validation`
- **Preconditions / setup:** E1 with `wise` enabled; R1 pending; synthetic file `proof-uat.pdf` (small PDF) and `proof-uat.png`.
- **Test data:** Files above; provider `wise`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | In the Wise block choose `proof-uat.pdf` and click "Send proof". | Redirect to checkout with the flash "Payment proof submitted. Your reservation is pending validation." |
  | 2 | Check the reservation. | Status `pending_validation`; note "Guest submitted payment proof for wise." appended; attempt `wise` with status `awaiting_validation` and payload `payment_proof` (path under `uploads/reservations/<id>/`, `original_name`, `uploaded_by: guest`). |
  | 3 | Reload checkout. | Amber notice "Your payment proof has been received. Payment is being validated by the host, you will receive a confirmation shortly."; payment options hidden; the attempt list links the proof by its original file name. |
  | 4 | Check host mailbox. | Notification "New payment proof for <ref>" with a "View reservation" action. |
  | 5 | Click the proof link in the Attempts list. | Downloads the file with its original name (route `reservations.payment-proof.download` with token). |
  | 6 | Check file storage. | File saved as `<timestamp>-<random>.pdf` in the public upload path (not the original name). |
  | 7 | Repeat with a PNG on another reservation. | Works the same. |
- **Acceptance criteria:** Proof stored, reservation moves to `pending_validation`, host notified, guest sees waiting state.
- **Evidence to capture:** Screenshots, DB rows, email, file listing.
- **Notes / assumptions / open questions:** Proofs are stored under the web-public `uploads/` path with random file names; direct URL access to the stored file path is possible if guessed. Verify whether the web server blocks direct access (security observation).

---

## UAT-PAY-032: Offline proof upload validation
- **Feature / journey:** Proof file validation
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `submitOfflineProof` rules (`provider` in interac,wise,revolut; `payment_proof` required file mimes jpg,jpeg,png,webp,pdf max 5120 KB)
- **Preconditions / setup:** R1 pending with `wise` enabled. Files: 5 000 KB PDF, 5 121 KB PDF, `proof.exe`, `proof.txt`, `proof.svg`, a PNG renamed to `.pdf`, 0-byte PDF.
- **Test data:** As above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with no file (remove `required` in dev tools). | Validation error "payment proof is required". |
  | 2 | Submit 5 000 KB PDF. | Accepted. |
  | 3 | Submit 5 121 KB PDF. | Rejected (max 5120 KB). |
  | 4 | Submit `.exe`, `.txt`, `.svg`. | Rejected (mimes). |
  | 5 | Submit a PNG with `.pdf` extension. | Rejected or accepted according to MIME sniffing; record. |
  | 6 | Submit a 0-byte file. | Record result (probably rejected as invalid upload). |
  | 7 | Submit `provider=paypal`. | Validation error (only interac, wise, revolut). |
  | 8 | Submit for a provider that is not enabled on E1 (e.g. `revolut`). | HTTP 422 "Unable to submit a payment proof for this reservation." |
  | 9 | Upload a second proof after the first. | Allowed? Record: the form is replaced by "Proof sent, awaiting validation by the host." once a proof exists, and the reservation is `pending_validation` so the server returns 422. |
- **Acceptance criteria:** Only allowed files up to 5 MB are accepted; rejected files leave the reservation unchanged.
- **Evidence to capture:** Screenshots of errors, DB status.
- **Notes / assumptions / open questions:** PHP `upload_max_filesize` / `post_max_size` limits in the UAT server may reject earlier than 5 MB with a different message; record the server limits.

---

## UAT-PAY-033: Offline proof not accepted when payment is closed or link expired
- **Feature / journey:** Proof state guards
- **Priority:** P1
- **Role / permissions:** Guest with token
- **References:** `submitOfflineProof` (`abort_unless` status payable -> 422 `checkout.offline_proof_unavailable`; `guardPaymentIsAllowed` 422/410)
- **Preconditions / setup:** Reservations: confirmed, cancelled, pending_validation, and one expired (> 48 h); wise enabled.
- **Test data:** Valid PDF.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST proof to the `confirmed` reservation. | HTTP 422 "Unable to submit a payment proof for this reservation." |
  | 2 | Same for `cancelled` and `pending_validation`. | HTTP 422. |
  | 3 | POST proof to the expired reservation. | HTTP 410 "This payment link has expired. Please contact us to complete your reservation." |
  | 4 | Check storage. | No file saved in any rejected case (validation and abort run before `move()`). |
- **Acceptance criteria:** No proof is stored for non-payable reservations.
- **Evidence to capture:** Responses, storage listing.
- **Notes / assumptions / open questions:** None.

---

## UAT-PAY-034: Payment proof download authorization
- **Feature / journey:** Proof file access
- **Priority:** P1
- **Role / permissions:** Token holder, owner, staff, outsider
- **References:** `GET /reservations/{reservation}/payment-proofs/{attempt}` (route `reservations.payment-proof.download`), `ReceiptController@downloadProof`, `authorizeReservation(allowCheckoutToken: true)`
- **Preconditions / setup:** R1 with an attempt A1 holding a proof (PAY-031); R2 with attempt A2.
- **Test data:** URLs for A1 with/without token.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET with the valid token. | File downloads under its original name. |
  | 2 | GET without token, anonymous. | HTTP 403. |
  | 3 | GET with a wrong token. | HTTP 403. |
  | 4 | GET as the owner (logged in) without token. | Download works. |
  | 5 | GET as a different customer. | HTTP 403. |
  | 6 | GET `/reservations/<R1>/payment-proofs/<A2 id>?token=<R1 token>` (attempt from another reservation). | HTTP 404. |
  | 7 | GET for an attempt with no stored proof. | HTTP 404. |
- **Acceptance criteria:** Proofs are downloadable only by authorized parties; attempts cannot be crossed between reservations.
- **Evidence to capture:** Status codes.
- **Notes / assumptions / open questions:** Path traversal is mitigated by `realpath` checks in code; an automated test with a manipulated payload path requires DB access and is not part of UAT.

---

## UAT-PAY-035: Download the PDF receipt as the reservation owner
- **Feature / journey:** Receipt PDF
- **Priority:** P0
- **Role / permissions:** Customer owner
- **References:** `GET /reservations/{reservation}/receipt` (route `reservations.receipt`, auth), `ReceiptController@download`, resources/views/receipts/show.blade.php, lang `receipts.*`, test `test_admin_can_generate_receipt_for_confirmed_reservation_without_one`
- **Preconditions / setup:** R-conf `confirmed` with at least one `paid` attempt and an issued receipt (after PAY-020/023 or admin confirmation); logged in as the owner.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | In the dashboard reservation page click "Download receipt <number>". | A PDF named `<receipt_number>.pdf` downloads. |
  | 2 | Open the PDF. | Title "Payment receipt <number>", Issued at `dd/mm/yyyy HH:mm`, reservation reference, customer name (or email), property name, price lines (label and amount with currency), "Amount paid" equals the receipt amount, payment method column. |
  | 3 | Compare amounts with checkout. | Lines add up to the total; currency and thousands separators are consistent. |
  | 4 | Click the "Download my PDF receipt" link in the receipt email. | Same file (the link requires login or is signed; record which). |
  | 5 | Generate receipt for a reservation with accented/long property names. | Characters render correctly (no `?` boxes). |
- **Acceptance criteria:** The PDF is generated for the latest receipt and contains correct data.
- **Evidence to capture:** The PDF, screenshot of the dashboard button.
- **Notes / assumptions / open questions:** The receipt is rendered with the app locale at download time, not the reservation locale (view uses `__()`); verify.

---

## UAT-PAY-036: Receipt access control and missing receipt
- **Feature / journey:** Receipt authorization
- **Priority:** P0
- **Role / permissions:** Anonymous, other customer, staff, owner
- **References:** `ReceiptController@download` / `authorizeReservation` (owner by user id or email, staff via `canManageReservations()`, 403 otherwise), `firstOrFail` for no receipt
- **Preconditions / setup:** R-conf with receipt; R-nopaid without receipt; customers 1 (owner) and 2.
- **Test data:** URLs `/reservations/<id>/receipt`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Anonymous GET `/reservations/<R-conf>/receipt`. | Redirect to `/login` (auth middleware). |
  | 2 | Logged in as customer 2. | HTTP 403. |
  | 3 | Logged in as the owner. | PDF downloads. |
  | 4 | Logged in as staff of the same tenant. | PDF downloads. |
  | 5 | Owner opens the receipt URL of R-nopaid. | HTTP 404. |
  | 6 | Append `?token=<checkout token>` as anonymous. | Still redirected to login: the receipt route does not accept the checkout token. |
- **Acceptance criteria:** Receipts are only downloadable by the owner or staff; missing receipts return 404.
- **Evidence to capture:** Status codes.
- **Notes / assumptions / open questions:** Guests who never create a password cannot download receipts from the checkout link; the receipt email link behavior decides the guest path (record in PAY-035). Cross-tenant staff access is a tenant-isolation question for the platform UAT owner.

