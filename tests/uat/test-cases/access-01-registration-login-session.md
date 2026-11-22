# Access 01: Customer registration, login, logout and session behaviour

Scope: `/register`, `/login`, `/logout`, session/CSRF handling, reCAPTCHA hook, locale switching for guests.
Source of truth: `routes/web.php`, `app/Http/Controllers/AuthController.php`, `app/Http/Middleware/{EnsureUserIsActive,SetLocale,ResolveTenant}.php`, `resources/views/auth/*.blade.php`, `resources/lang/{en,fr}/messages.php`, `tests/Feature/AuthAndAdminFlowTest.php`.

Conventions used in every case of this file:
- **Seed accounts** (created by `php artisan db:seed`, `database/seeders/AuthSeeder.php`): `admin@afrikappart.test` (role admin, `is_platform_admin`=true, password from `SEED_ADMIN_PASSWORD`), `guest@afrikappart.test` (customer), `concierge@afrikappart.test` (concierge), `host@afrikappart.test` (host). The last three use `SEED_GUEST_PASSWORD`.
- Passwords are written `<PLACEHOLDER from SEED_*_PASSWORD env / tester-provided>`. Newly registered test users use `*@example.test` emails and a tester-chosen password (`<tester-chosen password, 8+ chars>`).
- Messages quoted below are the English strings from `resources/lang/en/messages.php`; the French equivalents live in `resources/lang/fr/messages.php`. Laravel's own validation sentences come from `resources/lang/{en,fr}/validation.php`; record the exact text seen.

---

