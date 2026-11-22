# Access 07: Mobile API authentication and role access (`/api/v1`)

Source of truth: `routes/api.php`, `app/Http/Controllers/Api/MobileApiController.php`, `app/Http/Middleware/AuthenticateMobileToken.php` (alias `api-token`), `app/Http/Middleware/EnsureMobileRole.php` (alias `api-role:customer|staff|admin`), `app/Models/MobileAccessToken.php`, `docs/mobile-api.md`, `tests/Feature/MobileApiTest.php`.

Tooling: any HTTP client (Postman, curl). Base URL `{BASE}/api/v1`. Always send `Accept: application/json` unless a case says otherwise. Protected calls send `Authorization: Bearer <token>`. Tokens must be treated as secrets and masked in evidence. Passwords: `<PLACEHOLDER from SEED_*_PASSWORD env / tester-provided>`.

Role mapping used by `api-role` middleware:
| Alias | Allowed users |
|-------|--------------|
| `customer` | `role = customer` only |
| `staff` | `canManageReservations()`: admin, concierge, host |
| `admin` | `isAdmin()`: role admin or `is_admin = 1` |

Test accounts: customers `guest@afrikappart.test` and `uat-api-cust2@example.test`; concierge `concierge@afrikappart.test`; host `host@afrikappart.test`; admin `admin@afrikappart.test`; tenant B concierge `uat-conc-b@example.test` (cloud fixture from `access-04`).

---

## UAT-API-001: Login returns a token and user profile
- **Feature / journey:** Mobile sign-in
- **Priority:** P0
- **Role / permissions:** Customer, concierge, host, admin
- **References:** `POST /api/v1/auth/login` (`MobileApiController::login`), `docs/mobile-api.md`, `MobileApiTest::test_customer_can_authenticate_and_access_the_mobile_catalog_and_reservations`
- **Preconditions / setup:** Active accounts.
- **Test data:** body `{"email":"guest@afrikappart.test","password":"<PLACEHOLDER from SEED_GUEST_PASSWORD>","device_name":"uat-ios"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST the body | HTTP 201. JSON `{"token":"<80-char string>","user":{"id","name","email","role":"customer","locale"}}`. No password or hash in the response. |
  | 2 | Repeat with the staff and admin accounts | 201; `user.role` equals `concierge`, `host`, `admin` respectively. |
  | 3 | Omit `device_name` | 201 (default token name `mobile-app`). |
  | 4 | Send the email in upper case (`GUEST@AFRIKAPPART.TEST`) | 201 (server lower-cases the email); record actual. |
  | 5 | Log in twice | Two different tokens; both work (tokens are per login, not exclusive). |
  | 6 | Lead checks DB `mobile_access_tokens` | Token column holds a SHA-256 hash, not the plain value. |
- **Acceptance criteria:** Valid credentials return a bearer token usable on protected endpoints (UAT-API-003, UAT-API-004).
- **Evidence to capture:** Response bodies with token masked; DB hash check.
- **Notes / assumptions / open questions:** The login endpoint does not check tenant suspension (UAT-API-014) and has no throttle (UAT-AUTH-015). Stored emails with upper-case letters would not match the lower-cased lookup on case-sensitive databases; record environment collation.

## UAT-API-002: Login failures and validation
- **Feature / journey:** Mobile sign-in errors
- **Priority:** P0
- **Role / permissions:** Anonymous
- **References:** `MobileApiController::login` (rules `email` required email, `password` required string, `device_name` nullable string max 100; failure `422 {"message":"Invalid credentials."}`)
- **Preconditions / setup:** Active account, an inactive account `uat-inactive-api@example.test` (deactivated by admin).
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Wrong password for an existing account | HTTP 422 `{"message":"Invalid credentials."}`. |
  | 2 | Unknown email | HTTP 422 with the identical body. |
  | 3 | Inactive account with the correct password | HTTP 422 `Invalid credentials.` (same body, no leak). |
  | 4 | Empty body | HTTP 422 `message` + `errors.email`, `errors.password`. |
  | 5 | `email` = `abc` | HTTP 422 `errors.email`. |
  | 6 | `device_name` of 101 characters | HTTP 422 `errors.device_name`; 100 characters accepted. |
  | 7 | `password` sent as array | HTTP 422 (string rule). |
  | 8 | GET `/api/v1/auth/login` | HTTP 405. |
  | 9 | Account of a suspended tenant | Record outcome (see UAT-API-014). |
- **Acceptance criteria:** Identical failure body for the three credential failures; validation errors use Laravel's `message`/`errors` structure.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** Failure status is 422, not 401 (docs state 401 for missing or invalid tokens on protected routes only).

## UAT-API-003: Protected endpoints reject missing, malformed, unknown and expired tokens
- **Feature / journey:** Token authentication
- **Priority:** P0
- **Role / permissions:** Anonymous / invalid token
- **References:** `AuthenticateMobileToken` (401 `{"message":"Unauthenticated."}` when token missing, unknown, user missing or inactive, or `expires_at` in the past)
- **Preconditions / setup:** A valid token T for the customer. A way to set `expires_at` in the past on T (lead/DB) and to deactivate the user.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/me` without `Authorization` | 401 `Unauthenticated.` |
  | 2 | GET `/me` with `Authorization: Bearer ` (empty) and with `Authorization: Token abc` | 401. |
  | 3 | GET `/me` with `Bearer not-a-token` | 401. |
  | 4 | GET `/me` with T | 200 `{"data":{...user}}`. |
  | 5 | Set T `expires_at` to a past date; GET `/me` | 401. |
  | 6 | Deactivate the user in admin; GET `/me` with a still-valid token | 401. |
  | 7 | Send the token in the query string `?token=` or in `X-API-Key` | 401 (only the Bearer header is read). |
  | 8 | Call each protected route once without token: `POST /auth/logout`, `GET /me`, `PUT /me`, `GET /customer/reservations`, `GET /staff/reservations`, `GET /admin/properties` | All 401. |
