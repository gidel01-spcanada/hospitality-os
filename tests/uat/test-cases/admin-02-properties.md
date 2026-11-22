# Admin back-office: properties (CRUD, status, media, amenities, pricing, add-ons, availability, copy improver)

Area: UAT-PROP (001-029 in this file; amenity catalog is in `admin-03-amenities-catalog.md`, 030+).
Source of truth: `AdminPropertyController`, `PropertyCopyImprover`, `AvailabilityService`, `routes/web.php` lines 184-234, `tests/Feature/PropertyAdminControlsTest.php`, `tests/Unit/PropertyCopyImproverTest.php`.

Property editor tabs (from `editorRedirect`): `general`, `copy`, `photos`, `amenities`, `rules`, `features`, `availability`, `calendars`. After saving, the user returns to `/admin/properties/{id}/edit#<tab>`.

Common data: establishment `E1` (currency XOF, tenant A), establishment `E2` (assigned to nobody), host `host@afrikappart.test` assigned to `E1` only, admin `admin@afrikappart.test`.

---

## UAT-PROP-001: Create property (success path, defaults and publish flag)
- **Feature / journey:** Property creation
- **Priority:** P0
- **Role / permissions:** Admin; Host for own establishment
- **References:** `admin.properties.create`, `admin.properties.store`; `AdminPropertyController::store`; `resources/views/admin/properties/create.blade.php`; lang `flash.property_updated`
- **Preconditions / setup:** Logged in as admin. Amenities catalog has at least 3 amenities.
- **Test data:** establishment `E1`, name `UAT Loft 01`, slug `uat-loft-01`, property_type `apartment`, nightly_rate_xof `35000`, minimum_stay `2`, max_guests `4`, bedrooms `1`, bathrooms `1`, beds `2`, area `45`, area_unit `m2`, floor `3`, status `draft`, 2 amenities ticked, video_urls `https://example.test/v1.mp4`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/properties/create` | Form lists only manageable establishments and the amenity catalog. |
  | 2 | Submit the test data | Redirect to `/admin/properties/{id}/edit` with "Property created successfully." (hard-coded English in controller) |
  | 3 | Verify row: currency equals `E1` currency (XOF), `is_published=false` for status `draft`, amenities synced | Matches. |
  | 4 | Create a second property with status `published` (slug `uat-loft-02`) | `is_published=true`; property visible on `/properties` and `/properties/uat-loft-02`. |
  | 5 | Create a third with status `archived` (slug `uat-loft-03`) | Not visible on the public listing (record). |
  | 6 | Open `/admin/audit-logs` | Entries `created` for `App\Models\Property` and an `amenities_synced` entry. |
- **Acceptance criteria:** Property stored with correct establishment, currency and publish flag; amenities attached; audit entries created.
- **Evidence to capture:** Screenshots of form and edit page, DB rows for `properties`, `amenity_property`, audit list.
- **Notes / assumptions / open questions:** Stored `nightly_rate_xof` is used for all establishments regardless of currency (field name is XOF); how non-XOF establishments display it should be recorded. Slug is typed by the user (required) and is not auto-generated on create.

## UAT-PROP-002: Create property validation and boundaries
- **Feature / journey:** Property creation validation
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `AdminPropertyController::store` rules
- **Preconditions / setup:** Property with slug `uat-loft-01` exists.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit empty form | Errors on establishment_id, name, property_type, slug, nightly_rate_xof, minimum_stay, max_guests, bedrooms, bathrooms, beds, status. |
  | 2 | slug `uat-loft-01` (duplicate) | Unique error. |
  | 3 | nightly_rate_xof `-1`, `abc`, `0`, `0.5`, `99999999999` | `-1` and `abc` rejected; `0` and `0.5` accepted by rules (record); the huge value may fail at the database (record any 500). |
  | 4 | minimum_stay `0`, max_guests `0` | Rejected (min 1). `1` accepted. |
  | 5 | bedrooms/bathrooms/beds `-1`; `0` | `-1` rejected, `0` accepted. |
  | 6 | area `-5`; area_unit `km2` | Rejected. `ft2` accepted. |
  | 7 | floor `201`; `200`; `-1` | `201` and `-1` rejected, `200` accepted. |
  | 8 | property_type `castle`; status `live` | Rejected. |
  | 9 | name of 256 chars; 255 chars | 256 rejected, 255 accepted. |
  | 10 | amenity id that does not exist | Rejected. |
  | 11 | name `<script>alert(1)</script>` | Saved as text and rendered escaped on edit and public pages (no script execution). |
- **Acceptance criteria:** Invalid values never create a property; boundary-valid values do.
- **Evidence to capture:** Screenshots of errors, any server error page.
- **Notes / assumptions / open questions:** No upper limit on `minimum_stay`, `max_guests`, `nightly_rate_xof` in the rules. `max_guests` > 8 on a property conflicts with the admin reservation form limit of 8 adults (see UAT-RES-002).

## UAT-PROP-003: Establishment ownership rules on create and update (host and tenant)
- **Feature / journey:** Property authorization
- **Priority:** P0
- **Role / permissions:** Host (assigned to `E1`), Admin
- **References:** `store` (`managesEstablishment` abort 403), `authorizeProperty`, `establishmentExistsRule`; lang `errors.establishment_manager_required`; `PropertyAdminControlsTest::test_host_cannot_improve_copy_for_an_unassigned_property`
- **Preconditions / setup:** Property `PE2` belongs to `E2` (host not assigned). Another tenant's establishment id `X` exists if multi-tenant environment is available.
- **Test data:** Host `host@afrikappart.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Host opens `/admin/properties/create` | Establishment select only lists `E1`. |
  | 2 | Host submits create with `establishment_id=E2` (edit the HTML value) | 403 "Administrator or host access is required." No row created. |
  | 3 | Host opens `/admin/properties/PE2/edit` | 403. |
  | 4 | Host sends PUT `/admin/properties/PE2`, PUT `.../amenities`, POST `.../images`, POST `.../improve-copy` | Each 403. |
  | 5 | Host `/admin/properties` | Only properties of `E1`. |
  | 6 | Admin submits create with another tenant's establishment id `X` | Validation error (rule limits to current tenant). |
  | 7 | Host edits own property and sets `establishment_id=E2` in PUT | Record result (rule allows ids in the tenant; no host check on the target). |
