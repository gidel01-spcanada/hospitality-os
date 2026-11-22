# Access 02: Password reset, email verification status and host (tenant) self-signup

Scope: `/forgot-password`, `/reset-password/{token}`, `/host/register` (cloud mode).
Source of truth: `AuthController::{sendPasswordResetLink,showResetPasswordForm,resetPassword,validCheckoutReturn}`, `TenantSignupController`, `config/auth.php` (`passwords.users.expire` = 60 minutes, `throttle` = 60 seconds), `config/platform.php` (`PLATFORM_MODE` on_premise|cloud), `tests/Feature/CustomerSelfServiceTest.php`, `docs/adr/0002-hospitality-os-multi-tenant-architecture.md`.

Conventions: seeded accounts and password placeholders as in `access-01`. Emails are not actually delivered when `MAIL_MAILER=log`: reset links are read from the mail log / `email_outbox` table / mail catcher the tester has access to.

---

## UAT-AUTH-016: Request a password reset link (valid, unknown and malformed email)
- **Feature / journey:** Forgot password
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `GET/POST /forgot-password` (`password.request`, `password.email`), `AuthController::sendPasswordResetLink`, `resources/views/auth/forgot-password.blade.php`, `CustomerSelfServiceTest::test_password_reset_link_and_support_pages_are_available`
- **Preconditions / setup:** Customer `uat-reset@example.test` exists (register it first). Mail output is observable.
- **Test data:** `uat-reset@example.test`; unknown `nobody-uat@example.test`; malformed `abc`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/login` and click "Forgot your password?" | `/forgot-password` shows heading "Forgot password" and field `email`. |
  | 2 | Submit `uat-reset@example.test` | Back to the form with a success status flash (record the exact text; see notes). A reset email is generated with a link `/reset-password/<token>?email=...`. An `email_outbox` row `password_reset_requested` with status `queued` exists. |
  | 3 | Submit `nobody-uat@example.test` | Error attached to `email` (Laravel broker status for unknown user; record exact text). No email generated. |
  | 4 | Submit `abc` and then an empty field | Email format / required error; no broker call. |
  | 5 | Switch to French and repeat step 2 | Page and flash in French. |
- **Acceptance criteria:** Known accounts receive a link; unknown/malformed input is rejected with a visible error.
- **Evidence to capture:** Screenshots of flash/error; the reset email (token masked in the report); outbox row.
- **Notes / assumptions / open questions:** OPEN QUESTION: the flash uses `__('passwords.sent')`, `__('passwords.user')`, `__('passwords.throttled')`, but `resources/lang/{en,fr}` contains only `messages.php` and `validation.php` (no `passwords.php`). The page may display the raw key (for example `passwords.sent`) instead of a sentence. Record exactly what is shown. Step 3 reveals whether an email is registered (account enumeration); record as an observation.

## UAT-AUTH-017: Reset link request throttle (60 seconds)
- **Feature / journey:** Forgot password rate limit
- **Priority:** P2
- **Role / permissions:** Guest
- **References:** `config/auth.php` `passwords.users.throttle` = 60; `AuthController::sendPasswordResetLink`
- **Preconditions / setup:** Customer `uat-reset@example.test`.
- **Test data:** Same email.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the forgot-password form for `uat-reset@example.test` | Success flash. |
  | 2 | Submit again within 60 seconds | Error on `email` (broker throttled status); no second outbox row `password_reset_requested`. |
  | 3 | Wait 60+ seconds and submit again | Success again. |
- **Acceptance criteria:** A second request inside the window is refused; one after the window succeeds.
- **Evidence to capture:** Screenshots with timestamps; outbox row count.
- **Notes / assumptions / open questions:** Same lang-key question as UAT-AUTH-016.

## UAT-AUTH-018: Reset password with a valid link and sign in with the new password
- **Feature / journey:** Complete password reset
- **Priority:** P0
- **Role / permissions:** Guest (customer owner of the account)
- **References:** `GET /reset-password/{token}` (`password.reset`), `POST /reset-password` (`password.update`), `AuthController::resetPassword` (min 8, confirmed; sets `email_verified_at`, `last_login_at`, new remember token; redirect to `login` with status), `resources/views/auth/reset-password.blade.php`
- **Preconditions / setup:** Reset email from UAT-AUTH-016 (token younger than 60 minutes).
- **Test data:** New password `<tester-chosen new password, 8+ chars>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the emailed link | Form "Reset your password" with `password`, `password_confirmation`; hidden `token` and `email` populated. |
  | 2 | Enter the new password twice and submit | Redirect to `/login` with a success status (record text). |
  | 3 | Log in with the OLD password | Fails: "Invalid email or password." |
  | 4 | Log in with the NEW password | Success, redirect `/dashboard`. |
  | 5 | Open the same reset link again and submit | Rejected (token already used; error on `email`). |
