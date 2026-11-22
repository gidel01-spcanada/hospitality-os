# Admin back-office: establishments editor, taxes, payment provider enablement, hosts

Area: UAT-EST. Source of truth: `AdminEstablishmentController`, `routes/web.php` (group `establishment-manager`), `resources/views/admin/establishments/*`, `PropertyAdminControlsTest` (establishment tests), `PricingCalculator`.

Editor tabs (`activeTab`): `general`, `online`, `taxes`, `translations`, `payments`, `reviews`, `hosts`. Saving redirects to `/admin/establishments/{id}/edit#<tab>` using the posted `active_tab` (default `general`).
Payment providers handled: `pay_later`, `fedapay`, `paypal`, `cinetpay`, `mpesa`, `interac`, `wise`, `revolut`.
Access: list/edit/update for admin and assigned host; create/store/host-assignment-of-existing-user for admin only.

---

## UAT-EST-001: Establishment list and access by role
- **Feature / journey:** `/admin/establishments`
- **Priority:** P1
- **Role / permissions:** Admin (all), Host (assigned only), Concierge/Customer (403)
- **References:** `AdminEstablishmentController::index`; `PropertyAdminControlsTest::test_establishment_admin_page_links_to_filtered_properties`
- **Preconditions / setup:** Establishments `E1` (host assigned), `E2` (not assigned).
- **Test data:** `host@afrikappart.test`, `admin@afrikappart.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin opens `/admin/establishments` | Both establishments with property counts, sorted by name; link to `/admin/properties?establishment=<id>`. |
  | 2 | Host opens same URL | Only `E1`. |
  | 3 | Host opens `/admin/establishments/E2/edit` and sends PUT | 403 "Administrator or host access is required." |
  | 4 | Host opens `/admin/establishments/create` and posts to `/admin/establishments` | 403 "Administrator access is required." |
  | 5 | Concierge and customer | 403. |
- **Acceptance criteria:** Host sees and edits only assigned establishments; only admin can create.
- **Evidence to capture:** Screenshots, status codes.
- **Notes / assumptions / open questions:** None.

## UAT-EST-002: Create establishment (success)
- **Feature / journey:** Establishment creation
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `create`, `store`; `PropertyAdminControlsTest::test_admin_can_create_an_establishment`; lang `flash.establishment_created`
- **Preconditions / setup:** Admin logged in.
- **Test data:** name `UAT Residence Ouidah`, slug blank (derived), country_code `BJ`, city `Ouidah`, address `Rue 1, Ouidah`, currency `XOF`, secondary_currency `EUR`, secondary_currency_rate `655.957`, features `Garden, Wi-Fi` (comma/newline), email `ouidah@example.test`, phone `+22900000000`, latitude `6.36`, longitude `2.08`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/establishments/create` | Defaults country `BJ`, currency `XOF`; country and currency are selects (XOF, NGN, EUR, USD, GBP, CAD visible). |
  | 2 | Submit the test data | Redirect `/admin/establishments` with "Establishment created successfully." (lang). |
  | 3 | Open its edit page | Slug derived from name (`uat-residence-ouidah`); features saved as list `Garden`, `Wi-Fi`; `is_active` true by default. |
  | 4 | Check defaults on the Taxes tab | VAT 18, VAT included, city tax type `percent` amount 5, service fee 10, cancellation fee 0 percent / 0 days (store defaults in code). |
  | 5 | Open the public page `/establishments/uat-residence-ouidah` | Page renders in FR and EN. |
- **Acceptance criteria:** Establishment saved with the given and default values, and visible publicly.
- **Evidence to capture:** Screenshots of form and Taxes tab, DB row.
- **Notes / assumptions / open questions:** The created establishment is assigned to the current tenant by model behavior; verify `tenant_id` equals the admin's tenant.

