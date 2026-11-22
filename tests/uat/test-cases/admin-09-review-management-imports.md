# Admin back-office: review management and imports (Booking, Airbnb, Google)

Area: UAT-REVA. Source of truth: `AdminController` (global reviews: `reviews`, `storeReview`, `updateReview`, `deleteReview`, `importReviews`), `AdminEstablishmentController` (establishment reviews, `importReviews`, `syncGoogleReviews`), `ReviewImportService`, `GoogleReviewSyncService`, commands `reviews:import-booking-csv` and `reviews:sync-google`, `resources/views/admin/reviews.blade.php`, `resources/views/admin/establishments/review-create.blade.php`, tests `PropertyAdminControlsTest` (review import tests), `GoogleReviewSyncTest`, `GoogleReviewSyncCommandTest`, README (Google review sources).

Two admin entry points exist:
- Global page `/admin/reviews` (admin only): list with filters, create/update/delete, generic CSV paste import.
- Establishment tab `Reviews` in `/admin/establishments/{id}/edit#reviews` (admin and assigned host): create/update/delete, multi-format import (file or paste), Google sync button.
Review sources accepted on create/update: `booking`, `google`, `airbnb` (filter on the global page also allows `website`). Rating 1-5.

---

## UAT-REVA-001: Global review list filters (admin)
- **Feature / journey:** `/admin/reviews`
- **Priority:** P2
- **Role / permissions:** Admin only (Host, Concierge 403)
- **References:** `AdminController::reviews`; route `admin.reviews`
- **Preconditions / setup:** 8+ reviews across sources (booking, google, airbnb, website), ratings 1-5, dates spread, two properties.
- **Test data:** Filters `property`, `source`, `rating`, `date_from`, `date_to`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/reviews` | All reviews newest first, with property and establishment names. |
  | 2 | `?source=google`, `?rating=5`, `?property=<id>` | Filtered lists; combinations narrow further. |
  | 3 | `?date_from=2026-01-01&date_to=2026-01-31` | Only reviews dated in January (inclusive). |
  | 4 | `?date_to` before `?date_from` | Validation error. |
  | 5 | `?rating=6`, `?source=yelp`, `?property=999999` | Validation errors. |
  | 6 | Host and concierge open the page | 403. |
- **Acceptance criteria:** Filters work; invalid values rejected.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** The list is not paginated.

## UAT-REVA-002: Create, edit, hide and delete a review manually
- **Feature / journey:** Manual review curation
- **Priority:** P1
- **Role / permissions:** Admin (global page); Admin and assigned Host (establishment tab)
- **References:** `storeReview`, `updateReview`, `deleteReview`; establishment `storeReview/updateReview/destroyReview`; lang `flash.review_saved` ("Review saved."), `flash.review_updated` ("Review updated."), `flash.review_deleted` ("Review deleted."); `PropertyAdminControlsTest::test_admin_can_add_a_review_scoped_to_an_establishment`, `::test_admin_can_update_and_delete_a_review_scoped_to_an_establishment`
- **Preconditions / setup:** Establishment `E1` with property `PA`.
- **Test data:** source `booking`, reviewer_name `UAT Reviewer`, rating `5`, review_text `Great stay`, source_url `https://example.test/review/1`, reviewed_at `2026-03-01`, active.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Establishment tab: add the review (`/admin/establishments/E1/reviews/create`) | Redirect `...#reviews` with "Review saved." Review listed. |
  | 2 | Open `/reviews` (public) and the establishment page | Active review is displayed with name, rating, text, source. |
  | 3 | Edit: rating `4`, untick active | "Review updated." Public pages no longer show it (inactive). |
  | 4 | Re-activate, then delete (confirm) | "Review deleted." Removed from all lists and from public pages. |
  | 5 | Validation: reviewer_name empty/256 chars; rating `0`, `6`, `4.5`; review_text 2001 chars; source `yelp`; source_url `abc`; reviewed_at `abc` | Each rejected. Rating 1 and 5, text 2000 chars accepted. |
  | 6 | Omit reviewed_at | Defaults to now. |
  | 7 | Attach to property of another establishment (crafted `property_id`) | 422 on the establishment tab. On the global page: property must be in the tenant, else 422. |
  | 8 | Same flows on `/admin/reviews` (admin) | Same results with redirect to `/admin/reviews` and flash from `status`. |
  | 9 | Review text `<script>alert(1)</script>` | Escaped on public pages. |
