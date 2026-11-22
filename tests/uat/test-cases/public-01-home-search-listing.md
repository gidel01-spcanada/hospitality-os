# Public UAT 01: Home, search, listing, compare, establishment and static pages

Scope: anonymous and customer-facing browsing. All cases are **Not Run**. Source of truth is the repository (routes/web.php, PublicPropertyController, StaticPageController, resources/views, resources/lang).

**Shared synthetic fixtures (referenced by ID in the cases):**
- Establishment E1 (active, currency XOF, `cancellation_fee_percent` = 0), establishment E2 (active, `cancellation_fee_percent` > 0), establishment E3 (`is_active` = false).
- Property P-A (E1, city "Cotonou", XOF 40 000/night, max_guests 4, bedrooms 2, beds 2, type apartment, published, active). P-B (E1, city "Ouidah", XOF 25 000/night, max_guests 2, bedrooms 1, type studio). P-C (E2, city "Cotonou", XOF 60 000/night, max_guests 6, villa). P-D (draft or `is_active` = false). P-E (EUR currency, E1 or other) for the currency-mismatch compare case.
- Customer account `customer.uat1@example.test` (role customer, verified).
- Amenities "Wi-Fi" and "Climatisation" attached to P-A only.

---

## UAT-PUB-001: Home page renders for an anonymous visitor
- **Feature / journey:** Home page, featured properties, establishment filter, reviews summary
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** `GET /` (route `home`, closure in routes/web.php), resources/views/home.blade.php, resources/views/layouts/app.blade.php, docs/requirements-baseline.md (default locale fr, XOF default currency)
- **Preconditions / setup:** Fixtures P-A, P-B, P-C published; P-D unpublished; at least 4 published properties exist in total to exercise the "take 3" limit; cookies cleared; analytics disabled (default).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/` in a fresh browser profile. | HTTP 200. Header, search form (destination, dates, travelers), footer render. Page `<html lang>` equals the default locale (`fr` per baseline unless brand setting `default_locale` differs). |
  | 2 | Count the featured property cards. | At most 3 property cards are shown (code: `$properties->take(3)`). P-D is never shown. |
  | 3 | Check each card for name, city, nightly price formatted `40 000 XOF / night` style (`properties.nightly`), and image. | Price uses space as thousands separator and the property currency. |
  | 4 | Use the establishment select (`name="establishment"`, auto-submits on change) to choose E1. | Browser requests `/?establishment=<E1 id>`. Because `establishment` is in the redirect key list in routes/web.php, expect a redirect to `/properties?establishment=<id>` (see Notes). |
  | 5 | Scroll to the reviews block. | Shows up to 3 active site reviews and a "view all" link to `/reviews?from=home`. If no reviews exist, no broken block is displayed. |
  | 6 | Click each footer link (About, FAQ, Privacy, Terms, Cookies, Feedback). | Each opens its page with HTTP 200. |
- **Acceptance criteria:** Page loads without errors; only published properties of active establishments appear; at most 3 cards; links resolve.
- **Evidence to capture:** Full-page screenshot (desktop and 390 px), browser console log, URL after step 4.
- **Notes / assumptions / open questions:** In routes/web.php the home route redirects to `properties.index` whenever any of `establishment, destination, guests, check_in, check_out, sort` is in the query string, so the in-page `?establishment=` filter on home appears to redirect to the listing. Confirm intended behavior. The `published()` scope (app/Models/Property.php) requires status `published`, `is_active` and an active establishment, so properties of E3 must not appear anywhere on home.

---

## UAT-PUB-002: Home search form redirects to the listing with the selected filters
- **Feature / journey:** Search (destination, dates, travelers)
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** `GET /` then `GET /properties` (routes `home`, `properties.index`), resources/views/home.blade.php (`form.search-form`), tests/Feature/PublicPropertyBookingTest.php (`test_home_search_redirects_to_properties_with_selected_filters`, `test_home_search_accepts_one_traveler`)
- **Preconditions / setup:** Fixtures P-A (Cotonou, max 4), P-B (Ouidah, max 2).
- **Test data:** destination "Cotonou"; check_in = today + 14 days; check_out = today + 17 days; guests = 1, then 3.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On `/`, choose destination "Cotonou", pick the date range via the picker (hidden inputs `check_in`, `check_out`), leave travelers at 1, click Search. | Browser lands on `/properties?destination=Cotonou&guests=1&check_in=...&check_out=...` (empty values are dropped from the URL by the redirect). |
  | 2 | Verify listing results. | P-A is listed (city like "Cotonou"); P-B is not. |
  | 3 | Go back to `/` and set travelers to 3, search again. | P-B (max_guests 2) is excluded; P-A remains. |
  | 4 | Submit `/` with no fields filled. | Opens `/properties` or stays on `/` without error. Record which one in Notes. |
- **Acceptance criteria:** Redirect keeps all provided filters, drops blank ones; guest-count filter uses `max_guests >= guests`; destination match is a "contains" match on `city`.
- **Evidence to capture:** Screenshots of results; address-bar URL after redirect.
- **Notes / assumptions / open questions:** The home form `method="GET" action=properties.index` posts directly to `/properties`, so the redirect logic only applies to direct `/?...` URLs (shared links, home establishment select). Confirm whether travelers default "1" is always sent.

---

## UAT-PUB-003: Home and listing date validation (past, inverted and equal dates)
- **Feature / journey:** Search validation
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `GET /` and `GET /properties` validation rules (`check_in` after_or_equal:today, `check_out` after:check_in), resources/lang/en/validation.php, errors/422 view
- **Preconditions / setup:** None beyond published fixtures.
- **Test data:** Today = D. Cases: check_in = D-1; check_in = D+5 & check_out = D+5; check_in = D+5 & check_out = D+3; check_in = D (boundary, valid) & check_out = D+1; check_out only (no check_in).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties?check_in=<D-1>&check_out=<D+2>`. | Validation error for `check_in` (must be today or later). For GET requests Laravel redirects back with errors; if no referrer the response is a redirect to the previous/`/` page. Record exact behavior. |
  | 2 | Open `/properties?check_in=<D+5>&check_out=<D+5>`. | Validation error for `check_out` (must be after check_in). |
  | 3 | Open `/properties?check_in=<D+5>&check_out=<D+3>`. | Same validation error. |
  | 4 | Open `/properties?check_in=<D>&check_out=<D+1>`. | Accepted: HTTP 200 with filtered list. |
  | 5 | Open `/properties?check_out=<D+4>` only. | Accepted; date filter is not applied because both dates are needed (`$checkIn && $checkOut`). |
  | 6 | Open `/?check_in=not-a-date`. | Validation error for `check_in` (date rule). |
