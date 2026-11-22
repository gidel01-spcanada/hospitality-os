# Access 04: Multi-tenant isolation (cloud mode)

Source of truth: `app/Http/Middleware/ResolveTenant.php`, `app/Support/CurrentTenant.php`, `app/Models/Concerns/BelongsToTenant.php` (applied to `Establishment`, `Amenity`, `AmenityCategory`, `SiteReview`), route-binding overrides in `Property`, `Reservation`, `User`, `PlatformAdminController`, `AdminMessageController`, `docs/adr/0002-hospitality-os-multi-tenant-architecture.md`.

How tenant context works (from code): the signed-in user's `tenant_id` sets `CurrentTenant` for the request (web via `ResolveTenant`, API via `AuthenticateMobileToken`). Customers have `tenant_id` null and therefore no tenant filter. Properties, reservations and users are protected by route-model-binding overrides (cross-tenant ids resolve to 404), establishments/amenities/reviews by a global scope.

Common setup for all cases ("two-tenant fixture"):
- Cloud mode (`PLATFORM_MODE=cloud`).
- Tenant A: `admin@afrikappart.test` (default tenant) with establishment EA, property PA (published), reservation RA, staff host `uat-host-a@example.test`, message thread TA (customer `uat-cust@example.test` -> EA).
- Tenant B: created via `/host/register` as `owner-b@example.test` ("UAT Stays Beta"), with establishment EB, property PB (published), reservation RB (guest `uat-guest-b@example.test`), concierge `uat-conc-b@example.test`, thread TB.
- Passwords: `<PLACEHOLDER from SEED_*_PASSWORD env / tester-provided>`. IDs EA/PA/RA/TA/EB/PB/RB/TB are recorded by the tester.

---

## UAT-TEN-001: Admin lists and dashboard show only the own tenant's data
- **Feature / journey:** Data separation in admin lists
- **Priority:** P0
- **Role / permissions:** Tenant admin A, tenant admin B
- **References:** `AdminController::index` (stat cards), `/admin/establishments`, `/admin/properties`, `/admin/reservations`, `/admin/reviews`, `/admin/amenities`, `/admin/messages`, `/admin/users`
- **Preconditions / setup:** Two-tenant fixture.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as admin A; open `/admin` | Counters (properties, bookings, users) reflect tenant A only. |
  | 2 | Open `/admin/establishments`, `/admin/properties`, `/admin/reservations`, `/admin/messages` | Only EA, PA, RA, TA appear; nothing from tenant B. |
  | 3 | Open `/admin/amenities` and `/admin/reviews` | Only tenant A catalog and reviews. |
  | 4 | Search for "UAT Stays Beta", `PB`'s name, RB's reference, `uat-guest-b@example.test` in lists and global search boxes | No result. |
  | 5 | Log out; repeat with admin B | Only B data visible; none of A. |
  | 6 | Open `/admin/reports` as admin A and export `/admin/reports/export.csv` | Report and CSV contain tenant A rows only. |
- **Acceptance criteria:** No row of the other tenant is displayed anywhere in admin.
- **Evidence to capture:** Screenshots and the CSV.
- **Notes / assumptions / open questions:** Reports controller scoping not code-reviewed; this step verifies behaviour.

