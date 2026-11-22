# Admin back-office: property calendar, external iCal feeds, ICS export

Area: UAT-CALA. Source of truth: `AdminCalendarController`, `CalendarFeedSyncService`, `AvailabilityService`, `routes/console.php` (hourly feed sync), `resources/views/admin/properties/calendar*.blade.php`, `tests/Feature/CalendarFeedControlsTest.php`, `docs/requirements-baseline.md` (15-minute sync target).

Feed fields: `name` (max 255), `provider` (`booking`, `airbnb`, `vrbo`, `other`), `url` (url, max 2048), `is_enabled`. New feeds get `sync_interval_minutes = 60`, status `stale` until synced. Feed statuses seen in code: `stale`, `syncing`, `fresh`.
Access: admin and host assigned to the property's establishment. Concierge, customer: 403.

Test ICS source: the tester hosts a static file at an `example.test`-style URL reachable from the app server (or uses a local stub server). Sample `feed-a.ics`:
```
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:uat-event-1
DTSTART;VALUE=DATE:<T+40 as YYYYMMDD>
DTEND;VALUE=DATE:<T+43 as YYYYMMDD>
SUMMARY:Reserved
END:VEVENT
BEGIN:VEVENT
UID:uat-cancelled
DTSTART;VALUE=DATE:<T+50>
DTEND;VALUE=DATE:<T+52>
STATUS:CANCELLED
SUMMARY:Cancelled
END:VEVENT
END:VCALENDAR
```

---

## UAT-CALA-001: Add an external calendar feed and initial sync
- **Feature / journey:** Create feed
- **Priority:** P0
- **Role / permissions:** Admin, Host (assigned)
- **References:** `admin.properties.calendar.create/store`; `storeFeed`; `CalendarFeedSyncService::sync`; lang `flash.calendar_saved_and_synced`; `CalendarFeedControlsTest::test_admin_can_sync_external_calendar_feed_and_export_internal_calendar`
- **Preconditions / setup:** Property `PA` with no feeds; `feed-a.ics` reachable.
- **Test data:** name `UAT Airbnb`, provider `airbnb`, url `https://ical.example.test/feed-a.ics` (tester stub), enabled.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/properties/PA/edit#calendars`, then the add feed page `/admin/properties/PA/calendar/feeds/create` | Form with provider select, name, url, enabled. The public export URL with `data-copy-text` is shown on the editor. |
  | 2 | Submit the data | Redirect to `/admin/properties/PA/edit#calendars` with "Calendar feed saved and synced." |
  | 3 | Check feed row | Status `fresh`, `last_sync_at` and `last_successful_sync_at` set, `last_sync_error` empty. |
  | 4 | Check events | `uat-event-1` stored with start `T+40`, end `T+43`; `uat-cancelled` NOT stored. |
  | 5 | Open the property editor availability tab/calendar | Dates `T+40` to `T+42` shown as externally blocked. |
- **Acceptance criteria:** Feed saved, synced, and events displayed as blocked.
- **Evidence to capture:** Screenshots; DB rows `external_calendar_feeds`, `external_calendar_events`.
- **Notes / assumptions / open questions:** There is also an overview page `/admin/properties/{id}/calendar` (`admin.properties.calendar`); record what it shows compared with the editor tab.

## UAT-CALA-002: Feed form validation
- **Feature / journey:** Feed validation
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `storeFeed`, `updateFeed` rules
- **Preconditions / setup:** Property `PA`.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit empty form | Errors on name, provider, url. |
  | 2 | name 256 chars | Error. 255 accepted. |
  | 3 | provider `google` | Error (booking, airbnb, vrbo, other only). |
  | 4 | url `not-a-url`; url with 2049 chars | Errors. |
  | 5 | url `ftp://ical.example.test/a.ics`, `file:///etc/passwd`, `http://127.0.0.1/` | Record what the `url` rule accepts. If accepted, observe the sync result (see notes). |
  | 6 | Leave `is_enabled` unticked | Feed saved disabled, no sync attempted, flash "Calendar feed saved." |
- **Acceptance criteria:** Required fields enforced; rejected inputs never create feeds.
- **Evidence to capture:** Screenshots of errors; feed count.
- **Notes / assumptions / open questions:** SECURITY CANDIDATE (SSRF): the sync service fetches any URL accepted by the `url` rule from the server with `Http::get`. Internal addresses (`http://127.0.0.1`, `http://169.254.169.254`) are not blocked in code. Test only against harmless internal stub addresses in the test environment and report the result.

