# Access 03: Roles, permissions and middleware restrictions

Source of truth: `routes/web.php`, `bootstrap/app.php` (middleware aliases), `app/Http/Middleware/EnsureUser*.php`, `app/Models/User.php`, `app/Http/Controllers/AdminController.php` (user management), `database/seeders/AuthSeeder.php`, `tests/Feature/AuthAndAdminFlowTest.php`, `tests/Feature/PlatformAdminTest.php`.

## Role definitions (as implemented)
| Role | How it is determined | Capabilities |
|------|---------------------|--------------|
| Guest | No session | Public pages, auth pages, signed review links, checkout by token. |
| Customer | `users.role = 'customer'` (`tenant_id` null, shared across tenants) | `/dashboard`, `/account*`, `/messages*`, favorites, receipts, reservation modify. Only role allowed on `/messages`. |
| Concierge (reservation staff) | `role = 'concierge'` and `tenant_id` set | `canManageReservations()`: `/admin`, reservations/bookings, admin messages, cleaning. Not properties, establishments, reports. |
| Host | `role = 'host'`, `tenant_id` set, linked to establishments via `establishment_host` | `isEstablishmentManager()`: everything concierge can do plus establishments, properties, reports, admin preferences, but only for assigned establishments. Not users/settings/amenities/reviews/logs. |
| Admin (tenant admin) | `role = 'admin'` or `is_admin = 1` | Everything in own tenant including `/admin/users`, `/admin/settings`, `/admin/amenities`, `/admin/reviews`, `/admin/audit-logs`, `/admin/logs`, reservation delete. |
| Platform admin | `users.is_platform_admin = 1` (independent of `role`; seeded `admin@afrikappart.test` has both) | `/platform/tenants*` (middleware `platform-admin`). Operates inside its own tenant context for other pages. |

Middleware 403 messages (English): reservation-staff `errors.reservation_staff_required` = "Reservation staff access is required."; manager `errors.establishment_manager_required` = "Administrator or host access is required."; admin and platform admin `errors.admin_required` = "Administrator access is required."; messaging `errors.customer_messaging_required` = "Customer messaging access is required."

---

## UAT-RBAC-001: Role-by-route access matrix (read routes)
- **Feature / journey:** Server-side authorization of every route group
- **Priority:** P0
- **Role / permissions:** Guest, customer, concierge, host, tenant admin (no platform flag), platform admin
- **References:** `routes/web.php` groups `auth,active,locale` > `reservation-staff` / `establishment-manager` / `admin` / `platform-admin`; middleware classes listed above
- **Preconditions / setup:** Environment in cloud mode (so `/platform/tenants/create` exists), seeded accounts (`admin@afrikappart.test` = admin + platform admin; `concierge@`, `host@`, `guest@afrikappart.test`) plus a second admin without platform flag, `tenant-admin-uat@example.test`, created via `/admin/users` (role admin). Host `host@afrikappart.test` has at least one assigned establishment.
- **Test data:** Passwords `<PLACEHOLDER from SEED_*_PASSWORD env / tester-provided>`.
- **Steps and expected results:** For every role open each URL in the first column by typing it in the address bar (no UI link), and compare with the expected cell. "L" = redirect to `/login`; "403" = Forbidden page with the message stated under the table; "200" = page renders.
  | # | Action (GET URL) | Guest | Customer | Concierge | Host | Tenant admin | Platform admin |
  |---|------------------|-------|----------|-----------|------|--------------|----------------|
  | 1 | `/dashboard`, `/account`, `/account/preferences`, `/account/security` | L | 200 | 200 | 200 | 200 | 200 |
  | 2 | `/messages` | L | 200 | 403 (m) | 403 (m) | 403 (m) | 403 (m) |
  | 3 | `/admin`, `/admin/reservations`, `/admin/reservations/create`, `/admin/bookings`, `/admin/messages`, `/admin/cleaning`, `/admin/cleaning/create`, `/admin/cleaning/generate` | L | 403 (s) | 200 | 200 | 200 | 200 |
  | 4 | `/admin/reports`, `/admin/reports/export.csv`, `/admin/profile`, `/admin/preferences`, `/admin/establishments`, `/admin/establishments/create`, `/admin/properties`, `/admin/properties/create` | L | 403 (e) | 403 (e) | 200 | 200 | 200 |
  | 5 | `/admin/users`, `/admin/users/create`, `/admin/settings`, `/admin/amenities`, `/admin/reviews`, `/admin/audit-logs`, `/admin/logs` | L | 403 (a) | 403 (a) | 403 (a) | 200 | 200 |
  | 6 | `/platform/tenants` | L | 403 (a) | 403 (a) | 403 (a) | 403 (a) | 200 |
  | 7 | `/platform/tenants/create` | L | 403 (a) | 403 (a) | 403 (a) | 403 (a) | 200 in cloud mode, 404 in on-premise mode |
  | 8 | `/reservation/resume` | L | 404 (no pending reservation) | 404 | 404 | 404 | 404 |
  | 9 | `/login`, `/register`, `/forgot-password` | 200 | redirected away | redirected away | redirected away | redirected away | redirected away |
  | 10 | `/`, `/properties`, `/about`, `/faq`, `/contact` | 200 | 200 | 200 | 200 | 200 | 200 |
  Messages: (m) "Customer messaging access is required."; (s) "Reservation staff access is required."; (e) "Administrator or host access is required."; (a) "Administrator access is required."