## UAT-TEN-002: Cross-tenant direct URL access is blocked
- **Feature / journey:** Insecure direct object reference (IDOR) across tenants
- **Priority:** P0
- **Role / permissions:** Tenant admin A (also repeat with host A and concierge A for the reservation URLs)
- **References:** `Reservation::resolveRouteBinding`, `Property::resolveRouteBinding`, `User::resolveRouteBinding`, `Establishment` global scope
- **Preconditions / setup:** Two-tenant fixture; logged in as admin A. Record B's ids.
- **Test data:** RB, PB, EB, staff user `uat-conc-b@example.test` (id UB).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/admin/reservations/{RB}` , `/admin/reservations/{RB}/edit`, `/admin/bookings/{RB}` | HTTP 404 each (binding returns null). No data shown. |
  | 2 | PATCH `/admin/reservations/{RB}/status` with `status=cancelled` (replay with tool) | HTTP 404; RB status unchanged. Also try POST `/admin/reservations/{RB}/payment-link` and POST `/admin/reservations/{RB}/confirm-offline-payment`. |
  | 3 | GET `/admin/properties/{PB}/edit`, `/admin/properties/{PB}/calendar`, `/admin/properties/{PB}/availability` | HTTP 404. |
  | 4 | PUT `/admin/properties/{PB}` with a changed `name` | HTTP 404; name unchanged. |
  | 5 | GET `/admin/establishments/{EB}/edit` and PUT `/admin/establishments/{EB}` | HTTP 404; no change. |
  | 6 | GET `/admin/users/{UB}/edit`, PUT and DELETE `/admin/users/{UB}` | HTTP 404; user unchanged. |
  | 7 | GET `/reservations/{RB}/receipt` (staff A) | Denied (404 or 403); record. |
  | 8 | GET `/admin/messages/{TB}` and POST reply | HTTP 403 (see UAT-TEN-004). |
- **Acceptance criteria:** No read or write on tenant B objects; responses carry no B data.
- **Evidence to capture:** Request/response pairs, DB confirmation of no change.
- **Notes / assumptions / open questions:** Exact status code per URL (404 vs 403) is derived from code; record actual. Replays require a valid CSRF token from the tester's session.

## UAT-TEN-003: Customers are shared across tenants (expected visibility)
- **Feature / journey:** Customer accounts span tenants
- **Priority:** P1
- **Role / permissions:** Customer; admins A and B
- **References:** ADR 0002 (customers `tenant_id` null), `AuthController::dashboard`, `AdminController::users`, `User::resolveRouteBinding` comment
- **Preconditions / setup:** Customer `uat-cust@example.test` has reservations at PA and PB.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as the customer; open `/dashboard` with status filter `all` | Reservations from both tenants shown; no tenant branding of the host company. |
  | 2 | Customer opens `/messages` and sends a message to each establishment | Both succeed; threads per establishment. |
  | 3 | Admin A opens `/admin/users` | The customer is listed (customers visible to any admin). |
  | 4 | Admin A opens the customer's edit page | Opens (shared user). Record whether admin A can change this customer's name, role or delete the customer. |
  | 5 | Admin B opens `/admin/reservations` | Sees only reservation(s) at tenant B, including the customer's reservation RB. |
- **Acceptance criteria:** Behaviour matches ADR: shared customer, tenant-scoped reservations.
- **Evidence to capture:** Screenshots of steps 1-4.
- **Notes / assumptions / open questions:** OPEN QUESTION: `User::resolveRouteBinding` lets any tenant admin edit/delete any customer (tenant_id null). If a customer is promoted by admin A (`updateUser` assigns A's tenant) it disappears from admin B's list. Confirm with the product owner whether admins should be able to modify customers who booked with other tenants.

## UAT-TEN-004: Message threads are isolated per tenant and per host
- **Feature / journey:** Admin messaging isolation
- **Priority:** P0
- **Role / permissions:** Concierge/admin of tenant A; concierge of tenant B
- **References:** `AdminMessageController::{index,show,reply,belongsToCurrentTenant}`; `MessageEmailService::queueForMessage` (recipients are staff of the establishment's tenant only)
- **Preconditions / setup:** Two-tenant fixture with TA and TB; customer messaged both.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin A opens `/admin/messages` | Only TA. |
  | 2 | Admin A opens `/admin/messages/{TB}` | HTTP 403. |
  | 3 | Admin A POSTs `/admin/messages/{TB}` with `body=hello` | HTTP 403; no message stored. |
  | 4 | Customer sends a new message to EB; inspect email outbox | `message_received` emails go only to staff of tenant B (role admin, concierge, host with `email_message_updates` on), none to tenant A staff. |
  | 5 | Concierge B opens `/admin/messages/{TA}` | HTTP 403. |
- **Acceptance criteria:** Staff never see or answer threads of another tenant; notification recipients are tenant-bound.
- **Evidence to capture:** Screens, outbox recipients.
- **Notes / assumptions / open questions:** Host-level scoping inside one tenant is in UAT-RBAC-006.

## UAT-TEN-005: Suspending a tenant locks out its staff and API tokens; reactivation restores access
- **Feature / journey:** Tenant suspension
- **Priority:** P0
- **Role / permissions:** Platform admin (actor); tenant B staff; customer
- **References:** `PlatformAdminController::{suspend,activate}` (`POST /platform/tenants/{tenant}/suspend|activate`), `ResolveTenant` (logout + redirect to `/login` with `errors.tenant_suspended` = "This workspace has been suspended. Contact support for help."), `AuthenticateMobileToken` (403 JSON "This workspace has been suspended."), lang `platform.tenant_suspended`, `platform.tenant_activated`
- **Preconditions / setup:** Tenant B active. Concierge B logged in on browser A with an open `/admin` page. Concierge B also holds an API token (login `POST /api/v1/auth/login`). Platform admin on browser B. A customer is logged in on browser C.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Browser B: `/platform/tenants` -> "Suspend" for "UAT Stays Beta" | Redirect to the list with the flash "UAT Stays Beta has been suspended."; tenant status shows "Suspended". |
  | 2 | Browser A: reload `/admin` | Redirect to `/login` with "This workspace has been suspended. Contact support for help." Session ended. |
  | 3 | Browser A: log in again as concierge B | Credentials accepted, then immediately redirected back to `/login` with the same suspended message. |
  | 4 | API: `GET /api/v1/staff/reservations` with the concierge B token | HTTP 403 JSON `{"message":"This workspace has been suspended."}`. |
  | 5 | Browser C (customer): browse `/dashboard`, public property of tenant B | Customer is unaffected. Record whether tenant B's properties remain bookable/visible publicly. |
  | 6 | Browser B: "Activate" the tenant | Flash "UAT Stays Beta has been reactivated."; status "Active". |
  | 7 | Browser A: log in as concierge B | Works; API token works again. |
- **Acceptance criteria:** Suspension blocks all staff web and API access to the tenant and is reversible.
- **Evidence to capture:** Screens of each step, API response.
- **Notes / assumptions / open questions:** OPEN QUESTION: no code hides a suspended tenant's properties from the public catalog or blocks booking; verify and record. A platform admin cannot suspend their own tenant safely (platform admin seed lives in the default tenant); do NOT test suspending the tenant that the platform admin belongs to.

## UAT-TEN-006: New tenant starts empty and independent
- **Feature / journey:** Tenant bootstrap
- **Priority:** P1
- **Role / permissions:** Tenant admin B (new)
- **References:** `TenantSignupController::store` (seeds amenities and settings: `site_name`, `contact_email`, `default_locale`, `secondary_locale`), `AmenityCatalog::seedForTenant`
- **Preconditions / setup:** Fresh signup of "UAT Stays Beta" (UAT-AUTH-022).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/establishments`, `/admin/properties`, `/admin/reservations`, `/admin/reviews` | Empty states; none of tenant A's data. |
  | 2 | Open `/admin/amenities` | Own seeded catalog present. |
  | 3 | Rename an amenity category in B | Tenant A's category with the same name is unchanged (check as admin A). |
  | 4 | Change `/admin/settings` site name in B | Tenant A settings unchanged. |
  | 5 | Create an establishment and property in B and publish | Appears in public `/properties` (UAT-TEN-010); not in admin A lists. |
