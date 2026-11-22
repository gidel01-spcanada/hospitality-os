# Admin back-office: access control, dashboard, users, settings, preferences, tenants, messaging

Area: UAT-ADM. Source of truth: `routes/web.php`, `AdminController`, `AdminPreferencesController`, `AdminMessageController`, `PlatformAdminController`, middleware `EnsureUserIs*`, `resources/lang/{en,fr}/messages.php`.

**Role model (from code):**
- `admin` = `role=admin` or `is_admin=1`. Passes `reservation-staff`, `establishment-manager`, `admin`.
- `host` = `role=host`. Passes `reservation-staff` and `establishment-manager`. Scoped to establishments linked in `establishment_host`.
- `concierge` = `role=concierge`. Passes `reservation-staff` only (dashboard, reservations/bookings, messages, cleaning).
- `customer` and unauthenticated visitors: no admin access.
- `platform admin` = `is_platform_admin=1`, only for `/platform/tenants*`.

**Seeded local accounts (database/seeders/AuthSeeder.php):** `admin@afrikappart.test`, `host@afrikappart.test`, `concierge@afrikappart.test`, `guest@afrikappart.test`. Passwords come from `SEED_ADMIN_PASSWORD` / `SEED_GUEST_PASSWORD` in the tester's local `.env`. Never write passwords in evidence notes.

Locale note: expected texts below are quoted from `resources/lang/en/messages.php`. Switch UI with `/language/en` or `/language/fr` and compare with the `fr` file when testing French.

---

## UAT-ADM-001: Role access matrix for back-office routes
- **Feature / journey:** Route protection by role (admin, host, concierge, customer, guest)
- **Priority:** P0
- **Role / permissions:** Admin, Host (assigned), Concierge, Customer, unauthenticated
- **References:** `routes/web.php` lines 148-272 (groups `reservation-staff`, `establishment-manager`, `admin`, `platform-admin`); `app/Http/Middleware/EnsureUserIsAdmin.php`, `EnsureUserIsEstablishmentManager.php`, `EnsureUserIsReservationStaff.php`; lang `errors.admin_required`, `errors.establishment_manager_required`, `errors.reservation_staff_required`; `tests/Feature/AuthAndAdminFlowTest.php`, `tests/Feature/PropertyAdminControlsTest.php::test_customer_cannot_access_property_admin_routes`
- **Preconditions / setup:** Seeded admin, host (assigned to at least one establishment), concierge and customer accounts exist and are active. One property exists.
- **Test data:** Accounts `admin@afrikappart.test`, `host@afrikappart.test`, `concierge@afrikappart.test`, `guest@afrikappart.test`. Property id = `P`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Logged out, open `/admin`, `/admin/reservations`, `/admin/properties` | Redirected to `/login` (auth middleware). |
  | 2 | Log in as customer. Open `/admin`, `/admin/reservations`, `/admin/properties`, `/admin/reports`, `/admin/users`, `/admin/logs` | Each returns 403. |
  | 3 | Log in as concierge. Open `/admin`, `/admin/reservations`, `/admin/messages`, `/admin/cleaning` | Each returns 200. |
  | 4 | As concierge open `/admin/properties`, `/admin/establishments`, `/admin/reports`, `/admin/preferences`, `/admin/properties/P/calendar` | 403 with message "Administrator or host access is required." |
  | 5 | As concierge open `/admin/users`, `/admin/settings`, `/admin/amenities`, `/admin/reviews`, `/admin/audit-logs`, `/admin/logs` | 403 with "Administrator access is required." |
  | 6 | Log in as host. Open `/admin`, `/admin/reservations`, `/admin/properties`, `/admin/reports`, `/admin/establishments`, `/admin/preferences`, `/admin/cleaning` | Each returns 200. |
  | 7 | As host open `/admin/users`, `/admin/settings`, `/admin/amenities`, `/admin/reviews`, `/admin/audit-logs`, `/admin/logs`, `/admin/establishments/create` | 403 (admin-only). |
  | 8 | Log in as admin and open every URL from steps 3 to 7 | All return 200. |
  | 9 | As admin (not platform admin) open `/platform/tenants` | 403. |
