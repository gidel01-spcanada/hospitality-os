# Integrations: payment providers and webhooks (UAT-INT-001 to UAT-INT-019)

Scope: `POST /webhooks/{provider}` (`checkout.webhook`), checkout routes under `/reservations/{reservation}/checkout*`, `CheckoutController`, `PaymentGateway` contract (`app/Contracts/PaymentGateway.php`), `app/Services/Payments/*`, `PaymentAttempt`, `Receipt`, `EmailOutbox`.

## How the code decides what a webhook does (read before testing)

- Allowed providers in the URL: `pay_later`, `fedapay`, `paypal`, `cinetpay`, `mpesa`, `interac`, `wise`, `revolut`. Anything else returns 404.
- Signature check (`CheckoutController::validWebhookSignature`):
  - Default path, used by every provider unless one of the rules below applies: header `X-Webhook-Signature` must equal `hash_hmac('sha256', <raw request body>, PAYMENT_WEBHOOK_SECRET)`. Missing secret, header or mismatch returns 403.
  - `paypal` when `PAYPAL_ENVIRONMENT=production`: server calls PayPal `verify-webhook-signature` with the `Paypal-*` headers and `PAYPAL_WEBHOOK_ID`; anything but `SUCCESS` returns 403.
  - `cinetpay` when `CINETPAY_ENVIRONMENT=production`: only requires a non-empty `transaction_id`; the payment status is then verified server-side with CinetPay's check API.
  - `mpesa` when `MPESA_ENVIRONMENT=production`: only requires `Body.stkCallback.CheckoutRequestID`; status is then verified with the Daraja STK query.
- Which gateway handles the payload: `FEDAPAY_ENVIRONMENT` / `PAYPAL_ENVIRONMENT` / `CINETPAY_ENVIRONMENT` / `MPESA_ENVIRONMENT` set to `production` selects the live gateway, anything else selects the sandbox (simulated) gateway. The establishment's own payment-method `mode` setting selects the gateway at checkout. These two switches are separate.
- After a gateway returns an attempt that has a reservation: status `paid` or `completed` sets the reservation to `confirmed`, queues a `reservation_status_updated` email and issues a receipt (one receipt per payment attempt, `firstOrCreate`). Status `failed` or `cancelled` sets the reservation to `payment_failed` (unless it is already `confirmed`) and queues a status email. The JSON response is `{"ok":true,"provider":"<p>","status":"<attempt status or ignored>"}`.
- Sandbox gateways simulate behavior: FedaPay and `pay_later` read `reference`/`provider_reference` and `status` (default `paid`, allowed `paid|failed|cancelled|pending`); PayPal sandbox reads `resource.id` or `provider_reference`/`reference` and `event` (default `paid`, allowed `paid|completed|failed|cancelled|pending`); CinetPay and M-Pesa sandbox gateways always return nothing (response status `ignored`).

## Safe trigger methods (the only ones to use)

Never send tests against a production environment or with live merchant credentials. Never paste a secret into a test case, evidence file or chat.

- **S1 (simulated signed call, sandbox/staging only).** The environment owner gives the tester `PAYMENT_WEBHOOK_SECRET` for the test environment through a secure channel. The tester loads it into a local shell variable for the session only and signs the raw JSON body. Example (PowerShell, replace placeholders):
  ```powershell
  $base = "https://<test-host>"
  $body = '{"provider_reference":"<ATTEMPT_REF>","status":"paid"}'
  $key  = [Text.Encoding]::UTF8.GetBytes($env:PAYMENT_WEBHOOK_SECRET)
  $sig  = -join ([Security.Cryptography.HMACSHA256]::new($key).ComputeHash([Text.Encoding]::UTF8.GetBytes($body)) | % { $_.ToString('x2') })
  Invoke-WebRequest -Method Post -Uri "$base/webhooks/fedapay" -ContentType 'application/json' -Headers @{ 'X-Webhook-Signature' = $sig } -Body $body -SkipHttpErrorCheck | Select StatusCode, Content
  ```
  Send exactly the same string that was signed. The evidence shows the status code and body, never `$env:PAYMENT_WEBHOOK_SECRET` or `$sig` for a real secret.
- **S2 (provider test mode).** Use the provider's own sandbox: PayPal sandbox app and its webhook simulator or sandbox checkout; Safaricom Daraja sandbox; CinetPay test account; FedaPay sandbox account. To exercise the live-gateway code path against a sandbox, the test environment owner sets that provider's `*_ENVIRONMENT=production` together with the provider's sandbox `*_BASE_URL` and sandbox credentials. This is a configuration of the test environment only. Confirm with the developers that this combination is intended (see notes).
- **Reading the attempt reference.** The UI does not display `provider_reference`. The tester needs read-only SQL (SELECT only) on the test database: `SELECT id, reservation_id, provider, provider_reference, status, amount, currency FROM payment_attempts WHERE reservation_id = <id>;`.