- **Acceptance criteria:** Configuration and catalogs are per-tenant.
- **Evidence to capture:** Screens from A and B.
- **Notes / assumptions / open questions:** Brand settings and `BrandSettings` per-tenant scoping not fully reviewed; record any leakage.

## UAT-TEN-007: Admin users list respects tenant boundary when search/role filters are used
- **Feature / journey:** Tenant filter on user list
- **Priority:** P1
- **Role / permissions:** Tenant admin A
- **References:** `AdminController::users` and `AdminController::index` (`when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))` followed by `search` / `role` filters)
- **Preconditions / setup:** Two-tenant fixture. Tenant B has staff `uat-conc-b@example.test` (role concierge) and `owner-b@example.test` (role admin). Tenant A has no user named "uat-conc-b".
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As admin A open `/admin/users` (no filter) | Tenant A staff and customers only; no tenant B staff. |
  | 2 | Search `uat-nothing-matches` | No tenant B staff and no unrelated users in the result. |
  | 3 | Filter `role=concierge` | Only concierges of tenant A (and none of tenant B). |
  | 4 | Search `owner-b` | No tenant B user appears. |
  | 5 | Compare the total on the `/admin` dashboard "active users" card with step 1 | Card and list agree; no tenant B users counted. |
- **Acceptance criteria:** No tenant B user is listed under any filter combination.
- **Evidence to capture:** Screens of each filter.
- **Notes / assumptions / open questions:** Possible defect to verify: the controller adds `where tenant_id = X` then `orWhereNull(tenant_id)` at the same query level as the later `search` and `role` clauses, so SQL operator precedence could let filters bind only to the `NULL` branch. Record the real result; if tenant B users appear, raise as a tenant-isolation defect.

