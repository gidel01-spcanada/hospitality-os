# Admin back-office: reports, CSV export, audit logs, error logs

Areas: UAT-RPT and UAT-AUD. Source of truth: `AdminReportController`, `ReportingService`, `resources/views/admin/reports/index.blade.php`, `AdminAuditLogController`, `app/Providers/AppServiceProvider.php` (model event auditing), `AuditRecorder`, `AdminLogController`, `resources/views/admin/audit-logs.blade.php`, `logs.blade.php`, tests `ReportingTest`, `AuditLogTest`, `AdminLogTest`, `ErrorLoggingTest`.

Report definitions (from `ReportingService`): occupied statuses = `confirmed`, `checked_in`, `completed`; outstanding statuses = `pending`, `pending_payment`; successful payments = attempts with status `paid` or `verified`. Occupancy = occupied nights / (properties x days in period - blocked nights), capped at 100. Blocked nights = distinct dates covered by admin availability blocks plus events of enabled external feeds. Collected = receipts issued in the period. The reports page states that net margin, expenses, OTA commissions and refunds are not calculated.
Access: admin and host (`establishment-manager`). Concierge and customer: 403. Audit and error logs: admin only.

---

## UAT-RPT-001: Report page default view and metric blocks
- **Feature / journey:** `/admin/reports`
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `admin.reports.index`; `AdminReportController::reportContext`; `ReportingTest::test_admin_reporting_calculates_operational_and_financial_metrics`
- **Preconditions / setup:** Data set loaded for the current month: 2 properties; reservations: 1 confirmed (3 nights), 1 completed (2 nights), 1 pending, 1 cancelled; 1 receipt; payment attempts (1 paid, 1 failed).
- **Test data:** Synthetic reservations on `example.test` guests.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/reports` | Default period = first to last day of the current month. Sections: operations, finance, payments by provider, property performance, quality (reviews), upcoming arrivals (next 14 days, max 12). |
  | 2 | Compare reservation count, arrivals, departures, cancellations and cancellation rate with the data set | Totals match (cancellation rate = cancelled / all reservations in period). |
  | 3 | Compare booked value with the sum of `total_amount` of confirmed, checked-in, completed reservations that arrive in the period | Equal. Outstanding equals the pending + pending_payment totals. |
  | 4 | Compare "collected" with the receipts issued in the period | Equal; separate lines per currency. |
  | 5 | Compare payment success rate | Successful (paid/verified) / all attempts created in the period. |
  | 6 | Read the disclaimer text about margin, expenses, commissions and refunds | Present. |
  | 7 | Switch to FR | Labels localized, amounts formatted `100 000,00 XOF`. |
- **Acceptance criteria:** Displayed numbers equal the manual calculation from the dataset.
- **Evidence to capture:** Screenshot of the report, calculation sheet.
- **Notes / assumptions / open questions:** Reservations are included if they overlap the period, but "booked value" and "arrivals" count only check-ins inside the period.

## UAT-RPT-002: Filters and validation (dates, establishment, property)
- **Feature / journey:** Report filters
- **Priority:** P1
- **Role / permissions:** Admin, Host
- **References:** `reportContext`; lang `reports.period_too_long` ("The report period cannot exceed 366 days.")
- **Preconditions / setup:** Establishments `E1`, `E2`; properties `PA` (`E1`), `PB` (`E2`).
- **Test data:** Query strings below.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `?date_from=2026-01-01&date_to=2026-01-31` | Report for January. |
  | 2 | `?establishment=E1` | Only properties of `E1`. |
  | 3 | `?establishment=E1&property=PB` | 403 (property not in selected establishment). |
  | 4 | `?property=PA` | Only `PA`. |
  | 5 | `date_to` before `date_from` | Error on `date_to`. |
  | 6 | Period of exactly 366 days | Accepted. 367 days: error "The report period cannot exceed 366 days." |
  | 7 | `date_from=abc` | Validation error (no 500). |
  | 8 | Single day (`from = to`) | Report for that day. |
  | 9 | `?establishment=999999` | 403 (not in allowed set). |
  | 10 | Host: `?establishment=E2` | 403 "Administrator or host access is required." |
  | 11 | Only `date_from` given | `date_to` defaults to end of current month; may fail the 366-day check if `date_from` is very old. Record. |
- **Acceptance criteria:** Each filter narrows the data; limits are enforced with clear errors.
- **Evidence to capture:** Screenshots per case.
- **Notes / assumptions / open questions:** None.

## UAT-RPT-003: Occupancy, blocked nights and boundaries
- **Feature / journey:** Occupancy calculation
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `ReportingService::blockedNights`, `reservationNights`, `dateKeys`
- **Preconditions / setup:** One property `PA`, no other data. Period `D1` to `D10` (10 days). Reservation A confirmed `D2` to `D5` (3 nights). Reservation B confirmed `D9` to `D13` (runs past the period end; nights D9 and D10 fall inside the period = 2 nights). Admin block `D6` to `D8`. Enabled external feed event `D7` to `D9`; a disabled feed with event `D1` to `D3`.
- **Test data:** Dates as above; `D1` chosen in the current month.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the report for `D1`-`D10` | Sellable nights = 10 - blocked. Blocked nights = distinct dates of block (D6, D7) and enabled event (D7, D8) = D6, D7, D8 = 3. Sellable = 7. |
  | 2 | Occupied nights | A: 3 (D2, D3, D4) + B: 2 (D9, D10) = 5. |
  | 3 | Occupancy rate | 5 / 7 = 71.4 percent. |
  | 4 | Disable the enabled external feed; reload | Blocked = D6, D7 = 2; sellable 8; occupancy 62.5 percent. |
  | 5 | Cancel reservation A | Occupied 2; occupancy drops; cancellations 1 of 2 = 50 percent. |
  | 6 | Report for a period with zero properties (host with none) | No error, zeros. |
  | 7 | Block covering the entire period | Sellable nights 0, occupancy 0 (no division by zero). |
- **Acceptance criteria:** Numbers equal the manual calculation (re-derive with your real dates).
- **Evidence to capture:** Calculation sheet and screenshots.
- **Notes / assumptions / open questions:** Block `end_date` and event end dates are treated as exclusive in `dateKeys` (end day not counted). This is consistent with availability logic but may surprise users who think the end day is blocked.

## UAT-RPT-004: Finance metrics with multiple currencies and payment providers
- **Feature / journey:** Finance section
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `ReportingService::build` finance array; `moneyByCurrency`; `averageMoneyByCurrency`
- **Preconditions / setup:** Two establishments: `E1` (XOF) and an `E3` (EUR) with properties and bookings; payment attempts across `fedapay`, `paypal`, `interac` (statuses paid, verified, failed, created).
- **Test data:** Booking totals: XOF 100000 and 50000; EUR 200.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Report with both establishments | Money lines are separate per currency (never summed across XOF and EUR). |
  | 2 | Check average booking | Average per currency. |
  | 3 | Check provider breakdown | Per provider: attempts, successful (paid or verified) and amounts per currency. |
  | 4 | A reservation with total in the period but receipt issued next month | Counts in booked value this month and "collected" next month. |
  | 5 | Subtotal, fees, taxes | Sum of reservation fields for booked reservations; equals `total_amount` per reservation. |
  | 6 | Cancel a paid reservation | Booked value drops; collected (receipts) unchanged (refunds are not modelled). |
- **Acceptance criteria:** Figures match manual sums per currency.
- **Evidence to capture:** Screenshots and spreadsheet of expected values.
- **Notes / assumptions / open questions:** Receipt amounts without currency default to XOF in the grouping.

## UAT-RPT-005: CSV export content, format and filters
- **Feature / journey:** `/admin/reports/export.csv`
- **Priority:** P0
- **Role / permissions:** Admin, Host
- **References:** `exportCsv`, `csvRows`; lang `reports.csv_*`
- **Preconditions / setup:** Data from RPT-001. Spreadsheet application available (Excel and a text editor).
- **Test data:** `?date_from=<first of month>&date_to=<last of month>`; property named `=SUM(1+1)` (created for the formula check).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click Export on the report page | Download `reports-YYYY-MM-DD-YYYY-MM-DD.csv` matching the selected period, content type `text/csv; charset=UTF-8`. |
  | 2 | Open in a text editor | UTF-8 BOM at the start, semicolon delimiter, first row headers (section, metric, entity, currency, value, notes) localized. |
  | 3 | Open in Excel | Columns split correctly; accented characters correct. |
  | 4 | Compare values with the HTML report | Same numbers (operations, finance by currency, provider rows, property rows, quality). |
  | 5 | Apply filters `establishment`, `property` then export | CSV respects the filters. |
  | 6 | Period over 366 days | Export fails with the same validation error as the page. |
  | 7 | Property named `=SUM(1+1)` | Record: the CSV writer does not neutralize formula prefixes, so Excel may evaluate it (CSV injection risk). |
  | 8 | Export with no data | Header and zero rows without error. |
  | 9 | Switch UI to EN and export | Headers in English. |
  | 10 | Concierge/customer request the URL | 403. |
- **Acceptance criteria:** CSV is a faithful copy of the report; filters apply.
- **Evidence to capture:** The CSV file and a screenshot of the spreadsheet.
- **Notes / assumptions / open questions:** SECURITY CANDIDATE: step 7 CSV/formula injection through property names or guest-origin country values.

## UAT-RPT-006: Report scope for hosts and access by role
- **Feature / journey:** Report permissions
- **Priority:** P0
- **Role / permissions:** Host, Concierge, Customer
- **References:** `reportContext`; `ReportingTest::test_host_reporting_is_limited_to_assigned_establishments`, `::test_customer_cannot_access_reporting`
- **Preconditions / setup:** Host assigned to `E1` only; data in `E1` and `E2`.
- **Test data:** Accounts as above.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Host opens `/admin/reports` | Only `E1` and its properties appear in selects and figures. |
  | 2 | Host requests `?establishment=E2` and `?property=PB` | 403. |
  | 3 | Host exports CSV | Contains only `E1` data. |
  | 4 | Concierge opens `/admin/reports` and `/admin/reports/export.csv` | 403. |
  | 5 | Customer and anonymous | 403 / login redirect. |
  | 6 | Admin figures equal host figures plus `E2` figures | Reconciles. |
- **Acceptance criteria:** No data outside the host's scope is visible or exported.
- **Evidence to capture:** Screenshots and status codes.
- **Notes / assumptions / open questions:** Guest-origin and channel breakdowns are computed within the same scope.

## UAT-AUD-001: Audit entries for create, update and delete with redaction
- **Feature / journey:** Audit trail recording
- **Priority:** P0
- **Role / permissions:** Admin
- **References:** `AppServiceProvider` `eloquent.created/updated/deleted` listeners; `AuditLog` model; `AuditLogTest::test_object_changes_are_recorded_and_audit_page_is_admin_only`
- **Preconditions / setup:** Admin logged in. Note the current count of audit rows.
- **Test data:** Property `UAT Audit Property`; user `uat-audit-user@example.test` with password.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Create a property (admin UI) | Audit entry action `created`, model `App\Models\Property`, user = admin. |
  | 2 | Change its name to `After change` | Entry `updated` with `old` and `new` values for changed attributes only; shows `After change`. |
  | 3 | Delete a reservation or cleaning visit | Entry `deleted` with the previous values under `old`. |
  | 4 | Create a user with a password (ADM-004) and change that password (ADM-010) | Audit details for `App\Models\User` contain no `password`, `remember_token`, `api_token`, or `token` values. |
  | 5 | Open the audit page and inspect details | Details include url, ip, user agent of the request. |
  | 6 | Perform an action through the public checkout (customer, not logged in) | Entry created with no user (user column empty). |
  | 7 | Run a scheduled command (e.g. `reservations:complete-past`) that updates reservations | Entries created with user empty. |
- **Acceptance criteria:** Each change creates one correct entry; secrets are not stored.
- **Evidence to capture:** Screenshots of entries; DB `audit_logs.details` for the user case.
- **Notes / assumptions / open questions:** Other secrets (e.g. offline payment `security_answer`, API keys saved in settings, signed tokens in URLs) are not redacted. The `url` field stores the full URL including query strings (e.g. checkout tokens). Record any sensitive value found.

## UAT-AUD-002: Audit page filters, pagination and validation
- **Feature / journey:** `/admin/audit-logs`
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `AdminAuditLogController::index`; heading "Journal des modifications" (FR)
- **Preconditions / setup:** At least 60 audit rows covering types `App\Models\Property`, `App\Models\Reservation`, actions created/updated/deleted over several dates.
- **Test data:** Filters `event`, `type`, `date_from`, `date_to`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open page | Newest first, 50 per page, pager. |
  | 2 | `?event=updated` | Only updated entries. `?event=hacked` returns a validation error. |
  | 3 | Pick a type from the select (lists distinct model types) | Entries of that type. |
  | 4 | `?date_from=<d>&date_to=<d>` | Entries of that day (inclusive). `date_to` before `date_from`: error. |
  | 5 | Combine filters and go to page 2 | Filters preserved in pager links. |
  | 6 | Custom actions (`host_assigned`, `host_removed`, `amenities_synced`) | Visible without the event filter; hidden when `event` is set (filter accepts only created/updated/deleted). |
  | 7 | Entry whose user was deleted | Shows no user name, no error. |
- **Acceptance criteria:** Filters work and invalid values are rejected.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** The details column renders JSON; confirm long details do not break layout and that HTML in values is escaped (e.g. property name `<b>x</b>`).

## UAT-AUD-003: Audit and error log access restricted to admins
- **Feature / journey:** Access control for logs
- **Priority:** P0
- **Role / permissions:** Admin allowed; Host, Concierge, Customer, anonymous denied
- **References:** `routes/web.php` (`admin.audit-logs`, `admin.logs` in group `admin`); `AuditLogTest`, `AdminLogTest::test_admin_can_view_limited_log_tail_and_customer_cannot`
- **Preconditions / setup:** Accounts for each role.
- **Test data:** URLs `/admin/audit-logs`, `/admin/logs`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Admin opens both pages | 200. |
  | 2 | Host opens both | 403 "Administrator access is required." |
  | 3 | Concierge, customer open both | 403. |
  | 4 | Anonymous | Redirect to `/login`. |
  | 5 | Navigation menus for host/concierge | No links to these pages. |
- **Acceptance criteria:** Only administrators can see audit and error logs.
- **Evidence to capture:** Status codes.
- **Notes / assumptions / open questions:** None.

## UAT-AUD-004: Error log viewer: file selection, line limit and unsafe parameters
- **Feature / journey:** `/admin/logs`
- **Priority:** P1
- **Role / permissions:** Admin
- **References:** `AdminLogController::index`; `resources/views/admin/logs.blade.php`; `tests/Feature/ErrorLoggingTest.php`
- **Preconditions / setup:** `storage/logs` has at least two `.log` files (e.g. `laravel-YYYY-MM-DD.log` and an errors daily log) plus a non-log file; a log with more than 1000 lines.
- **Test data:** Query strings `file`, `lines`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/admin/logs` | Most recently modified `.log` file selected; last 300 lines shown. |
  | 2 | `?file=<other log name>` | That file shown; selector lists only `.log` files, newest first. |
  | 3 | `?lines=10` | Clamped to 50 lines. `?lines=5000` clamped to 1000. `?lines=abc` falls back to default (record). |
  | 4 | `?file=../../.env`, `?file=..%2F..%2F.env`, `?file=/etc/passwd`, `?file=nonexistent.log` | Falls back to the default (first) log file; `.env` content never displayed. |
  | 5 | A non-`.log` file in the folder | Not listed. |
  | 6 | Empty `logs` directory | Page renders with empty content, no error. |
  | 7 | Log containing `<script>alert(1)</script>` text | Rendered escaped. |
  | 8 | Trigger a known failure (e.g. copy improver failure PROP-018 or a reservation delete notice RES-017), reload | New line appears in the latest log. |
  | 9 | Check log content for secrets or guest PII | Record any password, token, API key or email in the visible tail. |