## UAT-EST-003: Establishment form validation and boundaries
- **Feature / journey:** Establishment validation
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `validatedData`
- **Preconditions / setup:** Establishment `UAT Residence Ouidah` exists.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with empty name, slug, country_code, currency | Errors on each (slug required even though code falls back to name). |
  | 2 | slug equal to another establishment's slug | Unique error. Own slug on update is accepted. |
  | 3 | country_code `BEN` (3) or `B` (1); currency `XO`/`XOFF` | Errors (size 2 / 3). |
  | 4 | secondary_currency equal to currency | Error "different". |
  | 5 | secondary_currency_rate `0` and `-1` | Error (gt 0). `0.0001` accepted. |
  | 6 | latitude `91`, `-91`; longitude `181` | Errors. `90`, `-180` accepted. |
  | 7 | email `bad`; google_maps_url `not-url`; google_reviews_import_url `ftp`; google_review_url `x` | URL/email errors. |
  | 8 | review_channel `facebook` | Error (internal, google, both). |
  | 9 | cancellation_fee_percent `101`; cancellation_fee_days `366`; vat_percent `100.01`; service_fee_percent `-1`; city_tax_type `daily` | Each rejected. `100`, `365`, `100` accepted. |
  | 10 | electricity_policy_note 1001 chars; payment instructions 501 chars | Rejected. |
  | 11 | Name/description with HTML `<b>x</b>` | Rendered escaped publicly. |
- **Acceptance criteria:** Invalid input is rejected without partial updates; boundary values accepted.
- **Evidence to capture:** Screenshots of error lists; DB unchanged check.
- **Notes / assumptions / open questions:** `currency` validation is `size:3` only, so any 3-letter code (e.g. `ABC`) is accepted by the server even though the UI limits it to a select.

## UAT-EST-004: Update general data and currency propagation to properties
- **Feature / journey:** Establishment update
- **Priority:** P0
- **Role / permissions:** Admin, Host (assigned)
- **References:** `update`; `PropertyAdminControlsTest::test_establishment_currency_change_propagates_to_existing_properties`; lang `flash.establishment_updated`
- **Preconditions / setup:** `E1` has 2 properties, currency XOF. One existing reservation in XOF.
- **Test data:** New currency `XAF`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Change currency to `XAF`, save from General tab | Redirect `...#general`, "Establishment updated successfully.". |
  | 2 | Check `properties.currency` for both properties | Both `XAF`. |
  | 3 | Public price display for a property | Uses XAF. |
  | 4 | Open the pre-existing reservation | Keeps its stored currency (XOF); record. |
  | 5 | Change name only | Other tabs' values (taxes, payment methods) unchanged. |
  | 6 | Untick `is_active` (if shown) and save | Record effect on public site. |
- **Acceptance criteria:** Currency is applied to all its properties; unrelated settings are not reset by saving another tab.
- **Evidence to capture:** Screenshots, DB values before/after.
- **Notes / assumptions / open questions:** Updating a tab sends only that tab's fields; the controller keeps existing values when keys are absent (`$request->has(...)`). The Taxes tab saved from another tab with missing keys must not reset taxes.

## UAT-EST-005: Cover image upload, property-picture selection and size limits
- **Feature / journey:** Establishment main picture
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `storeCoverImage`; `PropertyAdminControlsTest::test_admin_can_upload_an_establishment_main_picture`, `::test_establishment_upload_limit_failure_has_a_clear_message`, `::test_admin_can_select_a_property_picture_as_establishment_main_picture`; lang `errors.image_file_too_large`
- **Preconditions / setup:** `E1` with properties that have photos (`I1`), and a property photo of `E2` (`Z1`). Files: `cover-ok.jpg` 1 MB, `cover-big.jpg` 3.5 MB, `cover.gif`, `cover.pdf`.
- **Test data:** As above. External URL `https://example.test/cover.jpg`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Upload `cover-ok.jpg` as `cover_image_upload`, save | Redirect `...#general`; `cover_image` = `uploads/establishments/<timestamp>-<random>.jpg`; file exists; image shows on `/establishments/<slug>`. |
  | 2 | Upload `cover-big.jpg` | Error "Each photo must not be larger than 3 MB." Cover unchanged. |
  | 3 | Upload `cover.gif` and `cover.pdf` | Validation errors (jpg, jpeg, png, webp only). |
  | 4 | Choose a property picture (`property_image_id = I1`) | `cover_image` = that photo's path. |
  | 5 | Send `property_image_id = Z1` (photo of another establishment) | 422 "The selected property image does not belong to this establishment." |
  | 6 | Set `cover_image` text field to an external URL | Saved as given; image renders. |
  | 7 | Upload a new file when an upload already exists | New file stored; old file remains on disk (record). |
