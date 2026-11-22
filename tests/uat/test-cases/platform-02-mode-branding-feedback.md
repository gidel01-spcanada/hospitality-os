# Platform admin: platform mode, branding, navigation and platform feedback (UAT-PLAT-013 to UAT-PLAT-020)

Scope: `php artisan platform:mode` (`SetPlatformMode`), `config/platform.php`, `PlatformBrand`, admin navigation to platform pages, `/feedback` (`PlatformFeedbackController`, `PlatformFeedbackReceived`, `platform_feedback` table, `SPRINTPAY_FEEDBACK_EMAIL`).

Important behavior facts from the code:
- `platform:mode` rewrites the `PLATFORM_MODE` line in the project `.env` and runs `config:clear`. It must only be run on a local or disposable test environment, never on a shared or production `.env`.
- "Platform feedback" is a public form that stores a `platform_feedback` row and emails one configured address (`services.sprintpay.feedback_email`). No platform-admin screen lists or triages feedback; "triage" is therefore a mailbox and database activity. See UAT-PLAT-019 and `tests/uat/requirements-gaps.md`.

Common test data: platform admin = seeded `admin@afrikappart.test` (password supplied out of band). Feedback sender `feedback-tester@example.test`. Feedback recipient (test mailbox) `feedback-inbox@example.test`.

---

## UAT-PLAT-015: Public branding and navigation follow the platform mode
- **Feature / journey:** Cloud vs on-premise branding
- **Priority:** P1
- **Role / permissions:** Anonymous visitor; platform admin
- **References:** `App\Support\PlatformBrand`; `AppServiceProvider::boot` (sets `app.name`); `resources/views/layouts/app.blade.php` (host-register link, theme); `resources/views/layouts/admin.blade.php` line 156 (platform nav link); `tests/Feature/DatabaseSchemaTest.php::test_on_premise_branding_uses_the_default_tenant_settings`
- **Preconditions / setup:** Ability to switch modes (UAT-PLAT-013). Admin Settings site name set to `UAT Brand` in tenant `default`.
- **Test data:** Site name `UAT Brand`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Mode `on_premise`: open `/` logged out. Check page title, header and footer brand. | Brand is `UAT Brand` (tenant setting). No "Become a host" (host register) link. |
  | 2 | Mode `cloud`: reload `/`. | Brand reads `Hospitality OS` regardless of the tenant site name. A host-registration link to `/host/register` appears in the header. |
  | 3 | Mode `cloud`: log in as a tenant admin without platform flag and open the admin menu. | No "Tenants" platform link. |
  | 4 | Mode either: log in as the seeded platform admin and open the admin menu. | A "Tenants" link to `/platform/tenants` is shown and highlighted on `/platform/*` pages. |
  | 5 | After step 2, run `php artisan config:cache`, reload. | Brand still `Hospitality OS` (not blank or `null`). Record the result. |
- **Acceptance criteria:** Brand and navigation match the active mode, with and without cached config.
- **Evidence to capture:** Screenshots of header/footer and page title in each mode; admin menu screenshots.
- **Notes / assumptions / open questions:** Step 5 confirms the cached config yields the same brand. In cloud mode the customer theme is forced to `emerald-gold`; on-premise uses the tenant `customer_theme` setting.

---

## UAT-PLAT-019: Feedback records can be reviewed for triage (no in-app screen)
- **Feature / journey:** Feedback triage by the platform team
- **Priority:** P2
- **Role / permissions:** Platform admin; database read access holder
- **References:** `app/Models/PlatformFeedback.php`; `database/migrations/2026_10_01_000002_create_platform_feedback_table.php`; `routes/web.php` (no feedback admin route); `/admin/audit-logs`
- **Preconditions / setup:** At least 3 feedback rows with categories suggestion, issue, other (from UAT-PLAT-016).
- **Test data:** Rows created in UAT-PLAT-016.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | As platform admin browse the admin menu and `/platform/*` pages. | Record whether any screen lists feedback. Per the code, none exists. |
  | 2 | Try `/platform/feedback` and `/admin/feedback`. | HTTP 404 (fallback route). |
  | 3 | With database read access run `SELECT id, category, email, created_at FROM platform_feedback ORDER BY id`. | Rows exist and can be triaged from the table or from the mailbox. Message content is stored in clear text. |
  | 4 | Check `/admin/audit-logs` for the `PlatformFeedback` create entries. | Entries exist (generic model audit). Record whether the audit details include the message body and email. |
- **Acceptance criteria:** The product owner confirms that mailbox plus database access is the intended triage process, or logs a gap. This case does not pass or fail the build on its own.
- **Evidence to capture:** Screenshot of the 404 pages; SQL result (redact emails if real).
- **Notes / assumptions / open questions:** The table has only id, user_id, name, email, category, message and timestamps (migration `2026_10_01_000002`): no status, assignee or resolved fields. The audit journal storing personal data from feedback is a privacy question for the owner.

---

## UAT-PLAT-020: Platform admin dual role in on-premise installs (single tenant)
- **Feature / journey:** Seeded admin as both tenant admin and platform admin
- **Priority:** P2
- **Role / permissions:** Seeded admin
- **References:** `database/seeders/AuthSeeder.php` (comment: on-premise admin also gets platform admin); `README.md` seed section; `/platform/tenants`
- **Preconditions / setup:** Fresh seed (`php artisan migrate --force && php artisan db:seed`) with `SEED_ADMIN_PASSWORD` and `SEED_GUEST_PASSWORD` set; `PLATFORM_MODE=on_premise`.
- **Test data:** Seeded users `admin@afrikappart.test`, `concierge@afrikappart.test`, `guest@afrikappart.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in as `admin@afrikappart.test`. | Admin dashboard; a "Tenants" menu item is present. |
  | 2 | Open `/platform/tenants`. | One tenant `Default` (active) listed; no add button. |
  | 3 | Click "Suspend" on `Default` ONLY on a throwaway database. | Redirect with success; next request logs the admin out with the suspended message; all `default` tenant users are locked out until reactivated through the database (`UPDATE tenants SET status='active' WHERE slug='default'`). |
  | 4 | Log in as `concierge@afrikappart.test`. | No "Tenants" menu; `/platform/tenants` returns 403. |
- **Acceptance criteria:** Platform controls are visible only to the seeded admin; step 3 documents that the single-tenant operator can lock themselves out.
- **Evidence to capture:** Screenshots; SQL used for recovery.
- **Notes / assumptions / open questions:** Step 3 is destructive for the environment; run only on a disposable database. Product owner question: should a platform admin be prevented from suspending their own tenant, and should there be a confirmation step?