- **Acceptance criteria:** Every route returns the status listed per role; no admin page renders for a role outside its group; 403 pages show the localized message.
- **Evidence to capture:** Screenshot or status code for each role/URL pair (table), browser network log export for step 4/5/7.
- **Notes / assumptions / open questions:** Concierge has no preferences page (`/admin/preferences` is manager-only), so a concierge cannot change own password/locale from the admin area. Confirm whether intended.

## UAT-ADM-002: Deactivated staff account is logged out on next request
- **Feature / journey:** Account status enforcement
- **Priority:** P1
- **Role / permissions:** Admin (acting), Host (target)
- **References:** `app/Http/Middleware/EnsureUserIsActive.php`; `routes/web.php` (group `auth, active, locale`); `AdminController::updateUser`; lang `errors.inactive_account`
- **Preconditions / setup:** Admin session in browser A. Host `uat-host-inactive@example.test` exists, logged in browser B.
- **Test data:** Host email `uat-host-inactive@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Browser A (admin): `/admin/users`, edit the host, untick "active" (field `is_active`), save | Redirect to `/admin/users` with "User updated successfully." (lang `flash.user_updated`). |
  | 2 | Browser B (host): reload `/admin` | Session is invalidated, redirect to `/login` with the inactive-account error on the email field. |
  | 3 | Browser B: try to log in again | Login is refused or the user is logged out again on the first authenticated request (record which). |
  | 4 | Browser A: re-activate the user | User can log in again. |
- **Acceptance criteria:** A deactivated user cannot use any authenticated page after the next request.
- **Evidence to capture:** Screenshots of the login error, users list showing inactive state.
- **Notes / assumptions / open questions:** Whether login itself is blocked is not determined by the middleware; check `AuthController::login` behavior and record.

## UAT-ADM-003: Admin dashboard statistics and role-based landing
- **Feature / journey:** Dashboard `/admin`
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `routes/web.php` `admin.dashboard`; `AdminController::index`; `resources/views/admin/dashboard.blade.php`; `tests/Feature/PropertyAdminControlsTest.php::test_admin_workspace_links_to_establishments_without_global_menu_link`
- **Preconditions / setup:** Tenant has 2 establishments, 3 properties, 5 reservations with statuses: 2 `pending`, 1 `pending_payment`, 1 `confirmed`, 1 `cancelled`.
- **Test data:** Synthetic reservations with `*@example.test` guests.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as admin, open `/admin` | Page loads; stat cards show establishments, properties, bookings, active users, pending. |
  | 2 | Compare "bookings" with the count of reservations on `/admin/reservations` (no filters) | Equal (5 in this dataset). |
  | 3 | Compare "pending" with reservations in statuses `pending` + `pending_payment` | 3. `pending_validation` is not counted. |
  | 4 | Create one more reservation via `/admin/reservations/create` (status pending) and reload | bookings = 6, pending = 4. |
  | 5 | Check the navigation links shown to admin (establishments link points to `/admin/establishments`) | Link present for admin. Public home `/` does not show that link. |
  | 6 | Repeat as host and concierge | Page loads. Record which menu entries are visible per role. |
- **Acceptance criteria:** Counts match the data; page loads for all three staff roles; no data from another tenant appears.
- **Evidence to capture:** Screenshot of dashboard per role with dataset counts alongside.
- **Notes / assumptions / open questions:** Code observations to confirm with the product owner: (a) `establishments` count uses `Establishment::count()` without explicit tenant filter in the controller; (b) `active_users` counts all tenant users plus users with null tenant (customers) and does not filter on `is_active`; (c) host/concierge see tenant-wide counts, not host-scoped counts.

## UAT-ADM-004: Create user (customer, host, concierge, admin) with invitation
- **Feature / journey:** User management, creation
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `admin.users.create` / `admin.users.store`; `AdminController::storeUser`; `AccountInvitationService`; lang `flash.user_created_invitation_sent`
- **Preconditions / setup:** Admin logged in. Mail transport set to log or a capture tool.
- **Test data:** Name `UAT Customer One`, email `uat-customer-1@example.test`, password `UatPass#2024` + confirmation, locale `en`. Repeat for roles `host` (assign one establishment), `concierge`, `admin` with emails `uat-host-1@example.test`, `uat-concierge-1@example.test`, `uat-admin-1@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/users/create` | Form shows name, email, password, confirmation, role, locale, notification checkboxes, active, establishments. |
  | 2 | Submit customer data | Redirect `/admin/users` with status "User created. A secure password setup invitation has been sent." (lang `flash.user_created_invitation_sent`). User listed. |
  | 3 | Open user edit | Role `customer`, locale `en`, email verified, active. |
  | 4 | Create the host with one establishment ticked | Host listed. Logging in as host shows only that establishment under `/admin/establishments`. |
  | 5 | Create concierge and admin | Concierge sees only staff pages (ADM-001 step 3). New admin can open `/admin/users`. |
  | 6 | Check mail capture / `email_outbox` and password reset mail | A password reset link mail was sent for each user. An `email_outbox` row with `template=staff_account_invitation` and `status=queued` exists. |
  | 7 | Log in as the new customer with the given password | Succeeds (account is pre-verified). |
