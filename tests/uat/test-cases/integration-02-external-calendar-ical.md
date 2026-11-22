# Integrations: external iCalendar import and export (UAT-INT-020 to UAT-INT-031)

Scope: per-property external feeds (`/admin/properties/{property}/calendar*`), `CalendarFeedSyncService`, `AvailabilityService::hasExternalCalendarConflict`, authenticated export (`admin.properties.calendar.export`), public tokenized export (`GET /calendar/{property:slug}/{token}.ics`, `calendar.public-export`), scheduled sync in `routes/console.php`, `tests/Feature/CalendarFeedControlsTest.php`.

Behavior facts taken from the code:
- Feed admin actions require `managesEstablishment(property.establishment_id)`; otherwise 403. A feed id that belongs to another property returns 404.
- Create form fields: name, provider (`booking|airbnb|vrbo|other`), URL (any valid URL, max 2048), enabled checkbox. Created feeds start with status `stale` and `sync_interval_minutes = 60`; an enabled feed syncs immediately on creation.
- Sync sets `last_sync_at`, fetches with a 15 second timeout, replaces the stored event snapshot (events missing from the new fetch are deleted; an empty parsed list deletes all events), and on success sets status `fresh`, clears the error, sets `last_successful_sync_at` and `is_enabled = true`. On any exception it stores the message in `last_sync_error`, sets status `stale` and keeps existing events.
- Parser: handles folded lines, `DATE` and `DATETIME` values (with optional `TZID`), escaped text, skips events with `STATUS:CANCELLED`, and takes only `UID`, `SUMMARY`, `DTSTART`, `DTEND`. It does not expand `RRULE` recurrences.
- Availability treats an event as a conflict when `event.start < requested check-out` and `event.end > requested check-in` for enabled feeds. So a stay may check in on the event's end date and check out on the event's start date.
- Scheduler (`routes/console.php`): hourly closure syncs enabled feeds whose last sync is older than the feed's `sync_interval_minutes`.
- Export content: reservations with status `confirmed`, `checked_in`, `completed` plus availability blocks, as all-day events with summary `Unavailable`; imported events are not exported. Export file name `<slug>-calendar.ics`.

## Fixture to host for import tests (synthetic, no real channel data)

The tester hosts these files on a web server they control over HTTPS (for example a static file on a test host). Dates are placed relative to the test day `D`; replace `YYYYMMDD` values by real dates before use.

`feed-a.ics`
```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//UAT Fixture//EN
BEGIN:VEVENT
UID:uat-a-001@example.test
DTSTART;VALUE=DATE:<D+20>
DTEND;VALUE=DATE:<D+23>
SUMMARY:Reserved (UAT A1)
END:VEVENT
BEGIN:VEVENT
UID:uat-a-002@example.test
DTSTART:<D+30>T150000Z
DTEND:<D+32>T110000Z
SUMMARY:Block\, with comma and a very long folded summary line that continues
  on the next physical line
END:VEVENT
BEGIN:VEVENT
UID:uat-a-003@example.test
DTSTART;VALUE=DATE:<D+40>
DTEND;VALUE=DATE:<D+42>
STATUS:CANCELLED
SUMMARY:Cancelled booking
END:VEVENT
END:VCALENDAR
```
`feed-b.ics` is `feed-a.ics` with event `uat-a-001` removed, event `uat-a-002` moved to `<D+34>`/`<D+36>` and a new event `uat-b-004@example.test` at `<D+50>`/`<D+52>`.
`feed-empty.ics` contains only `BEGIN:VCALENDAR`, `VERSION:2.0`, `END:VCALENDAR`.
`not-a-calendar.html` is a plain HTML page served with HTTP 200.

Common test data: property `appartement-401` (slug), established by seed; admin = seeded `admin@afrikappart.test`; manager of another establishment `host-other@example.test` (role host, assigned to a different establishment); customer `guest@afrikappart.test`. Feed names `UAT Feed A` (provider `other`).

---