- **Acceptance criteria:** Every cell matches. No restricted page renders any data to a role that gets 403. Navigation links shown to a role never lead to a 403 (compare with UAT-RBAC-007).
- **Evidence to capture:** Completed matrix with the actual status/message per cell; screenshot of each distinct 403 page.
- **Notes / assumptions / open questions:** Row 8 is 404 only when no `pending_public_reservation` is in the session. Hosts get 200 on the manager pages but see only their establishments (UAT-RBAC-006). The `/platform/tenants` index, suspend and activate routes are not gated by platform mode; only create/store are.

## UAT-RBAC-002: Write endpoints reject unauthorized roles (not only the pages)
- **Feature / journey:** Server-side enforcement of state-changing requests
- **Priority:** P0
- **Role / permissions:** Customer, concierge, host
- **References:** `POST /admin/users`, `PUT /admin/users/{managedUser}`, `DELETE /admin/users/{managedUser}`, `POST /admin/settings`, `DELETE /admin/reservations/{reservation}` (admin only inside the reservation-staff group), `POST /admin/properties`, `POST /admin/establishments`, `POST /platform/tenants/{tenant}/suspend`
- **Preconditions / setup:** A reservation `UAT-RES-RBAC` exists for the default tenant. Logged in as each role in turn. Tester uses dev tools "Edit and resend" or an HTTP client holding the session cookie and a valid CSRF token (take it from any page of that session).
- **Test data:** Payloads minimal valid for each endpoint (e.g. user `name`, `email` `uat-rbac@example.test`, `password`, `password_confirmation`, `role=admin`, `locale=fr`).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As customer: POST `/admin/users` with role `admin` | HTTP 403 "Administrator access is required." No user created. |
  | 2 | As concierge: POST `/admin/properties`, POST `/admin/establishments` | HTTP 403 "Administrator or host access is required." |
  | 3 | As concierge: DELETE `/admin/reservations/{id}` | HTTP 403 "Administrator access is required." Reservation still exists. |
  | 4 | As host: DELETE `/admin/reservations/{id}`, POST `/admin/settings`, PUT `/admin/users/{id}` | HTTP 403 for each. |
  | 5 | As customer: PATCH `/admin/reservations/{id}/status` | HTTP 403 "Reservation staff access is required."; status unchanged. |
  | 6 | As tenant admin (non-platform): POST `/platform/tenants/{tenant}/suspend` | HTTP 403; tenant status unchanged. |
  | 7 | As guest: any of the above | Redirect to `/login`; no change. |
- **Acceptance criteria:** All attempts are refused and no data changes.
- **Evidence to capture:** Request/response pairs; DB row checks.
- **Notes / assumptions / open questions:** Step 1 expects the admin message because the route sits in the `admin` group; record the actual message.