- **Acceptance criteria:** Each user is created with the chosen role/flags; host-establishment link persists; invitation is triggered.
- **Evidence to capture:** Screenshots of the create form and users list, mail capture, DB rows of `users`, `establishment_host`, `email_outbox`.
- **Notes / assumptions / open questions:** The `staff_account_invitation` outbox template is not in the whitelist of `messages:send-email-notifications`, so that row likely stays `queued` forever. Only the password-reset email is actually delivered. Record observed behavior. Customers get `tenant_id` null; staff get the creating admin's tenant.

## UAT-ADM-005: Create user validation and boundary inputs
- **Feature / journey:** User creation validation
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `AdminController::storeUser` rules; `resources/lang/en/validation.php`
- **Preconditions / setup:** Admin logged in; user `uat-customer-1@example.test` exists.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit empty form | Errors on name, email, password, role, locale. No user created. |
  | 2 | Email = `uat-customer-1@example.test` (duplicate) | Error "email has already been taken" (localized). |
  | 3 | Email = `not-an-email` | Email format error. |
  | 4 | Password `short12` (7 chars) | Min 8 error. Password of exactly 8 chars is accepted (`Abcd#123`). |
  | 5 | Password and confirmation differ | Confirmation error. |
  | 6 | Name of 256 characters | Max 255 error. 255 characters accepted. |
  | 7 | Role `superadmin` (edit HTML select value) | Validation error (allowed: customer, admin, concierge, host). |
  | 8 | Locale `de` | Validation error (allowed: fr, en). |
  | 9 | Role host with `establishments[]=999999` | Validation error "exists:establishments,id". |
- **Acceptance criteria:** Every invalid input is rejected with a field error and no user row/email is created; boundary valid values are accepted.
- **Evidence to capture:** Screenshots of each error; users count before/after.
- **Notes / assumptions / open questions:** Establishment ids from another tenant: the rule is only `exists:establishments,id` and may accept them (global scope on the model decides). Record.

## UAT-ADM-006: Edit user, role change and self-protection
- **Feature / journey:** User management, update
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `AdminController::updateUser`; lang `errors.self_admin_access` ("You cannot remove your own administrator access."), `errors.self_deactivation` ("You cannot deactivate your own account."), `flash.user_updated`
- **Preconditions / setup:** Logged in as `admin@afrikappart.test`. Target user `uat-host-1@example.test` is a host with 1 establishment.
- **Test data:** Second establishment `E2`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Edit target host, tick `E2` as well, save | Both establishments assigned. |
  | 2 | Change target role to `concierge`, save | Establishment links are removed (`sync([])`). `is_admin` false. |
  | 3 | Change role to `admin`, save | `is_admin` true; user can open `/admin/users`. |
  | 4 | Change role to `customer` | `tenant_id` set to null; user loses back-office access. |
  | 5 | Edit own account, set role `host`, save | Back with error "You cannot remove your own administrator access." No change. |
  | 6 | Edit own account, untick active, save | Back with error "You cannot deactivate your own account." |
  | 7 | Change own name only, save | Succeeds. |
  | 8 | Set email to an email owned by another user | Unique error. Keeping own email does not error. |