- **Acceptance criteria:** Review lifecycle works; inactive reviews are hidden publicly.
- **Evidence to capture:** Screenshots admin and public.
- **Notes / assumptions / open questions:** Update/destroy on the establishment tab route-bind the review independently of the establishment in the URL; verify whether a review of `E2` can be edited/deleted through `/admin/establishments/E1/reviews/{E2 review}` (the code only checks the user manages `E1`). Run this as a security check and record the outcome.

## UAT-REVA-003: Global paste-CSV import (generic format)
- **Feature / journey:** `/admin/reviews/import`
- **Priority:** P2
- **Role / permissions:** Admin
- **References:** `AdminController::importReviews`; `ReviewImportService::importGenericCsv`; lang `flash.reviews_imported` (":count reviews imported.")
- **Preconditions / setup:** Admin logged in. Sample CSV below.
- **Test data:**
  ```
  reviewer_name,source,rating,review_text,source_url
  UAT Anna,booking,5,Very clean,https://example.test/r/1
  UAT Ben,google,9,Rating too high,
  UAT Chloe,tripadvisor,3,Unknown source,https://example.test/r/3
  ,google,4,No name,
  UAT Dan,google,4
  ```
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit empty `csv` | Error on `csv`: "Le contenu CSV est requis." (hard-coded French). |
  | 2 | Paste the sample and submit | Redirect to `/admin/reviews` with "3 reviews imported." (rows 1-3). |
  | 3 | Check stored rows | Anna: booking 5; Ben: rating clamped to 5 (google); Chloe: source forced to `google`, rating 3. Row without name and the row with fewer than 5 columns are skipped. Header row skipped. |
  | 4 | Check `reviewed_at` and establishment link | `reviewed_at` = import time; `establishment_id` empty (no establishment attached). |
  | 5 | Import the same CSV again | 3 more rows are inserted (no duplicate detection). Record. |
  | 6 | Rating `0` or `abc` | Clamped to 1 (record). |
  | 7 | Host/concierge posts to the route | 403. |
- **Acceptance criteria:** Behavior matches the documented parsing rules; limitations recorded.
- **Evidence to capture:** Screenshots; DB rows.
- **Notes / assumptions / open questions:** Generic importer is not idempotent, ignores any date column, cannot assign an establishment, and has no size limit (textarea). The review rows without establishment may not appear in establishment-scoped pages or reports (`ReportingService` filters by establishment ids).

## UAT-REVA-004: Establishment review import: Booking.com export
- **Feature / journey:** Multi-format import, `booking`
- **Priority:** P1
- **Role / permissions:** Admin, Host (assigned)
- **References:** `AdminEstablishmentController::importReviews`; `ReviewImportService::importBookingCsv`; lang `flash.reviews_imported_detailed` (":inserted added, :updated updated, :skipped skipped."), `errors.review_import_file_required`, `reviews.negative_note`; `PropertyAdminControlsTest::test_admin_can_import_reviews_for_an_establishment_in_multiple_formats`, `::test_establishment_review_import_requires_csv_content`
- **Preconditions / setup:** `E1` with no imported reviews.
- **Test data:** CSV file `booking-reviews.csv` (7 columns: date, reviewer, ?, title, positive, negative, score):
  ```
  Review date,Guest name,Country,Title,Positive,Negative,Score
  2026-02-10,UAT Guest A,BJ,Great,Clean flat,Noisy street,9.0
  2026-02-11,UAT Guest B,FR,Fine,Good location,,7.5
  2026-02-12,UAT Guest C,BJ,Bad score,,,0
  2026-02-13,,BJ,No name,x,,8
  short,row
  ```
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the Reviews tab, choose format `booking`, upload the file, submit | Redirect `...#reviews` with "2 added, 0 updated, 3 skipped." |
  | 2 | Check reviews | Guest A: rating 5 (score 9.0 / 2 rounded, capped 1-5), text "Great\n\nClean flat\n\nNote: Noisy street"; Guest B: rating 4 (7.5/2 = 3.75 rounds to 4). Source `booking`, linked to `E1`, active. |
  | 3 | Import the same file again | "0 added, 2 updated, 3 skipped." (matched on establishment, source, reviewer, date). |
  | 4 | Change the Guest A score to 5.0 in the file and re-import | Rating updated to 3 (2.5 rounds to 3). |
  | 5 | Paste the CSV in the textarea instead of a file | Same result. |
  | 6 | Submit with neither file nor text | Error on `import_file`: "Select a file or paste the CSV content to import." |
  | 7 | Upload a `.pdf`, a `.csv` over 5 MB, a `.xlsx` | Validation error (csv/txt up to 5120 KB). |
  | 8 | Format `trustpilot` | Validation error. |
  | 9 | Row with an unparsable date like `not a date` | Record: `Carbon::parse` may throw and cause a server error for the whole import. |