## UAT-RBAC-003: Admin creates users of every role
- **Feature / journey:** User administration (create)
- **Priority:** P0
- **Role / permissions:** Tenant admin
- **References:** `GET /admin/users/create`, `POST /admin/users` (`AdminController::storeUser`): rules `role in customer,admin,concierge,host`, `locale in fr,en`, password min 8 confirmed, unique email, `establishments.*` exists; flash `flash.user_created_invitation_sent` "User created. A secure password setup invitation has been sent."; tests `test_admin_can_create_a_concierge_user`, `test_admin_can_create_a_host_user`
- **Preconditions / setup:** Logged in as `admin@afrikappart.test`. At least one establishment exists.
- **Test data:** Users `uat-customer@example.test` (customer), `uat-concierge@example.test`, `uat-host@example.test` (assign one establishment), `uat-admin2@example.test`; password `<tester-chosen password, 8+ chars>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create each of the four users with the matching role | Redirect `/admin/users`, flash "User created. A secure password setup invitation has been sent." Each user appears in the list. |
  | 2 | Check the outbox | `staff_account_invitation` email queued for each address. |
  | 3 | Inspect records | Customer: `tenant_id` empty, `is_admin=0`. Concierge/host: `tenant_id` = admin's tenant, `is_admin=0`. Admin: `is_admin=1`, tenant set. All have `email_verified_at` set. |
  | 4 | Log in as the new host | Lands on `/admin`; sees only the assigned establishment. |
  | 5 | Submit the form with `role=superuser` (tamper) | Validation error on `role`; nothing created. |
  | 6 | Submit with a duplicate email, password of 7 chars, mismatched confirmation, empty name | Field errors for each; nothing created. |
  | 7 | Submit with `establishments[]=999999` | Validation error (does not exist). |
  | 8 | Submit with `is_platform_admin=1` extra field | Ignored; created user is not a platform admin. |
- **Acceptance criteria:** Roles map to the flags in the table above; invalid input is rejected.
- **Evidence to capture:** Users list, outbox rows, DB flags.
- **Notes / assumptions / open questions:** A password is required on creation even though an invitation is sent (form field `password`). Tenant isolation of `establishments.*` is covered by UAT-TEN-011.

## UAT-RBAC-004: Admin edits roles and the self-protection rules
- **Feature / journey:** User administration (update)
- **Priority:** P0
- **Role / permissions:** Tenant admin
- **References:** `GET /admin/users/{managedUser}/edit`, `PUT /admin/users/{managedUser}`; errors `errors.self_admin_access` "You cannot remove your own administrator access." and `errors.self_deactivation` "You cannot deactivate your own account."; tests `test_admin_can_manage_user_access_and_preferences`, `test_admin_cannot_remove_own_administrator_access`
- **Preconditions / setup:** Users from UAT-RBAC-003. Logged in as admin A (`admin@afrikappart.test`).
- **Test data:** `uat-customer@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Edit `uat-customer@example.test`: role `concierge` | Saved; flash "User updated successfully."; `tenant_id` set to admin's tenant; `is_admin=0`. |
  | 2 | Edit the same user: role `admin` | `is_admin=1`; user can open `/admin/users` after re-login/next request. |
  | 3 | Edit same user back to `customer` | `tenant_id` becomes empty; admin pages return 403 for the user. |
  | 4 | Edit the host user: change assigned establishments | Sync applied; host sees new set. Change role away from host: assignments cleared. |
  | 5 | As admin A edit own account: role `customer` | Error on `role` "You cannot remove your own administrator access."; role unchanged. |
  | 6 | As admin A edit own account: uncheck active | Error on `is_active` "You cannot deactivate your own account." |
  | 7 | Change a user's email to an existing user's email | Unique error; to its own current email: accepted. |
  | 8 | Check "email verified" on/off | `email_verified_at` set / cleared accordingly. |
- **Acceptance criteria:** Role changes update `role`, `is_admin`, `tenant_id` and host assignments consistently; the last-admin self lockout is prevented.
- **Evidence to capture:** Before/after screenshots and DB values.
- **Notes / assumptions / open questions:** Another admin can still demote or deactivate any admin other than themselves; no "last admin" safeguard beyond self-protection exists in code.

