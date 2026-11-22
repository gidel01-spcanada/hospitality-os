# Test case traceability

Generated catalog of every baseline case, followed by the requirement and route mapping. Case definitions live in `test-cases/`.

## 1. Requirement and route to case map

| Requirement / route / area | Source | Cases |
|---|---|---|
| Home, search, listing, filters, compare, favorites | `routes/web.php`, `PublicPropertyController` | PUB-001 to 014 |
| Property detail, availability, estimate, reserve, resume | `/properties/{slug}`, `/reservation/resume` | PUB-020 to 037 |
| Establishment page, sitemap, SEO, health, error pages | `/establishments/{slug}`, `/sitemap.xml`, `/health`, `/up` | PUB-011, 012, 060 to 062, OPS-001, 002, SEC-002, SEC-005 |
| Checkout access, tokens, expiry, cancel, pay later | `/reservations/{r}/checkout*`, baseline "payment hold" | PAY-001 to 017 |
| Provider payments (PayPal, FedaPay, CinetPay, M-Pesa), offline proof | `CheckoutController`, `app/Contracts/PaymentGateway.php` | PAY-020 to 034, INT-016 to 019, INT-040 |
| Payment webhooks `POST /webhooks/{provider}` | `routes/web.php`, README "Live Payments" | PAY-027, 028, INT-001 to 015 |
| Receipts and PDF | `ReceiptController`, `resources/views/receipts` | PAY-035, 036, INT-039, RES-016, ACCT-016 |
| Locale, currency, legal, consent, analytics | baseline, README analytics | I18N-001 to 008, LEGAL-001 to 006, INT-041, 042 |
| Accessibility and responsive | baseline "Responsive panels" | A11Y-001 to 004 |
| Help chat, platform feedback | `/help/chat`, `/feedback` | HELP-001 to 006, FEED-001 to 005, PLAT-019 |
| Public iCal export | `/calendar/{slug}/{token}.ics` | CAL-001 to 005, INT-026, 027 |
| Registration, login, logout, reset, session | `AuthController` | AUTH-001 to 024 |
| Roles and permissions | `app/Http/Middleware`, `User` | RBAC-001 to 010, ADM-001, 002, AUD-003 |
| Multi-tenancy | ADR 0002, `ResolveTenant` | TEN-001 to 011, PLAT-001 to 012, PLAT-020 |
| Customer account, reservation modify/cancel, profile | `/dashboard`, `/account` | ACCT-001 to 016 |
| Messaging (customer and admin) | `/messages`, `/admin/messages` | MSG-001 to 009, ADM-011 |
| Reviews (guest submit, admin manage, imports, Google sync) | `/review/{id}`, `/admin/reviews` | REV-001 to 008, REVA-001 to 008, INT-032 to 036, 046 |
| Mobile API | `routes/api.php`, `docs/mobile-api.md` | API-001 to 016 |
| Security hardening | `SecurityHeaders`, config | SEC-001 to 007, OPS-016, 017 |
| Admin dashboard, users, settings, preferences | `/admin*` | ADM-001 to 013 |
| Properties, media, pricing rules, availability, copy improver | `/admin/properties*` | PROP-001 to 019 |
| Amenities catalog | `/admin/amenities*` | PROP-030 to 036 |
| Establishments, taxes, provider enablement | `/admin/establishments*` | EST-001 to 012 |
| Reservations (admin), overlaps, status, proof approval | `/admin/reservations*` | RES-001 to 019 |
| Calendar sync and export | `/admin/calendar*` | CALA-001 to 008, 011, 012, INT-020 to 031 |
| Cleaning schedule | `/admin/cleaning*`, `/cleaning-schedule/{token}` | CLN-001 to 011 |
| Reports and CSV export | `/admin/reports*` | RPT-001 to 006 |
| Audit and error logs | `/admin/audit-logs`, `/admin/logs` | AUD-001 to 005, OPS-018 |
| Email notifications and outbox | `resources/views/emails`, `ReservationEmailService` | MAIL-001 to 009, INT-037, 038 |
| Scheduled jobs and commands | `routes/console.php`, `app/Console/Commands` | JOB-001 to 004, OPS-012 |
| Platform mode and branding | `platform:mode` | JOB-003, PLAT-015 |
| Install, seed, media import, DB export/import, migrations | README, `scripts/` | OPS-003 to 010 |
| Release, deploy, CI, config, repo hygiene | `bluehost-deploy-script.ps1`, `.github/workflows` | OPS-011 to 015, 019, 020 |

## 2. Merged or removed during reconciliation

These cases were dropped as duplicates. Unique angles to keep in mind when running the retained case are listed.