- **Acceptance criteria:** Only valid images under 3 MB are stored; foreign property pictures cannot be selected.
- **Evidence to capture:** Screenshots, directory listing of `public/uploads/establishments`.
- **Notes / assumptions / open questions:** Old uploads are not deleted on replacement.

## UAT-EST-006: Google Maps link fills missing coordinates
- **Feature / journey:** Location helpers
- **Priority:** P2
- **Role / permissions:** Admin, Host
- **References:** `coordinatesFromGoogleMapsUrl`; `PropertyAdminControlsTest::test_google_maps_link_populates_missing_establishment_coordinates`
- **Preconditions / setup:** Establishment with empty latitude and longitude.
- **Test data:** `https://www.google.com/maps?q=6.3703,2.3912`; `https://www.google.com/maps/@6.3703,2.3912,15z`; `https://maps.example.test/place/x`; `https://www.google.com/maps?q=95,200`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Save with first URL, no lat/long | latitude 6.3703, longitude 2.3912 stored. |
  | 2 | Clear lat/long, use the `@lat,lng` form | Coordinates parsed. |
  | 3 | Third URL without coordinates | Coordinates remain empty; no error. |
  | 4 | Fourth URL (out of range) | Coordinates ignored. |
  | 5 | Enter manual latitude `1.5` plus URL with other coordinates | Manual latitude kept (URL only fills missing). |
- **Acceptance criteria:** Parsed values are within valid ranges and never overwrite manual input.
- **Evidence to capture:** DB values and map display on public page.
- **Notes / assumptions / open questions:** Regex is permissive; any `a,b` number pair in the URL may be picked up. Report false positives.

## UAT-EST-007: Taxes, fees and cancellation policy settings drive reservation totals
- **Feature / journey:** Taxes tab
- **Priority:** P0
- **Role / permissions:** Admin, Host
- **References:** `update` tax fields; `PricingCalculator`; `PropertyAdminControlsTest::test_admin_can_configure_establishment_taxes_and_pricing_calculator_propagates_them`
- **Preconditions / setup:** Property with nightly rate 50000, 2 nights, no add-ons, no price rules.
- **Test data:** vat_percent `18`, vat_included off, city_tax_type `per_night`, city_tax_amount `1000`, service_fee_percent `10`, cancellation_fee_percent `20`, cancellation_fee_days `7`, electricity_billed_separately on, electricity_policy_note `Prepaid meter`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Save Taxes tab | Redirect `...#taxes`. Values persisted. |
  | 2 | Create admin reservation for 2 nights | Subtotal 100000; fees 10000; taxes 21800 (VAT 19800 + city tax 2000); total 131800 (as per automated test dataset; verify with the calculator output). |
  | 3 | Switch vat_included on | Totals recompute (record values) for a new reservation; existing reservations unchanged. |
  | 4 | city_tax_type `none`, `percent` (5), `per_guest_night` | Each produces the documented tax line; check price lines per type. |
  | 5 | Look at the public property page | Electricity policy note displayed when enabled (record). |
  | 6 | Submit the form from another tab (payments) | Tax values not reset. |
- **Acceptance criteria:** Totals follow configured taxes; changes affect only new reservations.
- **Evidence to capture:** Price line screenshots, calculation sheet.
- **Notes / assumptions / open questions:** Cancellation fee percent/days are stored; no admin cancellation flow applies them in `updateStatus` (see UAT-RES-011).

