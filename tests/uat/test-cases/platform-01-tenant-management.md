# Platform admin: tenant management (UAT-PLAT-001 to UAT-PLAT-012)

Scope: `/platform/tenants*` routes, `PlatformAdminController`, `EnsureUserIsPlatformAdmin` (`platform-admin` alias), `ResolveTenant` suspension check, `TenantSignupController` (`/host/register`), `AccountInvitationService`.

Common facts taken from the code (not assumptions):
- All `/platform/*` routes sit inside the `auth` + `active` + `locale` group and then the `platform-admin` middleware (`routes/web.php` lines 130 and 266-271). Access depends on `users.is_platform_admin`, not on `role`.
- Tenant creation (`create`, `store`) and the "Add tenant" button exist only when `PLATFORM_MODE=cloud`. `index`, `suspend`, `activate` work in both modes.
- `AuthSeeder` makes `admin@afrikappart.test` both tenant admin of tenant slug `default` and platform admin.
- Suspension is enforced by `ResolveTenant` (web middleware group only). It logs the user out with the message `messages.errors.tenant_suspended`.

Common test data (synthetic): platform admin = seeded `admin@afrikappart.test` (password supplied out of band by the environment owner from `SEED_ADMIN_PASSWORD`; never write it in evidence). New tenant = "UAT Tenant B", admin `tenant-b-admin@example.test`. A second non-platform user: `host-a@example.test` (role `host` in tenant `default`), created through Admin > Users.

---

## UAT-PLAT-001: Platform admin lists tenants with establishment, property and staff counts
- **Feature / journey:** Platform tenant overview
- **Priority:** P0
- **Role / permissions:** Platform admin (`is_platform_admin = true`)
- **References:** `GET /platform/tenants` (`platform.tenants.index`); `PlatformAdminController::index`; `resources/views/platform/tenants/index.blade.php`; `tests/Feature/PlatformAdminTest.php`
- **Preconditions / setup:** Database seeded (`php artisan db:seed`). At least tenant `default` exists with the 4 seeded apartments. Test once with `PLATFORM_MODE=cloud` and once with `PLATFORM_MODE=on_premise`.
- **Test data:** Seeded platform admin account; no extra data.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as the platform admin and open `/platform/tenants`. | HTTP 200. Page title is the localized "tenants" title. A table lists tenant `Default` with slug `default`, status badge "active", and numeric counts for establishments, properties and staff. |
  | 2 | Compare the Properties count for `default` with the property list in Admin > Properties. | Counts match (seed expects 4 properties; confirm against actual data). |
  | 3 | With `PLATFORM_MODE=cloud`, look at the page header. | An "Add tenant" button is visible. |
  | 4 | Switch to `PLATFORM_MODE=on_premise` (see UAT-PLAT-013), reload. | List still renders; the "Add tenant" button is absent. |
  | 5 | Switch the UI language FR/EN. | Labels change language; tenant data does not. |
- **Acceptance criteria:** List shows every tenant row with correct counts and status badge; button visibility follows `PLATFORM_MODE`.
- **Evidence to capture:** Screenshots of steps 1, 3, 4; the Admin > Properties count screenshot.
- **Notes / assumptions / open questions:** Counts are computed per request by loops (one query set per tenant); performance with many tenants is untested. No pagination or search exists on this page.

---