## UAT-AUTH-001: Customer registers with valid data and lands on the dashboard
- **Feature / journey:** Self-service customer registration
- **Priority:** P0
- **Role / permissions:** Guest (unauthenticated)
- **References:** `GET/POST /register` (route `register`), `AuthController::register`, `resources/views/auth/register.blade.php`, `AuthAndAdminFlowTest::test_registration_and_customer_dashboard_work`
- **Preconditions / setup:** Application seeded. Browser has no session. `reCAPTCHA` keys not configured (or see UAT-AUTH-013). Email `uat-reg-001@example.test` does not exist.
- **Test data:** name `Amina UAT`, email `uat-reg-001@example.test`, password `<tester-chosen password, 8+ chars>` (same in confirmation), locale `en`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/register` | Form shows fields `name`, `email`, `locale` (select), `password`, `password_confirmation`, submit "Create my account". |
  | 2 | Fill the form with the test data and submit | Redirect to `/dashboard`. User is logged in. UI is in English. |
  | 3 | Check the dashboard | Greeting "Hello, Amina UAT." Account type shows "Customer account". No reservations and no favorites (empty states). |
  | 4 | Open `/account/preferences` | Language = English. "Reservation updates" checked. "Marketing offers" and "Newsletter" unchecked (registration defaults). |
  | 5 | (Admin/DB check by lead) Look up the user | `role=customer`, `is_admin=0`, `tenant_id` empty, `locale=en`. |
- **Acceptance criteria:** A customer account is created, auto-logged-in, redirected to `/dashboard`, with the chosen locale and the documented default email preferences.
- **Evidence to capture:** Screenshots of the form, the dashboard after redirect, preferences page; DB row (role, tenant_id, locale) if available.
- **Notes / assumptions / open questions:** No email verification step exists in code (`MustVerifyEmail` is commented out in `app/Models/User.php`); see UAT-AUTH-021. `is_active` default relies on the DB column default (not set in `register()`); step 2 login success implies it is true.

## UAT-AUTH-002: Registration validation errors and field boundaries
- **Feature / journey:** Registration input validation
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `AuthController::register` rules: `name` required string max 255; `email` required email max 255 unique:users; `password` required string min 8 confirmed; `locale` in fr,en; `resources/lang/*/validation.php`
- **Preconditions / setup:** Existing user `guest@afrikappart.test`. No session.
- **Test data:** See table. Use `<tester-chosen password, 8+ chars>` for valid passwords.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit `/register` with all fields empty (remove `required` via dev tools or submit via a tool) | Back to `/register`, errors on `name`, `email`, `password`. Fields keep old input except passwords. Still a guest. |
  | 2 | email `not-an-email` | Email format error. |
  | 3 | email `guest@afrikappart.test` (already taken) | "email has already been taken"-type error (record exact text). No second account created. |
  | 4 | password of 7 characters, confirmation identical | Min-8 error on `password`. |
  | 5 | password of exactly 8 characters, confirmation identical, other fields valid, unique email `uat-reg-002@example.test` | Accepted: redirect `/dashboard` (boundary pass). |
  | 6 | password valid, `password_confirmation` different | "confirmation does not match" error. |
  | 7 | name of 256 characters | Max-255 error on `name`. Name of exactly 255 characters is accepted. |
  | 8 | Tamper `locale` to `es` (edit select option value) | Validation error on `locale`; no account created. |
  | 9 | Same email as existing user but different letter case (`GUEST@afrikappart.test`) | Record outcome (see notes). |
  | 10 | Switch UI to French and repeat step 3 | Error text appears in French. |
- **Acceptance criteria:** Each invalid input is rejected with a visible, localized error; no user row is created for rejected submissions; 8-character and 255-character boundaries pass.
- **Evidence to capture:** Screenshot per error; list of user emails before/after.
- **Notes / assumptions / open questions:** Case-insensitive uniqueness depends on the DB collation (`unique:users` is a plain DB lookup). Record actual behaviour; do not assume a pass/fail. Registration emails are not trimmed or lower-cased in code; record whether `  Uat@Example.test ` style input is accepted.

## UAT-AUTH-003: Registration cannot be used to self-assign privileged fields
- **Feature / journey:** Mass-assignment / privilege escalation protection on public signup
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `AuthController::register` (role hard-coded `customer`, `is_admin` false; only validated keys are used), `User::$fillable` includes `role`, `is_admin`, `tenant_id`, `is_platform_admin`
- **Preconditions / setup:** Ability to send a crafted POST (browser dev tools or an HTTP client with a valid CSRF token and cookie from `/register`).
- **Test data:** Valid registration data for `uat-reg-003@example.test` plus extra form fields `role=admin`, `is_admin=1`, `is_platform_admin=1`, `tenant_id=1`, `is_active=0`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the crafted POST to `/register` | Redirect to `/dashboard` (registration succeeds). |
  | 2 | Open `/admin` | HTTP 403 "Reservation staff access is required." |
  | 3 | Open `/platform/tenants` | HTTP 403 "Administrator access is required." |
  | 4 | Check the stored user | `role=customer`, `is_admin=0`, `is_platform_admin=0`, `tenant_id` empty, account active. |
- **Acceptance criteria:** Extra privileged fields are ignored; the new user is a plain customer.
- **Evidence to capture:** Request payload, responses of steps 2-3, user record.
- **Notes / assumptions / open questions:** Same check applies to `/host/register` (see UAT-AUTH-023 step on extra fields).

## UAT-AUTH-004: Login redirects each role to its landing page
- **Feature / journey:** Sign-in and role-based landing
- **Priority:** P0
- **Role / permissions:** customer, concierge, host, admin
- **References:** `POST /login` (`AuthController::login`): staff (`canManageReservations()`) -> `route('admin.dashboard')` (`/admin`), others -> `route('dashboard')`; `AuthAndAdminFlowTest::test_admin_login_redirects_to_admin_dashboard`
- **Preconditions / setup:** Seeded accounts. Log out between rows.
- **Test data:** Accounts `guest@afrikappart.test`, `concierge@afrikappart.test`, `host@afrikappart.test`, `admin@afrikappart.test`; passwords `<PLACEHOLDER from SEED_*_PASSWORD env / tester-provided>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as `guest@afrikappart.test` | Redirect `/dashboard`. Header shows no "Admin" button. |
  | 2 | Log in as `concierge@afrikappart.test` | Redirect `/admin`. Header shows admin button. |
  | 3 | Log in as `host@afrikappart.test` | Redirect `/admin`. |
  | 4 | Log in as `admin@afrikappart.test` | Redirect `/admin`. |
  | 5 | As staff, open `/dashboard` | Page loads; shows "Staff access" panel with "Open dashboard" link to `/admin`. |
- **Acceptance criteria:** Landing pages match the table; staff retain access to the customer account area.
- **Evidence to capture:** Final URL and screenshot per role.
- **Notes / assumptions / open questions:** If a public reservation is pending in the session, login redirects to `/reservation/resume` instead (UAT-AUTH-010).

## UAT-AUTH-005: Login failure uses one generic message and does not reveal accounts
- **Feature / journey:** Failed sign-in handling
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `AuthController::login` (back with `errors.credentials`, `onlyInput('email')`); `AuthAndAdminFlowTest::test_login_uses_one_generic_error_for_unknown_email_and_wrong_password`
- **Preconditions / setup:** Seeded accounts. English locale.
- **Test data:** (a) `unknown-uat@example.test` + any password; (b) `admin@afrikappart.test` + wrong password.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit case (a) | Back on `/login`, exactly one message "Invalid email or password." Email field is pre-filled; password field is empty. |
  | 2 | Submit case (b) | Identical message and layout. |
  | 3 | Compare the two pages | No wording that distinguishes "unknown email" from "wrong password". |
  | 4 | Submit with empty email and password | Field-level required errors (see UAT-AUTH-006). |
  | 5 | Repeat step 1 in French | French message from `errors.credentials` in `resources/lang/fr/messages.php`. |
- **Acceptance criteria:** Same message for both failures; no session created; email prefilled, password never echoed.
- **Evidence to capture:** Screenshots of both failures.
- **Notes / assumptions / open questions:** No throttling is declared on `POST /login` (see UAT-AUTH-015).

## UAT-AUTH-006: Login input validation
- **Feature / journey:** Login form validation
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `AuthController::login` rules: `email` required email, `password` required string
- **Preconditions / setup:** None
- **Test data:** Various.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | POST `/login` with empty `email` | Validation error on `email`; no auth attempt. |
  | 2 | email `plainaddress`, any password | Email format error. |
  | 3 | Valid email, empty `password` | Validation error on `password`. |
  | 4 | email with leading/trailing spaces around a valid seed email | Record outcome (accepted or generic failure). |
  | 5 | Very long password (10,000 chars) | Handled without a server error (generic failure message). |
- **Acceptance criteria:** No 500 errors; errors are localized and attached to the right field.
- **Evidence to capture:** Screenshots; HTTP status of each response.
- **Notes / assumptions / open questions:** Step 4 behaviour is not specified in code; record, do not assume.

## UAT-AUTH-007: Deactivated account cannot sign in and an active session is ended
- **Feature / journey:** Account deactivation enforcement
- **Priority:** P0
- **Role / permissions:** Customer; admin (to deactivate)
- **References:** `AuthController::login` (credentials include `is_active=true`), `EnsureUserIsActive` (alias `active`, error `errors.inactive_account` = "This account is inactive."), `AdminController::updateUser`, test `test_admin_can_deactivate_user_and_deactivated_user_cannot_login`
- **Preconditions / setup:** Customer `uat-inactive@example.test` registered and logged in on browser A. Admin logged in on browser B.
- **Test data:** Customer password `<tester-chosen password, 8+ chars>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Browser B: `/admin/users` -> edit the customer -> uncheck active (`is_active=0`) -> save | Redirect `/admin/users`, flash "User updated successfully." |
  | 2 | Browser A: reload `/dashboard` (session still open) | Redirected to `/login` with "This account is inactive."; session ended. |
  | 3 | Browser A: log in again with correct credentials | Fails with "Invalid email or password." (the inactive account is filtered at login). |
  | 4 | Browser B: re-activate the user | Login works again. |
- **Acceptance criteria:** Deactivated users are blocked at login and on the next request of an existing session.
- **Evidence to capture:** Screenshots of steps 2-3; admin edit form.
- **Notes / assumptions / open questions:** Step 3 shows the generic credentials message (not the inactive message) because `is_active=true` is part of the credential query. Record the actual text.

## UAT-AUTH-008: Logout ends the session and protected pages require login again
- **Feature / journey:** Sign-out
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** `POST /logout` (route `logout`, auth+active+locale), `AuthController::logout` (invalidate session, regenerate token, redirect `home`)
- **Preconditions / setup:** Logged in as `guest@afrikappart.test`.
- **Test data:** Seed customer.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Use the header logout control | Redirect to `/` (home); header shows login/sign up. |
  | 2 | Press browser Back, then reload `/dashboard` | Redirect to `/login`; no account data shown. |
  | 3 | Open `/logout` with GET (type the URL) | HTTP 405 (route is POST only) or redirect to login for a guest; no logout side effect for a logged-in user. Record actual. |
  | 4 | Log in on two browsers with the same user, log out in one | The other browser's session stays valid (sessions are independent). Record actual. |
- **Acceptance criteria:** After logout no protected page is reachable without re-authenticating.
- **Evidence to capture:** Screenshots of steps 1-3.
- **Notes / assumptions / open questions:** Step 4 is not specified in code; record behaviour.

## UAT-AUTH-009: Guest and signed-in redirects for auth pages and protected URLs
- **Feature / journey:** Route guards `auth` / `guest`
- **Priority:** P0
- **Role / permissions:** Guest; customer
- **References:** `routes/web.php` groups `['guest','locale']` and `['auth','active','locale']`
- **Preconditions / setup:** Logged out. Then logged in as customer.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As guest open `/dashboard`, `/account`, `/account/preferences`, `/account/security`, `/messages`, `/admin`, `/platform/tenants` | Each redirects to `/login`. |
  | 2 | As guest POST `/logout` | Redirect to `/login` (route requires auth). |
  | 3 | As guest open `/reservations/999999/receipt` | Redirect to `/login`. |
  | 4 | As customer open `/login`, `/register`, `/forgot-password`, `/host/register` | Redirected away (Laravel default for authenticated users; record the target URL). |
  | 5 | As guest open `/dashboard/reservations/1` | Redirect to `/login` (not a 403/404 leak). |
- **Acceptance criteria:** No protected page renders for guests; signed-in users do not see auth forms.
- **Evidence to capture:** URL list with final status/URL.
- **Notes / assumptions / open questions:** The redirect target for signed-in users on guest routes is the framework default and is not configured in `bootstrap/app.php`; record it.

## UAT-AUTH-010: Login resumes a pending public reservation
- **Feature / journey:** Guest starts a reservation, is asked to sign in, then continues
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** `AuthController::login` (`pending_public_reservation` session key -> `route('reservation.resume')`), `PublicPropertyController::resume` and `reserve` (sets `pending_public_reservation` at lines ~288 and ~415), lang `auth.existing_account_login`
- **Preconditions / setup:** Published property with open dates. Existing customer account whose email will be used in the booking form. Logged out.
- **Test data:** Existing customer email `guest@afrikappart.test`; dates in the future.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As guest, submit the reservation form on a property page using the existing customer's email | Redirect to `/login` (or register) with message "An account already exists with this email. Sign in to continue your reservation." (record exact path). |
  | 2 | Log in with that account | Redirect to `/reservation/resume`, then to the reservation/checkout flow with the stored form data. |
  | 3 | Open `/reservation/resume` again | HTTP 404 (session key is pulled once). |
- **Acceptance criteria:** Reservation data survives login exactly once; no duplicate reservation is created on reload.
- **Evidence to capture:** Screens of steps 1-3; reservation count before/after.
- **Notes / assumptions / open questions:** Booking details belong to the booking suite; this case only covers the auth hand-off. Exact trigger conditions are in `PublicPropertyController::reserve`, not fully reviewed here.

## UAT-AUTH-011: Expired CSRF token / expired session on form submit
- **Feature / journey:** CSRF protection and session expiry
- **Priority:** P1
- **Role / permissions:** Guest; customer
- **References:** Laravel CSRF (web group), `resources/views/errors/419.blade.php`, `SESSION_LIFETIME` (example 120 minutes) in `.env.example`, `config/session.php`
- **Preconditions / setup:** Ability to clear cookies or wait for lifetime (or tester lowers `SESSION_LIFETIME` in the test environment).
- **Test data:** Seed customer.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/login`, then delete the session cookie in dev tools, then submit the form | Custom 419 error page (not a stack trace); no login. |
  | 2 | Log in, open `/account/security`, delete the session cookie, submit the form | 419 page or redirect to login; password NOT changed. |
  | 3 | Log in, stay idle beyond session lifetime, open `/dashboard` | Redirect to `/login`. |
  | 4 | POST `/login` from an external page / without `_token` | 419; no login. |
- **Acceptance criteria:** State-changing requests without a valid CSRF token are rejected with a friendly page.
- **Evidence to capture:** Screenshots of 419 page; confirmation password unchanged.
- **Notes / assumptions / open questions:** In `local` environment `EncryptCookies` bypasses encryption for the session and `XSRF-TOKEN` cookies; run this case in a non-local environment.

## UAT-AUTH-012: Remember-me option
- **Feature / journey:** Persistent login
- **Priority:** P2
- **Role / permissions:** Customer
- **References:** `auth/login.blade.php` checkbox `remember` (value 1), `Auth::attempt(..., $request->boolean('remember'))`
- **Preconditions / setup:** Non-local environment.
- **Test data:** Seed customer.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in with "Remember me" checked | A long-lived `remember_web_*` cookie is set. |
  | 2 | Delete only the session cookie, reload `/dashboard` | User is still authenticated. |
  | 3 | Repeat without checking the box | After deleting the session cookie the user is logged out. |
- **Acceptance criteria:** Cookie present only when the box is checked.
- **Evidence to capture:** Cookie list screenshots.
- **Notes / assumptions / open questions:** Password reset rotates the remember token (`resetPassword` sets a new one); see UAT-AUTH-018.

## UAT-AUTH-013: reCAPTCHA behaviour on login, register and host register
- **Feature / journey:** Bot protection hook
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `app/Services/GoogleRecaptchaVerifier.php`, `config/services.php` (`recaptcha.enabled` only when both `RECAPTCHA_SITE_KEY` and `RECAPTCHA_SECRET_KEY` are set), `resources/views/partials/recaptcha.blade.php`, lang `security.captcha_failed`
- **Preconditions / setup:** Run twice: (A) environment without reCAPTCHA keys, (B) environment with test keys (tester-provided, not stored in repo).
- **Test data:** Seed customer credentials; new email `uat-captcha@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | (A) Open `/login` and `/register` | No reCAPTCHA script or hidden `g-recaptcha-response` input. Login and registration work. |
  | 2 | (B) Open `/login` | Page loads the Google script and a hidden `g-recaptcha-response` input. |
  | 3 | (B) POST `/login` with valid credentials but no `g-recaptcha-response` | Back on login with "The security verification failed. Please try again." attached to the `email` field; not logged in. |
  | 4 | (B) Same for `/register` and `/host/register` | Same message; no account created. |
  | 5 | (B) Normal browser submission | Succeeds when Google returns success and score >= `RECAPTCHA_MINIMUM_SCORE` (default 0.5). |
- **Acceptance criteria:** When enabled, a missing/invalid token blocks all three forms; when disabled nothing changes.
- **Evidence to capture:** Screenshots; environment config note (enabled or not).
- **Notes / assumptions / open questions:** Password-reset and forgot-password forms do not call the verifier (not in code). Verifier fails closed on HTTP error (returns false).

## UAT-AUTH-014: Guest language switching and persistence
- **Feature / journey:** FR/EN toggle for visitors
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `GET /language/{locale}` (`language.switch`), `SetLocale` middleware (priority: user locale, `?lang=fr|en`, `locale` cookie, session, brand default, `app.locale`), `AuthController::switchLanguage`, test `test_guest_can_switch_locale_and_only_sees_the_alternate_language`
- **Preconditions / setup:** Clean browser, logged out.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/` | Header shows a switch link only to the other language. |
  | 2 | Click the switch link | Redirected back to the previous page; text in the other language; cookie `locale` set (about one year). |
  | 3 | Close and reopen the browser tab, open `/` | Language kept (cookie). |
  | 4 | Open `/language/de` | Falls back to the app default locale (no error); record resulting language. |
  | 5 | Open `/login?lang=en` then `/register` | English persists on follow-up pages (cookie set from `?lang=`). |
  | 6 | Open `/login?lang=zz` | Invalid value ignored. |
- **Acceptance criteria:** Only `fr` and `en` are accepted; choice persists via cookie.
- **Evidence to capture:** Screenshots, cookie value.
- **Notes / assumptions / open questions:** Step 4 default depends on `BrandSettings::defaultLocale()` / `config('app.locale')`.

## UAT-AUTH-015: Repeated failed logins (throttling check)
- **Feature / journey:** Brute-force resistance
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `routes/web.php` (no `throttle` middleware on `POST /login`, `/register`, `/forgot-password`, `/reset-password`); `routes/api.php` (none on `/api/v1/auth/login`); throttled routes present: `/feedback` (5/min), `/help/chat` (10/min), cleaning status (30/min)
- **Preconditions / setup:** Non-production environment; test account `uat-brute@example.test` registered with a known password.
- **Test data:** Wrong password `<wrong-password-placeholder>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit 10 failed logins in under one minute | Each returns "Invalid email or password." (code declares no lockout). Record whether HTTP 429 or any lockout appears. |
  | 2 | Immediately submit the correct password | Record whether login succeeds. |
  | 3 | Repeat against `POST /api/v1/auth/login` | Record status codes (422 expected per code). |
- **Acceptance criteria:** Behaviour is recorded. If no throttling exists, raise it as a risk (not a test failure unless the product owner requires it).
- **Evidence to capture:** Response codes per attempt.
- **Notes / assumptions / open questions:** OPEN QUESTION: is login throttling a requirement? The repo shows none on web or API login.