- **Acceptance criteria:** Role, tenant, establishment assignments follow the role; self lock-out is impossible.
- **Evidence to capture:** Screenshots of each flash/error; DB rows for `users.role`, `is_admin`, `tenant_id`, `establishment_host`.
- **Notes / assumptions / open questions:** An admin can still demote the last other admin; no "last admin" guard exists in code.

## UAT-ADM-007: Delete user preserves history; self-delete blocked
- **Feature / journey:** User deletion
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `AdminController::deleteUser`; lang `flash.user_deleted_cascade`, `errors.self_deactivation`
- **Preconditions / setup:** Customer `uat-del@example.test` has one reservation, one favorite, one audit-log entry attributed to them.
- **Test data:** As above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On `/admin/users`, delete `uat-del@example.test` (confirm dialog) | Redirect to users with the cascade flash message. User no longer listed. |
  | 2 | Open the reservation in `/admin/reservations` | Reservation still exists with the guest's email; `user_id` is null. |
  | 3 | Open `/admin/audit-logs` | Entries from that user remain with no user name (user_id null). |
  | 4 | Try to delete own account (DELETE `/admin/users/{ownId}`) | 422 "You cannot deactivate your own account." |
- **Acceptance criteria:** User and owned tokens/favorites are removed; reservations and audit history remain.
- **Evidence to capture:** Before/after DB counts for `reservations`, `audit_logs`, `property_favorites`.
- **Notes / assumptions / open questions:** Deleting a host who is the only manager of an establishment is allowed; no warning. Deletion itself is not audited by `AuditRecorder` (it is by the generic model listener, see UAT-AUD-001).

## UAT-ADM-008: Users list search, role filter, pagination, tenant isolation
- **Feature / journey:** User list
- **Priority:** P2
- **Role / permissions:** Admin
- **References:** `AdminController::users`; `resources/views/admin/users/index.blade.php`
- **Preconditions / setup:** At least 16 users, including two staff users of another tenant (cloud mode) if available.
- **Test data:** Search terms `uat-`, `concierge`, `ZZZ-no-match`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/users` | Page 1 shows 15 rows; pager to page 2. |
  | 2 | `?search=uat-` | Only users whose name, email or role contains the term. Pagination keeps the query. |
  | 3 | `?role=host` | Only hosts. |
  | 4 | `?search=ZZZ-no-match` | Empty list, no error. |
  | 5 | (Multi-tenant) Check no staff user of another tenant appears | Not listed. Customers (null tenant) are listed. |
- **Acceptance criteria:** Filters work, pagination persists filters, other tenants' staff are hidden.
- **Evidence to capture:** Screenshots of each result.
- **Notes / assumptions / open questions:** The query is `where tenant_id = X or tenant_id is null` and then more `where` clauses; operator precedence could let the OR leak customers past the search/role filter. Check whether `?role=host&search=...` still shows customers. Record.

## UAT-ADM-009: Site settings (brand, contact, email sender) and validation
- **Feature / journey:** Admin settings `/admin/settings`
- **Priority:** P1
- **Role / permissions:** Admin only (host/concierge 403)
- **References:** `AdminController::settings`, `updateSettings`; lang `flash.settings_saved`; `PLATFORM_MODE` in `config/platform.php`
- **Preconditions / setup:** Admin logged in; platform mode `on_premise` (then repeat step 8 in `cloud`).
- **Test data:** site_name `UAT Stay`, site_icon `UA`, contact_email `contact@example.test`, default_locale `en`, secondary_locale `fr`, customer_theme `ocean-coral`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/settings` | Form shows current brand values. |
  | 2 | Fill the test data and save | Redirect with "Site settings saved." Public header/footer show `UAT Stay`. |
  | 3 | Clear `site_name`, save | Required error. |
  | 4 | `site_icon` = `ABCD` (4 chars) | Max 3 error. 3 chars accepted. |
  | 5 | `contact_email` = `bad` | Email error. |
  | 6 | `site_favicon_url` = `javascript:alert(1)` and `ftp-no-url` | URL validation error. |
  | 7 | `default_locale` = `es` | Error (fr or en). |
  | 8 | `customer_theme` empty | On-premise: required error. Cloud: accepted (nullable). Invalid theme name: error. |
  | 9 | Tick `restore_homepage_background_image` with an invalid background URL | Save succeeds; homepage background reverts to default (URL rule skipped). |
  | 10 | Tick `clear_site_favicon` | Favicon URL removed. |
  | 11 | Set `email_sender_email`, subjects and messages; trigger an admin-created reservation | Verify whether sender/subject customizations affect outgoing mail (record). |