- **Acceptance criteria:** Host cannot read or modify properties of unassigned establishments; cross-tenant establishment is rejected.
- **Evidence to capture:** Status codes and screenshots; DB check that `PE2` is unchanged.
- **Notes / assumptions / open questions:** Step 7 may allow a host to move a property into an establishment they do not manage. Raise as a defect if it succeeds and is unintended.

## UAT-PROP-004: Edit property general data and FR/EN translations
- **Feature / journey:** Property editing
- **Priority:** P1
- **Role / permissions:** Admin, Host (assigned)
- **References:** `update`; `tests/Feature/PropertyAdminControlsTest.php::test_property_translations_are_saved_and_rendered_for_active_locale`, `::test_admin_can_manage_property_pricing_and_availability`
- **Preconditions / setup:** Property `uat-loft-02` published.
- **Test data:** property_type `villa`, nightly_rate_xof `27000`, minimum_stay `3`, bedrooms `2`, bathrooms `1`, beds `3`; translations fr: name `Loft francais`, summary `Resume francais`, description `Description francaise`; en: name `English loft`, summary `English summary`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Edit and save general tab | Redirect `/admin/properties/{id}/edit#general`, flash "Property updated successfully." (lang `flash.property_updated`). |
  | 2 | Reload edit | Values persisted, including both translations. |
  | 3 | Switch UI to EN, open `/properties` | Shows `English loft` and `English summary`. Switch to FR: shows `Loft francais`. |
  | 4 | Clear all fields of one locale's translation and save | Existing translation is kept (only non-empty content triggers update). Record. |
  | 5 | Change slug to an existing slug | Unique error. Keep own slug: accepted. |
  | 6 | Empty the slug field | Required error (the `Str::slug(name)` fallback never runs). |
  | 7 | Check public URL after slug change | Old URL `/properties/<old-slug>` returns 404 (no redirect; record). |
  | 8 | Update with an unassigned host account | 403 (PROP-003). |