## UAT-PLAT-002: Create a tenant with an invited admin (cloud mode)
- **Feature / journey:** Tenant onboarding by platform admin
- **Priority:** P0
- **Role / permissions:** Platform admin
- **References:** `GET /platform/tenants/create`, `POST /platform/tenants` (`platform.tenants.create`, `platform.tenants.store`); `PlatformAdminController::store`; `AccountInvitationService::send`; `email_outbox` table; `tests/Feature/PlatformAdminTest.php`
- **Preconditions / setup:** `PLATFORM_MODE=cloud` (config cache cleared). `MAIL_MAILER` set to `log` or a mail-capture sandbox. Scheduler or `php artisan messages:send-email-notifications` available (see note on the invitation mail).
- **Test data:** Name `UAT Tenant B`; slug left blank (expect `uat-tenant-b`); contact email `contact-b@example.test`; admin name `Tenant B Admin`; admin email `tenant-b-admin@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As platform admin open `/platform/tenants/create`. | Form with tenant details (name, slug, contact email) and initial admin (name, email). |
  | 2 | Submit the test data with slug blank. | Redirect to `/platform/tenants` with a success flash naming the tenant. |
  | 3 | Find the new row. | Tenant `UAT Tenant B`, slug `uat-tenant-b`, status active, establishments 0, properties 0, staff 1. |
  | 4 | Check mail capture/log. | A password-reset style invitation was generated for `tenant-b-admin@example.test` (Laravel `Password::sendResetLink`). |
  | 5 | Read-only DB check: `email_outbox` for the recipient. | Row with template `staff_account_invitation`, status `queued`. |
  | 6 | Read-only DB check: `users` row for the admin. | `role=admin`, `is_admin=1`, `tenant_id` = new tenant id, `locale=fr`, `is_active=1`, `email_verified_at` set, `is_platform_admin` false/null. |
- **Acceptance criteria:** Tenant and admin created atomically from the user's point of view; admin is not a platform admin; invitation is generated.
- **Evidence to capture:** Screenshots of form, success flash, tenant list row; mail-capture message (redact token); SQL result screenshots (SELECT only).
- **Notes / assumptions / open questions:** (a) The `staff_account_invitation` outbox row is not in the list of templates processed by `messages:send-email-notifications`, so it stays `queued`; the actual reset mail is sent immediately by `Password::sendResetLink`. Confirm with the product owner whether the outbox row is only a log. (b) `store` does not wrap tenant, user and invitation in a transaction: if the invitation throws (mail failure), the tenant and user may remain. To observe this, repeat the creation once with the mail transport deliberately unreachable on a test environment and record the result. (c) New tenant gets no settings rows or amenity catalog from this path, unlike self-serve signup (`TenantSignupController` seeds `AmenityCatalog` and settings). Record what the new admin sees.

---

## UAT-PLAT-003: Tenant creation validation and duplicate protection
- **Feature / journey:** Tenant onboarding validation
- **Priority:** P1
- **Role / permissions:** Platform admin
- **References:** `PlatformAdminController::store` validation rules; `resources/views/platform/tenants/create.blade.php`
- **Preconditions / setup:** `PLATFORM_MODE=cloud`; tenant `UAT Tenant B` from UAT-PLAT-002 exists.
- **Test data:** Duplicate slug `uat-tenant-b`; duplicate admin email `tenant-b-admin@example.test`; invalid slug `bad slug!`; invalid emails `not-an-email`; 300-character name.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the form with every field empty. | Validation errors for name, admin name, admin email; no tenant created; form values preserved. |
  | 2 | Submit with slug `uat-tenant-b`. | Error on slug (unique). |
  | 3 | Submit with a new name but admin email `tenant-b-admin@example.test`. | Error on admin email (unique in users). |
  | 4 | Submit slug `bad slug!`. | Error (alpha_dash rule). |
  | 5 | Submit `contact_email` = `not-an-email`. | Error on contact email. |
  | 6 | Submit a 300-character name. | Error (max 255). |
  | 7 | Submit name `UAT Tenant B` (same name) with a different slug and admin email. | Validation error on name; no tenant or admin user is created. |
  | 8 | Submit a different name that derives to existing slug `uat-tenant-b`, leave slug blank, and use a new admin email. | Validation error on slug; no tenant or admin user is created. |
- **Acceptance criteria:** Invalid and duplicate names/slugs produce field-level validation errors and create nothing; no 500 response.
- **Evidence to capture:** Screenshot per error; for step 7 the page shown and any entry in `storage/logs/error-YYYY-MM-DD.log` (no stack traces shared outside the team).
- **Notes / assumptions / open questions:** Tenant names and slugs must be unique; a generated slug collision is reported against the slug field.: the `unique:tenants,slug` rule runs only on the submitted slug, and the controller uses `Str::slug(name)` when the slug is blank without a uniqueness check (unlike `TenantSignupController::uniqueTenantSlug`). Behavior is not documented.

---

## UAT-PLAT-004: Tenant creation is unavailable outside cloud mode
- **Feature / journey:** Platform mode gating
- **Priority:** P0
- **Role / permissions:** Platform admin
- **References:** `PlatformAdminController::create/store` (`abort_unless(config('platform.mode') === 'cloud', 404)`); `tests/Feature/PlatformAdminTest.php`
- **Preconditions / setup:** `PLATFORM_MODE=on_premise`, config cache cleared.
- **Test data:** Name `Blocked Onprem Tenant`; admin `blocked-onprem@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As platform admin open `/platform/tenants`. | 200; no "Add tenant" button. |
  | 2 | Open `/platform/tenants/create` directly. | HTTP 404. |
  | 3 | Submit `POST /platform/tenants` with valid data (use the browser form from an earlier cached page, or an authenticated HTTP client with a valid CSRF token). | HTTP 404; no tenant, user or outbox row created. |
  | 4 | Open `/host/register` while logged out. | HTTP 404 (self-serve signup is also cloud-only). |