## UAT-CALA-003: Feed with unreachable or failing URL
- **Feature / journey:** Integration failure on sync
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `CalendarFeedSyncService::sync` catch block; lang `flash.calendar_saved_sync_failed` ("Calendar feed saved, but the initial sync failed. Retry with the Sync button."); `CalendarFeedControlsTest::test_adding_a_calendar_feed_with_an_unreachable_url_flags_the_sync_failure`
- **Preconditions / setup:** Property `PA`; stub server able to return 404 and 500, and a non-ICS body.
- **Test data:** URLs `https://ical.example.test/missing.ics` (404), `https://unreachable.invalid/a.ics` (DNS failure), a URL returning HTML.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Add an enabled feed with the unreachable URL | Redirect to `#calendars` with an error flash (red) "Calendar feed saved, but the initial sync failed. Retry with the Sync button." |
  | 2 | Check feed row | Status `stale`, `last_sync_error` has the error text (e.g. "Calendar feed request failed with status 404"), `last_successful_sync_at` null. |
  | 3 | Feed with HTML or empty body | Sync "succeeds" with 0 events (record), existing events for that feed deleted. |
  | 4 | A feed that previously worked now returns 500, press Sync | Error stored in `last_sync_error`; existing events remain (events only deleted on a successful parse). Record. |
  | 5 | Slow response over 15 seconds | Request times out at 15 s with error stored; admin page does not hang longer than a reasonable time. |
  | 6 | Flash after manual Sync when it failed | Record: the sync button flash may still say "Calendar feed sync completed." even on failure (code redirects with `success` regardless). |
- **Acceptance criteria:** Failures are stored on the feed and, for creation, shown to the user.
- **Evidence to capture:** Screenshots of flash and feed status; DB fields.
- **Notes / assumptions / open questions:** DEFECT CANDIDATE (step 6): `syncFeed` ignores the boolean result of `sync()`. Verify UI.

## UAT-CALA-004: Manual sync updates, removals and cancelled events
- **Feature / journey:** Sync button
- **Priority:** P0
- **Role / permissions:** Admin, Host
- **References:** `syncFeed`; lang `flash.calendar_synced`; `CalendarFeedControlsTest` (sync assertions)
- **Preconditions / setup:** Feed from CALA-001 exists with `uat-event-1`.
- **Test data:** Edited ICS: remove `uat-event-1`, add `uat-event-2` (`T+60` to `T+61`); later ICS with no events; later ICS with `uat-event-2` marked `STATUS:CANCELLED`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Replace the ICS (event 1 removed, event 2 added); press Sync | Flash "Calendar feed sync completed." Event 1 removed, event 2 stored with `T+60`/`T+61`. |
  | 2 | Change the summary of event 2; Sync | Summary updated; no duplicate event rows (updateOrCreate by uid). |
  | 3 | ICS with no VEVENT; Sync | All events of the feed removed; availability reopens. |
  | 4 | ICS with event 2 `STATUS:CANCELLED`; Sync | Event 2 not stored. |
  | 5 | Sync a feed of another property through this property's URL | 404. |
  | 6 | Host unassigned presses Sync | 403. |
- **Acceptance criteria:** Event set after sync equals the non-cancelled events in the source.
- **Evidence to capture:** Before/after event table.
- **Notes / assumptions / open questions:** None.