| Removed | Covered by | Extra angle to check during the retained case |
|---|---|---|
| PLAT-013, PLAT-014 | JOB-003 | .env rewrite, missing .env, uppercase input |
| PLAT-016 to 018 | FEED-001 to 005 | Reply-To header, stored record with user_id, mail failure leaves stored row |
| INT-044 | HELP-003 to 006 | 1200-character message limit |
| INT-047 | MAIL-004 | tampered payment-link token |
| CALA-009, CALA-010 | INT-026, INT-027, CAL-001 to 003 | ICS content, token privacy |

Other partly overlapping pairs are kept on purpose because they test from different angles (functional area vs integration): INT-001 to 003/005/006/008 with PAY-027 to 029; INT-020 to 025 with CALA-001 to 008; INT-032 to 036 with REVA-003 to 008; INT-037/038 with MAIL-001/007; INT-039/040 with PAY-031 to 036; OPS-006 with JOB-004; OPS-012 with JOB-001/002.

## 3. Case catalog

| ID | Title | Priority | File |
|---|---|---|---|
| UAT-AUTH-001 | Customer registers with valid data and lands on the dashboard | P0 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-002 | Registration validation errors and field boundaries | P0 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-003 | Registration cannot be used to self-assign privileged fields | P0 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-004 | Login redirects each role to its landing page | P0 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-005 | Login failure uses one generic message and does not reveal accounts | P0 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-006 | Login input validation | P1 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-007 | Deactivated account cannot sign in and an active session is ended | P0 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-008 | Logout ends the session and protected pages require login again | P1 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-009 | Guest and signed-in redirects for auth pages and protected URLs | P0 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-010 | Login resumes a pending public reservation | P1 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-011 | Expired CSRF token / expired session on form submit | P1 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-012 | Remember-me option | P2 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-013 | reCAPTCHA behaviour on login, register and host register | P1 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-014 | Guest language switching and persistence | P1 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-015 | Repeated failed logins (throttling check) | P1 | `test-cases/access-01-registration-login-session.md` |
| UAT-AUTH-016 | Request a password reset link (valid, unknown and malformed email) | P0 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-017 | Reset link request throttle (60 seconds) | P2 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-018 | Reset password with a valid link and sign in with the new password | P0 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-019 | Reset password with invalid token, expired token and invalid input | P0 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-020 | Checkout password-setup link only returns to a same-host, token-matched checkout URL | P1 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-021 | Unverified email does not block sign-in (verification status display) | P2 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-022 | Host self-signup creates a tenant workspace (cloud mode) | P0 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-023 | Host signup validation, duplicate company names and unusual names | P1 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-AUTH-024 | Host signup is unavailable in on-premise mode | P1 | `test-cases/access-02-password-reset-and-host-signup.md` |
| UAT-RBAC-001 | Role-by-route access matrix (read routes) | P0 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-002 | Write endpoints reject unauthorized roles (not only the pages) | P0 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-003 | Admin creates users of every role | P0 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-004 | Admin edits roles and the self-protection rules | P0 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-005 | Admin deletes a user (history preserved) and cannot delete self | P1 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-006 | Host sees and changes only assigned establishments | P0 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-007 | Concierge limits and navigation visibility | P1 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-008 | Role changes take effect on the next request of a live session | P1 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-009 | Platform admin versus tenant admin | P0 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-RBAC-010 | Admin users list filters and pagination | P2 | `test-cases/access-03-roles-and-permissions.md` |
| UAT-TEN-001 | Admin lists and dashboard show only the own tenant's data | P0 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-002 | Cross-tenant direct URL access is blocked | P0 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-003 | Customers are shared across tenants (expected visibility) | P1 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-004 | Message threads are isolated per tenant and per host | P0 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-005 | Suspending a tenant locks out its staff and API tokens; reactivation restores access | P0 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-006 | New tenant starts empty and independent | P1 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-007 | Admin users list respects tenant boundary when search/role filters are used | P1 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-008 | Platform admin creates a tenant with an invited admin (validation and boundaries) | P1 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-009 | Platform tenant list counts | P2 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-010 | Public catalog is unified across tenants with platform branding | P2 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-TEN-011 | Foreign ids submitted in forms are not accepted (host assignment, property creation) | P1 | `test-cases/access-04-multi-tenant-isolation.md` |
| UAT-ACCT-001 | Customer dashboard overview and empty states | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-002 | Dashboard status filter, including invalid values | P1 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-003 | Reservations linked by account id or by matching email (case-insensitive) | P1 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-004 | Reservation detail page content and status-dependent actions | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-005 | Modify dates and guests, price unchanged vs price changed | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-006 | Modification field validation and boundaries | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-007 | Modification business rules (minimum stay, capacity, availability, status) | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-008 | Another customer's reservation cannot be modified or viewed | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-009 | Cancel an unpaid reservation from the dashboard | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-010 | Cancellation is refused for confirmed, completed or already cancelled reservations | P1 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-011 | Update profile name | P1 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-012 | Change password from account security | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-013 | Favorites from the web (add, remove, restrictions) | P1 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-014 | Communication preferences and language | P0 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-015 | Signed-in users keep their saved language (no switcher, link and cookie ignored) | P1 | `test-cases/access-05-customer-account.md` |
| UAT-ACCT-016 | Download receipt as owner, staff and non-owner | P1 | `test-cases/access-05-customer-account.md` |
| UAT-MSG-001 | Customer starts a conversation with an establishment | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-002 | Second message to the same establishment reuses the thread | P1 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-003 | Message validation and boundaries | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-004 | Customer replies inside an existing thread | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-005 | A customer cannot read or write another customer's thread | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-006 | Staff inbox, reply and linked reservation | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-007 | Message email notifications honour the recipient's preference | P1 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-008 | Only customers may use the customer messaging area | P1 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-MSG-009 | Contact the establishment from a reservation page | P1 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-001 | Review request email is created when a stay is completed | P1 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-002 | Guest submits one internal review through the signed link | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-003 | Duplicate or concurrent submissions are rejected | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-004 | Review field validation and boundaries | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-005 | Tampered, expired or unsigned review links are refused | P0 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-006 | Review link for a reservation that is not completed | P1 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-007 | Review channel settings: internal, Google, both | P1 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-REV-008 | A submitted review belongs to the establishment's tenant only | P2 | `test-cases/access-06-messaging-and-reviews.md` |
| UAT-API-001 | Login returns a token and user profile | P0 | `test-cases/access-07-mobile-api.md` |
| UAT-API-002 | Login failures and validation | P0 | `test-cases/access-07-mobile-api.md` |
| UAT-API-003 | Protected endpoints reject missing, malformed, unknown and expired tokens | P0 | `test-cases/access-07-mobile-api.md` |
| UAT-API-004 | Read and update the own profile (`/me`) | P1 | `test-cases/access-07-mobile-api.md` |
| UAT-API-005 | Logout and token lifecycle | P0 | `test-cases/access-07-mobile-api.md` |
| UAT-API-006 | Public catalogue list and detail | P1 | `test-cases/access-07-mobile-api.md` |
| UAT-API-007 | Availability check endpoint | P1 | `test-cases/access-07-mobile-api.md` |
| UAT-API-008 | Customer reservations: list, detail and ownership | P0 | `test-cases/access-07-mobile-api.md` |
| UAT-API-009 | Toggle favorite through the API | P2 | `test-cases/access-07-mobile-api.md` |
| UAT-API-010 | API role access matrix | P0 | `test-cases/access-07-mobile-api.md` |
| UAT-API-011 | Staff endpoints are tenant-scoped | P0 | `test-cases/access-07-mobile-api.md` |
| UAT-API-012 | Staff updates reservation status | P1 | `test-cases/access-07-mobile-api.md` |
| UAT-API-013 | Send payment link and its restrictions | P1 | `test-cases/access-07-mobile-api.md` |
| UAT-API-014 | Suspended workspace and inactive users | P1 | `test-cases/access-07-mobile-api.md` |
| UAT-API-015 | Error format without `Accept: application/json` and unusual requests | P2 | `test-cases/access-07-mobile-api.md` |
| UAT-API-016 | Documentation reconciles with behaviour | P2 | `test-cases/access-07-mobile-api.md` |
| UAT-SEC-001 | Security response headers on every kind of response | P0 | `test-cases/access-08-security-hardening.md` |
| UAT-SEC-002 | Custom error pages, localized, with no technical details | P1 | `test-cases/access-08-security-hardening.md` |
| UAT-SEC-003 | Cookie flags and session protection | P1 | `test-cases/access-08-security-hardening.md` |
| UAT-SEC-004 | User-supplied text is escaped everywhere it is shown | P0 | `test-cases/access-08-security-hardening.md` |
| UAT-SEC-005 | Public health endpoint and information disclosure | P2 | `test-cases/access-08-security-hardening.md` |
| UAT-SEC-006 | User-visible rate limits (429) | P2 | `test-cases/access-08-security-hardening.md` |
| UAT-SEC-007 | Sensitive files and directories are not served | P0 | `test-cases/access-08-security-hardening.md` |
| UAT-ADM-001 | Role access matrix for back-office routes | P0 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-002 | Deactivated staff account is logged out on next request | P1 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-003 | Admin dashboard statistics and role-based landing | P1 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-004 | Create user (customer, host, concierge, admin) with invitation | P0 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-005 | Create user validation and boundary inputs | P1 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-006 | Edit user, role change and self-protection | P0 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-007 | Delete user preserves history; self-delete blocked | P1 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-008 | Users list search, role filter, pagination, tenant isolation | P2 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-009 | Site settings (brand, contact, email sender) and validation | P1 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-010 | Admin preferences: profile, locale/timezone, theme, password | P1 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-011 | Admin inbox: view threads and reply to a customer | P1 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-012 | Platform tenant creation, suspension and activation (cloud mode) | P2 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-ADM-013 | Legacy "bookings" URLs mirror reservations | P2 | `test-cases/admin-01-access-dashboard-users-messaging.md` |
| UAT-PROP-001 | Create property (success path, defaults and publish flag) | P0 | `test-cases/admin-02-properties.md` |
| UAT-PROP-002 | Create property validation and boundaries | P0 | `test-cases/admin-02-properties.md` |
| UAT-PROP-003 | Establishment ownership rules on create and update (host and tenant) | P0 | `test-cases/admin-02-properties.md` |
| UAT-PROP-004 | Edit property general data and FR/EN translations | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-005 | Publish, draft, archive and active controls reflect on public site | P0 | `test-cases/admin-02-properties.md` |
| UAT-PROP-006 | Video URL list normalization and validation | P2 | `test-cases/admin-02-properties.md` |
| UAT-PROP-007 | Upload property photos (success, cover assignment, formats) | P0 | `test-cases/admin-02-properties.md` |
| UAT-PROP-008 | Photo upload limits and invalid files | P0 | `test-cases/admin-02-properties.md` |
| UAT-PROP-009 | Reorder photos, choose cover, set room tags | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-010 | Remove a photo and cover re-assignment | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-011 | Assign amenities from property editor tab and via general save | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-012 | Seasonal price rules and effect on booking price | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-013 | Property add-on features (extras) CRUD | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-014 | Availability blocks (maintenance/owner use): create, delete, boundaries | P0 | `test-cases/admin-02-properties.md` |
| UAT-PROP-015 | Admin availability check page | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-016 | Copy improver: preview suggestion is not saved automatically | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-017 | Copy improver input validation and daily limit | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-018 | Copy improver provider failure and missing configuration | P2 | `test-cases/admin-02-properties.md` |
| UAT-PROP-019 | Property list filters, scoping and access | P1 | `test-cases/admin-02-properties.md` |
| UAT-PROP-030 | Amenity catalog access and category list | P1 | `test-cases/admin-03-amenities-catalog.md` |
| UAT-PROP-031 | Create amenity category (EN/FR names, slug, ordering) | P1 | `test-cases/admin-03-amenities-catalog.md` |
| UAT-PROP-032 | Edit amenity category | P2 | `test-cases/admin-03-amenities-catalog.md` |
| UAT-PROP-033 | Create amenity inside a category (slug uniqueness) | P1 | `test-cases/admin-03-amenities-catalog.md` |
| UAT-PROP-034 | Edit amenity and move to another category | P2 | `test-cases/admin-03-amenities-catalog.md` |
| UAT-PROP-035 | Delete amenity and effect on properties | P1 | `test-cases/admin-03-amenities-catalog.md` |
| UAT-PROP-036 | Category deletion is blocked while it has amenities | P1 | `test-cases/admin-03-amenities-catalog.md` |
| UAT-EST-001 | Establishment list and access by role | P1 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-002 | Create establishment (success) | P0 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-003 | Establishment form validation and boundaries | P1 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-004 | Update general data and currency propagation to properties | P0 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-005 | Cover image upload, property-picture selection and size limits | P1 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-006 | Google Maps link fills missing coordinates | P2 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-007 | Taxes, fees and cancellation policy settings drive reservation totals | P0 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-008 | Enable payment providers in sandbox, instructions and pay-later defaults | P0 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-009 | Production mode is refused when credentials are not configured | P0 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-010 | Offline payment details (Interac, Wise, Revolut) shown to customers | P1 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-011 | Establishment translations (FR/EN) and feature list parsing | P2 | `test-cases/admin-04-establishments-payments.md` |
| UAT-EST-012 | Hosts tab: create host, assign existing user, remove host | P1 | `test-cases/admin-04-establishments-payments.md` |
| UAT-RES-001 | Create reservation from admin (success path) | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-002 | Create reservation validation and boundaries | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-003 | Double-booking prevention (overlap rules) | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-004 | Admin availability blocks and external calendar conflict, with override permission | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-005 | Edit reservation: dates, party size, guest data, price recalculation | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-006 | Reservation list: search, filters, pagination, calendar view | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-007 | Reservation detail page contents and internal notes | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-008 | Viewing a reservation creates a customer account and message thread | P2 | `test-cases/admin-05-reservations.md` |
| UAT-RES-009 | Status changes, internal note append and customer email | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-010 | Cancel releases dates; reopening a cancelled reservation is not re-validated | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-011 | Cancellation of a paid reservation: refund and fee handling | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-012 | Upload payment proof from admin (validation and replacement) | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-013 | Approve an offline payment (confirm with proof, receipt, emails) | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-014 | Rejecting an offline payment proof (gap check) | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-015 | Send payment link to customer | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-016 | Generate and send a receipt manually | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-017 | Delete reservation (admin only) and cleanup | P1 | `test-cases/admin-05-reservations.md` |
| UAT-RES-018 | Tenant and host scoping on reservation actions | P0 | `test-cases/admin-05-reservations.md` |
| UAT-RES-019 | Payment proof download authorization | P1 | `test-cases/admin-05-reservations.md` |
| UAT-CALA-001 | Add an external calendar feed and initial sync | P0 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-002 | Feed form validation | P1 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-003 | Feed with unreachable or failing URL | P0 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-004 | Manual sync updates, removals and cancelled events | P0 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-005 | iCal parsing edge cases | P2 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-006 | External events block bookings (public and admin) while the feed is enabled | P0 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-007 | Edit, disable, re-enable and delete a feed | P1 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-008 | Manual sync of a disabled feed silently re-enables it | P2 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-011 | Scheduled feed sync honors the per-feed interval | P1 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CALA-012 | Calendar access control and cross-property isolation | P0 | `test-cases/admin-06-calendar-sync.md` |
| UAT-CLN-001 | Schedule view: week/month navigation and filters | P1 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-002 | Schedule a cleaning visit manually | P0 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-003 | Assignee double-booking prevention | P0 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-004 | Visit linked to a reservation must belong to the same property | P1 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-005 | Edit visit, status progression, timestamps and deletion | P1 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-006 | Generate visits from departures (no duplicates, sequential slots) | P0 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-007 | Create and revoke a public share link | P0 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-008 | Public schedule page and PDF: privacy and expiry | P0 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-009 | Public status update by cleaner (field execution) | P1 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-010 | Role scoping on cleaning schedule | P0 | `test-cases/admin-07-cleaning.md` |
| UAT-CLN-011 | Overdue indicator for past scheduled visits | P2 | `test-cases/admin-07-cleaning.md` |
| UAT-RPT-001 | Report page default view and metric blocks | P1 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-RPT-002 | Filters and validation (dates, establishment, property) | P1 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-RPT-003 | Occupancy, blocked nights and boundaries | P1 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-RPT-004 | Finance metrics with multiple currencies and payment providers | P1 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-RPT-005 | CSV export content, format and filters | P0 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-RPT-006 | Report scope for hosts and access by role | P0 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-AUD-001 | Audit entries for create, update and delete with redaction | P0 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-AUD-002 | Audit page filters, pagination and validation | P1 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-AUD-003 | Audit and error log access restricted to admins | P0 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-AUD-004 | Error log viewer: file selection, line limit and unsafe parameters | P1 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-AUD-005 | Unhandled errors are written to the error log | P2 | `test-cases/admin-08-reports-audit-logs.md` |
| UAT-REVA-001 | Global review list filters (admin) | P2 | `test-cases/admin-09-review-management-imports.md` |
| UAT-REVA-002 | Create, edit, hide and delete a review manually | P1 | `test-cases/admin-09-review-management-imports.md` |
| UAT-REVA-003 | Global paste-CSV import (generic format) | P2 | `test-cases/admin-09-review-management-imports.md` |
| UAT-REVA-004 | Establishment review import: Booking.com export | P1 | `test-cases/admin-09-review-management-imports.md` |
| UAT-REVA-005 | Establishment review import: Airbnb, Google and generic formats | P1 | `test-cases/admin-09-review-management-imports.md` |
| UAT-REVA-006 | Google review sync from the establishment tab (manual) | P1 | `test-cases/admin-09-review-management-imports.md` |
| UAT-REVA-007 | Command `reviews:sync-google` (all or one establishment) | P2 | `test-cases/admin-09-review-management-imports.md` |
| UAT-REVA-008 | Command `reviews:import-booking-csv` with dry run | P2 | `test-cases/admin-09-review-management-imports.md` |
| UAT-MAIL-001 | Outbox processing command: delivery, ordering, limit and failure status | P0 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-002 | Reservation created email (admin-created reservation) | P0 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-003 | Status update email, including completion review request | P1 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-004 | Payment link and receipt emails (content and links) | P1 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-005 | Payment proof submitted: notification to staff | P1 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-006 | Message reply email to the customer (and back to staff) | P1 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-007 | Mail transport failure handling | P1 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-008 | Customer notification preferences versus reservation emails | P2 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-MAIL-009 | Account invitation email for users created by admin | P1 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-JOB-001 | `reservations:complete-past` and the hourly `CompletePastReservations` job | P0 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-JOB-002 | Scheduler overview and cadence of registered tasks | P1 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-JOB-003 | `platform:mode` command (on-premise vs cloud branding) | P2 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-JOB-004 | `property:sync-media` image import (dry run behavior and expected counts) | P2 | `test-cases/admin-10-emails-and-scheduled-jobs.md` |
| UAT-INT-001 | Webhook without a signature is rejected | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-002 | Webhook with a wrong or tampered signature is rejected | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-003 | Unsupported provider and wrong HTTP method | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-004 | Valid signed "paid" webhook confirms the reservation and issues a receipt (FedaPay sandbox) | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-005 | Duplicate and replayed webhook does not duplicate receipts or bookings | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-006 | Late "failed" webhook after payment does not downgrade the booking | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-007 | Webhook for an unknown payment reference is acknowledged and ignored | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-008 | Failed webhook moves the reservation to payment_failed and the guest can retry | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-009 | Forged or unknown status values are ignored | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-010 | PayPal webhook in sandbox mode (simulated payload) | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-011 | PayPal provider-signed webhook verification (provider sandbox) | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-012 | CinetPay webhook (sandbox config ignored; provider-verified path) | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-013 | M-Pesa (Daraja) callback handling | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-014 | Webhook endpoint accepts provider calls without a browser CSRF token (real HTTP stack) | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-015 | Webhook amount and currency are not compared with the stored attempt (exploratory) | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-016 | Guest cannot confirm payment from the browser; return URL alone does not confirm | P0 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-017 | Checkout provider eligibility, link expiry and non-payable states | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-018 | Webhook reference is matched without checking the provider (exploratory) | P2 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-019 | Paid webhook after the reservation was cancelled (late payment, exploratory) | P1 | `test-cases/integration-01-payment-webhooks.md` |
| UAT-INT-020 | Add an external calendar feed and import events on creation | P0 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-021 | Imported events block public booking and admin reservations (end date exclusive) | P0 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-022 | Re-sync replaces the snapshot (removed, moved, new, cancelled events) | P0 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-023 | Feed outage keeps previous events and shows the error | P0 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-024 | Empty, malformed or non-calendar responses (exploratory) | P1 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-025 | Disabling, editing or deleting a feed releases or retains its dates | P1 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-026 | Authenticated iCalendar export content and privacy | P0 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-027 | Public tokenized export URL | P0 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-028 | Calendar feed and export permissions | P0 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-029 | Scheduled calendar synchronization honors interval and enabled state | P1 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-030 | Feed URL safety checks (exploratory, security) | P1 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-031 | Stale or failed feed does not block booking (exploratory against requirement) | P1 | `test-cases/integration-02-external-calendar-ical.md` |
| UAT-INT-032 | Global generic CSV review import (admin reviews page) | P1 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-033 | Establishment review import: Booking.com CSV, update on re-import | P1 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-034 | Airbnb and Google header-mapped imports, name collisions and missing columns | P1 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-035 | Review import validation and permissions | P1 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-036 | Google reviews sync from a configured CSV/JSON source | P1 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-037 | Email outbox delivery, localization and failure marking | P0 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-038 | Mail outage does not roll back reservations or payments | P0 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-039 | Receipt PDF generation, content and access control | P0 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-040 | Offline payment proof (Interac, Wise, Revolut): guest upload and staff confirmation | P1 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-041 | Analytics is off by default and absent on every page | P0 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-042 | Analytics consent banner: accept, decline, persistence, GTM-only loading | P0 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-043 | reCAPTCHA verification on login, registration, host signup and booking | P1 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-045 | AI property copy assistant limits and failure handling | P2 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-INT-046 | Public pages show only approved review data after an import | P2 | `test-cases/integration-03-reviews-email-receipts-analytics.md` |
| UAT-OPS-001 | Public health endpoint returns safe status JSON | P0 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-002 | Framework health route `/up` and error behavior on failure | P1 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-003 | Clean install and seed from the README | P0 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-004 | Seeding is idempotent | P0 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-005 | Seeded reference data matches the baseline and the documentation | P1 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-006 | Property media import is idempotent and matches 14/12/19/13 photos | P1 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-007 | MySQL seed export and import scripts | P1 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-008 | `database/schema.sql` and migrations describe the same schema | P1 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-009 | Migrations on an existing database are safe and repeatable | P0 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-010 | Backup and restore drill (database and uploads) | P1 | `test-cases/ops-01-health-seed-database.md` |
| UAT-OPS-011 | Release bundle contains what production needs and nothing private | P0 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-012 | Scheduler runs every task: emails, calendar sync, review sync, reservation completion | P0 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-013 | Deployment script preflight, incremental upload and secret handling | P0 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-014 | Post-deploy validation script success and failure detection | P0 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-015 | CI and deploy workflows behave as documented | P1 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-016 | Production configuration, HTTPS and security headers | P0 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-017 | Private files and development artifacts are not reachable from the web | P0 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-018 | Error logging, log viewer and retention | P1 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-019 | Rollback and runbook documentation acceptance | P1 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-OPS-020 | Environment variable inventory and repository hygiene | P0 | `test-cases/ops-02-release-deploy-config.md` |
| UAT-PLAT-001 | Platform admin lists tenants with establishment, property and staff counts | P0 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-002 | Create a tenant with an invited admin (cloud mode) | P0 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-003 | Tenant creation validation and duplicate protection | P1 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-004 | Tenant creation is unavailable outside cloud mode | P0 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-005 | Platform routes deny non-platform users and anonymous visitors | P0 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-006 | Suspending a tenant locks out its users | P0 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-007 | Reactivating a suspended tenant restores access | P0 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-008 | Tenant data isolation between tenants | P0 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-009 | Invited tenant admin sets a password and logs in | P1 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-010 | Suspend and activate actions leave an audit trail | P2 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-011 | Self-serve host signup creates a tenant that appears in the platform list | P1 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-012 | Mobile API access for users of a suspended tenant (exploratory) | P1 | `test-cases/platform-01-tenant-management.md` |
| UAT-PLAT-015 | Public branding and navigation follow the platform mode | P1 | `test-cases/platform-02-mode-branding-feedback.md` |
| UAT-PLAT-019 | Feedback records can be reviewed for triage (no in-app screen) | P2 | `test-cases/platform-02-mode-branding-feedback.md` |
| UAT-PLAT-020 | Platform admin dual role in on-premise installs (single tenant) | P2 | `test-cases/platform-02-mode-branding-feedback.md` |
| UAT-PUB-001 | Home page renders for an anonymous visitor | P0 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-002 | Home search form redirects to the listing with the selected filters | P0 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-003 | Home and listing date validation (past, inverted and equal dates) | P1 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-004 | Listing filters combine correctly | P1 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-005 | Date filter excludes reserved, blocked and external-calendar conflicts | P0 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-006 | Listing validation for boundary and invalid filter values | P1 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-007 | Sorting, legacy city parameter and empty-result state | P2 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-008 | Compare two or three properties | P2 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-009 | Compare validation and mixed-currency rejection | P2 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-010 | Favorites require login; customers can toggle and filter favorites | P2 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-011 | Establishment landing page | P1 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-012 | Sitemap lists public pages, published properties and active establishments | P2 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-013 | Public reviews page, pagination and back link | P2 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-014 | Static pages (About, FAQ, Contact) content and links | P2 | `test-cases/public-01-home-search-listing.md` |
| UAT-PUB-020 | Property detail page renders for a published property | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-021 | Unpublished, inactive and inactive-establishment properties return 404 | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-022 | Detail page query prefill and its validation | P2 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-023 | Check availability for free dates shows estimated total | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-024 | Availability reports unavailable for reserved, blocked and external-calendar dates | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-025 | Minimum stay enforced on availability check | P1 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-026 | Availability endpoint input validation | P1 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-027 | Optional extras change the price and appear as price lines | P1 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-028 | New guest reservation requires email confirmation before the reservation is created | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-029 | Existing verified customer must sign in, then resumes the reservation | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-030 | Existing account with unverified email gets a confirmation mail | P1 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-031 | Logged-in customer reserves without re-entering name and email | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-032 | Reservation form validation (required fields, formats, limits) | P1 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-033 | Reserve rejects unavailable dates, minimum-stay violations and double booking | P0 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-034 | reCAPTCHA failure blocks anonymous reservation | P2 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-035 | Pricing consistency between estimate, checkout and price lines | P1 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-036 | Reservation placed by a staff/admin account is not treated as a customer booking | P2 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PUB-037 | Guest count above the property's capacity | P1 | `test-cases/public-02-property-detail-booking.md` |
| UAT-PAY-001 | Open checkout through the tokenized link | P0 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-002 | Invalid or missing checkout token redirects to the access-recovery page | P0 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-003 | State-changing checkout actions reject a bad token with 403 | P0 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-004 | Identity setup screen for guests without a confirmed account | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-005 | Guest skips identity setup and continues to payment | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-006 | Verified owner reaches checkout from the dashboard without a token | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-007 | Payment methods offered follow establishment configuration and currency rules | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-008 | Expired checkout link (older than 48 hours) | P0 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-009 | Payment cannot be started for non-payable statuses | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-010 | Guest cancels an unpaid reservation without a fee | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-011 | Cancellation inside the fee window adds a non-refundable fee line | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-012 | Cancel is rejected after confirmation or proof submission | P2 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-013 | Pay on arrival without a cancellation guarantee | P0 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-014 | Pay on arrival with a cancellation guarantee hold | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-015 | Start-payment input validation | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-016 | Payment simulation is available only in local/testing or for staff | P1 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-017 | Secondary-currency (EUR) display on checkout | P2 | `test-cases/public-03-checkout-access-and-lifecycle.md` |
| UAT-PAY-020 | PayPal Smart Buttons create and capture an order (success) | P0 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-021 | PayPal approval fails or is not yet confirmed | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-022 | Provider cancel redirect lands on a POST-only cancel route | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-023 | FedaPay (XOF) start, return and confirmation | P0 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-024 | Return page while the provider payment is still pending or failed | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-025 | CinetPay return and webhook | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-026 | M-Pesa (KES) is offered only for KES reservations and handles callbacks | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-027 | Webhook authentication and unsupported providers | P0 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-028 | Webhook state transitions and replay safety | P0 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-029 | Failed payment can be retried with another method | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-030 | Interac, Wise and Revolut instructions and amounts | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-031 | Guest uploads an offline payment proof | P0 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-032 | Offline proof upload validation | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-033 | Offline proof not accepted when payment is closed or link expired | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-034 | Payment proof download authorization | P1 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-035 | Download the PDF receipt as the reservation owner | P0 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-PAY-036 | Receipt access control and missing receipt | P0 | `test-cases/public-04-payment-providers-proofs-receipts.md` |
| UAT-I18N-001 | First visit uses the default locale | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-I18N-002 | Guest switches language with the header switch and it persists | P0 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-I18N-003 | Unsupported locale falls back to the application locale | P2 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-I18N-004 | `?lang=` links from emails set the language for the flow | P2 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-I18N-005 | Logged-in customer language follows the profile preference | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-I18N-006 | Currency display (XOF primary, secondary currency approximation) | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-I18N-007 | Hard-coded French validation messages on the reservation form in English mode | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-I18N-008 | Hard-coded English system messages shown to French users | P2 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-LEGAL-001 | Privacy policy page | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-LEGAL-002 | Terms and conditions page and consistency with checkout | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-LEGAL-003 | Cookie policy page | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-LEGAL-004 | Cookie policy wording vs analytics tracking | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-LEGAL-005 | Analytics consent banner accept and decline | P0 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-LEGAL-006 | Analytics disabled by default shows no banner and loads nothing | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-A11Y-001 | Keyboard navigation and focus on global layout | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-A11Y-002 | Form labels, errors and live regions on search, booking, feedback and help forms | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-A11Y-003 | Responsive layout of the public journey at phone, tablet and desktop widths | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-A11Y-004 | Images, gallery and map accessibility | P2 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-PUB-060 | 404 page for unknown URLs and hidden resources (FR and EN) | P1 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-PUB-061 | 403, 419, 422 and 429 error pages | P2 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-PUB-062 | SEO metadata, noindex on private pages, and health endpoints | P2 | `test-cases/public-05-i18n-legal-a11y-errors.md` |
| UAT-HELP-001 | Help chat widget opens, closes and shows privacy notice | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-HELP-002 | Help chat answers from the public FAQ | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-HELP-003 | Help chat reports unavailable when the API key is missing | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-HELP-004 | Help chat request validation | P2 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-HELP-005 | Help chat provider failure, timeout and empty answer | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-HELP-006 | Help chat rate limit (10 requests per minute) | P2 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-FEED-001 | Feedback form renders with and without delivery configured | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-FEED-002 | Submit feedback successfully | P0 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-FEED-003 | Feedback validation | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-FEED-004 | Feedback when delivery is not configured or mail fails | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-FEED-005 | Feedback rate limit (5 submissions per minute) | P2 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-CAL-001 | Public iCal feed opens with the valid token | P0 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-CAL-002 | Invalid, missing or wrong-property token returns 404 | P0 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-CAL-003 | Feed content privacy and status filtering | P0 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-CAL-004 | External calendar feeds affect public availability only when enabled | P1 | `test-cases/public-06-help-feedback-calendar.md` |
| UAT-CAL-005 | ICS format compatibility check | P2 | `test-cases/public-06-help-feedback-calendar.md` |