## UAT-INT-020: Add an external calendar feed and import events on creation
- **Feature / journey:** Calendar import (first sync)
- **Priority:** P0
- **Role / permissions:** Admin or establishment manager
- **References:** `GET/POST /admin/properties/{property}/calendar/feeds(/create)` (`admin.properties.calendar.create/store`); `CalendarFeedSyncService::sync/parse`; property edit page tab `#calendars`; `tests/Feature/CalendarFeedControlsTest.php`
- **Preconditions / setup:** `feed-a.ics` hosted at `https://<fixture-host>/feed-a.ics`.
- **Test data:** Name `UAT Feed A`, provider `other`, URL as above, enabled.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open property > Calendars tab > "Add calendar feed". | Form with name, provider, URL, enabled. |
  | 2 | Submit with the enabled box checked. | Redirect to `#calendars` with a "saved and synced" success flash. |
  | 3 | Read the feed row. | Status badge "fresh" (localized), last sync time shown, no error text. |
  | 4 | Property edit availability calendar (or `property edit` ranges). | Ranges `D+20..D+23` and `D+30..D+32` appear as unavailable. The cancelled event (`D+40..D+42`) does not appear. |
  | 5 | SQL: `SELECT uid, summary, start_date, end_date FROM external_calendar_events`. | Two events; the second summary is unfolded and the comma unescaped; dates are the date part of the `DATETIME` values. |
  | 6 | Create the same feed with the enabled box unchecked. | Flash "saved" (not synced); status `stale`; no events imported. |
- **Acceptance criteria:** An enabled feed imports its events once and shows fresh status; disabled feeds import nothing.
- **Evidence to capture:** Screenshots of flash, feed row, availability ranges; SQL result.
- **Notes / assumptions / open questions:** `DTSTART` datetime values with timezone are reduced to the date digits without timezone conversion; a start at 23:30 local in a negative offset could land on the next UTC date. Record any date mismatch seen in step 5. Recurring events (`RRULE`) are not expanded (master prompt requires it; see requirements-gaps.md).

---