## UAT-CALA-005: iCal parsing edge cases
- **Feature / journey:** ICS format tolerance
- **Priority:** P2
- **Role / permissions:** Admin
- **References:** `CalendarFeedSyncService::parse`, `normalizeDate`, `unescape`
- **Preconditions / setup:** A feed pointing to a controllable ICS.
- **Test data:** ICS variants listed below, each using unique UIDs.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `DTSTART:YYYYMMDD` / `DTEND:YYYYMMDD` (no VALUE=DATE) | Stored as dates. |
  | 2 | `DTSTART:YYYYMMDDT140000Z` and `DTEND:YYYYMMDDT100000Z` next day | Stored using the date part only. |
  | 3 | `DTSTART;TZID=Africa/Porto-Novo:YYYYMMDDT140000` | Stored by date part. |
  | 4 | Event without `DTEND` | `end_date` = start date (a zero-length event). Record whether it blocks anything (overlap logic uses `end_date > check_in`). |
  | 5 | Event without `UID` | Ignored. |
  | 6 | Malformed date `DTSTART:2026-13-45` | Event ignored. |
  | 7 | Folded lines (continuation lines starting with a space) in SUMMARY and UID | Joined correctly. |
  | 8 | `SUMMARY:Owner\, stay\nnotes` | Unescaped to `Owner, stay` + newline. |
  | 9 | CRLF vs LF line endings | Same result. |
  | 10 | 500 events in the file | Sync completes; record time and page behavior. |
  | 11 | Event spanning 365 days | Stored; blocks the period. |
- **Acceptance criteria:** Valid events are imported, invalid ones skipped without failing the whole feed.
- **Evidence to capture:** Event table per variant.
- **Notes / assumptions / open questions:** DTEND exclusivity (checkout day) is assumed; a one-day event with `DTEND` equal to `DTSTART` blocks nothing.