- **Acceptance criteria:** No tenant can be created in on-premise mode by any route.
- **Evidence to capture:** Screenshots/HTTP status of steps 2-4; DB count of `tenants` before and after.
- **Notes / assumptions / open questions:** None.

---

## UAT-PLAT-005: Platform routes deny non-platform users and anonymous visitors
- **Feature / journey:** Platform authorization
- **Priority:** P0
- **Role / permissions:** Anonymous; customer; tenant host; tenant admin without platform flag
- **References:** All `/platform/tenants*` routes; `EnsureUserIsPlatformAdmin`; `EnsureUserIsActive`
- **Preconditions / setup:** `PLATFORM_MODE=cloud`. Users: `guest@afrikappart.test` (customer), `concierge@afrikappart.test`, `host-a@example.test` (host), and `tenant-b-admin@example.test` (tenant admin, `is_platform_admin` false) from UAT-PLAT-002.
- **Test data:** The users above; a valid tenant id for suspend/activate URLs.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Anonymous: GET `/platform/tenants`. | Redirect to `/login`. |
  | 2 | Log in as customer; GET `/platform/tenants`, `/platform/tenants/create`. | HTTP 403 for each. |
  | 3 | Repeat as concierge and host. | HTTP 403. |
  | 4 | Repeat as tenant admin `tenant-b-admin@example.test`. | HTTP 403 (a tenant admin is not a platform admin). |
  | 5 | As tenant admin submit `POST /platform/tenants/{id}/suspend` for tenant `default`. | HTTP 403; tenant `default` stays active. |
  | 6 | Log in as a deactivated user (set `is_active=0` through Admin > Users) and GET `/platform/tenants`. | Logged out, redirect to login with inactive-account message. |
- **Acceptance criteria:** Only `is_platform_admin` users reach any platform route; denied attempts change no data.
- **Evidence to capture:** HTTP status per step (screenshot or HAR); DB status of tenant `default`.
- **Notes / assumptions / open questions:** Roles `host`/`concierge` assertions follow `EnsureUserIsPlatformAdmin` (403 with `messages.errors.admin_required`). The 403 text mentions "admin required"; confirm wording with product owner.

---