## UAT-RBAC-005: Admin deletes a user (history preserved) and cannot delete self
- **Feature / journey:** User administration (delete)
- **Priority:** P1
- **Role / permissions:** Tenant admin
- **References:** `DELETE /admin/users/{managedUser}` (`AdminController::deleteUser`), flash `flash.user_deleted_cascade` "User and associated personal data deleted. Reservation and payment history was preserved."; abort 422 `errors.self_deactivation` when deleting self
- **Preconditions / setup:** Customer `uat-delete@example.test` with one reservation, one favorite, one mobile token (login via API) and a pending password reset.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin deletes `uat-delete@example.test` (confirm dialog if any) | Redirect `/admin/users` with the cascade flash. User no longer listed. |
  | 2 | Open the reservation in `/admin/reservations` | Still present; no linked account. |
  | 3 | Use the deleted user's old mobile token on `GET /api/v1/me` | 401 `Unauthenticated.` |
  | 4 | Try to log in as the deleted user | "Invalid email or password." |
  | 5 | Admin tries to delete own account | HTTP 422 with "You cannot deactivate your own account." |
- **Acceptance criteria:** Personal data and tokens removed; reservations kept with null `user_id`.
- **Evidence to capture:** Screens; DB counts for tokens/favorites.
- **Notes / assumptions / open questions:** The delete button may be in the edit page or list; record where. Deletion of a staff user from another tenant: UAT-TEN-002.

## UAT-RBAC-006: Host sees and changes only assigned establishments
- **Feature / journey:** Host scoping
- **Priority:** P0
- **Role / permissions:** Host
- **References:** `User::managesEstablishment`, `AdminPropertyController::authorizeProperty`, `AdminReservationController` host filters, `AdminMessageController::tenantThreads`; tests `test_host_is_scoped_to_their_assigned_establishment_only`, `test_host_can_assign_and_remove_additional_hosts_on_their_own_establishment`, `test_host_reservations_and_messages_are_restricted_to_their_establishments`
- **Preconditions / setup:** Tenant has establishments E1 and E2, each with a property and a reservation; message threads exist for both. Host `uat-host@example.test` assigned to E1 only.
- **Test data:** Host login.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `/admin/establishments` and `/admin/properties` | Only E1 and its properties listed. |
  | 2 | `/admin/reservations`, `/admin/messages` | Only E1 reservations/threads. |
  | 3 | Open `/admin/properties/{E2 property}/edit` directly | HTTP 403 "Administrator or host access is required." |
  | 4 | Open `/admin/reservations/{E2 reservation}` and `/admin/reservations/{E2 reservation}/edit` | HTTP 403 (same message). |
  | 5 | Open `/admin/messages/{E2 thread}` and POST a reply | HTTP 403. |
  | 6 | On E1 add another host via `/admin/establishments/{E1}/hosts/create` | Allowed. Removing hosts on E1 allowed. Same actions on E2 are refused. |
  | 7 | Try to create a property with `establishment_id` = E2 | HTTP 403 "Administrator or host access is required." |
  | 8 | `/admin/users`, `/admin/settings` | HTTP 403. |
- **Acceptance criteria:** Host cannot read or modify data of establishments that are not assigned to them.
- **Evidence to capture:** Screens of lists and 403 pages.
- **Notes / assumptions / open questions:** Behaviour of the "create reservation" form for hosts (which establishments are offered) not fully reviewed; record what the property selector lists.