- **Acceptance criteria:** No protected data without a valid, unexpired, active-user token.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** OPEN QUESTION: login never sets `expires_at`, so issued tokens do not expire unless set manually. Confirm whether token expiry is a requirement.

## UAT-API-004: Read and update the own profile (`/me`)
- **Feature / journey:** Profile via API
- **Priority:** P1
- **Role / permissions:** Any authenticated user
- **References:** `GET /me`, `PUT /me` (`updateMe`: `name` sometimes string max 255; `locale` sometimes in fr,en)
- **Preconditions / setup:** Customer token.
- **Test data:** `{"name":"UAT API Renamed","locale":"en"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/me` | 200 `data` with `id`, `name`, `email`, `role`, `locale`. |
  | 2 | PUT the test data | 200 `data` shows new name and locale. Web `/account` shows the same name. |
  | 3 | PUT `{"locale":"de"}` | 422 `errors.locale`. |
  | 4 | PUT `{"name":""}` | 422 or accepted? Record (rule `sometimes string` with an empty value: Laravel turns empty to null, so `string` fails when present). |
  | 5 | PUT `{"name":"<256 chars>"}` | 422; 255 chars accepted. |
  | 6 | PUT `{"role":"admin","email":"x@example.test","is_admin":true}` | 200 but role and email unchanged (only validated keys are saved). |
  | 7 | PUT `{}` | 200, no change. |
- **Acceptance criteria:** Only `name` and `locale` can change; privileged fields cannot be set.
- **Evidence to capture:** Responses; web profile page.
- **Notes / assumptions / open questions:** `fill($validated)` only receives validated keys.

## UAT-API-005: Logout and token lifecycle
- **Feature / journey:** Token revocation
- **Priority:** P0
- **Role / permissions:** Any authenticated user
- **References:** `POST /auth/logout` (`MobileApiController::logout` reads `$request->attributes->get('mobile_access_token')?->delete()` and returns 204); `AuthenticateMobileToken` does not set that attribute; docs: "Call `POST /api/v1/auth/logout` to revoke the current token."; `AdminController::deleteUser` deletes tokens
- **Preconditions / setup:** Two tokens T1 and T2 for the same customer (two logins).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST `/auth/logout` with T1 | HTTP 204, empty body. |
  | 2 | GET `/me` with T1 | Per docs: 401. Record the actual status. |
  | 3 | GET `/me` with T2 | 200 (other tokens unaffected). |
  | 4 | Check DB `mobile_access_tokens` for T1 | Row deleted per docs; record whether it still exists. |
  | 5 | Customer changes the web password (UAT-ACCT-012); GET `/me` with T2 | Record whether the token is still valid (code does not revoke tokens on password change). |
  | 6 | Admin deactivates the user | Token stops working (UAT-API-003 step 6). |
  | 7 | Admin deletes the user | Token rows removed; `/me` 401. |