- **Acceptance criteria:** Edits persist; translations render by locale.
- **Evidence to capture:** Before/after screenshots, public pages in both locales.
- **Notes / assumptions / open questions:** Property update does not write an `AuditRecorder` action, but the generic model-update listener creates an `updated` audit row (verify in UAT-AUD-001).

## UAT-PROP-005: Publish, draft, archive and active controls reflect on public site
- **Feature / journey:** Property status control
- **Priority:** P0
- **Role / permissions:** Admin, Host
- **References:** `store` (sets `is_published` from status), `update` (`status`, `is_active`); `PublicPropertyController::index/show`; `app/Http/Controllers/AdminPropertyController.php`
- **Preconditions / setup:** Property `uat-loft-01` currently `draft`.
- **Test data:** Statuses `draft`, `published`, `archived`; `is_active` on/off.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As guest, open `/properties/uat-loft-01` | Not shown / 404 for draft (record exact). Not in `/properties`. |
  | 2 | In admin edit set status `published`, save | Flash ok. |
  | 3 | Reload `/properties` and `/properties/uat-loft-01` as guest | Record whether the property is now public. |
  | 4 | Check DB `is_published` after step 2 | Record value. |
  | 5 | Set `is_active` false (field is `is_active`) and save | Record whether public listing and booking form hide or block it. |
  | 6 | Set status `archived` | Property disappears from public listing; existing reservations remain visible in admin. |
  | 7 | Admin filter `/admin/properties?status=archived` and `?status=live` | Archived filter lists it; `live` triggers validation error. |
- **Acceptance criteria:** Public visibility follows the status control consistently.
- **Evidence to capture:** Screenshots of public pages per status, DB values of `status`, `is_published`, `is_active`.
- **Notes / assumptions / open questions:** OPEN QUESTION: `store` sets `is_published = (status === 'published')`, but `update` never recomputes `is_published` (not in validated rules). If the public site filters on `is_published`, a draft property switched to `published` via edit might stay hidden. Report observed behavior precisely.

## UAT-PROP-006: Video URL list normalization and validation
- **Feature / journey:** Property video links
- **Priority:** P2
- **Role / permissions:** Admin
- **References:** `normalizeVideoUrls`; lang `errors.invalid_video_urls` ("Video links must be valid HTTP(S) URLs: :urls")
- **Preconditions / setup:** Property exists.
- **Test data:** Valid: `https://example.test/a.mp4`. Invalid: `ftp://example.test/a.mp4`, `javascript:alert(1)`, `not a url`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Enter two valid URLs separated by newline and a third separated by a comma, plus a duplicate | Saved as 3 unique URLs. |
  | 2 | Enter `ftp://example.test/a.mp4` | Error on `video_urls` listing the invalid URL. Nothing saved. |
  | 3 | Enter `javascript:alert(1)` | Same error. |
  | 4 | Mix one valid and one invalid | Whole save rejected. |
  | 5 | Empty field | Saved as empty list. |
  | 6 | 10001 characters | Max length error. |
- **Acceptance criteria:** Only http(s) URLs are stored, deduplicated.
- **Evidence to capture:** Screenshots; DB `video_urls` JSON.
- **Notes / assumptions / open questions:** None.

## UAT-PROP-007: Upload property photos (success, cover assignment, formats)
- **Feature / journey:** Media upload
- **Priority:** P0
- **Role / permissions:** Admin, Host (assigned)
- **References:** `admin.properties.images.upload`; `uploadImages`; lang `flash.photos_uploaded`; `PropertyAdminControlsTest::test_admin_can_upload_property_photos`, `::test_admin_can_upload_the_supplied_png_file`
- **Preconditions / setup:** Property with no photos. Prepare synthetic images: `uat-a.jpg` (400 KB), `uat-b.png` (300 KB), `uat-c.webp` (200 KB). Total under 3 MB.
- **Test data:** The three files above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Photos tab: select `uat-a.jpg` only and upload | Redirect `...#photos`, flash "Property photos uploaded successfully." Photo listed, marked cover. |
  | 2 | Upload `uat-b.png` and `uat-c.webp` together | Both added; sort order continues (2, 3); cover stays on first photo. |
  | 3 | Check file system / URL of images | Files exist under `public/uploads/properties/<slug>/` with name `YYYYMMDDHHMMSSmmm-<12 chars>.<ext>`. Images load on the public property page. |
  | 4 | Check DB `property_images` | `mime_type`, `width`, `height`, `sort_order`, `is_cover` filled; `properties.cover_image` equals the first path. |
  | 5 | Upload same file twice | Two separate files stored (no de-duplication). |