- **Acceptance criteria:** Valid settings persist and render; invalid ones are rejected with field errors.
- **Evidence to capture:** Screenshots of form, public header before/after, error messages.
- **Notes / assumptions / open questions:** Email template customization fields (`customer_confirmation_*`, `pre_arrival_*`, `post_stay_*`, `guest_account_setup_*`) are saved, but the queued emails built by `ReservationEmailService` use lang subjects, so their use is unconfirmed. Do not claim they work; record observation.

## UAT-ADM-010: Admin preferences: profile, locale/timezone, theme, password
- **Feature / journey:** `/admin/preferences` (alias `/admin/profile`)
- **Priority:** P1
- **Role / permissions:** Admin, Host (concierge 403)
- **References:** `AdminPreferencesController`; lang `profile.updated`, `admin.locale_preferences_updated`, `admin.theme_updated`, `security.updated`
- **Preconditions / setup:** Logged in as `uat-admin-1@example.test` (see ADM-004) with known password `UatPass#2024`.
- **Test data:** New name `UAT Admin Renamed`, timezone `Africa/Porto-Novo`, theme `dark-admin`, new password `UatPass#2025`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | PUT profile with name/email and wrong `current_password` | Error on `current_password`. Nothing saved. |
  | 2 | PUT profile with correct current password | "Your profile has been updated." (lang `profile.updated`). Name shown in header. |
  | 3 | Change email to another user's email | Unique error. |
  | 4 | Locale `en`, timezone `Africa/Porto-Novo` | Saved; UI switches to English. |
  | 5 | Timezone `Mars/Base` | Validation error (timezone rule). Locale `de` also rejected. |
  | 6 | Theme `dark-admin`, then `emerald`, `gold`, `light-admin`; then `pink` | First four accepted; `pink` rejected. |
  | 7 | Password change: wrong current / new 7 chars / confirmation mismatch | Each rejected with the specific error. |
  | 8 | Password change valid | "Your password has been updated." (lang `security.updated`). Log out, log in with the new password; old password fails. |
- **Acceptance criteria:** Each preference persists and survives logout/login.
- **Evidence to capture:** Screenshots; `users` row values (no password hashes in evidence).
- **Notes / assumptions / open questions:** Changing own email does not reset email verification in code. Confirm if intended.

## UAT-ADM-011: Admin inbox: view threads and reply to a customer
- **Feature / journey:** Messaging from admin side
- **Priority:** P1
- **Role / permissions:** Admin, Host (own establishments), Concierge
- **References:** `AdminMessageController`; routes `admin.messages.index/show/reply`; lang `messages.sent`; `tests/Feature/MessagingTest.php`
- **Preconditions / setup:** Customer `uat-guest-msg@example.test` has a thread with establishment `E1` and one message. Host assigned to `E1` only; second thread exists for `E2`.
- **Test data:** Reply body `Bonjour, votre arrivee est confirmee.`; 5000-char body; 5001-char body.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin opens `/admin/messages` | Threads for both establishments, latest first. |
  | 2 | Open a thread | Messages shown, with most recent reservation (matched by user id or email, same establishment) linked. |
  | 3 | Submit empty reply | Error on `body`. |
  | 4 | Submit valid reply | Redirect to `/admin/messages` with "Message sent." (lang). Message visible to customer at `/messages/{thread}`. |
  | 5 | Submit 5000 chars, then 5001 chars | 5000 accepted; 5001 rejected. |
  | 6 | Log in as host (only `E1`): open `/admin/messages` | Only `E1` threads. GET `/admin/messages/{E2 thread}` returns 403. |
  | 7 | Concierge opens `/admin/messages` and replies | Allowed. |
  | 8 | Customer opens `/admin/messages` | 403. |
  | 9 | As admin of tenant A, open a thread id of tenant B (multi-tenant) | 403. |