## Common test data

Property: seeded `appartement-401` (currency XOF). Establishment payment methods: FedaPay enabled in mode `sandbox` (editor: Admin > Establishments > payment methods). Guest: `guest-pay@example.test`, name `Pay Tester`. Reservation reference is shown at checkout. Each case starts from a fresh `pending` reservation unless stated. Mail: `MAIL_MAILER=log` or a capture sandbox; run `php artisan messages:send-email-notifications` to flush the outbox.

---

## UAT-INT-001: Webhook without a signature is rejected
- **Feature / journey:** Webhook authenticity
- **Priority:** P0
- **Role / permissions:** Anonymous caller (payment provider)
- **References:** `POST /webhooks/fedapay`; `CheckoutController::webhook`, `validWebhookSignature`; `tests/Feature/PaymentWorkflowTest.php`
- **Preconditions / setup:** Reservation in `pending_payment` with a FedaPay sandbox attempt (checkout with FedaPay). `FEDAPAY_ENVIRONMENT` not `production`. `PAYMENT_WEBHOOK_SECRET` set on the server. Note the attempt reference via SQL.
- **Test data:** Body `{"provider_reference":"<ATTEMPT_REF>","status":"paid"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the body with no `X-Webhook-Signature` header. | HTTP 403 (message `Invalid webhook signature.`). |
  | 2 | Send with header present but empty. | HTTP 403. |
  | 3 | Re-query the attempt and reservation. | Attempt status unchanged (`created`); reservation still `pending_payment`; no new `receipts` or `email_outbox` rows. |
- **Acceptance criteria:** No state change without a valid signature.
- **Evidence to capture:** Status codes and bodies; SQL before/after.
- **Notes / assumptions / open questions:** If `PAYMENT_WEBHOOK_SECRET` is empty on the server every non-special-cased webhook is rejected (the check requires `filled($secret)`); record the secret's presence (yes/no) only.

---

## UAT-INT-002: Webhook with a wrong or tampered signature is rejected
- **Feature / journey:** Webhook authenticity
- **Priority:** P0
- **Role / permissions:** Anonymous caller
- **References:** `validWebhookSignature` (`hash_equals` over the raw body)
- **Preconditions / setup:** As UAT-INT-001. Trigger method S1.
- **Test data:** Body A `{"provider_reference":"<ATTEMPT_REF>","status":"paid"}`; a made-up key `uat-wrong-key` (not a real secret).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Sign Body A with `uat-wrong-key` and send it. | HTTP 403. |
  | 2 | Sign Body A with the correct secret (S1) but send a changed body (`"status":"failed"` or an extra space). | HTTP 403 (signature covers the raw body). |
  | 3 | Send a correctly signed body with the signature in upper case hex. | Record the result (`hash_equals` is case-sensitive, expect 403). |
  | 4 | Verify the attempt and reservation. | Unchanged. |
- **Acceptance criteria:** All altered or wrongly signed requests return 403 with no state change.
- **Evidence to capture:** Status codes; SQL snapshot.
- **Notes / assumptions / open questions:** None.

---

## UAT-INT-003: Unsupported provider and wrong HTTP method
- **Feature / journey:** Webhook routing
- **Priority:** P1
- **Role / permissions:** Anonymous caller
- **References:** `Route::post('/webhooks/{provider}')`; allowed provider list in `CheckoutController::webhook`
- **Preconditions / setup:** Test environment; method S1 available.
- **Test data:** Provider slugs `unknown-provider`, `stripe`, `FEDAPAY` (upper case).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST a correctly signed body to `/webhooks/unknown-provider`. | HTTP 404 (provider rejected before the signature check). |
  | 2 | POST to `/webhooks/stripe` and `/webhooks/FEDAPAY`. | HTTP 404 each. |
  | 3 | GET `/webhooks/fedapay` in a browser. | HTTP 405 (route is POST-only) or 404 from the fallback; record which. |
  | 4 | POST to `/webhooks/interac` and `/webhooks/wise` with a correct signature and a known reference. | HTTP 200 with status `ignored` (these gateways do not process webhooks); no state change. |
- **Acceptance criteria:** Unsupported providers are never processed; manual-transfer providers do not change state.
- **Evidence to capture:** Status codes and bodies.
- **Notes / assumptions / open questions:** Step 4 documents that `interac`, `wise`, `revolut` accept a webhook URL but ignore it.

---

## UAT-INT-004: Valid signed "paid" webhook confirms the reservation and issues a receipt (FedaPay sandbox)
- **Feature / journey:** Payment confirmation through webhook
- **Priority:** P0
- **Role / permissions:** Anonymous caller (provider); guest views result
- **References:** `CheckoutController::start`, `webhook`; `FedaPaySandboxGateway::createIntent/handleWebhook`; `ReservationEmailService::issueReceiptAndQueueEmail`; `GET /reservations/{reservation}/receipt` (`reservations.receipt`); `emails/receipt-issued.blade.php`
- **Preconditions / setup:** Reservation `pending`, currency XOF, FedaPay enabled (sandbox mode) on the establishment. Guest opens the checkout link and selects FedaPay (creates an attempt with reference `fedapay-sandbox-<RANDOM>` and status `created`; the reservation becomes `pending_payment`).
- **Test data:** Guest `guest-pay@example.test`; body `{"provider_reference":"<ATTEMPT_REF>","status":"paid"}` sent with S1.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Confirm via SQL: attempt status `created`, reservation `pending_payment`. | As stated. |
  | 2 | Send the signed webhook (S1) to `/webhooks/fedapay`. | HTTP 200, body `{"ok":true,"provider":"fedapay","status":"paid"}`. |
  | 3 | SQL: attempt, reservation, receipts, email_outbox. | Attempt `paid`; reservation `confirmed`; exactly 1 receipt row (`RC-<yyyymmdd>-<8 chars>`, amount = attempt amount); outbox rows `reservation_status_updated` and `receipt_issued`, both `queued`. |
  | 4 | Run `php artisan messages:send-email-notifications`. | Both rows become `sent`; captured emails to `guest-pay@example.test` in the reservation locale. |
  | 5 | Guest opens the checkout link and the receipt link. | Checkout shows a confirmed state; the receipt downloads as a PDF named `<receipt number>.pdf`. |
  | 6 | Staff opens Admin > Reservations > reservation. | Status `confirmed`. |
- **Acceptance criteria:** A single valid webhook yields one confirmed reservation, one receipt and the queued emails; amounts on receipt equal the attempt amount.
- **Evidence to capture:** HTTP response, SQL result sets, captured emails, receipt PDF (redact nothing; data is synthetic).
- **Notes / assumptions / open questions:** The webhook handler does not compare the amount or currency in the payload with the stored attempt (see UAT-INT-015).

---

## UAT-INT-005: Duplicate and replayed webhook does not duplicate receipts or bookings
- **Feature / journey:** Webhook idempotency
- **Priority:** P0
- **Role / permissions:** Anonymous caller
- **References:** `ReservationEmailService::issueReceiptAndQueueEmail` (`firstOrCreate` on `payment_attempt_id`); `CheckoutController::webhook`; master prompt "duplicated callbacks do not duplicate bookings or charges"
- **Preconditions / setup:** Run UAT-INT-004 up to step 3. Record the counts of `receipts` and `email_outbox` rows per template for this reservation.
- **Test data:** The identical signed request from UAT-INT-004 (same body and signature).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Replay the identical request 3 more times (S1). | Each returns HTTP 200 with status `paid`. |
  | 2 | SQL: count receipts for the reservation. | Still exactly 1. |
  | 3 | SQL: count outbox rows with template `receipt_issued` for the recipient. | Still exactly 1. |
  | 4 | SQL: count outbox rows with template `reservation_status_updated`. | Record the count. Expected by the code: one extra row per replay (the handler queues a status email every time the attempt is paid). |
  | 5 | SQL: reservation count for the property and dates. | Still 1 reservation; status `confirmed`; no new reservations. |
  | 6 | Two replays sent concurrently (two shells at once). | Still 1 receipt; record any 500 response or duplicate rows. |
- **Acceptance criteria:** No duplicate receipt, reservation or payment attempt. Duplicate "reservation confirmed" status emails (step 4) are recorded as a defect candidate against the master-prompt requirement; the product owner decides severity.
- **Evidence to capture:** SQL counts before and after; captured emails count.
- **Notes / assumptions / open questions:** No webhook event/ID deduplication table exists. Step 6 may race on `firstOrCreate` because `receipts.payment_attempt_id` may not be unique (check schema); record the outcome.

---

## UAT-INT-006: Late "failed" webhook after payment does not downgrade the booking
- **Feature / journey:** Out-of-order webhooks
- **Priority:** P0
- **Role / permissions:** Anonymous caller
- **References:** `FedaPaySandboxGateway::handleWebhook` (paid is not overwritten by non-paid); `CheckoutController::webhook` (`status !== 'confirmed'` guard)
- **Preconditions / setup:** Reservation confirmed through UAT-INT-004.
- **Test data:** Signed bodies `{"provider_reference":"<ATTEMPT_REF>","status":"failed"}` and `"status":"cancelled"`, `"status":"pending"`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the `failed` webhook (S1). | HTTP 200. |
  | 2 | SQL: attempt and reservation. | Attempt remains `paid`; reservation remains `confirmed`. |
  | 3 | Repeat with `cancelled` and `pending`. | Same result. |
  | 4 | Check the outbox. | No "payment failed" email created. Record whether further "confirmed" status emails were created (the controller re-processes any attempt that is still `paid`). |
- **Acceptance criteria:** A paid and confirmed booking never moves to `payment_failed` or `cancelled` because of a later webhook.
- **Evidence to capture:** SQL; outbox rows.
- **Notes / assumptions / open questions:** Real provider events can arrive out of order; the live gateways only set `paid` and never downgrade. The sandbox behavior is what this test exercises.

---

## UAT-INT-007: Webhook for an unknown payment reference is acknowledged and ignored
- **Feature / journey:** Unknown payment attempt
- **Priority:** P0
- **Role / permissions:** Anonymous caller
- **References:** `FedaPaySandboxGateway::handleWebhook` returns `null` when no attempt matches; controller returns status `ignored`; `tests/Feature/PaymentWorkflowTest.php`
- **Preconditions / setup:** Test environment; record the count of rows in `payment_attempts`, `reservations`, `receipts`, `email_outbox`.
- **Test data:** Body `{"provider_reference":"unknown-reference-123","status":"paid"}`; also a body with no reference `{"status":"paid"}`; also invalid JSON `{not json`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the unknown-reference body, correctly signed (S1). | HTTP 200, body status `ignored`. |
  | 2 | Send the no-reference body, signed. | HTTP 200, `ignored`. |
  | 3 | Send invalid JSON, signed over the same raw string. | Record the HTTP status; a 500 is a defect. |
  | 4 | Compare table counts. | All unchanged. |
- **Acceptance criteria:** Unknown references never create or alter records and never produce a server error.
- **Evidence to capture:** Status codes; table counts; error log lines for step 3 (`storage/logs/error-YYYY-MM-DD.log`).
- **Notes / assumptions / open questions:** The provider receives a 200 for an unknown reference; whether the provider should be told to retry is a product-owner decision. No alert or log line is written for an unknown reference (exploratory: check `storage/logs`).

---

## UAT-INT-008: Failed webhook moves the reservation to payment_failed and the guest can retry
- **Feature / journey:** Payment failure and retry
- **Priority:** P1
- **Role / permissions:** Anonymous caller; guest
- **References:** `CheckoutController::webhook`, `PAYABLE_STATUSES = pending, pending_payment, payment_failed`; `emails/reservation-status-updated.blade.php`
- **Preconditions / setup:** Reservation `pending_payment` with a FedaPay sandbox attempt (attempt A).
- **Test data:** Body `{"provider_reference":"<REF_A>","status":"failed"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the signed failed webhook (S1). | HTTP 200, status `failed`. |
  | 2 | SQL: attempt A and reservation. | Attempt `failed`; reservation `payment_failed`; a `reservation_status_updated` outbox row queued. |
  | 3 | Flush the outbox. | Guest receives a status email (locale of the reservation) with a link to the checkout page. |
  | 4 | Guest opens the checkout link. | Payment methods are offered again. |
  | 5 | Guest selects FedaPay again (attempt B, new reference). | New attempt created; reservation `pending_payment`. |
  | 6 | Send a signed `paid` webhook for `<REF_B>`. | Reservation `confirmed`; one receipt linked to attempt B; attempt A remains `failed`. |
- **Acceptance criteria:** Failure is visible to guest and staff and does not block a second payment.
- **Evidence to capture:** SQL after each step; captured emails.
- **Notes / assumptions / open questions:** Sandbox FedaPay creates the retry attempt with a new idempotency key built from the reservation reference and provider; check that attempt B does not reuse attempt A (the live gateways create a new provider transaction on each start).

---

## UAT-INT-009: Forged or unknown status values are ignored
- **Feature / journey:** Webhook payload validation
- **Priority:** P1
- **Role / permissions:** Anonymous caller
- **References:** Status allow-list in sandbox gateways; `tests/Feature/PaymentWorkflowTest.php` (forged-status case)
- **Preconditions / setup:** Reservation `pending_payment` with FedaPay sandbox attempt.
- **Test data:** Signed bodies with `"status":"forged-status"`, `"status":"refunded"`, `"status":"PAID"`, `"status":""`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send each body (S1), one by one. | HTTP 200 with status `ignored` for each (values outside `paid|failed|cancelled|pending` return no attempt). Record the empty-string case: `input('status', 'paid')` returns `''` for an empty string field. |
  | 2 | SQL after each request. | Attempt status never changes to the forged value; reservation unchanged. |
  | 3 | Send a signed body with NO `status` key. | Record the result. By the code the default is `paid`, so the attempt would be marked paid. |
- **Acceptance criteria:** Steps 1-2 as stated. Step 3 is recorded for the product owner: a signed request without a status confirms payment.
- **Evidence to capture:** Responses and SQL.
- **Notes / assumptions / open questions:** Step 3 depends on the sandbox default `status = 'paid'`; it applies only to non-production gateways. Run step 3 last because it can confirm the reservation.

---

## UAT-INT-010: PayPal webhook in sandbox mode (simulated payload)
- **Feature / journey:** PayPal webhook, simulated environment
- **Priority:** P1
- **Role / permissions:** Anonymous caller
- **References:** `PayPalSandboxGateway::handleWebhook` (reads `resource.id` and `event`); `CheckoutController::createPayPalOrder`, `capturePayPalOrder`; `tests/Feature/PaymentWorkflowTest.php::test_paypal_smart_buttons_create_and_capture_order`
- **Preconditions / setup:** `PAYPAL_ENVIRONMENT` not `production`. Reservation in a PayPal-eligible currency (or establishment secondary currency configured) with PayPal enabled in sandbox mode. Create the PayPal order from checkout; read the attempt reference via SQL.
- **Test data:** Signed body `{"resource":{"id":"<ATTEMPT_REF>"},"event":"completed"}`; second body with `"event":"failed"`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the `completed` body (S1) to `/webhooks/paypal`. | HTTP 200, status `completed`. |
  | 2 | SQL. | Attempt status `completed`; reservation `confirmed`; receipt issued. |
  | 3 | Send a `failed` body for another pending PayPal attempt. | HTTP 200; attempt `failed`; reservation `payment_failed`. |
  | 4 | Send `{"event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"<ATTEMPT_REF>"}}` (real PayPal shape) signed with S1. | Record the result: the sandbox gateway reads `event`, not `event_type`, so the default `paid` is applied. |
- **Acceptance criteria:** Steps 1-3 behave as stated. Step 4 outcome is recorded.
- **Evidence to capture:** Responses and SQL.
- **Notes / assumptions / open questions:** The sandbox payload shape is internal to the application and differs from PayPal's real event; UAT-INT-011 covers real PayPal events.

---

## UAT-INT-011: PayPal provider-signed webhook verification (provider sandbox)
- **Feature / journey:** PayPal webhook authenticity via PayPal verification API
- **Priority:** P1
- **Role / permissions:** PayPal sandbox account holder (test environment owner) and tester
- **References:** `validWebhookSignature` PayPal branch; `PayPalLiveGateway::handleWebhook`; `.env.example` names `PAYPAL_ENVIRONMENT`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID`, `PAYPAL_BASE_URL`; README "Live Payments"
- **Preconditions / setup:** Test environment only. Owner sets `PAYPAL_ENVIRONMENT=production` (selects the live code path), `PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com`, sandbox client credentials and the sandbox webhook ID of a webhook registered for `https://<test-host>/webhooks/paypal`. Establishment PayPal method enabled with mode `production`. Do not use live credentials.
- **Test data:** A sandbox buyer account created in the PayPal developer dashboard; a PayPal order created from checkout.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Complete a sandbox PayPal checkout from the app (guest approves in the PayPal sandbox). | Return to the app; reservation confirmed after server-side capture; receipt issued. |
  | 2 | Let PayPal deliver `PAYMENT.CAPTURE.COMPLETED` to the webhook (or use the dashboard webhook simulator). | HTTP 200 from the app in PayPal's delivery log; no duplicate receipt. |
  | 3 | Send the same JSON body to `/webhooks/paypal` from a shell without the `Paypal-*` headers. | HTTP 403 (verification fails). |
  | 4 | Send the real headers with a modified body (change one character). | HTTP 403. |
  | 5 | Make PayPal's verification endpoint unreachable (wrong `PAYPAL_BASE_URL` on the test env) and send a genuine event. | HTTP 403 and an entry in the error log; record whether PayPal retries are visible in the dashboard. |
- **Acceptance criteria:** Only events that PayPal's verification API confirms are processed; outage fails closed.
- **Evidence to capture:** PayPal delivery log screenshot (no secrets), app responses, SQL.
- **Notes / assumptions / open questions:** Using `PAYPAL_ENVIRONMENT=production` with a sandbox base URL is a test-environment workaround; confirm with developers. The code does not compare the PayPal event amount with the stored attempt. Webhook retry volume is unthrottled (no rate limit on `/webhooks/*`).

---

## UAT-INT-012: CinetPay webhook (sandbox config ignored; provider-verified path)
- **Feature / journey:** CinetPay notification handling
- **Priority:** P1
- **Role / permissions:** Anonymous caller; CinetPay test account holder
- **References:** `CinetPaySandboxGateway::handleWebhook` (always `null`); `CinetPayLiveGateway::handleWebhook/verifyStatus`; `validWebhookSignature` CinetPay branch; `.env.example` `CINETPAY_ENVIRONMENT`, `CINETPAY_SITE_ID`, `CINETPAY_API_KEY`, `CINETPAY_BASE_URL`
- **Preconditions / setup:** Establishment with currency XOF (or XAF) and CinetPay enabled. Part A: `CINETPAY_ENVIRONMENT` not `production`. Part B (provider test mode): test environment configured with CinetPay test credentials and `CINETPAY_ENVIRONMENT=production`.
- **Test data:** Body `transaction_id=<ATTEMPT_REF>` (form or JSON).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Part A: send a correctly signed (S1) body with `transaction_id`. | HTTP 200, status `ignored`; no state change. |
  | 2 | Part B: POST with an empty body. | HTTP 403 (missing `transaction_id`). |
  | 3 | Part B: POST `transaction_id=UNKNOWN-REF`. | HTTP 200, status `ignored`. |
  | 4 | Part B: pay in the CinetPay test checkout; let CinetPay call the notify URL. | App calls CinetPay's payment-check API; on `ACCEPTED` the attempt becomes `paid`, reservation `confirmed`, receipt issued. |
  | 5 | Part B: abandon or refuse a test payment (`REFUSED`/`CANCELLED`). | Attempt `failed`; reservation `payment_failed`. |
  | 6 | Part B: replay the notification for the paid attempt. | HTTP 200; still one receipt. |
- **Acceptance criteria:** In provider-verified mode status is always taken from CinetPay's check API, never from the request body.
- **Evidence to capture:** Responses; SQL; CinetPay dashboard transaction screenshot.
- **Notes / assumptions / open questions:** In the production path no shared secret or signature protects the endpoint: any caller knowing a valid `transaction_id` can trigger a provider lookup (the lookup result still decides the status). Reading `app/Services/Payments/CinetPayLiveGateway.php` shows orphaned array-literal lines directly after the closing brace of `createIntent` (lines 61-67), outside any method. That looks like a PHP parse error that would break any request that loads the live CinetPay class (production mode). Before running Part B, a developer must run a static `php -l` on the file and report the result; if it fails, Part B is Blocked and the defect is raised.

---

## UAT-INT-013: M-Pesa (Daraja) callback handling
- **Feature / journey:** M-Pesa STK Push callback
- **Priority:** P1
- **Role / permissions:** Anonymous caller; Daraja sandbox account holder
- **References:** `MpesaSandboxGateway` (webhook ignored); `MpesaLiveGateway::createIntent/handleWebhook/verifyStatus`; `validWebhookSignature` M-Pesa branch; checkout rule: M-Pesa offered only when the reservation currency is `KES`; `.env.example` `MPESA_*`
- **Preconditions / setup:** Property with KES pricing and guest phone in Kenyan format (use Daraja sandbox test number from the Safaricom developer portal). Part A: `MPESA_ENVIRONMENT` not `production`. Part B: test environment with Daraja sandbox credentials, `MPESA_ENVIRONMENT=production` and `MPESA_BASE_URL=https://sandbox.safaricom.co.ke` (test environment workaround).
- **Test data:** Callback body per Daraja: `{"Body":{"stkCallback":{"CheckoutRequestID":"<ATTEMPT_REF>","ResultCode":0}}}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On a reservation in a non-KES currency open checkout. | M-Pesa is not offered. |
  | 2 | Part A: send a signed callback body (S1). | HTTP 200, status `ignored`. |
  | 3 | Part B: callback without `CheckoutRequestID`. | HTTP 403. |
  | 4 | Part B: callback with an unknown `CheckoutRequestID`. | HTTP 200, `ignored`. |
  | 5 | Part B: start a sandbox STK push, approve in the Daraja simulator, let Daraja call back. | App queries Daraja STK status; `ResultCode 0` gives attempt `paid`, reservation `confirmed`, receipt. |
  | 6 | Part B: cancel/time out the STK prompt (ResultCode 1032 or 1037). | Attempt `cancelled`; reservation `payment_failed`. |
  | 7 | Part B: replay the paid callback. | One receipt only. |
- **Acceptance criteria:** Payment state comes from the Daraja status query; only known checkout request IDs change state.
- **Evidence to capture:** Responses; SQL; Daraja simulator logs.
- **Notes / assumptions / open questions:** Reading `app/Services/Payments/MpesaLiveGateway.php` shows orphaned array-literal lines directly after the closing brace of `createIntent` (from line 68), outside any method. That looks like a PHP parse error affecting production mode. A developer must run a static `php -l` on the file first; if it fails, Part B is Blocked and the defect is raised. Callback authentication relies on the follow-up STK query only. Daraja callback IP allow-listing is not implemented.

---

## UAT-INT-014: Webhook endpoint accepts provider calls without a browser CSRF token (real HTTP stack)
- **Feature / journey:** Webhook reachability on a deployed environment
- **Priority:** P0
- **Role / permissions:** Anonymous caller (outside the browser)
- **References:** `routes/web.php` line 348 (webhook route is in the `web` stack); `bootstrap/app.php` (no CSRF exclusion configured); `config/session.php`; master prompt note "webhook endpoints may be exempt from browser CSRF middleware"; automated tests run with CSRF validation disabled
- **Preconditions / setup:** A deployed staging environment with `APP_ENV` other than `testing` (the real middleware stack). Method S1 with a fresh cookie jar, no `X-CSRF-TOKEN`, no `XSRF-TOKEN` cookie.
- **Test data:** A correctly signed body for a pending sandbox attempt (as UAT-INT-004).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | From a shell outside any browser session send the signed webhook (S1) to `https://<staging>/webhooks/fedapay`. | Expected for a working integration: HTTP 200 and the attempt updated. |
  | 2 | If the response is HTTP 419 ("Page Expired"), record it as a blocking defect: providers cannot reach the endpoint. | 419 is a P0 defect, not a pass. |
  | 3 | Repeat with an invalid signature. | HTTP 403 (or 419 if CSRF blocks first; record). |
- **Acceptance criteria:** A signed webhook without any CSRF token reaches the handler on the deployed stack.
- **Evidence to capture:** Raw HTTP status and response headers.
- **Notes / assumptions / open questions:** Why this case exists: `bootstrap/app.php` registers no `validateCsrfTokens(except: ...)` and the route is not wrapped with `withoutMiddleware`. In a standard Laravel 12 `web` stack the CSRF middleware applies to POST requests. The PHPUnit suite does not expose this because CSRF validation is disabled while running tests. The developers must confirm. This is the single most important integration check.

---

## UAT-INT-015: Webhook amount and currency are not compared with the stored attempt (exploratory)
- **Feature / journey:** Payment amount integrity
- **Priority:** P1
- **Role / permissions:** Anonymous caller
- **References:** Master prompt step 7 ("compare provider merchant/account, internal reference, amount, currency ... Reject and alert on any mismatch"); `FedaPaySandboxGateway::handleWebhook`; `FedaPayLiveGateway::handleWebhook`; `PayPalLiveGateway::handleWebhook`
- **Preconditions / setup:** Reservation `pending_payment` with a FedaPay sandbox attempt for 138000 XOF (or the checkout total).
- **Test data:** Signed body `{"provider_reference":"<ATTEMPT_REF>","status":"paid","amount":1,"currency":"EUR"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the body (S1). | Record the HTTP response and status. |
  | 2 | SQL: attempt, reservation, receipt amount. | Record. The code does not read `amount` or `currency` from the payload; expect the attempt to become `paid` with the original stored amount. |
  | 3 | Compare to the requirement. | If the reservation is confirmed despite the mismatching amount, log a requirements gap (R-PAY-07 in `tests/uat/requirements-gaps.md`). |
- **Acceptance criteria:** To be decided by the product owner. The master prompt asks for rejection and an alert; no such check exists in the code reviewed.
- **Evidence to capture:** Request, response, SQL.
- **Notes / assumptions / open questions:** For the live FedaPay and CinetPay/M-Pesa paths the status comes from the provider API lookup (FedaPay live uses payload status in the webhook; `verifyStatus` is used on return). Amount comparison is absent in all gateways reviewed.

---

## UAT-INT-016: Guest cannot confirm payment from the browser; return URL alone does not confirm
- **Feature / journey:** Return/cancel URL as navigation only
- **Priority:** P0
- **Role / permissions:** Guest with a checkout token; customer; staff; anonymous
- **References:** `CheckoutController::complete` (staff or local/testing only), `return`, `authorizeCheckout`, `canAccessCheckout`; `tests/Feature/PaymentWorkflowTest.php::test_customer_cannot_simulate_offline_payment_verification_outside_dev_environment`, `test_only_staff_can_simulate_payment_in_production`
- **Preconditions / setup:** Staging environment with `APP_ENV` not `local`/`testing`. Reservation `pending_payment` with a FedaPay sandbox attempt (status `created`).
- **Test data:** Checkout link with token; second customer `other-customer@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Guest (token link) POSTs `checkout.complete` (`/reservations/{id}/checkout/complete?token=...`) using the browser or a crafted form. | HTTP 403 (`simulate unavailable`). Reservation stays `pending_payment`. |
  | 2 | Guest opens `/reservations/{id}/checkout/fedapay/return?token=...` before any webhook. | Redirect to checkout with "payment is awaiting confirmation"; reservation not confirmed. |
  | 3 | Anonymous user opens the checkout and return URLs without token. | Redirect to `/reservations/access` (show) or HTTP 403 (return/complete). |
  | 4 | Another logged-in customer opens the first guest's checkout link with a wrong token. | Denied (redirect or 403). |
  | 5 | Staff user (reservation staff) POSTs `checkout.complete`. | Allowed; attempt verified through the gateway; if the gateway status is paid the reservation is confirmed. Record the outcome for a sandbox attempt in status `created`. |
- **Acceptance criteria:** Only a verified provider status, or staff action, changes a reservation to confirmed.
- **Evidence to capture:** Status codes; SQL.
- **Notes / assumptions / open questions:** Token comparison uses `hash_equals`. The checkout token appears in URLs and emails; token lifetime is the reservation lifetime (no rotation seen).

---

## UAT-INT-017: Checkout provider eligibility, link expiry and non-payable states
- **Feature / journey:** Checkout guards
- **Priority:** P1
- **Role / permissions:** Guest with checkout token
- **References:** `CheckoutController::paymentMethods`, `start`, `guardPaymentIsAllowed`, `LINK_VALID_HOURS = 48`; messages `checkout.payment_no_longer_available`, `checkout.link_expired`
- **Preconditions / setup:** Establishment with FedaPay, PayPal, M-Pesa, Pay later enabled. Reservations: R1 in XOF, R2 in EUR, R3 older than 48 hours (backdate `created_at` by SQL on a test DB), R4 `confirmed`.
- **Test data:** Crafted `POST /reservations/{id}/checkout` with `provider=mpesa`, `provider=fedapay`, `provider=stripe`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open checkout for R1 (XOF). | FedaPay offered; M-Pesa not offered (needs KES). |
  | 2 | Open checkout for R2 (EUR). | FedaPay not offered (needs XOF). |
  | 3 | POST `provider=mpesa` for R1. | HTTP 422 ("not available for this establishment"). |
  | 4 | POST `provider=stripe`. | Validation error (not in allowed list). |
  | 5 | R3: open checkout and attempt payment. | Page flags the link as expired; payment start returns HTTP 410. |
  | 6 | R4 (confirmed): POST a payment start. | HTTP 422 (not payable). |
- **Acceptance criteria:** Guests can only start payments the establishment enabled for the reservation currency and only while the reservation is payable and the link is not expired.
- **Evidence to capture:** Screenshots and status codes.
- **Notes / assumptions / open questions:** The 20-minute payment hold in the baseline (`PAYMENT_HOLD_MINUTES`, `payment_hold_minutes` setting) has no enforcement in checkout; the effective limit is 48 hours. See requirements-gaps.md R-PAY-02.

---

## UAT-INT-018: Webhook reference is matched without checking the provider (exploratory)
- **Feature / journey:** Cross-provider reference confusion
- **Priority:** P2
- **Role / permissions:** Anonymous caller holding a valid signature
- **References:** `FedaPaySandboxGateway::handleWebhook` and `PayLaterGateway::handleWebhook` (`PaymentAttempt::where('provider_reference', ...)` with no provider filter); live gateways filter by provider
- **Preconditions / setup:** Two pending reservations: one with a Pay-later attempt (reference P) and one with a FedaPay sandbox attempt (reference F). Non-production environment.
- **Test data:** Signed body `{"provider_reference":"<P>","status":"paid"}` posted to `/webhooks/fedapay`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Send the body to `/webhooks/fedapay` (S1). | Record the response. By the code the sandbox gateway finds the Pay-later attempt because it ignores `provider`. |
  | 2 | SQL. | Record whether the Pay-later attempt became `paid` through the FedaPay endpoint. |
- **Acceptance criteria:** Product owner decides. A webhook for provider X changing an attempt of provider Y is a defect candidate (affects only non-production gateways).
- **Evidence to capture:** Responses and SQL.
- **Notes / assumptions / open questions:** Low risk in production if the live gateways are active (they filter by provider); the test documents behavior of sandbox mode.

---

## UAT-INT-019: Paid webhook after the reservation was cancelled (late payment, exploratory)
- **Feature / journey:** Late payment handling
- **Priority:** P1
- **Role / permissions:** Anonymous caller; staff; guest
- **References:** `CheckoutController::cancel`, `webhook`; master prompt "late payment ... do not silently overbook ... manual_review"
- **Preconditions / setup:** Reservation `pending_payment` with a FedaPay sandbox attempt. Guest cancels from checkout (reservation becomes `cancelled`). Another guest then books overlapping dates on the same property (allowed because cancelled stays do not block).
- **Test data:** Signed `paid` body for the cancelled reservation's attempt.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Guest 1 cancels from checkout; confirm status `cancelled`. | As stated. |
  | 2 | Guest 2 creates and pays an overlapping reservation (confirmed). | Confirmed. |
  | 3 | Send the signed `paid` webhook for guest 1's attempt. | Record the response. |
  | 4 | SQL: guest 1's reservation status; check overlap with guest 2. | Record. The code sets `confirmed` unconditionally on a paid attempt, which would double-book. |
  | 5 | Check Admin dashboard/logs for any alert. | Record whether any alert or manual-review status exists. |
- **Acceptance criteria:** A late payment must not silently create an overlapping confirmed booking; any overlap is a P0 defect candidate for the product owner.
- **Evidence to capture:** SQL of both reservations; admin screenshots.
- **Notes / assumptions / open questions:** The status `manual_review` named in the master prompt does not exist in the code. Run on a disposable dataset.