- **Acceptance criteria:** Password is changed, token is single-use, old password invalid.
- **Evidence to capture:** Screens of each step; outbox/log excerpt.
- **Notes / assumptions / open questions:** A successful reset marks the email as verified (`email_verified_at`), including for unverified accounts.

## UAT-AUTH-019: Reset password with invalid token, expired token and invalid input
- **Feature / journey:** Reset failure paths
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `AuthController::resetPassword` validation: `token` required, `email` required email, `password` required min 8 confirmed; config expire 60 minutes
- **Preconditions / setup:** A fresh reset link (UAT-AUTH-016). Ability to wait 60+ minutes or to age the token row in `password_reset_tokens` (lead/DB).
- **Test data:** `<tester-chosen password, 8+ chars>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/reset-password/not-a-real-token?email=uat-reset@example.test`, submit a valid new password | Error on `email` (invalid token status); password unchanged. |
  | 2 | Valid link but passwords differ | Confirmation error; token still usable afterwards. |
  | 3 | Valid link, password of 7 characters | Min-8 error. |
  | 4 | Valid link, password of exactly 8 characters | Accepted. |
  | 5 | Valid token with a different `email` value (edit hidden field) | Rejected; no password change for either account. |
  | 6 | Use a link older than 60 minutes | Rejected as invalid/expired token. |
  | 7 | Hidden `email` removed | Validation error on `email`. |
- **Acceptance criteria:** Only a matching, unexpired token plus a valid password changes the password.
- **Evidence to capture:** Screenshot per failure; confirmation old password still works where applicable.
- **Notes / assumptions / open questions:** The message wording depends on the missing `passwords.*` language file (see UAT-AUTH-016).

## UAT-AUTH-020: Checkout password-setup link only returns to a same-host, token-matched checkout URL
- **Feature / journey:** `checkout_return` handling after reset (open-redirect protection)
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `AuthController::validCheckoutReturn` (host must equal request host; path must match `/reservations/{id}/checkout`; `token` query must equal the reservation `checkout_token`); lang `checkout.password_setup_complete` = "Your email is confirmed and your password has been updated."; `CheckoutController::requestIdentitySetup`
- **Preconditions / setup:** A guest reservation with a `checkout_token` and a password-setup email (flow started on the checkout identity screen `POST /reservations/{reservation}/checkout/password-setup`). Reset link carries `?checkout_return=<url>`.
- **Test data:** Reservation synthetic (guest `uat-checkout@example.test`).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the emailed reset link, set a valid password | Redirect to the checkout page of the reservation with flash "Your email is confirmed and your password has been updated." |
  | 2 | Repeat with `checkout_return=https://evil.example.test/reservations/1/checkout?token=x` | Ignored: redirect to `/login` with the normal reset status. |
  | 3 | Repeat with correct host but wrong `token` value | Ignored: redirect to `/login`. |
  | 4 | Repeat with a correct host but a path other than `/reservations/{id}/checkout` | Ignored: redirect to `/login`. |
- **Acceptance criteria:** Only the exact checkout URL of the matching reservation is honoured.
- **Evidence to capture:** Final URLs per step.
- **Notes / assumptions / open questions:** Checkout/payment behaviour itself belongs to the booking suite.

## UAT-AUTH-021: Unverified email does not block sign-in (verification status display)
- **Feature / journey:** Email verification status
- **Priority:** P2
- **Role / permissions:** Customer; admin
- **References:** `User` (`MustVerifyEmail` commented out), `AuthController::register` (does not set `email_verified_at`), `AdminController::updateUser` (`email_verified` checkbox), lang `dashboard.verified` "Verified", `dashboard.pending_verification` "Pending verification"
- **Preconditions / setup:** Freshly registered customer `uat-unverified@example.test`.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Register, then open `/dashboard`, `/account`, `/messages` | All pages open (no verification gate). |
  | 2 | Check where "Pending verification" / "Verified" is shown (profile, dashboard or admin user list) | Status shown as "Pending verification" for the new user. |
  | 3 | Admin edits the user, checks "email verified", saves | Status becomes "Verified". |
  | 4 | Complete a password reset (UAT-AUTH-018) for an unverified user | Status becomes "Verified". |
- **Acceptance criteria:** Behaviour matches code: no verification gate; verified flag set by admin or by password reset.
- **Evidence to capture:** Screenshots of the status label before/after.
- **Notes / assumptions / open questions:** OPEN QUESTION: no `email/verify` routes exist; confirm the product accepts sign-in without verification. Checkout may require identity setup for unverified customers (`CheckoutController::show`), covered by the booking suite.