## UAT-TEN-008: Platform admin creates a tenant with an invited admin (validation and boundaries)
- **Feature / journey:** Platform tenant provisioning
- **Priority:** P1
- **Role / permissions:** Platform admin
- **References:** `GET/POST /platform/tenants/create|/platform/tenants` (`PlatformAdminController::store`): `name` required max 255; `slug` nullable alpha_dash max 255 unique:tenants,slug; `contact_email` nullable email; `admin_name` required; `admin_email` required email unique:users,email; invitation via `AccountInvitationService`; lang `platform.tenant_created`; test `test_platform_admin_can_create_a_new_tenant_with_an_invited_admin`
- **Preconditions / setup:** Cloud mode; logged in as `admin@afrikappart.test`.
- **Test data:** name `UAT Tenant Gamma`, slug empty / `uat-gamma`, contact `contact-gamma@example.test`, admin `Gamma Admin` / `admin-gamma@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit valid data with empty slug | Redirect to `/platform/tenants`; flash ":name has been created. An invitation email was sent to the administrator."; slug generated `uat-tenant-gamma`; status active. |
  | 2 | Check outbox | `staff_account_invitation` queued for `admin-gamma@example.test`. |
  | 3 | Open the invitation link and set a password; log in | Admin lands in an empty workspace for the new tenant. |
  | 4 | Submit with slug `uat gamma!` | `alpha_dash` error. |
  | 5 | Submit with slug equal to an existing one | Unique error. |
  | 6 | Submit with an existing admin email | Unique error. |
  | 7 | Submit with name only (all other required fields empty) | Required errors for `admin_name`, `admin_email`. |
  | 8 | Create a second tenant with the same name and empty slug | Record outcome (the platform form does not add a numeric suffix; the slug uniqueness is only checked when a slug is typed). |
- **Acceptance criteria:** Valid tenants are created with an invited admin; invalid input is rejected.
- **Evidence to capture:** Screens, outbox, tenant list counts.
- **Notes / assumptions / open questions:** OPEN QUESTION (step 8): with an empty slug the code uses `Str::slug(name)` without uniqueness handling; a duplicate slug may raise a database error. Record the real behaviour.

## UAT-TEN-009: Platform tenant list counts
- **Feature / journey:** Platform overview
- **Priority:** P2
- **Role / permissions:** Platform admin
- **References:** `PlatformAdminController::index` (counts of establishments, properties, users per tenant), view `resources/views/platform/tenants/index.blade.php`
- **Preconditions / setup:** Two-tenant fixture with known counts (e.g. B: 1 establishment, 1 property, 2 users).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/platform/tenants` | Each tenant row shows name, status, Establishments, Properties and Staff counts equal to the fixture. |
  | 2 | Create one more staff user in B, reload | Staff count of B increases by one; A unchanged. |
  | 3 | Suspend/activate buttons | Shown per tenant according to status (record). |
- **Acceptance criteria:** Counts are accurate per tenant.
- **Evidence to capture:** Screenshot, DB counts.
- **Notes / assumptions / open questions:** The user count excludes shared customers (tenant_id null).

## UAT-TEN-010: Public catalog is unified across tenants with platform branding
- **Feature / journey:** Public visibility of multiple tenants
- **Priority:** P2
- **Role / permissions:** Guest
- **References:** ADR 0002 ("one unified catalog"), `config/platform.php` (`brand_name` "Hospitality OS" in cloud mode), `/properties`, `/properties/{slug}`, `/sitemap.xml`
- **Preconditions / setup:** Published properties PA (tenant A) and PB (tenant B).
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As guest open `/properties` | PA and PB both listed. |
  | 2 | Open each property page | No tenant company name shown as owner; platform brand used. |
  | 3 | Open `/sitemap.xml` | URLs for both properties' slugs. |
  | 4 | Unpublish PB (as admin B) | PB disappears from `/properties` and sitemap; direct URL returns 404. |
- **Acceptance criteria:** Guests see an aggregated, unbranded catalog; unpublished items vanish.
- **Evidence to capture:** Screenshots, sitemap.
- **Notes / assumptions / open questions:** Catalog behaviour is owned by the public-booking suite; this case checks only tenant visibility.

## UAT-TEN-011: Foreign ids submitted in forms are not accepted (host assignment, property creation)
- **Feature / journey:** Cross-tenant references in form payloads
- **Priority:** P1
- **Role / permissions:** Tenant admin A
- **References:** `AdminController::storeUser` / `updateUser` (`establishments.*` rule is plain `exists:establishments,id`), `AdminPropertyController::store` (`establishment_id` authorized with `managesEstablishment`, which returns true for any admin and any id), `AdminPropertyController` amenity ids rule (scoped to tenant), `AdminEstablishmentController::storeHost`
- **Preconditions / setup:** Two-tenant fixture; EB id known.
- **Test data:** EB id; synthetic property data.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As admin A, create a host user and submit `establishments[]=<EB id>` (replay with a tool) | Expected: rejected or ignored. Record the actual result and whether the host is linked to EB. |
  | 2 | As admin A, POST `/admin/properties` with `establishment_id=<EB id>` and otherwise valid data | Expected: rejected (403/422/404). Record actual; check that no property appears under EB in tenant B. |
  | 3 | As admin A, POST `/admin/properties` with `amenity_ids[]` from tenant B's catalog | Validation error (rule is tenant-scoped). |
  | 4 | As admin A open `/admin/establishments/{EB}/hosts/create` | HTTP 404 (EB hidden by scope). |
- **Acceptance criteria:** No object of tenant A is attached to tenant B resources and vice versa.
- **Evidence to capture:** Requests and DB rows.
- **Notes / assumptions / open questions:** Steps 1-2 target parameters whose validation is not tenant-scoped in the code reviewed. They may be exploitable only by a tenant admin and are recorded as potential isolation defects; do not assume the outcome.