## UAT-EST-008: Enable payment providers in sandbox, instructions and pay-later defaults
- **Feature / journey:** Payments tab, enablement
- **Priority:** P0
- **Role / permissions:** Admin, Host
- **References:** `normalizePaymentMethods`; `PropertyAdminControlsTest::test_admin_can_update_establishment_content_and_location` (payment_methods assertions); lang `checkout.pay_on_arrival_default_instructions`
- **Preconditions / setup:** Establishment `E1`; server environment in sandbox mode (no production credentials needed).
- **Test data:** Enable `fedapay` (sandbox, instructions `Use mobile money`), `paypal` (sandbox), `pay_later` with empty instructions, `interac` with email `pay@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Payments tab: tick enabled for `fedapay`, `paypal`, `pay_later`, `interac`; save | Redirect `...#payments`. |
  | 2 | Reopen tab | Enabled flags and modes persist. `pay_later` mode is `manual`; empty instructions replaced by the default pay-on-arrival text. |
  | 3 | Start a public checkout for a pending reservation of `E1` | Only enabled methods are offered. |
  | 4 | Disable `paypal`, save | PayPal no longer offered at checkout. |
  | 5 | Disable all methods, save | Checkout shows no available method (record message). |
  | 6 | Instructions 501 chars | Validation error. 500 accepted. |
  | 7 | Method email `bad` | Validation error. |
  | 8 | Mode `staging` | Validation error (sandbox or production). |
  | 9 | Leave a provider untouched in another tab's save | Config for that provider preserved. |
- **Acceptance criteria:** Enabled methods persist and control what checkout offers.
- **Evidence to capture:** Screenshots of payments tab and checkout options.
- **Notes / assumptions / open questions:** Checkout flows themselves are covered by payment test cases from another area.

## UAT-EST-009: Production mode is refused when credentials are not configured
- **Feature / journey:** Payment provider production readiness guard
- **Priority:** P0
- **Role / permissions:** Admin, Host
- **References:** `paymentProviderReadiness`, `normalizePaymentMethods`; `config/services.php`; `tests/Feature/ProductionSecurityTest.php`
- **Preconditions / setup:** Server `.env` has no production environment/keys for FedaPay, PayPal, CinetPay, M-Pesa (typical local). Optionally a second run on an environment with full production credentials (tester must not copy keys into evidence).
- **Test data:** For each of `fedapay`, `paypal`, `cinetpay`, `mpesa`, `interac`, `wise`, `revolut`: enabled + mode `production`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Set `fedapay` enabled with mode `production`; save | Validation error on `payment_methods.fedapay.mode`: "Production credentials must be configured in the server environment before activating fedapay." Nothing saved. |
  | 2 | Repeat for `paypal`, `cinetpay`, `mpesa` | Same error text with the provider name. |
  | 3 | Set `interac`, `wise`, `revolut` to `production` and enabled | Error as well (code has no readiness entry for them, so they can never be activated in production mode). Record. |
  | 4 | Set `pay_later` enabled | Accepted (always ready; mode forced to `manual`). |
  | 5 | Set provider mode `production` but not enabled | Accepted (guard applies only when enabled). |
  | 6 | On an environment with full credentials, enable `fedapay` production | Accepted. |
  | 7 | Check that the edit page never prints provider secrets | No secrets in HTML source. |
- **Acceptance criteria:** Production mode cannot be activated without server-side credentials; secrets never appear in the UI.
- **Evidence to capture:** Screenshot of error; page source search showing no keys.
- **Notes / assumptions / open questions:** The error text is hard-coded English even in French UI. `interac`/`wise`/`revolut` are manual/offline methods: confirm product intent that "production" is meaningless for them and whether the guard should apply.

## UAT-EST-010: Offline payment details (Interac, Wise, Revolut) shown to customers
- **Feature / journey:** Offline payment instructions
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `normalizePaymentMethods` fields `email`, `security_question`, `security_answer`, `instructions`; `app/Services/Payments/InteracGateway.php`, `WiseGateway.php`, `RevolutGateway.php`; `CheckoutController::submitOfflineProof`
- **Preconditions / setup:** Establishment `E1`; a pending reservation.
- **Test data:** Interac email `pay@example.test`, question `Colour?`, answer `uat-blue`, instructions `Send transfer then upload proof`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Save the Interac configuration (enabled, sandbox) | Persisted. |
  | 2 | Open checkout for a pending reservation and select Interac | Customer sees the instructions and recipient email (record whether question/answer are shown). |
  | 3 | Submit a proof as the customer | Reservation moves to a pending validation state; staff notification created (UAT-MAIL-006); proof visible to staff (UAT-RES-013). |
  | 4 | Look at `/admin/audit-logs` for the establishment update | Check whether `security_answer` text appears in the audit details. |