- **Acceptance criteria:** Import counts and ratings match the mapping; re-import updates instead of duplicating.
- **Evidence to capture:** Flash screenshots; DB rows; the CSV used.
- **Notes / assumptions / open questions:** Review text appends the negative comment with the lang label "Note: :comment". Dates are parsed by `Carbon::parse`; day/month ambiguity in locale-specific exports is not handled.

## UAT-REVA-005: Establishment review import: Airbnb, Google and generic formats
- **Feature / journey:** Multi-format import, header-mapped formats
- **Priority:** P1
- **Role / permissions:** Admin, Host (assigned)
- **References:** `ReviewImportService::importAirbnbCsv`, `importGoogleCsv`, `importHeaderMappedCsv`, `HEADER_ALIASES`; lang `flash.reviews_imported` (":count reviews imported.")
- **Preconditions / setup:** `E1` open on the Reviews tab.
- **Test data:**
  Airbnb CSV (columns in non-default order):
  ```
  Date,Guest,Overall rating,Public review,Listing URL
  2026-03-01,UAT Airbnb One,5,Lovely,https://example.test/l/1
  2026-03-02,UAT Airbnb Two,3.5,Okay,
  ```
  Google CSV:
  ```
  Reviewer Name,Star Rating,Comment,Review Date,Review Link
  UAT Google One,4,Nice,2026-03-03,https://example.test/g/1
  ```
  Generic CSV: `reviewer_name,source,rating,review_text,source_url` rows as in REVA-003.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Format `airbnb`, upload the Airbnb CSV | "2 reviews imported." Columns matched by header names regardless of order; source `airbnb`; linked to `E1`. |
  | 2 | Re-import | Record the count (header-mapped importers use an identity check; compare with tests in `PropertyAdminControlsTest`). |
  | 3 | Format `google`, upload the Google CSV | "1 reviews imported." Source `google`. |
  | 4 | Format `generic` with the REVA-003 sample | Rows imported and linked to `E1`; no duplicate detection. |
  | 5 | Airbnb file with unknown headers only | Record behavior (missing column mapping may skip all rows or error). |
  | 6 | Rating `0`, empty reviewer, empty lines | Rows skipped. |
  | 7 | Wrong format chosen for the file (Airbnb file as `google`) | Record (rows skipped or garbled); no server error expected. |
  | 8 | Check Reports quality block for the period | Imported active reviews counted in review count and average (`/admin/reports`). |
- **Acceptance criteria:** Each format imports the described columns and links to the establishment.
- **Evidence to capture:** Screenshots; DB rows.
- **Notes / assumptions / open questions:** Import message uses the singular-unaware text ":count reviews imported." even for 1.

## UAT-REVA-006: Google review sync from the establishment tab (manual)
- **Feature / journey:** Sync Google reviews source URL
- **Priority:** P1
- **Role / permissions:** Admin, Host (assigned)
- **References:** `AdminEstablishmentController::syncGoogleReviews`; `GoogleReviewSyncService`; lang `flash.google_reviews_synced`, `review_request.google_import_url_missing` ("No Google source URL is configured for this establishment."), `review_request.google_import_invalid_payload` ("The Google source does not contain a usable review list."); `tests/Feature/GoogleReviewSyncTest.php`
- **Preconditions / setup:** Stub endpoints available: (a) CSV with Google columns, (b) JSON `{"reviews":[{"author":"UAT J","rating":5,"comment":"Nice","date":"2026-03-05","url":"https://example.test/g/9"}]}` with `Content-Type: application/json`, (c) HTTP 500, (d) JSON `{"reviews":[]}`.
- **Test data:** Set `google_reviews_import_url` on `E1` to each stub in turn.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | No URL configured; press sync | Redirect `#reviews` with error on `google_reviews_import_url`: "No Google source URL is configured for this establishment." |
  | 2 | URL (a); press sync | Success flash with the number of synchronized reviews; `google_reviews_last_sync_at` set, error cleared. |
  | 3 | Press sync again | Reviews are not duplicated (idempotent per `GoogleReviewSyncTest`). Updated text in source updates the stored review. |
  | 4 | URL (b) JSON | Converted to CSV and imported; keys `reviewer_name/author/name`, `rating/stars`, `review_text/comment/text`, `review_date/date`, `source_url/review_url/url` all work. |
  | 5 | URL (c) | Error message "Google review source returned HTTP 500" on the field; `google_reviews_last_sync_error` stored; existing reviews untouched. |
  | 6 | URL (d) | Error "The Google source does not contain a usable review list." |
  | 7 | URL `ftp://...` or invalid text saved in the establishment form | URL validation error on save (`url` rule). |
  | 8 | Unreachable host | Error message shown and stored; no 500 page. |
  | 9 | Host of another establishment presses sync | 403. |
  | 10 | Check the public review link `google_review_url` is unaffected | It is a separate field (README). |