## UAT-INT-021: Imported events block public booking and admin reservations (end date exclusive)
- **Feature / journey:** Availability uses external events
- **Priority:** P0
- **Role / permissions:** Anonymous visitor; admin
- **References:** `AvailabilityService::isAvailable/hasExternalCalendarConflict`; `PublicPropertyController` availability (`POST /properties/{slug}/availability`) and `reserve`; `AdminReservationController` external-calendar override (`tests/Feature/AdminReservationCalendarOverrideTest.php`)
- **Preconditions / setup:** UAT-INT-020 completed (event `D+20..D+23` imported and enabled).
- **Test data:** Stay requests: S1 = `D+20` to `D+22`; S2 = `D+19` to `D+21`; S3 = `D+23` to `D+25`; S4 = `D+18` to `D+20`; S5 = `D+22` to `D+24`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On the public property page check availability for S1. | Unavailable. |
  | 2 | Check S2 and S5 (overlap by one night). | Unavailable. |
  | 3 | Check S3 (check-in on the event's end date). | Available (end date exclusive). |
  | 4 | Check S4 (check-out on the event's start date). | Available. |
  | 5 | Try to submit the reservation for S1 directly (crafted POST to `reserve`). | Rejected with the unavailable message; no reservation created. |
  | 6 | As admin create a reservation for S1 in Admin > Reservations. | Rejected unless the explicit external-calendar override is used (see override test; note the override and its audit). |
- **Acceptance criteria:** Overlaps are blocked at search, booking and admin creation; boundary days follow check-out-exclusive logic.
- **Evidence to capture:** Screenshots of availability results; SQL showing no reservation for S1.
- **Notes / assumptions / open questions:** No freshness check happens at booking time (see UAT-INT-031).

---

## UAT-INT-022: Re-sync replaces the snapshot (removed, moved, new, cancelled events)
- **Feature / journey:** Calendar re-synchronization
- **Priority:** P0
- **Role / permissions:** Admin or establishment manager
- **References:** `CalendarFeedSyncService::sync` (delete events missing from the fetch, `updateOrCreate` by `feed_id`+`uid`); `POST .../calendar/feeds/{feed}/sync` (`admin.properties.calendar.sync`)
- **Preconditions / setup:** Feed A imported (UAT-INT-020). Replace the hosted `feed-a.ics` content with `feed-b.ics` at the same URL.
- **Test data:** `feed-b.ics` as defined above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click "Sync now" on the feed. | Success flash "synced"; last successful sync time updated. |
  | 2 | SQL events for the feed. | `uat-a-001` is gone; `uat-a-002` now `D+34..D+36`; `uat-b-004` present at `D+50..D+52`; no duplicate rows (same UID updates in place). |
  | 3 | Check availability for `D+20..D+22`. | Now available. |
  | 4 | Check availability for `D+30..D+32` (old position of 002). | Available. For `D+34..D+36`: unavailable. |
  | 5 | Click "Sync now" again with the same file. | No changes; no duplicates (idempotent). |
  | 6 | Change the hosted file so that `uat-b-004` carries `STATUS:CANCELLED` and sync. | Event removed from the snapshot. |
- **Acceptance criteria:** After each successful sync the stored events equal the non-cancelled events of the source; repeated syncs are idempotent.
- **Evidence to capture:** SQL before/after; screenshots of availability.
- **Notes / assumptions / open questions:** None.

---

## UAT-INT-023: Feed outage keeps previous events and shows the error
- **Feature / journey:** Failed synchronization
- **Priority:** P0
- **Role / permissions:** Admin or establishment manager
- **References:** `CalendarFeedSyncService::sync` catch block; `tests/Feature/CalendarFeedControlsTest.php::test_adding_a_calendar_feed_with_an_unreachable_url_flags_the_sync_failure`; feed status labels `messages.admin.calendar_feed_status_*`
- **Preconditions / setup:** Feed A fresh with events (UAT-INT-020).
- **Test data:** Make the hosted URL return HTTP 500, then 404, then time out (delay more than 15 seconds), then restore.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Fixture host returns 500. Click "Sync now". | Flash says synced (the controller always shows the success flash for a manual sync); feed status badge becomes "stale"; error text `Calendar feed request failed with status 500` shown. |
  | 2 | Check events and availability. | Previous events still stored; their dates stay unavailable. |
  | 3 | Repeat with 404, then with a response delayed beyond 15 s. | Same: status stale, error text recorded (timeout message for the delay). |
  | 4 | Create a new feed pointing to a non-existent host name. | Flash "saved, sync failed"; status stale; error recorded. |
  | 5 | Restore the fixture and sync. | Status fresh, error cleared. |
- **Acceptance criteria:** A failed fetch never deletes data and is visible to the admin; recovery clears the error.
- **Evidence to capture:** Screenshots of status and error text at each stage; SQL of events.
- **Notes / assumptions / open questions:** Step 1: the manual sync action (`syncFeed`) flashes "calendar synced" regardless of the result, so an admin may read a success message while the feed is stale. Log this as a usability finding if observed. The error text can include internal exception messages (no feed URL is expected; verify the text does not reveal credentials in the URL).

---

## UAT-INT-024: Empty, malformed or non-calendar responses (exploratory)
- **Feature / journey:** Feed data quality
- **Priority:** P1
- **Role / permissions:** Admin or establishment manager
- **References:** `CalendarFeedSyncService::sync/parse`; master prompt "Never erase prior data after a partial/failed fetch" and tests for "malformed, empty" feeds
- **Preconditions / setup:** Feed A fresh with 2 events. Prepare `feed-empty.ics` and `not-a-calendar.html` on the fixture host.
- **Test data:** Point the feed URL (edit form) to each file in turn.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Edit the feed URL to `feed-empty.ics` and sync. | Record the status and events. By the code: sync succeeds, status fresh, all events deleted (a valid but empty calendar means "no bookings"). |
  | 2 | Restore feed A, sync, then change the URL to `not-a-calendar.html` and sync. | Record. By the code: HTTP 200 with no `VEVENT` blocks parses to zero events, so the snapshot is wiped and the feed is marked fresh. |
  | 3 | Serve a truncated file (cut mid-event, no `END:VCALENDAR`). | Record which events survive. |
  | 4 | Serve a file with an event missing `UID`, one with invalid dates. | Those events are skipped; others import. |
  | 5 | Confirm availability after steps 1-2. | Dates previously blocked become bookable. |
- **Acceptance criteria:** The product owner decides whether wiping on a non-calendar 200 response is acceptable. If a feed returns HTML or a truncated body and the blocked dates are released, log a defect candidate (risk of double booking).
- **Evidence to capture:** SQL events after each step; screenshots of status.
- **Notes / assumptions / open questions:** The parser does not check for `BEGIN:VCALENDAR`. No ETag/Last-Modified handling or consecutive-failure counter exists.

---

## UAT-INT-025: Disabling, editing or deleting a feed releases or retains its dates
- **Feature / journey:** Feed lifecycle
- **Priority:** P1
- **Role / permissions:** Admin or establishment manager
- **References:** `PUT /admin/properties/{property}/calendar/feeds/{feed}` (`admin.properties.calendar.update`); `DELETE ...` (`...calendar.destroy`); `AvailabilityService::hasExternalCalendarConflict` (`is_enabled = true`)
- **Preconditions / setup:** Feed A fresh with events.
- **Test data:** Feed A.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Edit the feed: uncheck "enabled", keep the URL, save. | Saved; events remain stored but the feed is ignored for availability: previously blocked dates are bookable. |
  | 2 | Re-enable and save. | Dates blocked again (events still stored; no sync needed). |
  | 3 | Edit the URL to another valid URL and save. | Status becomes `stale` (URL changed); events unchanged until the next sync. |
  | 4 | Delete the feed (confirm dialog). | Feed removed; its events removed (or no longer used); dates bookable. |
  | 5 | Edit form validation: empty name, URL `not a url`, provider `abc`. | Field errors; nothing saved. |
  | 6 | Click "Sync now" on a disabled feed. | Record: a successful manual sync re-enables the feed (`is_enabled` is set to true by sync). |
- **Acceptance criteria:** Enabled state controls whether a feed affects availability; deleting removes its effect.
- **Evidence to capture:** Screenshots and SQL (`is_enabled`, `status`, events count).
- **Notes / assumptions / open questions:** Step 6 documents a side effect that may surprise admins who disabled a feed on purpose.

---

## UAT-INT-026: Authenticated iCalendar export content and privacy
- **Feature / journey:** Calendar export (admin download)
- **Priority:** P0
- **Role / permissions:** Admin or establishment manager
- **References:** `GET /admin/properties/{property}/calendar/export.ics` (`admin.properties.calendar.export`); `AdminCalendarController::buildCalendarResponse`; `tests/Feature/CalendarFeedControlsTest.php`
- **Preconditions / setup:** Property has: one `confirmed` reservation, one `pending` reservation, one `cancelled` reservation (all with guest `export-guest@example.test`, phone `+22900000000`, notes `private note`), one `completed` reservation, one availability block with reason `Maintenance private`, and imported events from feed A.
- **Test data:** As above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click "Export calendar" (or open the URL). | File downloads as `appartement-401-calendar.ics`, `Content-Type: text/calendar; charset=UTF-8`. |
  | 2 | Open the file in a text editor. | Starts with `BEGIN:VCALENDAR`, `VERSION:2.0`, `PRODID:-//Afrik Appart//Calendar//EN`; each event has `UID`, `DTSTART`, `DTEND` as `YYYYMMDD`, `SUMMARY:Unavailable`; ends with `END:VCALENDAR`. |
  | 3 | Count events. | One for the confirmed and one for the completed reservation, one for the availability block; none for pending, cancelled or imported events. |
  | 4 | Search the file for the guest email, phone, notes, block reason, prices, reservation reference. | No occurrences. |
  | 5 | Import the file into a calendar client (for example a throwaway calendar account). | Events appear as all-day busy periods on the correct dates (check-out date exclusive). |
  | 6 | Export twice and compare UIDs. | UIDs are stable (`reservation-<id>`, `block-<id>`). |
- **Acceptance criteria:** Only confirmed/checked-in/completed stays and blocks are exported, with neutral summaries and no personal data; imported events are not echoed.
- **Evidence to capture:** The exported file (synthetic data), screenshot of the calendar client.
- **Notes / assumptions / open questions:** The file has no `DTSTAMP`, which RFC 5545 requires for strict clients; observe whether Airbnb/Booking style clients accept it (test with a sandbox calendar client). `Content-Disposition` is `attachment`, so a channel that polls the authenticated URL cannot use it; channels use the public URL (UAT-INT-027).

---

## UAT-INT-027: Public tokenized export URL
- **Feature / journey:** Calendar export (channel subscription URL)
- **Priority:** P0
- **Role / permissions:** Anonymous caller holding the URL
- **References:** `GET /calendar/{property:slug}/{token}.ics`; `Property::calendar_export_token` (48 hex characters, generated on creation); property edit page `#calendars` (read-only URL field with copy button)
- **Preconditions / setup:** Two properties with different tokens. Copy the public URL for `appartement-401` from the property edit page.
- **Test data:** Valid URL U1; U1 with the last character of the token changed; token of property 402 used with slug 401; U1 with an uppercase token; URL with token omitted.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open U1 in a private browser window (no login). | HTTP 200 and the `.ics` content identical in structure to UAT-INT-026. |
  | 2 | Open the tampered token URL. | HTTP 404. |
  | 3 | Open slug 401 with token of 402. | HTTP 404. |
  | 4 | Open the URL without the token segment or without `.ics`. | HTTP 404. |
  | 5 | Create a reservation and block on the property, reload U1. | New confirmed reservation/block appear; no personal data. |
  | 6 | Subscribe a throwaway calendar client to U1. | Subscription accepted; events show as busy. |
- **Acceptance criteria:** Only the exact token serves the calendar; the response never contains personal data.
- **Evidence to capture:** Status codes; downloaded file.
- **Notes / assumptions / open questions:** There is no screen or route to regenerate or revoke the token (master prompt requires it); a leaked URL stays valid for the life of the property. No rate limit on the public URL and no cache headers. Confirm slug change behavior (the URL embeds the slug).

---

## UAT-INT-028: Calendar feed and export permissions
- **Feature / journey:** Calendar authorization
- **Priority:** P0
- **Role / permissions:** Anonymous; customer; concierge; host of another establishment; admin
- **References:** `AdminCalendarController::authorizeProperty` (`managesEstablishment`), `abort_unless($feed->property_id === $property->id, 404)`; route middleware `establishment-manager`; `tests/Feature/CalendarFeedControlsTest.php::test_customer_cannot_access_calendar_admin_routes`
- **Preconditions / setup:** Property P (establishment E1) with feed F. Host `host-other@example.test` manages only establishment E2. Another property P2 with feed F2.
- **Test data:** URLs for P/F and P2/F2.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Anonymous GET `/admin/properties/P/calendar` and `/calendar/export.ics`. | Redirect to login. |
  | 2 | Customer GET the same and POST sync for F. | HTTP 403 (or redirect); no sync executed (`last_sync_at` unchanged). |
  | 3 | Concierge GET `/admin/properties/P/calendar/feeds/create` and POST a feed. | HTTP 403 (`isEstablishmentManager` is true only for admin and host roles). |
  | 4 | Host of E2 GET `/admin/properties/P/calendar`, export, sync F, delete F. | HTTP 403 each; feed F unchanged. |
  | 5 | Admin: POST sync for `/admin/properties/P/calendar/feeds/{F2}/sync` (feed from another property). | HTTP 404. |
  | 6 | Admin of another tenant (tenant B admin, see UAT-PLAT-002) opens `/admin/properties/P/calendar` and the export URL for property P of the first tenant. | Expected: 403 or 404. Record the actual result: `User::managesEstablishment` returns true for any admin when the establishment id is non-null, and `Property` has no tenant scope, so access may succeed. A 200 response is a P0 cross-tenant defect candidate. |
- **Acceptance criteria:** Only managers of the property's establishment can view, modify or sync its feeds and exports; no cross-property or cross-tenant access.
- **Evidence to capture:** Status codes; DB `last_sync_at`.
- **Notes / assumptions / open questions:** Step 6 is exploratory for cross-tenant isolation (see also UAT-PLAT-008).

---

## UAT-INT-029: Scheduled calendar synchronization honors interval and enabled state
- **Feature / journey:** Background sync
- **Priority:** P1
- **Role / permissions:** Operator with shell access on a test environment
- **References:** `routes/console.php` (hourly closure over `ExternalCalendarFeed`); README cron section; `php artisan schedule:list`, `php artisan schedule:run`; `docs/requirements-baseline.md` ("every 15 minutes")
- **Preconditions / setup:** Test environment with scheduler available. Feeds: F1 enabled, last sync 2 hours ago (update `last_sync_at` via SQL on a test DB); F2 enabled, last sync 10 minutes ago; F3 disabled, last sync 2 hours ago. The hosted fixture changes between runs so a sync is observable.
- **Test data:** Fixture content changes between feed-a and feed-b.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run `php artisan schedule:list`. | Lists the calendar closure (hourly), `CompletePastReservations` (hourly), `messages:send-email-notifications --limit=50` (every minute), the Google reviews closure (every six hours). Record the next-run times. |
  | 2 | Run `php artisan schedule:run` at a minute where the hourly task is due (or run the closure through a scheduler tick at the top of the hour). | F1 syncs (`last_sync_at` updated); F2 is skipped (synced less than its interval ago); F3 is skipped (disabled). |
  | 3 | Check SQL `last_sync_at`, `status` for the three feeds. | As in step 2. |
  | 4 | Run the scheduler again within the same hour. | No additional calendar syncs (closure is hourly). |
- **Acceptance criteria:** Only enabled, due feeds are synced by the schedule.
- **Evidence to capture:** Command output; SQL.
- **Notes / assumptions / open questions:** Product baseline asks for a 15-minute schedule and a 15-minute freshness threshold; the implemented schedule is hourly and the default feed interval 60 minutes. See requirements-gaps.md R-CAL-03. Also verify the cron entry on the hosting side (UAT-OPS case on scheduler).

---

## UAT-INT-030: Feed URL safety checks (exploratory, security)
- **Feature / journey:** Calendar feed URL validation
- **Priority:** P1
- **Role / permissions:** Admin or establishment manager
- **References:** `AdminCalendarController::storeFeed` rule `url` (max 2048); master prompt (HTTPS by default, reject localhost/private/link-local, redirect limit, response size limit, encrypted URL); `ExternalCalendarFeed` casts
- **Preconditions / setup:** Test environment only. A listener on the test server's loopback or internal network that records requests (for example a simple HTTP request logger on `127.0.0.1:<port>` or an internal host you own).
- **Test data:** URLs: `http://127.0.0.1:<port>/probe.ics`, `http://<internal-host-you-own>/probe.ics`, `ftp://example.test/a.ics`, `file:///etc/passwd` style value, `https://user:pass@example.test/a.ics`, a URL redirecting (302) to the loopback URL, a very large (50 MB) response.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Save a feed with the loopback URL, enabled. | Record whether it is accepted and whether the listener receives the server's request. Expected by the master prompt: rejected. By the code: only URL syntax is validated, so the request is sent. |
  | 2 | Same with the internal host URL. | Record. |
  | 3 | Save `ftp://` and `file://` style URLs. | Record whether they pass validation (Laravel `url` accepts any scheme with a host) and what the sync reports. |
  | 4 | Save a URL with embedded credentials. | Record whether the feed list or error message reveals them. |
  | 5 | Save a URL that redirects to loopback; sync. | Record whether the redirect is followed. |
  | 6 | Save a URL serving a very large body. | Record memory/time behavior and the result (timeout is 15 s; no size limit in the code). |
  | 7 | Check how the URL is stored (read-only SQL `SELECT url FROM external_calendar_feeds`). | Record: plaintext or encrypted (no `encrypted` cast is present in the model). |
- **Acceptance criteria:** Any request sent to loopback/internal addresses is a defect candidate (server-side request forgery risk). Findings go to the product owner and security reviewer.
- **Evidence to capture:** Listener log lines (redact internal hostnames outside the team); screenshots; SQL result with the URL column masked.
- **Notes / assumptions / open questions:** Only run against a disposable test environment. Do not target third-party or production internal systems.

---

## UAT-INT-031: Stale or failed feed does not block booking (exploratory against requirement)
- **Feature / journey:** Booking freshness rule
- **Priority:** P1
- **Role / permissions:** Anonymous visitor; admin
- **References:** `docs/requirements-baseline.md` ("External calendar freshness threshold before final booking: 15 minutes"); `database/seeders/ReferenceDataSeeder.php` (`calendar_freshness_minutes = 15`); `PublicPropertyController::reserve`; master prompt "fail closed"
- **Preconditions / setup:** Property with an enabled feed whose last successful sync is older than 15 minutes (leave it unsynced for 20 minutes, or set `last_successful_sync_at` via SQL on a test DB). Make the fixture host unreachable so an on-demand refresh would fail.
- **Test data:** A stay on free dates with no event overlap.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create a reservation for the free dates from the public site. | Record the result. Requirement: booking should not auto-confirm and the guest sees a helpful message. By the code: no freshness check exists in `AvailabilityService` or the reserve action, so the booking is created. |
  | 2 | Check whether the feed status or the dashboard warns the admin of a stale feed. | Record. The property edit page shows the status badge `stale`; no dashboard warning is known. |
  | 3 | Check admin logs for an operational error. | Record. |
- **Acceptance criteria:** Product owner decides. A result of "booking created" confirms requirements-gaps.md R-CAL-04 as not implemented.
- **Evidence to capture:** Screenshots; SQL (`reservations`, feed row).
- **Notes / assumptions / open questions:** The setting `calendar_freshness_minutes` is seeded but no PHP code reads it (searched `app/`, `routes/`, `config/`).