- **Acceptance criteria:** Valid images store, display publicly and first upload becomes cover.
- **Evidence to capture:** Screenshots of Photos tab and public page; directory listing; DB rows.
- **Notes / assumptions / open questions:** The server-side per-request limit is 3 MB combined (see PROP-008). PHP `upload_max_filesize` may reject earlier with `messages.errors.image_upload_failed`.

## UAT-PROP-008: Photo upload limits and invalid files
- **Feature / journey:** Media upload validation
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `uploadImages`; `MAX_PROPERTY_PHOTO_UPLOAD_BYTES = 3 MB`; lang `errors.image_file_too_large`, `errors.photos_upload_limit`, `errors.image_upload_failed`; `PropertyAdminControlsTest::test_admin_cannot_upload_a_property_photo_larger_than_three_megabytes`, `::test_admin_is_told_which_photos_exceed_the_combined_three_megabyte_upload_limit`
- **Preconditions / setup:** Property with 0 photos. Files: `big.jpg` 3.5 MB, `exact.jpg` just under 3 MB (e.g. 3,145,000 bytes), `first.jpg` 2 MB, `second.jpg` 1.5 MB, `doc.pdf`, `anim.gif`, `vector.svg`, `fake.jpg` (text file renamed .jpg), `empty.jpg` (0 bytes).
- **Test data:** The files above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Upload `big.jpg` alone | Error on `photos.0`: "Each photo must not be larger than 3 MB." 0 photos stored. |
  | 2 | Upload `first.jpg` + `second.jpg` together (3.5 MB total) | `first.jpg` stored; `second.jpg` rejected with "The following photos were not uploaded because they exceed the 3 MB upload limit: second.jpg. Please upload them again." Success flash and error both visible. |
  | 3 | Upload `exact.jpg` (just under 3 MB) | Stored. |
  | 4 | Upload exactly 3,145,728 bytes file | Record result (limit check is `>` 3 MB). |
  | 5 | Upload `doc.pdf`, `anim.gif`, `vector.svg` | Validation error (only jpg, jpeg, png, webp). |
  | 6 | Upload `fake.jpg` and `empty.jpg` | Validation error (`image` rule). |
  | 7 | Submit with no file | Error on `photos` (required). |
  | 8 | Upload 20 small images at once | All stored in selection order, or record any max-file/post_max_size failure. |
  | 9 | Upload filename with spaces/unicode, e.g. `Ã©tÃ© photo.jpg` | Stored under generated safe name. |
- **Acceptance criteria:** Over-limit and invalid files are never stored; message names the rejected files.
- **Evidence to capture:** Screenshot of each error; photo count in DB.
- **Notes / assumptions / open questions:** The limit is per request combined size, not per file only. The 3 MB limit is stricter than the 5 MB used for payment proofs.

## UAT-PROP-009: Reorder photos, choose cover, set room tags
- **Feature / journey:** Media ordering
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `updateImages`; lang `flash.photos_updated`, `errors.property_image_ownership`; `PropertyAdminControlsTest::test_admin_can_order_property_photos_and_choose_card_cover`
- **Preconditions / setup:** Property has photos `I1`, `I2`, `I3`; another property has photo `Z1`.
- **Test data:** Order I2=1, I1=2, I3=3; cover `I2`; tag for I2 `Living room`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Reorder, pick cover `I2`, tag, save | Redirect `...#photos` with "Property photos updated successfully.". Order and cover persisted; `properties.cover_image` equals I2 path. |
  | 2 | Public property page and `/properties` card | Cover and order reflect the change. |
  | 3 | Submit duplicate order numbers (two photos = 1) | Validation error (`distinct`). |
  | 4 | Submit order value `0` | Error (`min:1`). |
  | 5 | Tag of 101 chars | Error (max 100). 100 accepted. |
  | 6 | Craft a request including `Z1` (other property) in `image_order` | Error "The selected images do not belong to this property." Nothing saved. |
  | 7 | Craft cover_image_id = `Z1` | Same error. |
  | 8 | Omit cover_image_id | Required error. |