- **Acceptance criteria:** Invalid date combinations never produce a 500; today is accepted; a single date does not filter.
- **Evidence to capture:** Screenshot or HTTP capture of each response, message text in the active locale.
- **Notes / assumptions / open questions:** Because there is no `back()` target on a direct URL open, the visible UX of the error may be a redirect to `/` without an error display. Record whether the user sees any message (possible UX gap).

---

## UAT-PUB-004: Listing filters combine correctly
- **Feature / journey:** Property listing filters (establishment, destination, guests, bedrooms, beds, property type, price range, amenities, flexible cancellation)
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `GET /properties` (`PublicPropertyController@index`), resources/views/properties/index.blade.php, tests/Feature/PublicPropertyBookingTest.php (`test_properties_page_filters_by_establishment_and_capacity`)
- **Preconditions / setup:** Fixtures P-A, P-B (E1, cancellation fee 0), P-C (E2, cancellation fee > 0). Amenity "Wi-Fi" on P-A only.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties` with no filters. | All published properties of active establishments listed (P-A, P-B, P-C). P-D and properties of E3 absent. |
  | 2 | Select establishment E2 and apply. | Only P-C. |
  | 3 | Set guests = 5. | Only properties with `max_guests >= 5` (P-C). |
  | 4 | Set bedrooms = 2. | P-A and P-C (bedrooms >= 2); P-B excluded. |
  | 5 | Choose property type "studio". | Only P-B. |
  | 6 | Set min price 30000 and max price 50000. | Only P-A (nightly_rate_xof between bounds, inclusive). |
  | 7 | Tick amenity "Wi-Fi". | Only P-A. |
  | 8 | Tick "Flexible cancellation only" (`flexible_cancellation=1`). | Only properties of establishments with `cancellation_fee_percent <= 0` (P-A, P-B). |
  | 9 | Check the active filter counter next to the filter summary. | Shows the number of active filters (`properties.active_filters`, singular "1 active filter" vs plural). |
  | 10 | Click "Reset". | Returns to `/properties` with no query string and the full list. |
- **Acceptance criteria:** Each filter narrows results as specified; filters are ANDed; amenity filter uses "any of the selected" semantics (`whereIn`).
- **Evidence to capture:** Screenshot per step with URL visible.
- **Notes / assumptions / open questions:** Multi-amenity semantics are "any" (OR) by code; confirm that this matches the business intent (guests may expect "all"). The listing city filter is a `LIKE %value%` match, so "Co" matches "Cotonou".

---

## UAT-PUB-005: Date filter excludes reserved, blocked and external-calendar conflicts
- **Feature / journey:** Availability-aware search
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** `GET /properties`, `GET /` (shared availability logic), app/Services/AvailabilityService.php, tests/Feature/PublicPropertyBookingTest.php (`test_properties_date_filter_excludes_external_calendar_conflicts`), CalendarFeedControlsTest
- **Preconditions / setup:** P-A has a non-cancelled reservation D+10 to D+13. P-B has an admin availability block D+10 to D+13. P-C has an enabled external calendar feed with an event D+10 to D+13. A second property P-F has a **disabled** feed with the same event. Another property P-G has only a **cancelled** reservation in the same period.
- **Test data:** Search window check_in D+10, check_out D+13; then check_in D+13, check_out D+15 (back-to-back).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties?check_in=<D+10>&check_out=<D+13>`. | P-A, P-B, P-C absent. P-F (disabled feed) and P-G (cancelled reservation) present. |
  | 2 | Open `/properties?check_in=<D+13>&check_out=<D+15>`. | P-A, P-B, P-C present (a stay may start on the day the previous one ends; overlap uses strict `<` / `>`). |
  | 3 | Open `/properties?check_in=<D+11>&check_out=<D+12>` (inside the booked range). | P-A, P-B, P-C absent. |
  | 4 | Repeat step 1 via `/?check_in=...&check_out=...`. | Redirect to `/properties` with the same params and the same exclusions. |
  | 5 | Open a listed property card (P-F). | Detail URL carries `?check_in=...&check_out=...` (and guests when set) and prefills the booking form. |