- **Acceptance criteria:** Replies persist, show to the customer, respect host and tenant scope.
- **Evidence to capture:** Screenshots of inbox, reply flash, `email_outbox` row (see UAT-MAIL-007).
- **Notes / assumptions / open questions:** Reply POST has no `thread->customer` null guard; threads for deleted customers were not tested. After reply the redirect goes to the inbox, not the thread.

## UAT-ADM-012: Platform tenant creation, suspension and activation (cloud mode)
- **Feature / journey:** Tenant onboarding by platform admin
- **Priority:** P2
- **Role / permissions:** Platform admin (`is_platform_admin`)
- **References:** `PlatformAdminController`; routes `platform.tenants.*`; `config/platform.php`; `tests/Feature/PlatformAdminTest.php`; lang `platform.tenant_created`, `platform.tenant_suspended`, `platform.tenant_activated`
- **Preconditions / setup:** Run once with `PLATFORM_MODE=on_premise` and once with `cloud` (tester environment change only). Platform admin account available.
- **Test data:** Tenant name `UAT Tenant Alpha`, slug `uat-tenant-alpha`, contact `contact-alpha@example.test`, admin `Alpha Admin` / `alpha-admin@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Platform admin opens `/platform/tenants` | Lists tenants with counts of establishments, properties, users. |
  | 2 | On-premise mode: open `/platform/tenants/create` and POST create | 404 (create and store only in cloud mode). |
  | 3 | Cloud mode: open `/platform/tenants/create`, submit valid data | Redirect to index with tenant-created flash. New tenant status `active`. Admin user created with role `admin`, verified, invitation sent. |
  | 4 | Submit duplicate slug, duplicate admin email, slug `bad slug!` (not alpha_dash), empty name | Respective validation errors. |
  | 5 | Leave slug blank | Slug derived from name via `Str::slug`. |
  | 6 | Click suspend, then activate | Status toggles with flash messages. |
  | 7 | While suspended, log in as the tenant admin | Record behavior (blocked or not). |
  | 8 | Tenant admin (non platform admin) opens `/platform/tenants` | 403. |
- **Acceptance criteria:** Tenant and admin are created only in cloud mode; validation errors appear; status toggles persist.
- **Evidence to capture:** Screenshots; `tenants` and `users` rows; mail capture.
- **Notes / assumptions / open questions:** Suspension enforcement for tenant users is not visible in the controller; observe `ResolveTenant` behavior and record. Index and suspend/activate do not check platform mode.

## UAT-ADM-013: Legacy "bookings" URLs mirror reservations
- **Feature / journey:** `/admin/bookings*` aliases
- **Priority:** P2
- **Role / permissions:** Admin, Host, Concierge
- **References:** `routes/web.php` lines 169-174 (`admin.bookings.*`); `AdminReservationController`
- **Preconditions / setup:** One reservation `R` exists.
- **Test data:** Reservation id `R`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/bookings` | Same list as `/admin/reservations`. |
  | 2 | Open `/admin/bookings/R` and `/admin/bookings/R/edit` | Same pages as the reservation equivalents. |
  | 3 | Save the edit form from `/admin/bookings/R/edit` | Works (PUT `admin.bookings.update`) and redirects to `admin.reservations.show`. |
  | 4 | Try `PATCH /admin/bookings/R/status` or `DELETE /admin/bookings/R` | 405/404 (not defined for bookings aliases). |
- **Acceptance criteria:** Aliases behave like the primary routes for the defined verbs.
- **Evidence to capture:** Status codes per URL.
- **Notes / assumptions / open questions:** Aliases lack status/notes/delete routes. Confirm no navigation link uses the aliases for those actions.
