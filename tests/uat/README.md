# Afrik Appart UAT suite

User acceptance test definitions for the Afrik Appart Laravel 12 short-term rental platform (public booking site, customer account, host/staff back office, platform admin, mobile API, payment and calendar integrations, deployment).

Status: baseline for review. No case has been executed. Nothing here claims a pass.

## Layout

| Path | Purpose |
|---|---|
| `test-cases/` | Approved test definitions, one file per feature group. Do not record results here. |
| `traceability.md` | Requirement and route to case map, reconciliation notes, full case catalog. |
| `requirements-gaps.md` | Stated requirements vs implementation evidence, product-owner questions, defect candidates. |
| `runs/run-template.md` | Copy per run to record results. |
| `runs/evidence/` | Screenshots and files, in `<run-id>/<case-id>/`. |

Repository evidence is the source of truth. Requirements come from `docs/requirements-baseline.md`, `README.md`, `docs/adr/0002-hospitality-os-multi-tenant-architecture.md`, `docs/mobile-api.md`, the master prompt files and the code. Where behavior is unclear, the case says "record actual" and lists the question. A tester must not treat a case's expectation marked as an assumption as confirmed.

## Case format

Each case has: ID, title, feature, priority, role, references, preconditions, test data, a numbered action/expected-result table, acceptance criteria, evidence to capture, and notes. Priorities: P0 critical (money, access, data isolation, booking integrity), P1 high, P2 standard.

## Execution

1. Copy `runs/run-template.md` to `runs/<date>-<environment>.md` and fill the run header.
2. Run cases in priority order (P0 first). Cases are designed to be independent; where one needs data from another, its preconditions say so.
3. Set each status to `Not Run`, `Pass`, `Fail` or `Blocked`. Use `Blocked` when a precondition cannot be met (for example a provider sandbox is unavailable), and state why in Actual result.
4. For every `Fail`, capture a screenshot or response and the exact message. For cases that say "record actual", write what happened; judge Pass/Fail against the product owner's answer to the linked question, or mark `Blocked` until answered.
5. Never edit `test-cases/` during a run. Propose definition changes in a review note.

### Rerunning failed or blocked cases
Last run: `runs/2026-11-22-afrikappart.md` (244 Pass, 107 Fail, 56 Blocked). Its per-case table lists the IDs to retest.

1. Fix the defects, then run `composer install` (the PDF library `barryvdh/laravel-dompdf` was missing from `vendor/`).
2. Run `powershell -File tests/uat/scripts/setup-local-uat.ps1 -Instances 5`. It creates throwaway seeded SQLite databases and starts servers on `http://127.0.0.1:8101` to `:8105`. Passwords are generated into `%TEMP%\uat-run\secrets.txt`, outside the repo. Before any `php artisan` command for instance N, run `& "$env:TEMP\uat-run\env.ps1" -N <N>`.
3. Run the failed and blocked cases, one instance per tester. Copy `runs/run-template.md` to a new dated file in `runs/` and keep the earlier report as history.
4. Run `powershell -File tests/uat/scripts/teardown-local-uat.ps1` when finished. It stops the servers, removes uploads the run created and restores the two tracked files that seeding rewrites.

Blocked cases that need more than this setup (provider sandboxes, a browser, MySQL, a staging host) are listed with their required setup in section 4 of the report.

### Evidence to capture
Screenshots of each key expected result, the final URL, any error message text, generated records (reservation reference, receipt PDF, email body from the log or mail catcher, audit log row), and the locale and viewport used.

## Environment and accounts

- Dedicated non-production environment (local `php artisan serve` or staging) migrated and seeded per `README.md`. Production data and real payment accounts must not be used.
- Seeded accounts: `admin@afrikappart.test` (admin and platform admin), `host@afrikappart.test`, `concierge@afrikappart.test`, `guest@afrikappart.test`. Passwords come from `SEED_ADMIN_PASSWORD` and `SEED_GUEST_PASSWORD` in the tester's `.env`. Obtain them from the environment owner; they are not in this repository. Extra users are created by the cases with `@example.test` addresses.
- A second tenant is needed for isolation cases (created through the platform admin or host signup, `PLATFORM_MODE=cloud`). Cases state when cloud mode is required.
- Mail: use `MAIL_MAILER=log` or a mail catcher. Payment: provider sandbox credentials or the documented simulated webhook, supplied out of band via `.env`. Never paste secrets into cases or evidence.
- Browsers: a current Chromium-based browser and one mobile viewport (about 375 px wide). Both `fr` (default) and `en` locales where a case says so.
- Fixture files (small JPEG/PNG, oversize file, PDF, `.ics`, CSV) are described in the cases using placeholder names. Create synthetic ones; do not use real guest data.

## Roles

| Role | How determined |
|---|---|
| Guest | No session |
| Customer | `users.role = customer`, no tenant, shared across tenants |
| Concierge | `role = concierge`, reservation staff |
| Host | `role = host`, limited to assigned establishments |
| Tenant admin | `role = admin` or `is_admin = 1`, own tenant |
| Platform admin | `is_platform_admin = 1`, `/platform/tenants*` |