- **Acceptance criteria:** Instructions render at checkout; offline flow reaches staff.
- **Evidence to capture:** Screenshots of checkout and admin; audit detail text.
- **Notes / assumptions / open questions:** SECURITY OBSERVATION: `security_answer` is stored as plain text inside `payment_methods` JSON and the audit listener only redacts `password`, `remember_token`, `api_token`, `token`, so it can show in `audit_logs.details`. Record as a finding if visible.

## UAT-EST-011: Establishment translations (FR/EN) and feature list parsing
- **Feature / journey:** Translations tab
- **Priority:** P2
- **Role / permissions:** Admin, Host
- **References:** `saveTranslations`; `PropertyAdminControlsTest::test_establishment_translation_is_used_in_public_filter`
- **Preconditions / setup:** Establishment `E1`.
- **Test data:** en name `English Residence`; en features `Pool, Gym\nParking`; fr name `Residence francaise`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Save the Translations tab | Redirect `...#translations`. |
  | 2 | Open `/properties` in EN and FR | Establishment filter shows the localized name. |
  | 3 | Open `/establishments/<slug>` in EN | Features list `Pool`, `Gym`, `Parking`. |
  | 4 | Clear a locale's name and description, save | Previous translation remains (empty content is skipped). Record. |
  | 5 | Feature list of the main field: `Wi-Fi,, ,Pool` | Blank entries dropped. |
- **Acceptance criteria:** Translations are used per locale; features parsed by comma/newline.
- **Evidence to capture:** Screenshots in both locales.
- **Notes / assumptions / open questions:** There is no way in the UI to delete a translation once saved.

## UAT-EST-012: Hosts tab: create host, assign existing user, remove host
- **Feature / journey:** Host assignment
- **Priority:** P1
- **Role / permissions:** Admin (all options); Host (create new host only, assigned establishment)
- **References:** `createHost`, `storeHost`, `destroyHost`; lang `flash.host_added`, `flash.host_removed`; `AuditRecorder` actions `host_assigned`, `host_removed`
- **Preconditions / setup:** Establishment `E1`. Customer `uat-customer-1@example.test` exists (candidate for assignment).
- **Test data:** New host name `UAT Host Two`, email `uat-host-2@example.test`, password `UatPass#2024` + confirmation.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin: Hosts tab, mode `create` with test data | "Host added and assigned to the establishment." Host row shown; user has role `host`, tenant of `E1`, verified; password-reset invitation mail sent. |
  | 2 | Admin: mode `existing`, choose the customer | User becomes role `host`, `is_admin=false`, active, tenant set, assigned. |
  | 3 | Duplicate email in create mode; password mismatch; password 7 chars; missing name | Validation errors (`required_if:mode,create`). |
  | 4 | Mode `existing` with no `existing_user_id` | Validation error. |
  | 5 | Existing user id of another tenant (not null tenant) | 404 (not found in allowed set). |
  | 6 | Remove host with the delete button | "Host removed from the establishment." Pivot row removed; user keeps role `host`. |
  | 7 | Remove a user id that is not a host of this establishment | 404. |
  | 8 | Host (non admin) uses mode `existing` | 403 "Administrator access is required." |
  | 9 | Check `/admin/audit-logs` | Entries `host_assigned` and `host_removed` with host id and mode. |
- **Acceptance criteria:** Hosts can be created/assigned/removed with correct role effects and audit trail.
- **Evidence to capture:** Screenshots; DB rows `users`, `establishment_host`; audit entries.
- **Notes / assumptions / open questions:** Assigning an existing user whose `is_admin` was true silently strips admin rights (`is_admin=false`). Removing a host does not demote the role. A host-created host (mode `create`) is allowed for host users on their own establishment.
