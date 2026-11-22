# Admin back-office: cleaning schedule and public share links

Area: UAT-CLN. Source of truth: `CleaningScheduleController`, `resources/views/admin/cleaning/*`, `resources/views/cleaning/schedule.blade.php`, `resources/views/cleaning/pdf.blade.php`, lang `cleaning.*`, `tests/Feature/CleaningScheduleTest.php`.

Access: admin, host (own establishments' properties), concierge (all tenant properties). Customers 403. Public share links (`/cleaning-schedule/{token}`) need no login.
Visit statuses: `scheduled`, `in_progress`, `completed`, `cancelled`. Duration 15-1440 minutes. Share validity: 7, 30 or 90 days.

Shared data: properties `PA` (`E1`) and `PB` (`E2`); host `host@afrikappart.test` assigned to `E1` only; concierge `concierge@afrikappart.test`; confirmed reservation `RA` on `PA` with check-out `T+5`, reservation `RB` on `PB`.

---

## UAT-CLN-001: Schedule view: week/month navigation and filters
- **Feature / journey:** `/admin/cleaning`
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `index`, `period`, `visitQuery`; lang `cleaning.week`, `cleaning.month`, `cleaning.unknown_assignee`; `CleaningScheduleTest`
- **Preconditions / setup:** At least 6 visits across 2 properties, 2 assignees (`Awa Menage`, `Fatou Menage`) and different statuses, spread over two weeks.
- **Test data:** Query strings `mode=week|month`, `date=YYYY-MM-DD`, `property`, `assignee`, `status`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/cleaning` | Current week (Monday start), visits ordered by time, count text e.g. "2 visits". |
  | 2 | `?mode=month&date=<mid-month>` | Whole month; previous/next period navigation works. |
  | 3 | Filter by property, assignee, status | Visits narrow accordingly; combinations work. |
  | 4 | `?assignee=Nobody` | Validation error "This assignee is not part of the accessible schedule." |
  | 5 | `?status=done` | 422. |
  | 6 | `?mode=year` or `?date=abc` | Validation error (no 500). |
  | 7 | `?property=<id not accessible>` | 403. |
  | 8 | Period without visits | Empty-state text "No cleaning visits during this period." |
- **Acceptance criteria:** Views show only visits in the period and filters; invalid inputs are handled.
- **Evidence to capture:** Screenshots of each filter state.
- **Notes / assumptions / open questions:** Month boundary and timezone: visits at 23:30 and 00:30 around midnight should land on the correct day (app timezone).

## UAT-CLN-002: Schedule a cleaning visit manually
- **Feature / journey:** Create visit
- **Priority:** P0
- **Role / permissions:** Admin, Host (own properties), Concierge
- **References:** `create`, `store`, `validateVisit`; lang `cleaning.created`
- **Preconditions / setup:** Reservation `RA` exists on `PA`.
- **Test data:** property `PA`, reservation `RA`, assignee_name `Awa Menage`, scheduled_at `T+5 11:00`, duration_minutes `90`, status `scheduled`, instructions `Change linen`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/cleaning/create` | Form with property, reservation (optional), assignee, date/time, duration, status, instructions. |
  | 2 | Submit | Redirect to `/admin/cleaning` (keeping mode/date filters) with "Cleaning visit scheduled." |
  | 3 | Check list | Visit shown on its day with assignee, property, status Scheduled and instructions. |
  | 4 | Submit empty form | Errors on property, assignee, scheduled_at, duration, status. |
  | 5 | duration `14`, `15`, `1440`, `1441` | 14 and 1441 rejected; 15 and 1440 accepted. |
  | 6 | assignee 121 chars; instructions 2001 chars | Rejected. 120 / 2000 accepted. |
  | 7 | status `done`; scheduled_at `not a date` | Rejected. |
  | 8 | Visit with no reservation | Accepted. |
  | 9 | Visit in the past | Accepted (overdue flag in CLN-011). |
- **Acceptance criteria:** Valid visits stored; invalid input rejected.
- **Evidence to capture:** Screenshots; DB `cleaning_visits` row.
- **Notes / assumptions / open questions:** `property_id` has no `exists` rule; an unknown id returns 403 via `ownedProperty`.

## UAT-CLN-003: Assignee double-booking prevention
- **Feature / journey:** Overlap check per assignee
- **Priority:** P0
- **Role / permissions:** Admin, Concierge
- **References:** `ensureAssigneeAvailable`, `overlappingAssigneeVisits`; lang `cleaning.assignee_conflict` ("This person already has a visit scheduled during that time."); `CleaningScheduleTest::test_manual_visit_rejects_an_overlapping_assignment`
- **Preconditions / setup:** Visit `V1`: `Awa Menage`, `T+5 11:00`, 90 minutes (ends 12:30) on `PA`.
- **Test data:** Candidates below.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create `Awa Menage` at `T+5 12:00`, 60 min | Error on `scheduled_at`: "This person already has a visit scheduled during that time." |
  | 2 | Create `  awa menage ` (different case and spaces) at `T+5 11:30` | Same error (case-insensitive, trimmed). |
  | 3 | Create `Awa Menage` at `T+5 12:30` | Accepted (starts exactly when V1 ends). |
  | 4 | Create `Awa Menage` at `T+5 09:30`, 90 min (ends 11:00) | Accepted. |
  | 5 | Create `Awa Menage` at `T+5 10:00`, 120 min (spans V1) | Error. |
  | 6 | Create the same time for `Fatou Menage` on property `PB` | Accepted (different person). |
  | 7 | Set `V1` status to `cancelled`, then create an overlapping visit | Accepted (cancelled visits ignored). |
  | 8 | Edit `V1` itself (change instructions only) | Accepted (own visit ignored). |
  | 9 | Edit another visit to overlap `V1` | Error. |
  | 10 | Same person, same time, but the other visit is on a property of another establishment in the tenant | Error (assignee conflict is tenant-wide). |
- **Acceptance criteria:** No assignee has overlapping non-cancelled visits.
- **Evidence to capture:** Screenshots of error and list.
- **Notes / assumptions / open questions:** Only visits scheduled within one day before the candidate start are fetched by the query (`whereBetween scheduled_at [start-1 day, end]`), so a visit lasting longer than 24 hours could be missed; duration max is 1440, so boundary at exactly 24 h should be checked with a 1440 minute visit started the day before.

## UAT-CLN-004: Visit linked to a reservation must belong to the same property
- **Feature / journey:** Reservation link integrity
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `validateReservationProperty` (abort 422)
- **Preconditions / setup:** `RA` on `PA`, `RB` on `PB`.
- **Test data:** property `PA` with reservation `RB`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create visit for `PA` linked to `RB` | 422 error page. No visit created. |
  | 2 | Create visit for `PA` linked to `RA` | Accepted. |
  | 3 | Edit the visit and change property to `PB` while keeping `RA` | 422. |
  | 4 | Link to a non-existent reservation id | Validation error (exists rule). |
  | 5 | Reservation select on the create page | Lists confirmed, checked-in and completed reservations with check-out within the period -7/+7 days. |
- **Acceptance criteria:** Mismatched links are impossible.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** A host can link a reservation id of another establishment only if it belongs to the same property chosen; cross-tenant ids are not otherwise verified.

## UAT-CLN-005: Edit visit, status progression, timestamps and deletion
- **Feature / journey:** Visit lifecycle
- **Priority:** P1
- **Role / permissions:** Admin, Host, Concierge
- **References:** `edit`, `update`, `executionTimestamps`, `destroy`; lang `cleaning.updated`, `cleaning.deleted`, `cleaning.delete_confirmation`
- **Preconditions / setup:** Visit `V2` scheduled.
- **Test data:** Status sequence.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/cleaning/V2/edit`, change assignee/time, save | "Cleaning visit updated." Values persisted. |
  | 2 | Set status `in_progress` | `started_at` set, `completed_at` null. |
  | 3 | Set `completed` | `completed_at` set, `started_at` kept. |
  | 4 | Set back to `scheduled` | `started_at` and `completed_at` cleared. |
  | 5 | Set `cancelled` | Timestamps unchanged by this branch (record). |
  | 6 | Delete with confirmation "Permanently delete this cleaning visit?" | "Cleaning visit deleted." Visit gone. |
  | 7 | Host edits/deletes a visit of `PB` | 404. |
  | 8 | Delete without JS/confirm | Record (server deletes immediately). |
- **Acceptance criteria:** Timestamps follow status; deletion works and is scoped.
- **Evidence to capture:** DB rows `started_at`/`completed_at` after each step.
- **Notes / assumptions / open questions:** Deletion is permanent and appears in audit logs as a `deleted` entry for `App\Models\CleaningVisit`.

## UAT-CLN-006: Generate visits from departures (no duplicates, sequential slots)
- **Feature / journey:** Generate from departures
- **Priority:** P0
- **Role / permissions:** Admin, Host, Concierge
- **References:** `generateForm`, `generateFromDepartures`, `nextAvailableSlot`; lang `cleaning.generated` ("{0} No new departures to schedule.|{1} :count visit created from departures.|[2,*] :count visits created from departures."); `CleaningScheduleTest::test_staff_generates_one_cleaning_visit_per_eligible_departure_without_duplicates`
- **Preconditions / setup:** Week contains 3 departures on `PA`/`PB` (statuses confirmed, checked_in, completed) on the same day, 1 departure with status `pending`, 1 `cancelled`, and 1 reservation that already has a visit.
- **Test data:** assignee `Awa Menage`, scheduled_time `11:00`, duration `60`, instructions `Standard turnover`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/cleaning/generate` and submit | Redirect with "3 visits created from departures." |
  | 2 | Check times for the same day | First at 11:00, second at 12:00, third at 13:00 (each starts after the previous one for the same assignee). |
  | 3 | Check excluded reservations | Pending, cancelled and already-linked reservations get no visit. |
  | 4 | Submit again | "No new departures to schedule." No duplicates. |
  | 5 | Submit with `property` filter on `PA` | Only `PA` departures. |
  | 6 | scheduled_time `25:00`, `9:5`, empty; duration `14` and `1441`; assignee empty | Validation errors. |
  | 7 | Host without access to a `property` filter id | 403. |
  | 8 | Departure on the first/last day of the period | Included (period inclusive). |
- **Acceptance criteria:** One visit per eligible departure; no overlaps for the assignee; idempotent.
- **Evidence to capture:** Screenshots of the list, DB rows.
- **Notes / assumptions / open questions:** The "already has a visit" check looks at all `cleaning_visits.reservation_id` values in the database (not tenant-filtered).

## UAT-CLN-007: Create and revoke a public share link
- **Feature / journey:** Share schedule
- **Priority:** P0
- **Role / permissions:** Admin, Host, Concierge
- **References:** `share`, `revoke`; lang `cleaning.share_created`, `cleaning.share_revoked`, `cleaning.revoke_confirmation`, `cleaning.unknown_assignee`; routes `admin.cleaning.share`, `admin.cleaning.shares.revoke`
- **Preconditions / setup:** Visits exist for `Awa Menage` and `Fatou Menage` in the current week.
- **Test data:** expires_in_days `7`; assignee `Awa Menage`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create a share for the week with `expires_in_days=7` and assignee `Awa Menage` | "Schedule link created for this period." A URL `/cleaning-schedule/<64 hex chars>` is displayed with copy and WhatsApp options. |
  | 2 | `expires_in_days` = `5`, `0`, `365` (crafted) | Validation error (only 7, 30, 90). 30 and 90 accepted. |
  | 3 | Assignee `Nobody` | Error "This assignee is not part of the accessible schedule." |
  | 4 | Share with a `property` filter the user cannot access | 403. |
  | 5 | Recent share list | Shows the latest 10 with expiry date ("Expires <date>"). |
  | 6 | Revoke the link (confirm "Revoke this public link?") | "Schedule link revoked." The URL then returns 404. |
  | 7 | Host revoking a link created by another user | 403. Admin and concierge revoke any link of the tenant. |
  | 8 | Revoke a link of another tenant | 404. |
- **Acceptance criteria:** Links are random, scoped to the chosen filters and revocable.
- **Evidence to capture:** Screenshots; share rows (do not paste tokens into shared reports).
- **Notes / assumptions / open questions:** The share snapshots period, property ids and assignee at creation; later visits in the period are included, other properties are not.

## UAT-CLN-008: Public schedule page and PDF: privacy and expiry
- **Feature / journey:** Public cleaning view
- **Priority:** P0
- **Role / permissions:** Anonymous with link
- **References:** `publicSchedule`, `publicPdf`, `publicContext`; routes `cleaning.public`, `cleaning.public.pdf`; `CleaningScheduleTest::test_admin_manages_and_shares_a_privacy_safe_cleaning_schedule`
- **Preconditions / setup:** Share `S1` for assignee `Awa Menage`; reservation guest email `private-guest@example.test`, ref `CLEAN-PRIVATE-GUEST`; a share `S2` for all assignees; an expired share `S3` (set `expires_at` in the past by waiting or DB edit by developer).
- **Test data:** Links `S1`, `S2`, `S3`; token `deadbeef`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `S1` in a private window | Schedule for `Awa Menage` only, with property name, time, duration, instructions. No login required. |
  | 2 | Search page source for guest email, reservation ref, phone | Not present. |
  | 3 | Open `S1` PDF (`/cleaning-schedule/<token>/pdf`) | A landscape A4 PDF `planning-menage-YYYY-MM-DD.pdf` with the same visits. |
  | 4 | Open `S2` | Visits of all assignees for the shared properties and period. |
  | 5 | Open `S3` (expired) and its PDF | 404. |
  | 6 | Open `/cleaning-schedule/deadbeef` | 404. |
  | 7 | Add a new visit inside the period for `Awa Menage`; reload `S1` | New visit appears. A visit outside the period or for a non-shared property does not. |
  | 8 | Check page in FR/EN | Texts localized. |
- **Acceptance criteria:** Public view exposes only operational data, respects scope and expiry.
- **Evidence to capture:** Screenshots, PDF.
- **Notes / assumptions / open questions:** Instructions text entered by staff is shown publicly; staff should not put private data there.

## UAT-CLN-009: Public status update by cleaner (field execution)
- **Feature / journey:** Cleaner marks visit started/completed
- **Priority:** P1
- **Role / permissions:** Anonymous with link
- **References:** `publicStatus`; route `cleaning.public.status` (`throttle:30,1`); lang `cleaning.field_status_updated` ("Visite terminÃ©e." in FR test)
- **Preconditions / setup:** Share `S1` (assignee `Awa Menage`), visits `VA` (Awa, in period), `VF` (Fatou), `VO` (Awa, outside period or other property).
- **Test data:** POST status `in_progress`, `completed`, `scheduled`, `cancelled`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Press "start" on `VA` | Redirect to the public page with success message; visit `in_progress`, `started_at` set. Admin list shows the new status. |
  | 2 | Press "complete" | Status `completed`, `completed_at` set; message shown. |
  | 3 | POST `status=cancelled` or `scheduled` (crafted) | Validation error (only in_progress, completed). |
  | 4 | POST on `VF` (other assignee) through `S1` | 404. |
  | 5 | POST on `VO` | 404. |
  | 6 | POST using an expired or revoked token | 404. |
  | 7 | Send 31 requests in one minute | The 31st gets 429 (throttle 30 per minute). |
  | 8 | Admin checks audit log | Status change recorded as `updated` on `CleaningVisit` with user null. |
- **Acceptance criteria:** Cleaners can only progress visits within their share; abuse is throttled.
- **Evidence to capture:** Screenshots; status codes.
- **Notes / assumptions / open questions:** Anyone with the link can change statuses without authentication. Confirm acceptable.

## UAT-CLN-010: Role scoping on cleaning schedule
- **Feature / journey:** Host/concierge/customer access
- **Priority:** P0
- **Role / permissions:** Host (assigned `E1`), Concierge, Customer
- **References:** `manageableProperties`, `ownedProperty`, `authorizeVisit`; `CleaningScheduleTest::test_host_is_scoped_and_concierge_can_view_and_plan`
- **Preconditions / setup:** Visits on `PA` (`E1`) and `PB` (`E2`).
- **Test data:** Accounts as above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Host opens `/admin/cleaning` | Only `PA` visits and property names. |
  | 2 | Host posts a visit for `PB` | 403. |
  | 3 | Host opens edit page of a `PB` visit | 404. |
  | 4 | Host generates from departures | Only `E1` departures. |
  | 5 | Host sees share list | Only shares created by that host. |
  | 6 | Concierge opens `/admin/cleaning` | Sees all tenant properties, can create/edit/delete/share. |
  | 7 | Customer opens `/admin/cleaning` | 403. |
- **Acceptance criteria:** Host scope enforced; concierge has tenant-wide access.
- **Evidence to capture:** Status codes and screenshots.
- **Notes / assumptions / open questions:** Concierge sees all establishments (not scoped to assignments).

## UAT-CLN-011: Overdue indicator for past scheduled visits
- **Feature / journey:** Overdue flag
- **Priority:** P2
- **Role / permissions:** Admin
- **References:** lang `cleaning.overdue`; `CleaningScheduleTest::test_admin_schedule_marks_past_scheduled_visits_as_overdue`; `resources/views/admin/cleaning/index.blade.php`
- **Preconditions / setup:** Visit scheduled 2 hours ago (status `scheduled`), visit 2 hours ago with status `completed`, visit in the future.
- **Test data:** As above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the current week schedule | The past `scheduled` visit shows the overdue label. |
  | 2 | Check completed past visit and future visit | No overdue label. |
  | 3 | Mark the overdue visit `in_progress` | Record whether the flag disappears. |
- **Acceptance criteria:** Only past visits not yet completed are flagged.
- **Evidence to capture:** Screenshot.
- **Notes / assumptions / open questions:** Rule for `in_progress` and `cancelled` is not in the controller; take the view logic as the reference and record.