- **Acceptance criteria:** Order, cover and tags persist and are scoped to the property.
- **Evidence to capture:** Screenshots; DB `property_images` rows.
- **Notes / assumptions / open questions:** Partial `image_order` (only some photos listed) leaves the unlisted ones unchanged and may clash on sort_order; record behavior if tested.

## UAT-PROP-010: Remove a photo and cover re-assignment
- **Feature / journey:** Media removal
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `updateImages` branch `remove_image_id`; lang `flash.photo_removed`; `PropertyAdminControlsTest::test_admin_can_remove_a_property_photo_and_reassign_cover`
- **Preconditions / setup:** Property has 2 uploaded photos (cover = first).
- **Test data:** Photos `I1` (cover), `I2`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Remove non-cover `I2` | Flash "Property photo removed successfully.", file deleted from `public/uploads/properties/<slug>/`, cover unchanged. |
  | 2 | Re-upload one photo, then remove cover `I1` | The next photo by `sort_order` becomes cover; `properties.cover_image` updated. |
  | 3 | Remove the last remaining photo | `cover_image` becomes null; property page shows placeholder/no image. |
  | 4 | POST `remove_image_id` of an image of another property | 404. |
  | 5 | Remove a seeded/imported photo whose path is not under `uploads/` | DB row removed; file untouched (guard `str_starts_with 'uploads/'`). |
- **Acceptance criteria:** Cover is always valid or null; files removed from disk.
- **Evidence to capture:** Directory listing before/after, DB rows.
- **Notes / assumptions / open questions:** Only the original file path is deleted; variants generated by `property:sync-media` (`-full`, `-large`, `-thumb`) are not deleted (JOB-004).

## UAT-PROP-011: Assign amenities from property editor tab and via general save
- **Feature / journey:** Property amenities
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `admin.properties.amenities.update`, `updateAmenities`; `PropertyAdminControlsTest::test_admin_can_manage_amenities_from_their_own_editor_tab`
- **Preconditions / setup:** Catalog has at least 5 amenities in 2 categories. Property has none.
- **Test data:** Amenity ids `A1..A3`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Amenities tab, tick A1-A3, save | Redirect `...#amenities`, flash "Property updated". |
  | 2 | Public property page | The 3 amenities appear grouped by category in the active locale. |
  | 3 | Untick all and save | All amenities removed (empty array syncs to none). |
  | 4 | Save the General tab without any `amenity_ids` field | Existing amenities stay unchanged. |
  | 5 | Save the General tab with `amenity_ids[]=A1` | Amenities replaced by A1 only; audit entry `amenities_synced`. |
  | 6 | Submit an amenity id belonging to another tenant or nonexistent | Validation error. |
- **Acceptance criteria:** Amenity sync persists exactly the submitted set.
- **Evidence to capture:** Screenshots; pivot table rows.
- **Notes / assumptions / open questions:** The amenities-tab route does not write the `amenities_synced` audit action (only store/update do); generic audit may differ. Check `/admin/audit-logs`.