- **Acceptance criteria:** Conflicts from reservations (not cancelled), admin blocks and enabled external feeds exclude the property; same-day turnover is allowed.
- **Evidence to capture:** Screenshots of listings and URLs.
- **Notes / assumptions / open questions:** The listing excludes properties on any `reservations` row where status is not `cancelled`, including `pending`, `pending_payment`, `payment_failed`. See AvailabilityService (same rule). Baseline mentions a 20-minute payment hold and a 15-minute external calendar freshness check; the code shows no hold expiry for pending reservations (see PAY-008 notes). Record whether abandoned `pending` reservations keep dates blocked.

---

## UAT-PUB-006: Listing validation for boundary and invalid filter values
- **Feature / journey:** Filter input validation
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `PublicPropertyController@index` validation rules
- **Preconditions / setup:** Fixtures from the header.
- **Test data:** Values listed per step.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `/properties?guests=0` | Validation error (min 1). |
  | 2 | `/properties?guests=100` then `guests=101` | 100 accepted; 101 rejected (max 100). |
  | 3 | `/properties?bedrooms=51` and `beds=101` | Both rejected. |
  | 4 | `/properties?property_type=castle` | Rejected (allowed: apartment, house, villa, studio, room, other). |
  | 5 | `/properties?min_price=50000&max_price=10000` | `max_price` rejected (`gte:min_price`). |
  | 6 | `/properties?min_price=-1` | Rejected (min 0). |
  | 7 | `/properties?establishment=999999` | Rejected (`exists:establishments,id`). |
  | 8 | `/properties?sort=newest` | Rejected (allowed: recommended, price_asc, price_desc). |
  | 9 | `/properties?amenities[]=abc` and `amenities[]=999999` | Rejected (integer / exists). |
  | 10 | `/properties?destination=<121 characters>` | Rejected (max 120). |
  | 11 | `/properties?destination=%27%20OR%201%3D1--` | No error page; list simply empty (treated as text). |
  | 12 | `/properties?guests=&bedrooms=&sort=` (blank values) | Blank values ignored; full list shown (test `test_properties_page_ignores_blank_filter_values`). |