- **Acceptance criteria:** Logout revokes the presented token as documented.
- **Evidence to capture:** Responses and DB check.
- **Notes / assumptions / open questions:** POSSIBLE DEFECT TO VERIFY: the controller expects a request attribute that the middleware never sets (by reading the code, logout would be a no-op and T1 stay valid). Do not assume; record the real behaviour. If T1 still works after logout, raise as a security defect.

## UAT-API-006: Public catalogue list and detail
- **Feature / journey:** Browse properties without login
- **Priority:** P1
- **Role / permissions:** Anonymous
- **References:** `GET /properties` (filters `city` string max 120, `guests` integer min 1, `establishment_id` integer), `GET /properties/{slug}`; docs "Public catalogue"
- **Preconditions / setup:** Published properties in at least two cities and a draft property with slug `uat-draft`.
- **Test data:** `?city=Cotonou`, `?guests=4`, `?establishment_id=<id>`, `?guests=0`, `?guests=abc`, `?city=<121 chars>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/properties` | 200 `data` array of published properties only, ordered by `nightly_rate_xof` ascending; each with `id`, `slug`, `name`, `city`, `currency`, `nightly_rate_xof`, `max_guests`, `bedrooms`, `bathrooms`, `beds`, `minimum_stay`, `cover_image`, `establishment{id,name,city}`. |
  | 2 | Apply each valid filter | Results narrowed (city is a "contains" match; guests = `max_guests >= n`). |
  | 3 | `?guests=0`, `?guests=abc`, 121-char city | 422 with `errors`. |
  | 4 | GET `/properties/{slug}` of a published property | 200 `data` with extra `images`, `description`, `amenities`. |
  | 5 | GET `/properties/uat-draft` and `/properties/does-not-exist` | 404. |
  | 6 | Compare list against the web `/properties` | Same published set. |
- **Acceptance criteria:** Only published data, with documented fields and filters.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** The list uses `Property::published()` (status published, property active, establishment active). The detail and availability endpoints only check `status = published`, whereas the web availability route also checks `is_active` flags (`PublicPropertyController::availability`). Add a published property whose `is_active` is false (or whose establishment is inactive): it should be absent from the list; record whether `GET /properties/{slug}` and the availability endpoint still answer 200.

## UAT-API-007: Availability check endpoint
- **Feature / journey:** Availability via API
- **Priority:** P1
- **Role / permissions:** Anonymous
- **References:** `POST /properties/{slug}/availability` (`check_in` required date, `check_out` required date after `check_in`); response `{"available": true|false}`
- **Preconditions / setup:** Published property with a known reservation on 2 known dates.
- **Test data:** free dates; overlapping dates; `check_out` = `check_in`; past dates; invalid date `2027-13-45`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Free range | 200 `{"available":true}`. |
  | 2 | Range overlapping the existing reservation | 200 `{"available":false}`. |
  | 3 | `check_out` equal to or before `check_in` | 422 `errors.check_out`. |
  | 4 | Past dates | Record outcome (no `after_or_equal:today` rule on this endpoint). |
  | 5 | Invalid date string / missing fields | 422. |
  | 6 | Draft property slug | 404. |
- **Acceptance criteria:** Boolean answer consistent with the web availability result.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** Minimum stay and capacity are not evaluated by this endpoint.