## UAT-PROP-012: Seasonal price rules and effect on booking price
- **Feature / journey:** Pricing rules
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `admin.properties.price-rules.create/store`; `storePriceRule`; `PricingCalculator`; lang `flash.price_rule_saved`
- **Preconditions / setup:** Property base rate 30000, minimum_stay 1. Establishment taxes known (record VAT, service fee, city tax).
- **Test data:** Rule: effective_from `T+30`, effective_to `T+36`, nightly_rate_xof `45000`, minimum_stay `2`, rule_type `seasonal`. Booking dates `T+31` to `T+34`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/properties/{id}/price-rules/create`, save the rule | Redirect `...#rules` with "Price rule saved.". |
  | 2 | Create an admin reservation for `T+31` to `T+34` | Subtotal uses 45000 per night for the nights inside the rule (3 x 45000 = 135000 before fees/taxes); price lines on the reservation show it. |
  | 3 | Create a reservation `T+37` to `T+39` (outside) | Uses base rate. |
  | 4 | Rule with effective_to before effective_from | Error (after_or_equal). Same date accepted. |
  | 5 | Rule with nightly rate `-1`, minimum_stay `0`, empty rule_type, rule_type of 51 chars | Each rejected. |
  | 6 | Stay that straddles the rule boundary (`T+35` to `T+38`) | Nights priced per rule/base per night; verify with manual math. |
  | 7 | Overlapping second rule (same dates, different rate) | Record which rate wins (no overlap check in controller). |
- **Acceptance criteria:** Rules validate and drive booking totals as documented by pricing code.
- **Evidence to capture:** Reservation price line screenshots with manual calculation sheet.
- **Notes / assumptions / open questions:** Rule `minimum_stay` enforcement against reservation min stay is not applied by admin reservation rules (they use `properties.minimum_stay`). No UI to edit or delete rules appears in routes (only create/store). Record.

## UAT-PROP-013: Property add-on features (extras) CRUD
- **Feature / journey:** Property features
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `admin.properties.features.*`; `PropertyAdminControlsTest::test_admin_can_manage_property_pricing_and_availability`, `::test_admin_can_update_and_delete_property_features`, `::test_property_feature_actions_reject_a_feature_from_another_property`, `::test_customer_can_select_property_feature_addons_during_booking`; lang `flash.feature_saved`, `flash.feature_updated`, `flash.feature_deleted`
- **Preconditions / setup:** Property `P` and property `P2` exist.
- **Test data:** name `Private chef`, description `Dinner for 4`, cost_xof `15000`, is_active on.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/properties/P/features/create`, save | Redirect `...#features`, "Property feature saved.". |
  | 2 | Public booking page of `P` | Add-on shown and selectable; reservation includes a price line for it. |
  | 3 | Edit: cost `20000`, untick active | Saved. Add-on disappears from public booking form. |
  | 4 | Validation: name empty, name 256 chars, description 1001 chars, cost `-1`, cost `abc` | Each rejected. Cost `0` accepted. |
  | 5 | PUT/DELETE a feature of `P2` through `P` URL | 404, feature unchanged. |
  | 6 | Delete feature | "Property feature deleted."; reservations that already include it keep their price line. |
- **Acceptance criteria:** Add-on lifecycle works and is scoped to the property.
- **Evidence to capture:** Screenshots; DB rows; existing reservation price lines after deletion.
- **Notes / assumptions / open questions:** None.

## UAT-PROP-014: Availability blocks (maintenance/owner use): create, delete, boundaries
- **Feature / journey:** Manual availability block
- **Priority:** P0
- **Role / permissions:** Admin, Host
- **References:** `admin.properties.availability.create/store/destroy`; `storeAvailabilityBlock`; `AvailabilityService::isAvailable` (`block.start_date < checkOut` and `block.end_date > checkIn`); lang `flash.availability_saved`, `flash.availability_deleted`
- **Preconditions / setup:** Property `P` with min stay 1 and no reservations in the target window.
- **Test data:** Block A: start `T+10`, end `T+13`, reason `Plumbing`. Block B: start = end = `T+20`, reason `Owner`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create block A | Redirect `...#availability`, "Availability block saved.". Block listed. |
  | 2 | Public/admin availability check for `T+10` to `T+12` | Not available. |
  | 3 | Check `T+13` to `T+15` | Record result: according to code (`end_date > checkIn` is false when equal) it is available, so `end_date` behaves as the first free day (exclusive). |
  | 4 | Check `T+9` to `T+10` | Available (checkout on start day). |
  | 5 | Create block B (start = end) | Accepted by validation (`after_or_equal`). |
  | 6 | Check `T+19` to `T+21` and `T+20` to `T+21` | Record: a zero-length block may not block `T+20` to `T+21`. |
  | 7 | Validation: end before start; reason empty; reason 256 chars; dates empty | Rejected. |
  | 8 | Delete block A | "Availability block deleted."; dates available again. |
  | 9 | Delete block id of another property via this property's URL | 404. |
  | 10 | Block over dates with an existing confirmed reservation | Record (no conflict check on creation). |