- **Acceptance criteria:** Invalid values never reach the query as-is and never raise HTTP 500; boundaries as listed.
- **Evidence to capture:** Status code and visible message for each step.
- **Notes / assumptions / open questions:** HTML inputs on the listing set `min="1"` but no `max`; server limit is 100/50. The legacy `city` query parameter is still accepted and mapped to destination (see PUB-007).

---

## UAT-PUB-007: Sorting, legacy city parameter and empty-result state
- **Feature / journey:** Sort order and "no results"
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `GET /properties?sort=...`, resources/lang `properties.sort_*`, `properties.none`
- **Preconditions / setup:** P-A (40 000), P-B (25 000), P-C (60 000).
- **Test data:** `sort=price_asc|price_desc|recommended`; `city=Ouidah`; `destination=Nowhere`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties?sort=price_asc`. | Order P-B, P-A, P-C. |
  | 2 | Open `/properties?sort=price_desc`. | Order P-C, P-A, P-B. |
  | 3 | Open `/properties` (default recommended). | Ordered by PropertyRecommendationService; record order. Not strictly price-ascending guaranteed. |
  | 4 | Open `/properties?city=Ouidah`. | Only P-B; the destination select shows "Ouidah" selected (code copies `city` to `destination`). |
  | 5 | Open `/properties?destination=Nowhere`. | Empty-state message "No properties are currently available." (EN) / "Aucun logement n'est actuellement disponible." (FR). No error. |
- **Acceptance criteria:** Sort orders match; legacy link works; empty state shown.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** The "recommended" ordering rules are not documented in the files reviewed; the tester should only record observed order. The empty-state text says "currently available" even for filtered searches (wording question).

---

## UAT-PUB-008: Compare two or three properties
- **Feature / journey:** Property comparison
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `GET /properties/compare` (route `properties.compare`), resources/views/properties/compare.blade.php, tests (`test_public_catalog_can_compare_two_properties`)
- **Preconditions / setup:** P-A, P-B, P-C all in XOF and published.
- **Test data:** check_in D+20, check_out D+23, guests 2.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On `/properties`, observe the compare button. | Disabled with the hint "Select 2 or 3 properties from the list." and counter "0 selected". |
  | 2 | Tick "Compare" on P-A. | Counter "1 selected"; button still disabled. |
  | 3 | Tick P-B. | Counter "2 selected"; button enabled. |
  | 4 | Tick P-C, then attempt a fourth property if available. | 3 allowed; a fourth tick is prevented or the server rejects (see PUB-009). |
  | 5 | Click Compare. | Opens `/properties/compare?properties[]=..&properties[]=..` in the order selected, with a column per property: price per night, rating, amenities, cancellation. |
  | 6 | Repeat from a search that had check_in/check_out/guests set. | Compare page shows computed totals "for N night(s)" ("fees and taxes included") per property. |
  | 7 | Use copy-link and WhatsApp share buttons. | Link is copied ("Link copied."); WhatsApp link text is "Here is our property comparison: <url>". |
- **Acceptance criteria:** Selected properties are shown in selection order; pricing equals PricingCalculator totals for the dates.
- **Evidence to capture:** Screenshots of the compare toolbar and result page.
- **Notes / assumptions / open questions:** The compare toolbar passes `guests` default 1 when none selected.

---

## UAT-PUB-009: Compare validation and mixed-currency rejection
- **Feature / journey:** Compare error handling
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `PublicPropertyController@compare` (`required|array|min:2|max:3`, `distinct`, `exists`; `abort_if` 404 / 422), `properties.compare_same_currency`
- **Preconditions / setup:** P-A, P-B, P-C (XOF), P-E (EUR), P-D (unpublished).
- **Test data:** IDs of the fixtures.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties/compare` with no parameter. | Validation error (properties required). |
  | 2 | Open with one id. | Validation error (min 2). |
  | 3 | Open with four ids. | Validation error (max 3). |
  | 4 | Open with the same id twice. | Validation error (distinct). |
  | 5 | Open with a nonexistent id. | Validation error (exists). |
  | 6 | Open with P-A and P-D (unpublished). | HTTP 404 (count mismatch after `published()` scope). |
  | 7 | Open with P-A and P-E (different currencies). | HTTP 422 with message "Properties must use the same currency to be compared." rendered in the 422 error page. |