## UAT-PLAT-006: Suspending a tenant locks out its users
- **Feature / journey:** Tenant suspension
- **Priority:** P0
- **Role / permissions:** Platform admin (actor); tenant B admin (affected)
- **References:** `POST /platform/tenants/{tenant}/suspend` (`platform.tenants.suspend`); `ResolveTenant`; `messages.errors.tenant_suspended`
- **Preconditions / setup:** Tenant `UAT Tenant B` active with admin `tenant-b-admin@example.test` who has set a password (UAT-PLAT-009). Open a second browser profile logged in as that admin.
- **Test data:** Tenant B and its admin.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | In profile 2 (tenant B admin) open Admin dashboard. | Loads. |
  | 2 | In profile 1 (platform admin) click "Suspend" on tenant B. | Redirect to tenant list with the success flash naming tenant B; status badge becomes "suspended" (red) and the row shows an "Activate" button. |
  | 3 | In profile 2 reload any page or navigate. | Session ended, redirect to `/login` with the "tenant suspended" message. |
  | 4 | In profile 2 log in again with correct credentials. | Login does not result in a usable session; user is again redirected to login with the suspended message. |
  | 5 | As anonymous visitor open tenant B's public pages, if any exist (none exist if no properties). | Record whether public pages of a suspended tenant still render. |
  | 6 | In profile 1 confirm tenant `default` and its users are unaffected. | Platform admin session continues normally. |
- **Acceptance criteria:** Every web request by a user of a suspended tenant is rejected and the session ends; other tenants are unaffected.
- **Evidence to capture:** Screenshots of steps 2-4; audit log entry (see UAT-PLAT-010).
- **Notes / assumptions / open questions:** (a) Enforcement is only in the web middleware group; mobile API tokens (`/api/v1`) are not checked by `ResolveTenant`. Exploratory: see UAT-PLAT-012. (b) Suspend has no confirmation dialog and no reason field. (c) Suspending the tenant that the platform admin belongs to (seeded `default`) logs the platform admin out on the next request and can lock everyone out. Do NOT run that on a shared environment; ask the product owner whether it should be blocked.

---

## UAT-PLAT-007: Reactivating a suspended tenant restores access
- **Feature / journey:** Tenant reactivation
- **Priority:** P0
- **Role / permissions:** Platform admin; tenant B admin
- **References:** `POST /platform/tenants/{tenant}/activate` (`platform.tenants.activate`); `ResolveTenant`
- **Preconditions / setup:** Tenant B suspended (UAT-PLAT-006).
- **Test data:** Tenant B and its admin.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As platform admin click "Activate" on tenant B. | Redirect to list; success flash; badge "active"; button returns to "Suspend". |
  | 2 | As tenant B admin log in. | Login succeeds; Admin dashboard loads. |
  | 3 | Send a repeated activate request to an already active tenant (browser back + resubmit). | No error; tenant remains active. |
  | 4 | Request `POST /platform/tenants/999999/suspend` as platform admin. | HTTP 404. |
- **Acceptance criteria:** Data created by tenant B before suspension is intact and accessible after activation; repeated actions are harmless.
- **Evidence to capture:** Screenshots; list of tenant B records before/after.
- **Notes / assumptions / open questions:** None.

---

## UAT-PLAT-008: Tenant data isolation between tenants
- **Feature / journey:** Multi-tenant isolation
- **Priority:** P0
- **Role / permissions:** Tenant B admin; tenant `default` admin; platform admin
- **References:** `BelongsToTenant` trait (applied to Amenity, AmenityCategory, Establishment, SiteReview); `CurrentTenant`; `ResolveTenant`; `AdminEstablishmentController`; `AdminController` review routes
- **Preconditions / setup:** Tenant B active with admin able to log in. Tenant `default` has establishment(s), properties and at least one review.
- **Test data:** Tenant B admin; IDs of a `default` establishment (`{E}`) and review (`{R}`) read from the `default` admin session.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as tenant B admin, open Admin > Establishments. | List is empty (no `default` data visible). |
  | 2 | Open `/admin/establishments/{E}/edit`. | HTTP 404 or 403, never the edit form. |
  | 3 | `PUT /admin/reviews/{R}` or `DELETE /admin/reviews/{R}` (via a crafted form/HTTP client with valid CSRF). | HTTP 404/403; the review is unchanged. |
  | 4 | Open Admin > Properties, Reservations, Users, Reports. | No `default` records appear. Record any that do. |
  | 5 | Log in as `default` admin and confirm tenant B's data is not visible. | Same isolation in the other direction. |
  | 6 | Open `/admin/audit-logs` and `/admin/logs` as tenant B admin. | Record whether audit entries or log lines from other tenants are visible. |