## UAT-API-008: Customer reservations: list, detail and ownership
- **Feature / journey:** Customer reservation endpoints
- **Priority:** P0
- **Role / permissions:** Customer
- **References:** `GET /customer/reservations`, `GET /customer/reservations/{reservation}`; `customerReservation` returns 403 when `user_id` differs; docs "Customer reservation endpoints only return reservations that belong to the authenticated customer account."
- **Preconditions / setup:** Customer A has reservation RA1 (linked `user_id`). Customer B has RB1. Reservation REM has A's email but no `user_id`.
- **Test data:** tokens for A and B.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | A: GET `/customer/reservations` | 200 `data` listing RA1 only (fields `id`, `reference`, `status`, `check_in`, `check_out`, `total_amount`, `currency`, `property{id,name,slug}`), newest first. REM is absent. |
  | 2 | A: GET `/customer/reservations/{RA1}` | 200 with extra `guest{full_name,email,phone}` and `price_lines[{label,amount,currency}]`. |
  | 3 | A: GET `/customer/reservations/{RB1}` | 403. |
  | 4 | A: GET `/customer/reservations/999999999` | 404. |
  | 5 | Customer with no reservations | 200 `{"data":[]}`. |
  | 6 | Compare REM with the web dashboard (UAT-ACCT-003) | Web shows REM, API does not; record. |
- **Acceptance criteria:** Customers only receive their own reservations by `user_id`.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** OPEN QUESTION: ownership by email (web) vs by `user_id` only (API).

## UAT-API-009: Toggle favorite through the API
- **Feature / journey:** Favorites via API
- **Priority:** P2
- **Role / permissions:** Customer
- **References:** `POST /customer/properties/{property}/favorite` (`toggleFavorite`; binds by numeric id since route has `{property}` without slug key)
- **Preconditions / setup:** Customer token; published property id P; draft property id D.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST for P | 200 `{"property_id":P,"favorited":true}`; web dashboard lists P under favorites. |
  | 2 | POST for P again | `{"favorited":false}`; removed. |
  | 3 | POST for unknown id | 404. |
  | 4 | POST for draft D | Record outcome (code does not check `published`, unlike the web `FavoriteController`, which returns 404). |
  | 5 | Staff token on the same endpoint | 403 (customer role only). |
- **Acceptance criteria:** Toggle alternates and matches the web favorites list.
- **Evidence to capture:** Responses; dashboard screenshot.
- **Notes / assumptions / open questions:** Step 4 may reveal inconsistent behaviour between web and API.

## UAT-API-010: API role access matrix
- **Feature / journey:** `api-role` middleware enforcement
- **Priority:** P0
- **Role / permissions:** Customer, concierge, host, tenant admin, anonymous
- **References:** `routes/api.php` groups; `EnsureMobileRole` (403 `{"message":"Forbidden."}`)
- **Preconditions / setup:** One valid token per role (UAT-API-001).
- **Test data:** ids of one reservation R (tenant of the staff) and property P.
- **Steps and expected results:**
  | # | Action (endpoint) | Anonymous | Customer | Concierge | Host | Admin |
  |---|-------------------|-----------|----------|-----------|------|-------|
  | 1 | `GET /me`, `PUT /me`, `POST /auth/logout` | 401 | 200/204 | 200/204 | 200/204 | 200/204 |
  | 2 | `GET /customer/reservations`, `GET /customer/reservations/{id}`, `POST /customer/properties/{id}/favorite` | 401 | 200 | 403 | 403 | 403 |
  | 3 | `GET /staff/reservations`, `GET /staff/reservations/{R}` | 401 | 403 | 200 | 200 | 200 |
  | 4 | `PATCH /staff/reservations/{R}/status`, `POST /staff/reservations/{R}/payment-link` | 401 | 403 | 200 / 202 | 200 / 202 | 200 / 202 |
  | 5 | `GET /admin/properties` | 401 | 403 | 403 | 403 | 200 |
  | 6 | `GET /properties`, `GET /properties/{slug}`, `POST /properties/{slug}/availability`, `POST /auth/login` | 200/201 | same | same | same | same |
- **Acceptance criteria:** All cells match; 403 body is `{"message":"Forbidden."}`.
- **Evidence to capture:** Filled matrix with status codes.
- **Notes / assumptions / open questions:** `docs/mobile-api.md` says "Concierge and admin tokens" for staff endpoints, but code also allows hosts (`canManageReservations`). Hosts are not limited to their assigned establishments in API staff endpoints (only tenant-scoped); verify in UAT-API-011. Use the 200/202 results only on throw-away reservations because they change data.