- **Acceptance criteria:** Viewer is read-only, restricted to the log folder and limited to 1000 lines.
- **Evidence to capture:** Screenshots; note of any sensitive data seen.
- **Notes / assumptions / open questions:** Files with very large size are fully read into memory by `File::get`; record page behavior on a 50 MB+ log (performance).

## UAT-AUD-005: Unhandled errors are written to the error log
- **Feature / journey:** Error logging channel
- **Priority:** P2
- **Role / permissions:** Admin (viewer), developer/ops (trigger)
- **References:** `tests/Feature/ErrorLoggingTest.php::test_errors_channel_writes_an_exception_to_the_daily_error_log`; `config/logging.php`; `/health` route
- **Preconditions / setup:** A non-production environment where an operator can cause a controlled failure (for example point `DB_HOST` to a wrong host for one request, or request a route known to throw). Production must not be used.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Cause the controlled failure and load a page | Generic error page shown to the user (no stack trace when `APP_DEBUG=false`). |
  | 2 | Open `/admin/logs` | An error entry with timestamp and exception class appears in the newest log. |
  | 3 | Check the page output | No credentials appear in the page or log entry. |
  | 4 | Restore settings; reload | Normal operation. |
- **Acceptance criteria:** Failures are logged and not leaked to end users.
- **Evidence to capture:** Screenshot of the error page and log excerpt (redacted).
- **Notes / assumptions / open questions:** Exact channel name and file naming follow `config/logging.php`; confirm with ops where the file lands.