- **Acceptance criteria:** No record from another tenant can be read or changed through any admin screen.
- **Evidence to capture:** Screenshots of empty lists and denied URLs; notes of any cross-tenant leak (treat as a P0 defect).
- **Notes / assumptions / open questions:** Only four models use `BelongsToTenant`. `Property`, `User`, `Reservation`, `AuditLog` do not use the trait; isolation for them depends on controller-level scoping (e.g. `AdminReservationController::tenantProperties`). Steps 4 and 6 are therefore exploratory and may expose gaps. `docs/adr/0002-hospitality-os-multi-tenant-architecture.md` should be consulted by the product owner for intended scope.

---

## UAT-PLAT-009: Invited tenant admin sets a password and logs in
- **Feature / journey:** Account invitation
- **Priority:** P1
- **Role / permissions:** Invited tenant admin (anonymous at start)
- **References:** `AccountInvitationService`; password-reset routes (`/reset-password/{token}`); `PlatformAdminController::store`
- **Preconditions / setup:** UAT-PLAT-002 completed; mail capture shows the reset link for `tenant-b-admin@example.test`.
- **Test data:** New password chosen by the tester (use a throwaway local value, do not store it in evidence).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the reset link from the captured email. | Reset-password form for that email. |
  | 2 | Set a valid password (min length per the form) and submit. | Success; redirected to login. |
  | 3 | Log in with the new password. | Redirected to the Admin dashboard; locale FR (default for platform-created admins); no email verification prompt (account is pre-verified). |
  | 4 | Reuse the same reset link. | Rejected as invalid/expired. |
  | 5 | Wait longer than the reset token lifetime (see `config/auth.php` `passwords.users.expire`) and use a fresh unused link. | Rejected as expired. Record the configured lifetime. |
- **Acceptance criteria:** Invitation link works exactly once and within its lifetime; admin lands in the correct tenant.
- **Evidence to capture:** Screenshots of steps 1-4 (blur the token); the configured expiry value.
- **Notes / assumptions / open questions:** The email wording is the standard Laravel password-reset email, not a custom invitation template (`messages.account_invitation.subject` is only stored in the outbox payload). Confirm with the product owner that this is acceptable.

---