- **Acceptance criteria:** Imports are idempotent; failures are shown and stored without data loss.
- **Evidence to capture:** Screenshots of messages; establishment `google_reviews_*` fields.
- **Notes / assumptions / open questions:** README states the app never publishes reviews to Google. Reviews created by this importer have `source=google`.

## UAT-REVA-007: Command `reviews:sync-google` (all or one establishment)
- **Feature / journey:** CLI Google sync
- **Priority:** P2
- **Role / permissions:** Operator with shell access (tester with a test environment)
- **References:** `app/Console/Commands/SyncGoogleReviews.php`; `tests/Feature/GoogleReviewSyncCommandTest.php`; README
- **Preconditions / setup:** `E1` with a working CSV source, `E2` with a failing source (HTTP 500), `E3` with no source.
- **Test data:** Establishment ids of `E1`, `E2`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan reviews:sync-google` | Prints one line per establishment with a source: "<name>: N review(s) synchronized." for `E1`, an error line for `E2`; `E3` ignored. Final line "Processed 1 establishment(s); 1 failure(s)." Exit code 1. |
  | 2 | `php artisan reviews:sync-google --establishment=<E1 id>` | Only `E1`; exit code 0. |
  | 3 | `--establishment=999999` | "Processed 0 establishment(s); 0 failure(s)." exit code 0. |
  | 4 | Check `E2` fields | `google_reviews_last_sync_error` set. |
  | 5 | `php artisan schedule:list` | Entry running every six hours for establishments whose last sync is null or older than 6 hours. |
- **Acceptance criteria:** Command output and exit codes match; failure of one establishment does not stop others.
- **Evidence to capture:** Console output (copy), DB fields.
- **Notes / assumptions / open questions:** The scheduled version swallows exceptions silently (errors are persisted on the establishment only).

## UAT-REVA-008: Command `reviews:import-booking-csv` with dry run
- **Feature / journey:** CLI Booking import
- **Priority:** P2
- **Role / permissions:** Operator with shell access
- **References:** `app/Console/Commands/ImportBookingReviews.php`; signature `reviews:import-booking-csv {path} {--dry-run}`
- **Preconditions / setup:** Booking-format CSV from REVA-004 saved as `storage/app/uat-booking.csv` (inside the project) and a copy elsewhere with an absolute path.
- **Test data:** Same CSV rows as REVA-004.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan reviews:import-booking-csv storage/app/uat-booking.csv --dry-run` | "Dry run complete. Inserted: 2; updated: 0; skipped: 3." No rows saved. |
  | 2 | Same without `--dry-run` | "Import complete. Inserted: 2; updated: 0; skipped: 3." Rows saved, source `booking`, active. |
  | 3 | Run again | "Inserted: 0; updated: 2; skipped: 3." |
  | 4 | Absolute path | Works. |
  | 5 | Missing file path | "CSV file not found: <path>" and exit code 1. |
  | 6 | Check saved rows | Tenant = tenant of the first establishment found (`Establishment::value('tenant_id')`); no `establishment_id` is set. |
  | 7 | Check the rows on establishment pages/reports | Record whether they appear (no establishment link). |
- **Acceptance criteria:** Counts and idempotency match; dry run saves nothing.
- **Evidence to capture:** Console output; DB rows.
- **Notes / assumptions / open questions:** In multi-tenant setups the command assigns all rows to the first establishment's tenant and ignores establishment. The admin UI importer (REVA-004) is the supported way to attach reviews to an establishment.