- **Acceptance criteria:** Each invalid combination gives the specified response; no 500.
- **Evidence to capture:** Screenshot or HTTP status per step.
- **Notes / assumptions / open questions:** Confirm the 422 page shows the abort message text or only the generic `errors.422_message` (the error view in resources/views/errors/422.blade.php should be checked by the tester).

---

## UAT-PUB-010: Favorites require login; customers can toggle and filter favorites
- **Feature / journey:** Favorites
- **Priority:** P2
- **Role / permissions:** Anonymous, then customer
- **References:** `POST /properties/{property}/favorite` (route `properties.favorite.toggle`, auth middleware group), `GET /properties?favorites=1`, test `test_authenticated_customer_can_toggle_property_favorite`
- **Preconditions / setup:** Customer `customer.uat1@example.test`; P-A, P-B.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As anonymous, view a property card on `/properties`. | The heart control is a link to `/login` titled "Sign in to save this apartment". |
  | 2 | Click it. | Login page opens. |
  | 3 | Log in as the customer, return to `/properties`, click the heart on P-B. | Property marked as favorite (button state changes). |
  | 4 | Reload `/properties`. | P-B is listed first (favorites sorted first when sort is not price-based) and remains marked. |
  | 5 | Tick "Favorites only" (`favorites=1`) and apply. | Only P-B. |
  | 6 | As anonymous, open `/properties?favorites=1`. | Filter is ignored (no auth) and the full list is shown. |
  | 7 | Click the heart on P-B again. | Favorite removed. |
- **Acceptance criteria:** Anonymous users cannot store favorites; favorites persist per customer.
- **Evidence to capture:** Screenshots, network trace of the POST.
- **Notes / assumptions / open questions:** Favorites ordering interacts with `sort` (price sort orders by price after the favorites CASE expression). Confirm intended.

---