## UAT-CALA-006: External events block bookings (public and admin) while the feed is enabled
- **Feature / journey:** Availability enforcement from external calendars
- **Priority:** P0
- **Role / permissions:** Admin; anonymous visitor on the public site
- **References:** `AvailabilityService::hasExternalCalendarConflict`; `PublicPropertyController::availability/reserve`; `AdminReservationCalendarOverrideTest`
- **Preconditions / setup:** Feed with event `T+40` to `T+43` synced and enabled (CALA-001).
- **Test data:** Public stay `T+41` to `T+43`; admin stay `T+43` to `T+45`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Public: check availability for `T+41` to `T+43` on `/properties/<slug>` | Not available. |
  | 2 | Public: submit the reserve form | Rejected; no reservation created. |
  | 3 | Admin: create reservation `T+41` to `T+43` | Error "The property is not available for these dates." (override covered in UAT-RES-004). |
  | 4 | Public/admin: stay `T+43` to `T+45` (starts on the event's end date) | Available. |
  | 5 | Disable the feed (edit, untick `is_enabled`) and retry step 1 | Available (disabled feeds do not block). |
  | 6 | Re-enable; delete the feed | After delete, events removed and dates available. |
  | 7 | Check the stale state: feed `last_successful_sync_at` older than 15 minutes | Record whether the public flow warns or refuses bookings (baseline requires a 15 minute freshness threshold; code in `AvailabilityService` does not check freshness). |
- **Acceptance criteria:** Enabled feed events block bookings on both flows; disabled/deleted feeds do not.
- **Evidence to capture:** Screenshots of results per step.
- **Notes / assumptions / open questions:** OPEN QUESTION: `docs/requirements-baseline.md` states "External calendar freshness threshold before final booking: 15 minutes" and "sync schedule: every 15 minutes". The reviewed code syncs hourly (`routes/console.php`) and does not enforce freshness. Record as a gap if the product owner still expects 15 minutes.

## UAT-CALA-007: Edit, disable, re-enable and delete a feed
- **Feature / journey:** Feed maintenance
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `editFeed`, `updateFeed`, `deleteFeed`; lang `flash.calendar_updated`, `flash.calendar_deleted`
- **Preconditions / setup:** Feed `F1` with 1 synced event.
- **Test data:** New name `UAT Updated feed`, new URL `https://ical.example.test/feed-b.ics`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Edit name only; save | "Calendar feed updated." Redirect `#calendars`. Status unchanged. |
  | 2 | Edit URL; save | Status becomes `stale`; events from the old URL remain until the next sync. Record. |
  | 3 | Press Sync | Events replaced by those of `feed-b.ics`. |
  | 4 | Untick enabled; save | No sync on save; events no longer block (CALA-006 step 5). |
  | 5 | Delete feed | "Calendar feed removed." Feed and its events removed. |
  | 6 | Edit or delete a feed id belonging to another property | 404. |
  | 7 | Invalid URL on edit | Validation error; feed unchanged. |
- **Acceptance criteria:** Feed changes persist; deletion cleans events.
- **Evidence to capture:** Screenshots; DB rows.
- **Notes / assumptions / open questions:** Saving an enabled feed does not trigger an immediate sync on update (only on create).

## UAT-CALA-008: Manual sync of a disabled feed silently re-enables it
- **Feature / journey:** Disabled feed behavior
- **Priority:** P2
- **Role / permissions:** Admin
- **References:** `CalendarFeedSyncService::sync` (success branch sets `is_enabled = true`); `syncFeed`
- **Preconditions / setup:** Feed `F2` created disabled (CALA-002 step 6).
- **Test data:** Working ICS URL.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Check feed state | Disabled; no events. |
  | 2 | Press Sync | Flash "Calendar feed sync completed." |
  | 3 | Re-open the feed | Record whether it is now enabled and whether its events block bookings. |
- **Acceptance criteria:** Behavior is recorded and compared with the owner's intent.
- **Evidence to capture:** Screenshots before/after.
- **Notes / assumptions / open questions:** DEFECT CANDIDATE: a manual sync enables a feed the user deliberately disabled (code sets `is_enabled => true` after success).

## UAT-CALA-011: Scheduled feed sync honors the per-feed interval
- **Feature / journey:** Automatic sync (scheduler)
- **Priority:** P1
- **Role / permissions:** System (scheduler), observed by admin
- **References:** `routes/console.php` first `Schedule::call(...)->hourly()`; `external_calendar_feeds.sync_interval_minutes`, `last_sync_at`; README "Laravel scheduler" section
- **Preconditions / setup:** Scheduler runs (`php artisan schedule:run` every minute by the test environment owner). Two enabled feeds: `F1` synced 5 minutes ago, `F3` last synced 3 hours ago, plus one disabled feed `F4`.
- **Test data:** Feeds with a stub ICS whose event list changes between runs.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run `php artisan schedule:list` | Shows the hourly closure and `messages:send-email-notifications` every minute. |
  | 2 | At the next hour boundary, observe feeds | `F3` re-synced (`last_sync_at` updated, new events). |
  | 3 | Observe `F1` | Skipped because last sync is newer than `sync_interval_minutes` (60). |
  | 4 | Observe `F4` (disabled) | Not synced. |
  | 5 | A feed that fails during the run | Error recorded on feed; other feeds still processed. Record whether an exception stops the loop (the call is not wrapped in try/catch, but `sync()` catches throwables). |
- **Acceptance criteria:** Interval and enabled flags are honored; sync frequency documented against the 15 minute baseline.
- **Evidence to capture:** `schedule:list` output; feed timestamps.
- **Notes / assumptions / open questions:** The task is registered `hourly()`, so the effective frequency is at most hourly even if `sync_interval_minutes` were set lower. Discrepancy with the baseline document (15 minutes). There is no UI to change `sync_interval_minutes`.

## UAT-CALA-012: Calendar access control and cross-property isolation
- **Feature / journey:** Calendar permissions
- **Priority:** P0
- **Role / permissions:** Host assigned, Host unassigned, Concierge, Customer, anonymous
- **References:** `authorizeProperty`; `CalendarFeedControlsTest::test_customer_cannot_access_calendar_admin_routes`
- **Preconditions / setup:** Property `PA` (`E1`), property `PB` (`E2`), host assigned to `E1` only. Feed `FB` belongs to `PB`.
- **Test data:** Routes below.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Host opens `/admin/properties/PA/calendar` and feed pages | 200. |
  | 2 | Host opens `/admin/properties/PB/calendar`, `/calendar/feeds/create`, `/calendar/export.ics`, and POSTs feeds, sync | 403. |
  | 3 | Host opens `/admin/properties/PA/calendar/feeds/FB/edit` (feed of PB under PA) | 404. |
  | 4 | Concierge and customer open `/admin/properties/PA/calendar` | 403. |
  | 5 | Anonymous opens `/admin/properties/PA/calendar` | Redirect to `/login`. |
  | 6 | Admin of another tenant opens `/admin/properties/PA/calendar` | Record (access depends on `managesEstablishment` for admins, which returns true for any admin; route binding scope may 404). |
- **Acceptance criteria:** Only authorized users reach calendar tools of their properties.
- **Evidence to capture:** Status codes table.
- **Notes / assumptions / open questions:** `managesEstablishment` returns true for any admin, so cross-tenant isolation for admins depends on model scoping. Step 6 applies only in multi-tenant environments.