## Coverage summary

407 cases: 157 P0, 189 P1, 61 P2.

| Area | Prefixes (count) |
|---|---|
| Public site, booking, search | PUB (35), I18N (8), LEGAL (6), A11Y (4), HELP (6), FEED (5), CAL (5) |
| Checkout and payments | PAY (34) |
| Authentication, roles, tenancy | AUTH (24), RBAC (10), TEN (11), SEC (7) |
| Customer account, messaging, reviews | ACCT (16), MSG (9), REV (8) |
| Mobile API | API (16) |
| Admin back office | ADM (13), PROP (26), EST (12), RES (19), CALA (10), CLN (11), RPT (6), AUD (5), REVA (8), MAIL (9), JOB (4) |
| Platform admin | PLAT (15) |
| Integrations | INT (45) |
| Operations and deployment | OPS (20) |

Every route group in `routes/web.php` and `routes/api.php` is mapped in `traceability.md` section 1. The case catalog is in section 3.

### Out of scope or limited coverage
- Visual design fidelity to the sample HTML pages (`home_preview.html`, `details_preview.html`) beyond the responsive and accessibility cases. No design acceptance criteria exist in the repository.
- Load, performance and penetration testing. SEC and INT cases are user-observable checks only.
- Live production payments. Cases use provider sandboxes or simulated webhooks. CinetPay and M-Pesa live paths can be tested only if sandbox accounts exist.
- AI answer quality for the help chat and copy improver (no acceptance threshold is defined). Only availability, limits and failure behavior are covered.
- Public routes only partly covered: mobile-API public endpoints are in API-006/007; `/cleaning-schedule/{token}` is in CLN-008/009.
- No WCAG conformance target is stated in the repository, so accessibility cases check keyboard use, labels and responsive layout and record findings rather than a formal level.
- `docs/M01-audit-proposal.md` and `docs/M02-COMPLETION-SUMMARY.md` describe work that does not match the current `project-state.json`; they were not used as requirements.
- Most cases were designed from reading code and the existing PHPUnit tests, not from running the app. Some steps may need adjustment on first execution (record the correction in a review note, not in the baseline).

## Unresolved questions for the product owner

Details and sources are in `requirements-gaps.md`. The ones that decide how P0 cases are judged:

1. Payment hold: baseline says 20 minutes; code uses a 48-hour checkout link. Do unpaid `pending` reservations keep dates blocked, and for how long?
2. Is online payment required (baseline) while `pay_later` is on by default?
3. Are provider webhooks exempt from CSRF in the deployed stack? No exclusion was found in `bootstrap/app.php` (INT-014, PAY-027).
4. What should happen when a guest abandons a provider payment page? `checkout.cancel` is POST-only but is used as a provider cancel URL (PAY-022).
5. Should guest count be checked against `max_guests` on reserve and availability (PUB-037)?
6. Calendar sync cadence (baseline 15 minutes, schedule hourly) and whether a stale external feed blocks booking (INT-031, CALA-006).
7. The public iCal feed exports only confirmed, checked-in and completed reservations, while the site blocks dates for all non-cancelled ones. Is this intended (CAL-003, CAL-004)?
8. Suspending a tenant: should it hide its public properties, block booking, and block the mobile API (TEN-005, PLAT-006, PLAT-012)?
9. Login, register and API login have no throttle. Is one required (AUTH-015)?
10. Should API tokens expire and should API logout revoke the token (API-005)?
11. Offline payment proofs: is there a reject action, and should proof files be web-reachable (RES-014, PAY-034)?
12. Which refund and cancellation-fee behavior is expected for confirmed reservations (RES-011, ACCT-010)?
13. Who receives platform feedback, and is a triage screen expected (PLAT-019, FEED-004)?
14. Is there a target accessibility standard and a required visitor currency switcher (A11Y, I18N-006)?
15. Queue worker availability on the host for `CompletePastReservations` (OPS-012, JOB-001).

## Possible defects found while reading (to verify at execution, not confirmed)

- `app/Services/Payments/CinetPayLiveGateway.php` and `MpesaLiveGateway.php` appear to contain orphaned lines after `createIntent`. Run `php -l` on both before executing INT-012 and INT-013.
- Webhook CSRF and `checkout.cancel` method mismatch (above).
- Hard-coded French or English validation and payment messages regardless of locale (I18N-007, I18N-008).
- `AdminController::users` filter precedence may leak users across tenants (TEN-007, ADM-008). Host and tenant ownership checks in property and offline-payment confirmation may be too permissive (PROP-003, RES-018, TEN-011).
- `afrikappart_db.txt` is tracked and contains a token-like string. Review and rotate if it is a credential (OPS-020). Its value is deliberately not reproduced here.
- Reservation emails and receipts: wording, formatting and opt-out issues listed in MAIL-002 to MAIL-008.

## Maintenance

Add new cases to the matching file in `test-cases/` using the next free ID in the prefix, then update `traceability.md`. A removed case must not have its ID reused. `traceability.md` section 2 lists cases merged during reconciliation.