## UAT-PUB-011: Establishment landing page
- **Feature / journey:** Public tenant/establishment page
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `GET /establishments/{slug}` (route `establishments.show`), `StaticPageController@establishment`, resources/views/establishments/show.blade.php, test `test_establishment_landing_page_only_shows_its_published_properties`
- **Preconditions / setup:** E1 (with latitude, longitude, google_maps_url set) with P-A, P-B published and P-D draft; E3 inactive; E2 with only drafts.
- **Test data:** Slugs of E1, E3 and an invalid slug "does-not-exist".
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/establishments/<E1 slug>`. | HTTP 200; name and description in the active locale (translations), properties P-A and P-B ordered by name; P-D absent. |
  | 2 | Check the location block. | "Open in Google Maps" link has `target="_blank" rel="noopener noreferrer"`; an embedded map iframe titled "Location" is present only when coordinates exist. |
  | 3 | Check reviews. | Up to 6 active reviews for these properties and the rating average; "view all" link works. |
  | 4 | Click "Copy link" under Share. | Message "Link copied! Paste it into your story or post." |
  | 5 | Click a property card. | Opens `/properties/<slug>`. |
  | 6 | Open `/establishments/<E3 slug>`. | HTTP 404 error page. |
  | 7 | Open `/establishments/does-not-exist`. | HTTP 404 error page. |
  | 8 | Open the page for E2 (no published property). | Page renders with the empty-state text and no fatal error. |
- **Acceptance criteria:** Only active establishments are public; only their published properties are shown.
- **Evidence to capture:** Screenshots, response codes.
- **Notes / assumptions / open questions:** Tenant-specific domains or subdomains (ResolveTenant middleware) were not covered in this area; tenant resolution by host is an open item for the platform UAT owner.

---

## UAT-PUB-012: Sitemap lists public pages, published properties and active establishments
- **Feature / journey:** SEO sitemap
- **Priority:** P2
- **Role / permissions:** Anonymous / crawler
- **References:** `GET /sitemap.xml` (route `sitemap`), test `test_public_pages_expose_seo_metadata_and_sitemap`, layouts/app.blade.php (canonical, og tags)
- **Preconditions / setup:** Fixtures with E1 (2 published properties), E2 (no published property), E3 inactive.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/sitemap.xml`. | HTTP 200, `Content-Type: application/xml`, well-formed `urlset` with `<loc>` entries. |
  | 2 | Check the entries. | Contains home, `/properties`, about, contact, reviews, faq, privacy, terms, cookies, every published property URL and E1's establishment URL. |
  | 3 | Look for P-D, E2 and E3. | Absent. |
  | 4 | View the HTML source of `/properties/<P-A slug>`. | Has `<link rel="canonical">`, `og:title`, `og:description`, `og:image`, `og:locale` (`fr_FR` for fr, `en_US` for en), `twitter:*` tags. |
- **Acceptance criteria:** Sitemap valid and consistent with visibility rules.
- **Evidence to capture:** Saved XML file, page source excerpt.
- **Notes / assumptions / open questions:** The sitemap does not include `lastmod`/hreflang; no hreflang tags were found in layouts/app.blade.php (informational).

---

## UAT-PUB-013: Public reviews page, pagination and back link
- **Feature / journey:** Guest reviews
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `GET /reviews` (route `reviews`), `StaticPageController@reviews`, resources/views/reviews/index.blade.php, `messages.reviews.back_home|back_property`
- **Preconditions / setup:** At least 19 active site reviews, some inactive; P-A with slug S.
- **Test data:** Query `?property=S`, `?property=unknown`, `?page=2`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/reviews`. | 18 reviews per page, newest first; average and count in the header; inactive reviews absent. |
  | 2 | Go to page 2 via pagination. | Remaining reviews; URL `?page=2`. |
  | 3 | Open `/reviews?property=<S>`. | Back link label is the "back to property" text and points to `/properties/<S>`. |
  | 4 | Open `/reviews?property=unknown`. | Back link points to home with the "back home" label; no error. |
  | 5 | Open `/reviews` with zero reviews (empty DB). | Page renders with count 0 and no fatal error. |
- **Acceptance criteria:** Pagination and back links behave per code.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** The signed review-submission links (`/review/{reservation}`) belong to the reservation/email area, not covered here.

---

## UAT-PUB-014: Static pages (About, FAQ, Contact) content and links
- **Feature / journey:** Static informational pages
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `GET /about`, `/faq`, `/contact`, resources/views/about.blade.php, faq.blade.php, contact.blade.php, brand settings (`contact_email`, `support_phone`)
- **Preconditions / setup:** Brand settings filled with synthetic `support@example.test` and a synthetic phone.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/about`. | HTTP 200; no placeholder text such as "TODO" (see docs/content-todo.md). |
  | 2 | Open `/faq`. | Six topics from the FAQ (booking, payment, cancellation, checkin, amenities, support) show a question and an answer. |
  | 3 | Open `/contact`. | Email is a `mailto:` link; phone is a `tel:` link with only digits and `+`; opening hours shown. |
  | 4 | Switch language and re-open each page. | Text switches between FR and EN; no raw translation keys (e.g. `messages.faq.`) visible. |
- **Acceptance criteria:** Pages render in both languages without key leakage; contact links are well-formed.
- **Evidence to capture:** Screenshots in FR and EN.
- **Notes / assumptions / open questions:** The FAQ is also the only knowledge base for the help chat (see HELP-002).