- **Acceptance criteria:** Blocks prevent bookings over their span; boundary semantics documented from observed results.
- **Evidence to capture:** Screenshots of availability results, block list.
- **Notes / assumptions / open questions:** OPEN QUESTION for product owner: is `end_date` inclusive in the UI wording? The service treats it as exclusive, so the last day entered may stay bookable as a check-in night, and a one-day block (start=end) may not block anything.

## UAT-PROP-015: Admin availability check page
- **Feature / journey:** `/admin/properties/{id}/availability` check
- **Priority:** P1
- **Role / permissions:** Admin, Host (own), others 403
- **References:** `availabilityCheck`; `resources/views/admin/properties/availability.blade.php`
- **Preconditions / setup:** Property with min stay 2, a confirmed reservation `T+5` to `T+8`, an enabled external feed event `T+12` to `T+14`, and a block `T+20` to `T+22`.
- **Test data:** Query strings `?check_in=...&check_out=...`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `?check_in=T+1&check_out=T+3` | Available. |
  | 2 | `?check_in=T+6&check_out=T+9` | Not available (overlap with reservation). |
  | 3 | `?check_in=T+8&check_out=T+10` | Available (back-to-back with checkout). |
  | 4 | `?check_in=T+12&check_out=T+13` | Not available (external feed). Also min stay 2 not met. |
  | 5 | `?check_in=T+21&check_out=T+23` | Not available (block). |
  | 6 | `?check_in=T+1&check_out=T+2` (1 night, min stay 2) | Not available (minimum stay). |
  | 7 | check_out equal or before check_in; missing params | Validation error. |
  | 8 | Cancelled reservation on `T+30` to `T+33`; check those dates | Available. |
- **Acceptance criteria:** Result matches the service rules for each case.
- **Evidence to capture:** Screenshot per case.
- **Notes / assumptions / open questions:** The check has no way to distinguish reasons (reservation vs block vs minimum stay); only a boolean is shown.

## UAT-PROP-016: Copy improver: preview suggestion is not saved automatically
- **Feature / journey:** AI copy improver (Copy tab)
- **Priority:** P1
- **Role / permissions:** Admin, Host (assigned)
- **References:** `admin.properties.improve-copy`; `improveCopy`; `PropertyCopyImprover`; `tests/Feature/PropertyAdminControlsTest.php::test_property_copy_preview_requires_approval_and_obeys_daily_limit`; `tests/Unit/PropertyCopyImproverTest.php`; `config/services.php` `property_copy.*`
- **Preconditions / setup:** Environment has a writing provider configured (`services.property_copy.api_key`, `base_url`, `model`) or a stub endpoint controlled by the test team. Property has FR description.
- **Test data:** summary_fr `Joli appartement.`, description_fr `Appartement lumineux pres du centre, 2 chambres.`, summary_en and description_en empty.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Copy tab: click the improve button | JSON suggestion with `summary_fr`, `description_fr`, `summary_en`, `description_en` displayed for review. |
  | 2 | Do not accept; reload page | Stored translations unchanged (the suggestion is not persisted). |
  | 3 | Accept/apply the suggestion in the UI, then save the form | Translations saved via normal update. |
  | 4 | Read the suggested text against property facts | No invented amenities, distances or claims beyond facts and input (prompt rule). Record any invented facts as defects. |
  | 5 | Repeat identical request within 24 h | Returned from cache (no new provider call if observable). |
- **Acceptance criteria:** Suggestion needs explicit user approval; nothing persists until save.
- **Evidence to capture:** Screenshots of the preview and unchanged DB translations; provider stub request log if available.
- **Notes / assumptions / open questions:** If no provider is available the case is Blocked, see PROP-018 for the failure behavior.