## UAT-RBAC-007: Concierge limits and navigation visibility
- **Feature / journey:** Concierge role
- **Priority:** P1
- **Role / permissions:** Concierge
- **References:** `test_concierge_can_coordinate_reservations_but_not_manage_properties`; layouts under `resources/views/layouts` and `resources/views/admin`
- **Preconditions / setup:** Logged in as `concierge@afrikappart.test`.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin` | Dashboard renders. Sidebar shows reservations/messages/cleaning and a profile link to `/account`. |
  | 2 | Inspect the sidebar | No links to `/admin/users`, `/admin/properties`, `/admin/settings`. |
  | 3 | Type `/admin/properties`, `/admin/users` | HTTP 403. |
  | 4 | Open `/admin/reservations` and one reservation | Renders. Delete control absent; direct DELETE refused (UAT-RBAC-002). |
  | 5 | Open `/account` | Own profile loads. |
- **Acceptance criteria:** Hidden links agree with enforced permissions.
- **Evidence to capture:** Screenshots of nav and 403s.
- **Notes / assumptions / open questions:** Same visibility check for hosts: manager links shown, user/settings links hidden (`test_host_can_manage_establishments_and_properties_but_not_site_configuration`).

## UAT-RBAC-008: Role changes take effect on the next request of a live session
- **Feature / journey:** Permission change without re-login
- **Priority:** P1
- **Role / permissions:** Admin (actor), concierge (subject)
- **References:** Middleware reads `$request->user()` per request; `AdminController::updateUser`
- **Preconditions / setup:** Concierge `uat-concierge@example.test` logged in on browser A at `/admin`. Admin on browser B.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Browser B: change the user's role to `customer` | Saved. |
  | 2 | Browser A: reload `/admin` | HTTP 403 "Reservation staff access is required." |
  | 3 | Browser A: open `/dashboard` | Works as a customer. |
  | 4 | Browser B: change the role back to `host` with E1 | Browser A `/admin/properties` works without re-login. |
- **Acceptance criteria:** Access follows the stored role at once.
- **Evidence to capture:** Screens of steps 2-4.
- **Notes / assumptions / open questions:** Changing role to customer clears `tenant_id`; `ResolveTenant` then runs without a tenant context for that user.

## UAT-RBAC-009: Platform admin versus tenant admin
- **Feature / journey:** Platform administration access
- **Priority:** P0
- **Role / permissions:** Platform admin; tenant admin
- **References:** `EnsureUserIsPlatformAdmin`, `/platform/tenants` routes, `PlatformAdminController`, tests `test_non_platform_admin_cannot_create_a_tenant`, `test_platform_admin_can_create_a_new_tenant_with_an_invited_admin`
- **Preconditions / setup:** Cloud mode. `admin@afrikappart.test` (platform admin) and a tenant admin created through `/host/register` (UAT-AUTH-022).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Tenant admin opens `/platform/tenants` and `/platform/tenants/create` | HTTP 403 "Administrator access is required." |
  | 2 | Tenant admin POSTs `/platform/tenants` | HTTP 403; no tenant created. |
  | 3 | Platform admin opens `/platform/tenants` | List of tenants with name, status and counts (Establishments, Properties, Staff). Button "Add an entity" (`platform.add_tenant`). |
  | 4 | Platform admin opens `/platform/tenants/create`, submits valid data (see UAT-TEN-008) | Tenant created. |
  | 5 | Check whether the platform admin menu is shown to tenant admins | Not shown. |
- **Acceptance criteria:** Only `is_platform_admin` users reach platform functions, regardless of the `admin` role.
- **Evidence to capture:** Screens of 403 and list.
- **Notes / assumptions / open questions:** In on-premise seed data the single admin is also platform admin ("no meaningful distinction with a single tenant" per `AuthSeeder` comment).

## UAT-RBAC-010: Admin users list filters and pagination
- **Feature / journey:** User search and role filter
- **Priority:** P2
- **Role / permissions:** Tenant admin
- **References:** `AdminController::users` (query params `search`, `role`; `paginate(15)`; `withQueryString`)
- **Preconditions / setup:** At least 16 users in scope (create synthetic users `uat-bulk-NN@example.test`).
- **Test data:** search `uat-bulk`, `Concierge`, `zzzz-none`; role `host`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/users` | 15 rows per page, pagination links. |
  | 2 | Search `uat-bulk` | Only matching users (by name, email or role text). Query string kept on page 2. |
  | 3 | Filter `role=host` | Only hosts. |
  | 4 | Search `zzzz-none` | Empty-state / no rows. |
  | 5 | Search with `%` and `_` characters | No error (record whether they act as wildcards). |
  | 6 | Combine search + role filter | Only rows satisfying BOTH conditions appear. |
- **Acceptance criteria:** Filters narrow the list as described.
- **Evidence to capture:** Screens per step.
- **Notes / assumptions / open questions:** See UAT-TEN-007 for a possible tenant-scope interaction with filters (SQL `OR` grouping in `AdminController::users`).