## UAT-PLAT-010: Suspend and activate actions leave an audit trail
- **Feature / journey:** Operational audit of platform actions
- **Priority:** P2
- **Role / permissions:** Platform admin who is also a tenant admin (seeded admin)
- **References:** `AppServiceProvider::boot` model event listeners (`eloquent.created/updated/deleted: *`); `/admin/audit-logs` (`admin.audit-logs`); `AuditLog`; CHANGELOG M25
- **Preconditions / setup:** UAT-PLAT-002, 006 and 007 executed in this order by the seeded admin.
- **Test data:** Tenant B.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/audit-logs` as the seeded admin. | Page loads. |
  | 2 | Filter by object type `Tenant` (or search by tenant B id). | Entries exist for: created (UAT-PLAT-002), updated with `status` active to suspended (006), updated suspended to active (007). |
  | 3 | Open an entry. | Shows actor user, timestamp, IP, user agent, URL, before/after values; no password or token values. |
  | 4 | Check the entry for the tenant B admin user creation. | Present; the `password` field is not displayed. |
- **Acceptance criteria:** Each platform action has an entry with the correct actor and no secret values.
- **Evidence to capture:** Screenshots of filtered journal.
- **Notes / assumptions / open questions:** Audit entries are generic Eloquent events, not explicit platform audit events. Whether the audit log is tenant-scoped (a tenant B admin viewing platform actions) is unknown; see UAT-PLAT-008 step 6.

---

## UAT-PLAT-011: Self-serve host signup creates a tenant that appears in the platform list
- **Feature / journey:** Self-serve tenant signup (cloud mode)
- **Priority:** P1
- **Role / permissions:** Anonymous visitor; then platform admin
- **References:** `GET/POST /host/register` (`host.register`, `host.register.store`); `TenantSignupController`; `GoogleRecaptchaVerifier`; `AmenityCatalog::seedForTenant`
- **Preconditions / setup:** `PLATFORM_MODE=cloud`. reCAPTCHA keys unset (verifier returns true when disabled) for the main flow; a second run with test reCAPTCHA keys if available.
- **Test data:** Company `UAT Signup Co`; contact `contact-signup@example.test`; name `Signup Owner`; email `signup-owner@example.test`; password chosen by tester.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/host/register` logged out and submit valid data. | Account created, logged in, redirect to `/admin` dashboard with the host-welcome flash. |
  | 2 | As platform admin open `/platform/tenants`. | Tenant `UAT Signup Co` (slug `uat-signup-co`), active, staff count 1. |
  | 3 | Register again with the same company name and a different email. | Second tenant created with slug `uat-signup-co-2`. |
  | 4 | Register with an existing email. | Validation error (email unique), nothing created. |
  | 5 | Submit with password confirmation mismatch or password shorter than 8. | Validation errors. |
  | 6 | With reCAPTCHA enabled and an empty/invalid token. | Error `messages.security.captcha_failed` on the email field; nothing created. |
  | 7 | Check the new admin's Settings and amenities pages. | Site name equals the company name; amenity catalog is present. |
- **Acceptance criteria:** Signup is atomic, slugs are unique, and the tenant is visible and manageable by the platform admin.
- **Evidence to capture:** Screenshots; tenant list row; DB counts.
- **Notes / assumptions / open questions:** `/host/register` has no `throttle` middleware in `routes/web.php`; rate limiting for signup is a master-prompt requirement without implementation evidence (see requirements-gaps.md). Contact email is not verified. The signup admin has no `email_verified_at` set (unlike platform-created admins); record whether the app prompts for verification.

---

## UAT-PLAT-012: Mobile API access for users of a suspended tenant (exploratory)
- **Feature / journey:** Suspension coverage across channels
- **Priority:** P1
- **Role / permissions:** Tenant B staff or admin holding a mobile token
- **References:** `routes/api.php` (`/api/v1/auth/login`, `/api/v1/me`, `/api/v1/staff/reservations`); `AuthenticateMobileToken`; `EnsureMobileRole`; `ResolveTenant` (registered only in the `web` group in `bootstrap/app.php`)
- **Preconditions / setup:** Tenant B has a staff user with a valid mobile API token (obtained via `POST /api/v1/auth/login`). Tenant B is then suspended (UAT-PLAT-006).
- **Test data:** Tenant B staff credentials chosen by the tester (use `staff-b@example.test`, role `concierge`).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Before suspension, call `GET /api/v1/me` and `GET /api/v1/staff/reservations` with the bearer token. | 200 with tenant B data only. |
  | 2 | Suspend tenant B. | As in UAT-PLAT-006. |
  | 3 | Repeat step 1 calls and a fresh `POST /api/v1/auth/login`. | Record the HTTP status and body. |
- **Acceptance criteria:** To be set by the product owner. A strict reading of "suspended tenant" implies the API calls are refused; the code shows no tenant status check on API routes.
- **Evidence to capture:** HTTP requests and responses with the token redacted.
- **Notes / assumptions / open questions:** Open question for the product owner: should suspension also revoke or block mobile tokens? Mark any 200 response after suspension as a defect candidate rather than a pass.