## UAT-PROP-017: Copy improver input validation and daily limit
- **Feature / journey:** AI copy improver limits
- **Priority:** P1
- **Role / permissions:** Admin; Host assigned (allowed); Host unassigned (403)
- **References:** `improveCopy`; lang `ai_copy.source_required` ("Enter at least one summary or description to improve."), `ai_copy.limit_reached` ("You have reached your daily improvement limit. Try again tomorrow."); `services.property_copy.daily_limit` (default 10)
- **Preconditions / setup:** Provider configured or stubbed; daily limit set to a small value (e.g. 2) via `PROPERTY_COPY` env if possible, else default 10. Rate limit key is per user per day.
- **Test data:** Text of exactly 2000 chars (summary) and 10000 chars (description) and +1 char variants.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit with all four fields empty | 422 with message "Enter at least one summary or description to improve." |
  | 2 | summary_fr 2001 chars; description_fr 10001 chars | 422 validation error. 2000 and 10000 accepted. |
  | 3 | Send the limit number of successful requests, then one more | The extra request returns 429 with "You have reached your daily improvement limit. Try again tomorrow." |
  | 4 | As another admin user the same day | Own counter; still allowed. |
  | 5 | Next calendar day (or after cache reset by developer) | Allowed again. |
  | 6 | Host assigned to another establishment | 403. |
  | 7 | Failed provider calls (PROP-018) | Do not consume the quota (hit only after success). |
- **Acceptance criteria:** Limit is per user per day; empty input is blocked; ownership is checked.
- **Evidence to capture:** Network responses (status and JSON body).
- **Notes / assumptions / open questions:** Limit key includes the date in server timezone.

## UAT-PROP-018: Copy improver provider failure and missing configuration
- **Feature / journey:** AI copy improver integration failure
- **Priority:** P2
- **Role / permissions:** Admin
- **References:** `improveCopy` catch block (503); `PropertyCopyImprover` (`ai_copy.not_configured`, `ai_copy.provider_unavailable`, `ai_copy.invalid_response`); `Log::warning('Property copy improvement failed.')`
- **Preconditions / setup:** Ability to blank the API key or point `base_url` to an unreachable host or a stub returning HTTP 500 / invalid JSON (test environment settings only).
- **Test data:** Any valid source copy.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | API key empty, click improve | HTTP 503 with the localized "not configured" text; UI shows it and form remains usable. |
  | 2 | Provider returns HTTP 500 | 503 with "The writing service is temporarily unavailable. Try again later." |
  | 3 | Provider returns JSON missing one of the four keys or blank values | 503 with the "invalid response" text. |
  | 4 | Timeout (stub sleeps >20 s) | 503 after about 20 s plus retries; no hang beyond that. |
  | 5 | Open `/admin/logs` | A warning line "Property copy improvement failed." with property_id and user_id exists, without API key content. |
- **Acceptance criteria:** Failures are reported to the user in a friendly way and logged; no stored data changes.
- **Evidence to capture:** Screenshots of the UI error, log excerpt (redact keys).
- **Notes / assumptions / open questions:** Raw exception messages of `RuntimeException` type are shown to the user; confirm no sensitive data is exposed.

## UAT-PROP-019: Property list filters, scoping and access
- **Feature / journey:** `/admin/properties` index
- **Priority:** P1
- **Role / permissions:** Admin, Host; Concierge and Customer 403
- **References:** `AdminPropertyController::index`; `PropertyAdminControlsTest::test_establishment_admin_page_links_to_filtered_properties`
- **Preconditions / setup:** Properties across `E1` (draft, published) and `E2` (archived); host assigned to `E1`.
- **Test data:** Filters `establishment=E1`, `status=published`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin `/admin/properties` | All tenant properties, newest first. |
  | 2 | `?establishment=E1`, `?status=published`, both | Filters apply and combine. |
  | 3 | `?establishment=999999`, `?status=live` | Validation error (rule limited to tenant establishments). |
  | 4 | From `/admin/establishments` click the properties link of `E1` | Opens list filtered to `E1`. |
  | 5 | Host `/admin/properties` | Only `E1` properties. `?establishment=E2` returns a validation error or empty list (record). |
  | 6 | Concierge and customer | 403. |
- **Acceptance criteria:** Lists respect filters and role scope.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** The list is unpaginated (`get()`); record load time with 100+ properties if data exists.