## UAT-API-011: Staff endpoints are tenant-scoped
- **Feature / journey:** Staff API tenant isolation
- **Priority:** P0
- **Role / permissions:** Concierge A, concierge B (cloud fixture)
- **References:** `staffReservations` (`tenantReservations()` filters by `CurrentTenant`), route binding `Reservation::resolveRouteBinding`, `adminProperties`
- **Preconditions / setup:** Two-tenant fixture: reservation RA in tenant A, RB in tenant B. Tokens for concierge A and concierge B. Also host H assigned only to E1 in tenant A, with reservation R-E2 on E2.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | A: GET `/staff/reservations` | Contains RA, not RB. |
  | 2 | A: GET `/staff/reservations/{RB}` | 404. |
  | 3 | A: PATCH `/staff/reservations/{RB}/status` `{"status":"cancelled"}` | 404; RB unchanged. |
  | 4 | A: POST `/staff/reservations/{RB}/payment-link` | 404; no email queued. |
  | 5 | B: repeat symmetric checks for RA | 404. |
  | 6 | Host H: GET `/staff/reservations` and `/staff/reservations/{R-E2}` | Record whether R-E2 is visible (the web restricts hosts to assigned establishments; the API code only filters by tenant). |
  | 7 | Admin A: GET `/admin/properties` | Properties of tenant A only. |
- **Acceptance criteria:** No cross-tenant access. Host scoping difference is recorded.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** OPEN QUESTION (step 6): host establishment scoping in API.

## UAT-API-012: Staff updates reservation status
- **Feature / journey:** `PATCH /staff/reservations/{reservation}/status`
- **Priority:** P1
- **Role / permissions:** Concierge/admin
- **References:** `updateReservationStatus`: `status` required in `pending,pending_payment,pending_validation,confirmed,checked_in,completed,cancelled`; `notes` nullable string max 500; queues `reservation_status_updated` email only when the status changes
- **Preconditions / setup:** Throw-away reservation R with status `pending`.
- **Test data:** `{"status":"confirmed","notes":"UAT note"}`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | PATCH the test data | 200 `data` with new status; web admin shows status and notes; outbox row `reservation_status_updated` queued. |
  | 2 | PATCH the same status again | 200; no second email. |
  | 3 | PATCH `{"status":"payment_failed"}` | 422 (not in the API list although the web dashboard knows this status). |
  | 4 | PATCH `{"status":"bogus"}` and `{}` | 422. |
  | 5 | `notes` of 501 chars | 422; 500 accepted. |
  | 6 | PATCH without `notes` | Existing notes kept. |
  | 7 | PATCH `{"status":"completed"}` | Status changes and the review-request email is queued (UAT-REV-001); record whether email is queued (API path uses `queueForReservation`, not `queueStatusUpdate`). |
- **Acceptance criteria:** Only allowed statuses; side effects as stated.
- **Evidence to capture:** Responses; outbox.
- **Notes / assumptions / open questions:** The API does not validate legal transitions (for example `cancelled` to `confirmed`); record behaviour and raise as an open question.

## UAT-API-013: Send payment link and its restrictions
- **Feature / journey:** `POST /staff/reservations/{reservation}/payment-link`
- **Priority:** P1
- **Role / permissions:** Concierge/admin; customer (denied)
- **References:** `sendPaymentLink` (`abort_unless($reservation->canSendPaymentLink(), 403)`; 202 body `message` "Payment link queued." and `checkout_url`), `Reservation::canSendPaymentLink` (status not `confirmed` and no `verified` payment attempt), `MobileApiTest::test_concierge_can_queue_a_payment_link_but_customer_cannot`
- **Preconditions / setup:** Reservation Rp pending; Rc confirmed; Rv with a `verified` payment attempt.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Customer token on Rp | 403 `Forbidden.` |
  | 2 | Concierge on Rp | 202 `{"message":"Payment link queued.","checkout_url":"{BASE}/reservations/{id}/checkout?token=..."}`; outbox `payment_link` queued for the guest email. |
  | 3 | Concierge on Rc | 403 (no body message from `Forbidden.` middleware; record body). |
  | 4 | Concierge on Rv | 403. |
  | 5 | Open `checkout_url` in a browser | Checkout page for that reservation opens. |