## UAT-AUTH-022: Host self-signup creates a tenant workspace (cloud mode)
- **Feature / journey:** Start hosting / tenant signup
- **Priority:** P0
- **Role / permissions:** Guest
- **References:** `GET/POST /host/register` (`host.register`, `host.register.store`), `TenantSignupController`, `AmenityCatalog::seedForTenant`, lang `auth.host_register_title` "Create your Hospitality OS workspace", `auth.host_welcome` "Your workspace is ready. Add your first establishment to get started."
- **Preconditions / setup:** Environment with `PLATFORM_MODE=cloud`. Logged out. Company `UAT Stays Alpha` does not exist.
- **Test data:** company_name `UAT Stays Alpha`; contact_email `contact-alpha@example.test`; name `Alpha Owner`; email `owner-alpha@example.test`; password `<tester-chosen password, 8+ chars>`; locale `fr`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/host/register` | Fields `company_name`, `contact_email`, `name`, `email`, `locale`, `password`, `password_confirmation`; button "Create my workspace". Header shows a "Start hosting" link (shown only in cloud mode). |
  | 2 | Submit valid data | Redirect to `/admin` with flash "Your workspace is ready. ..." (in French if locale fr). User is logged in as admin of the new tenant. |
  | 3 | Inspect admin navigation | Establishments, properties, users, settings, amenities are reachable (admin role). Establishment list empty. |
  | 4 | Open `/admin/amenities` | Amenity catalog was seeded for the new tenant. |
  | 5 | Open `/admin/settings` | Site name shows `UAT Stays Alpha`; contact email `contact-alpha@example.test`; default locale `fr`. |
  | 6 | Check the user record | `role=admin`, `is_admin=1`, `tenant_id` = new tenant; `is_platform_admin` false. |
  | 7 | Open `/platform/tenants` | HTTP 403 "Administrator access is required." (not a platform admin). |
- **Acceptance criteria:** A new isolated tenant plus admin user exist and the admin lands in an empty workspace.
- **Evidence to capture:** Screenshots; tenant slug (`uat-stays-alpha`) from DB/platform list.
- **Notes / assumptions / open questions:** Tenant status is created as `active`. The new tenant appears in `/platform/tenants` for a platform admin (UAT-TEN-008).

## UAT-AUTH-023: Host signup validation, duplicate company names and unusual names
- **Feature / journey:** Tenant signup input validation
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `TenantSignupController::store` rules (company_name, contact_email, name, email unique:users, password min 8 confirmed, locale in fr,en); `uniqueTenantSlug` appends `-2`, `-3`...
- **Preconditions / setup:** `PLATFORM_MODE=cloud`. Tenant `UAT Stays Alpha` exists (UAT-AUTH-022).
- **Test data:** See steps; emails `*@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit empty form | Errors on company_name, contact_email, name, email, password. No tenant created. |
  | 2 | `contact_email` = `bad` | Email format error on `contact_email`. |
  | 3 | `email` = `owner-alpha@example.test` (already used) | Unique error on `email`; no tenant created (the transaction is not started). |
  | 4 | company_name `UAT Stays Alpha` again with new owner email `owner-alpha2@example.test` | Succeeds; second tenant gets slug `uat-stays-alpha-2` (visible in `/platform/tenants` or DB). Data of both tenants is separate. |
  | 5 | company_name of 256 characters | Max-255 error. |
  | 6 | company_name made only of symbols, e.g. `!!!` | Record outcome (slug becomes an empty string in code; see notes). |
  | 7 | Extra fields `role=customer`, `is_platform_admin=1` in the POST | Ignored: user is tenant admin, not platform admin. |
  | 8 | Tamper `locale=es` | Error on `locale`. |
- **Acceptance criteria:** Invalid submissions create nothing; duplicate names get unique slugs.
- **Evidence to capture:** Screens, tenant list, DB slug values.
- **Notes / assumptions / open questions:** OPEN QUESTION (step 6): `Str::slug('!!!')` returns an empty string; the code does not guard against an empty slug. Record actual behaviour (error, empty slug, or other).

## UAT-AUTH-024: Host signup is unavailable in on-premise mode
- **Feature / journey:** `PLATFORM_MODE` gating
- **Priority:** P1
- **Role / permissions:** Guest
- **References:** `TenantSignupController::create/store` (`abort_unless(config('platform.mode') === 'cloud', 404)`), `.env.example` `PLATFORM_MODE=on_premise`, `tests/Feature/PlatformAdminTest::test_tenant_creation_is_unavailable_outside_cloud_mode`
- **Preconditions / setup:** Environment with `PLATFORM_MODE=on_premise`.
- **Test data:** Valid host-signup payload.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/host/register` | HTTP 404 custom page. |
  | 2 | POST valid payload to `/host/register` | HTTP 404; no tenant/user created. |
  | 3 | Check header/home for a "Start hosting" link | Link is absent (`layouts/app.blade.php` renders it only when `platform.mode` is `cloud`). |
  | 4 | Log in as `admin@afrikappart.test` and open `/platform/tenants/create` | HTTP 404 (cloud-only). `/platform/tenants` itself is still reachable for a platform admin. |
- **Acceptance criteria:** No tenant can be created in on-premise mode.
- **Evidence to capture:** Screenshots of 404 pages; header screenshot.
- **Notes / assumptions / open questions:** `PlatformAdminController::index/suspend/activate` do not check platform mode (only `create/store` do).