- **Acceptance criteria:** Link only for unpaid reservations; matches the web admin behaviour documented.
- **Evidence to capture:** Responses; outbox row.
- **Notes / assumptions / open questions:** The response contains the checkout token (a bearer secret): handle evidence securely.

## UAT-API-014: Suspended workspace and inactive users
- **Feature / journey:** Tenant suspension via API
- **Priority:** P1
- **Role / permissions:** Concierge B; platform admin
- **References:** `AuthenticateMobileToken` (403 `{"message":"This workspace has been suspended."}` when `tenant.status = suspended`; 401 for inactive users), `PlatformAdminController::suspend/activate`
- **Preconditions / setup:** Tenant B concierge token. Cloud fixture. Platform admin session.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Suspend tenant B; GET `/staff/reservations` with the concierge B token | 403 `This workspace has been suspended.` |
  | 2 | GET `/me` with the same token | 403 (middleware runs before any route logic). |
  | 3 | POST `/auth/login` as concierge B | Record: login still succeeds (no tenant check in `login`). |
  | 4 | Customer token (tenant null) | Unaffected. |
  | 5 | Activate tenant B | Token works again. |
- **Acceptance criteria:** Suspended tenants cannot use protected API endpoints.
- **Evidence to capture:** Responses.
- **Notes / assumptions / open questions:** Same scenario in the web UI: UAT-TEN-005.

## UAT-API-015: Error format without `Accept: application/json` and unusual requests
- **Feature / journey:** API error conventions
- **Priority:** P2
- **Role / permissions:** Anonymous / customer
- **References:** `docs/mobile-api.md` "Response conventions" (`message` and `errors` for validation; 401/403 as described); Laravel exception rendering
- **Preconditions / setup:** Customer token.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST `/auth/login` with an invalid body and NO `Accept` header | Record whether JSON 422 or an HTML redirect is returned. |
  | 2 | GET `/customer/reservations/999999999` with token and no `Accept` header | Record (404 HTML vs JSON). |
  | 3 | Same with `Accept: application/json` | JSON 404 `{"message":"..."}`. |
  | 4 | POST `/auth/login` with malformed JSON body | 4xx without stack trace. |
  | 5 | Very large body (>1 MB) on `PUT /me` | Handled without 500. |
  | 6 | GET `/api/v1/unknown` | 404 JSON (with Accept header) / custom 404 page otherwise. |
- **Acceptance criteria:** Documented conventions hold when `Accept: application/json` is sent; no stack traces in any case.
- **Evidence to capture:** Responses and headers.
- **Notes / assumptions / open questions:** The app does not force JSON for `api/*` in `bootstrap/app.php`; mobile clients must send `Accept` as the docs instruct.

## UAT-API-016: Documentation reconciles with behaviour
- **Feature / journey:** `docs/mobile-api.md` accuracy
- **Priority:** P2
- **Role / permissions:** Tester with all roles
- **References:** `docs/mobile-api.md`, `routes/api.php`
- **Preconditions / setup:** Results of UAT-API-001 to 014.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Compare each documented endpoint to a real call | Method, path, parameters and responses match; list every difference. |
  | 2 | Check the statement "Tokens are stored only as hashes" | Confirmed by DB (UAT-API-001 step 6). |
  | 3 | Check the statement "Call POST /api/v1/auth/logout to revoke the current token" | Confirmed or not (UAT-API-005). |
  | 4 | Check "Concierge and admin tokens ... staff endpoints" vs host tokens | Host also allowed (record). |
  | 5 | Check "Missing or invalid tokens return 401; role and ownership violations return 403" | Confirmed (also note: suspended workspace returns 403). |
  | 6 | Check the documented validation response shape | Confirmed. |
- **Acceptance criteria:** A list of verified statements and discrepancies.
- **Evidence to capture:** Comparison table.
- **Notes / assumptions / open questions:** Doc does not mention `PUT /me` fields, `device_name` or the 403 for suspended workspaces.
