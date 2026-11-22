# Ops acceptance: release build, deployment, scheduler, production configuration, exposure, logging (UAT-OPS-011 to UAT-OPS-020)

Scope: `scripts/build-release.(ps1|sh)`, `scripts/deploy-bluehost.ps1`, `bluehost-deploy-script.ps1`, `scripts/post-deploy-validate.sh`, `scripts/post-deploy-bluehost.sh`, `scripts/upload-*-bluehost.ps1`, `.github/workflows/ci.yml`, `.github/workflows/deploy.yml`, `.github/dependabot.yml`, `.bluehost-credentials.template.json`, `routes/console.php`, `config/*`, `.env.example`, `app/Http/Middleware/SecurityHeaders.php`, `public/.htaccess`, `config/logging.php`, `AdminLogController`.

Rules: use staging infrastructure only. Credentials files, `.env` values and CI secrets are never opened in front of evidence capture; record names and presence only. Variable names referenced below come from `.env.example` and `config/*.php`.

---

## UAT-OPS-011: Release bundle contains what production needs and nothing private
- **Feature / journey:** Release packaging
- **Priority:** P0
- **Role / permissions:** Release engineer
- **References:** `scripts/build-release.ps1` / `.sh` (outputs `release/app`, `release/public`, `release/release-manifest.sha256`); `.gitignore` (`/release`); `docs/adr/0001-laravel-bluehost.md`; master prompt "release can be uploaded ... without a running Node service"
- **Preconditions / setup:** Clean checkout on a build machine with Composer and Node. Note: the script runs the test suite (excluding `PropertyMediaImportTest`), runs `git clean -fd -- public/uploads` and finishes with `composer install --no-dev --optimize-autoloader`.
- **Test data:** None. Place a dummy file `public/uploads/uat-untracked.txt` before the run to observe the clean step.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run `powershell -ExecutionPolicy Bypass -File .\scripts\build-release.ps1` (or `./scripts/build-release.sh`). | Completes with exit code 0; if `npm ci` fails the script warns and falls back to `npm install`. |
  | 2 | Check `release/app` and `release/public`. | `release/public/build/manifest.json` exists; `release/app/vendor` is production-only (no PHPUnit); no `public/hot` file. Record whether `release/app/public/build` exists: the build script removes it from the app bundle, and `deploy-bluehost.ps1` uploads `release/public/build` to both `<publicPath>/build` and `<appPath>/public/build`, which the post-deploy scripts require. |
  | 3 | Search `release/app` recursively for private content: `.env`, `.env.example`, `*.sql` (especially `afrik-appart-mysql-seed-export.sql`, `afrik-appart-mysql-export.sql`), `afrikappart_db.txt`, `.bluehost-credentials*`, `tests/`, `storage/logs/*`, `database/*.sqlite`, `test-failure-full.txt`, `.phpunit.result.cache`, `*.sha256`. | None present. Record every hit: the skip list in `build-release.ps1` does not name the SQL exports, `test-failure-full.txt` or the root `.sha256` files, so they may be copied into `release/app`. |
  | 4 | Check `database/*.sql` in the bundle (`schema.sql`, `seed.sql`, `sample-data.sql`, `bluehost-import.sql`). | Record: these are shipped to the application folder (outside the web root). Confirm with the product owner they may sit on the server. |
  | 5 | Verify `release-manifest.sha256`. | Contains a hash per file; spot-check three files with `Get-FileHash`. |
  | 6 | Check the untracked dummy file. | Removed by `git clean -fd -- public/uploads` (warning: this deletes any untracked local uploads such as imported photos or proofs on the build machine). |
  | 7 | Run the build twice. | Output identical in structure; the second run does not carry stale files (the release folders are recreated). |
- **Acceptance criteria:** The bundle contains the runnable application and compiled assets, with no secrets, database dumps, tests or local data.
- **Evidence to capture:** Directory listing of the bundle (top levels), results of the search in step 3, build log (no secrets).
- **Notes / assumptions / open questions:** A tracked file `afrikappart_db.txt` is explicitly excluded; see UAT-OPS-020 for why. Running `git clean` on `public/uploads` is a data-loss risk for developers; the product owner should decide whether the script should do it.

---

## UAT-OPS-012: Scheduler runs every task: emails, calendar sync, review sync, reservation completion
- **Feature / journey:** Cron and scheduled jobs
- **Priority:** P0
- **Role / permissions:** Operator with cron access on the host
- **References:** `routes/console.php`; README "Project status" (single cron entry `* * * * * ... php artisan schedule:run`); `app/Jobs/CompletePastReservations.php` (`ShouldQueue`); `QUEUE_CONNECTION` in `.env.example` (`database`); `CHECKOUT_COMPLETION_GRACE_HOURS`; `php artisan reservations:complete-past`
- **Preconditions / setup:** Staging host with the application and one cron entry exactly as in the README (path adapted). Queue worker NOT started. Test data: one `confirmed` reservation whose check-out date is two days ago; one queued `reservation_created` outbox row.
- **Test data:** Synthetic reservation `UAT-CRON-001` for `cron-guest@example.test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `php artisan schedule:list`. | Shows: calendar sync closure (hourly), `CompletePastReservations` job (hourly), `messages:send-email-notifications --limit=50` (every minute), Google review sync closure (every 6 hours). |
  | 2 | Wait for two cron ticks. | The queued outbox row becomes `sent` (or `failed` with an error if mail is unreachable) without manual action. |
  | 3 | Check the cron daemon log or `crontab -l`. | One line only; no separate lines per command. |
  | 4 | At the top of the next hour check the reservation `UAT-CRON-001`. | Record the status. Expected by the README: `completed` plus a status email queued. |
  | 5 | If still `confirmed`: `SELECT COUNT(*) FROM jobs;` | Record whether a pending `CompletePastReservations` job sits in the `jobs` table (the scheduler dispatches it to the configured queue; with `QUEUE_CONNECTION=database` it runs only when a worker processes it). |
  | 6 | Run `php artisan queue:work --stop-when-empty` once. | The reservation becomes `completed`. Record that a worker or `QUEUE_CONNECTION=sync` is required. |
  | 7 | Run `php artisan reservations:complete-past` on another overdue reservation. | Completed immediately (runs synchronously). |
  | 8 | Stop the cron entry for 10 minutes and queue an email. | Email waits; after cron resumes it is sent. No alert exists for a stopped scheduler. |
- **Acceptance criteria:** Each scheduled capability runs from the single cron entry. Step 5 or 6 results decide whether a queue worker is a documented requirement.
- **Evidence to capture:** `schedule:list` output; `crontab -l` with paths masked; SQL status before/after; `jobs` table count.
- **Notes / assumptions / open questions:** CI uses `QUEUE_CONNECTION=sync`, so the test suite does not reveal the missing worker. The README does not mention `queue:work`. Product owner: should the production default be `sync`, or should a worker be required? The reservation completion drives review-request emails (UAT-INT-037).

---

## UAT-OPS-013: Deployment script preflight, incremental upload and secret handling
- **Feature / journey:** Deploy to Bluehost (FTP/FTPS script)
- **Priority:** P0
- **Role / permissions:** Release engineer with staging hosting credentials
- **References:** `scripts/deploy-bluehost.ps1` (`-CredentialsPath`, `-ReleaseRoot`, `-CleanRemoteDevelopmentTrees`, `-Force`, `-SkipVendor`); `bluehost-deploy-script.ps1` (wrapper with a fixed Windows path); `.bluehost-credentials.template.json` (keys: host, username, password, port, useTls, allowInvalidCertificate, appPath, publicPath); `scripts/upload-seed-media-bluehost.ps1`, `upload-property-videos-bluehost.ps1`, `upload-reviews-bluehost.ps1`, `deploy-seo-files-bluehost.ps1`
- **Preconditions / setup:** A built release (UAT-OPS-011). A staging hosting account and a credentials JSON file outside the repository (never inside `repository/`). Credentials file content is created by the environment owner.
- **Test data:** Staging paths supplied by the owner.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run the script with `-CredentialsPath` pointing to a missing file. | Error `Credentials file not found: <path>`; nothing uploaded. |
  | 2 | Run with a credentials file whose `password` still starts with `REPLACE_`. | Error `The credentials file still contains the placeholder password.` |
  | 3 | Run with a credentials file missing `appPath`. | Error `Missing 'appPath' in the credentials file.` |
  | 4 | Run with `-ReleaseRoot` pointing to an empty folder or to a release without `public/build/manifest.json`. | Error `Vite manifest not found ... Build the release before deploying.` |
  | 5 | Set `allowInvalidCertificate` to true on staging. | A warning that TLS validation is disabled appears. Reset to false and record that production must use false. |
  | 6 | Run a normal deploy to staging. | Output lists uploaded files, then `Bluehost deployment completed: N file(s) uploaded, M file(s) skipped (unchanged).`; the remote `.deploy-manifest.json` is written. |
  | 7 | Check the remote app folder. | The remote `.env` is untouched and still present; no `.env` or `.env.example` was uploaded (script skips them). |
  | 8 | Run the same deploy again. | Almost all files skipped (checksum manifest); the count of uploaded files is near zero. |
  | 9 | Change one PHP file in the release, redeploy. | Only that file is uploaded. |
  | 10 | Run with `-Force`. | All files re-upload. |
  | 11 | Interrupt the network midway (disconnect for 20 seconds) during a deploy. | Upload retries up to 3 times per file; after failure the script stops with an error; the remote manifest is not updated for files that failed. Re-run completes the rest. |
  | 12 | Confirm persistent data: uploaded property photos and payment proofs on the server before and after the deploy. | Still present; not deleted by the deploy. |
- **Acceptance criteria:** Safe preflight failures, incremental behavior, `.env` and uploads preserved.
- **Evidence to capture:** Script output (mask host and user); remote directory listing; manifest entry counts.
- **Notes / assumptions / open questions:** The README and master prompt describe an SFTP deployment while the script uses FTP/FTPS (`FtpWebRequest`, `useTls`); the GitHub workflow uses SSH/SCP. Confirm which channel the hosting plan supports. `bluehost-deploy-script.ps1` hard-codes a local Windows path and a credentials path one folder above the repository; it will not run elsewhere. With `-CleanRemoteDevelopmentTrees` the script deletes the remote `hot` files (`<publicPath>/hot`, `<appPath>/public/hot`); verify that effect on staging.

---

## UAT-OPS-014: Post-deploy validation script success and failure detection
- **Feature / journey:** Post-deploy checks
- **Priority:** P0
- **Role / permissions:** Operator with shell access on the host (or staging copy)
- **References:** `scripts/post-deploy-validate.sh [APP_ROOT]`; `scripts/post-deploy-bluehost.sh [APP_ROOT]`; `/up`, `/health`, `/api/v1/properties`
- **Preconditions / setup:** Deployed staging app with a valid `.env`, database reachable, `APP_URL` set to the staging HTTPS URL.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run `bash scripts/post-deploy-validate.sh <app-root>`. | Prints PHP version, DB settings (names, host, database, user; no password), runs `config:cache`, `route:cache`, `view:cache`, `migrate --force`, links storage, checks `/up` (then `/health`), the home page for dev-server asset references, and `/api/v1/properties` returning a JSON `data` key. Ends with `Post-deploy validation completed successfully.` Exit 0. |
  | 2 | Remove `public/build/manifest.json` and rerun. | Fails with "Vite manifest is missing" and exit code 1. Restore. |
  | 3 | Rename `.env` and rerun. | Fails with ".env file is missing". Restore. |
  | 4 | Create `public/hot` (dev-server marker) with a Vite reference in the home page (simulate by adding `<script src="http://localhost:5173/@vite/client">` in a test view). | Script fails: "Production page is still referencing Vite dev-server assets." Revert. |
  | 5 | Point `APP_URL` to a wrong host. | Fails with "FAILED ... Primary health checks failed" and prints the last 80 log lines. Check that those log lines contain no secrets. |
  | 6 | Stop the database and rerun. | Migration step is skipped with a message (database unavailable) and the API check then fails (exit 1). Record the message. |
  | 7 | Use a database with tables but no `migrations` table. | Fails with the recovery message (see UAT-OPS-009 step 5). |
  | 8 | Make `/api/v1/properties` return 500 (break DB) and rerun. | "Mobile API check failed" exit 1. |
  | 9 | Run `post-deploy-bluehost.sh` with `APP_URL` env var empty and `.env` without `APP_URL`. | Fails "APP_URL is not set in .env". |
- **Acceptance criteria:** The script fails fast with a clear message for each broken precondition and succeeds only on a working deployment.
- **Evidence to capture:** Terminal output with exit codes.
- **Notes / assumptions / open questions:** The script uses `curl -k` (ignores TLS certificate errors) and treats redirects as success; an expired SSL certificate would not be detected here (master prompt lists "expired SSL" in the runbook). Both scripts clear caches and run `migrate --force` automatically, which is a deployment decision to confirm with the owner. The mobile API check requires `data` in the response; confirm it passes on a database with zero published properties.

---

## UAT-OPS-015: CI and deploy workflows behave as documented
- **Feature / journey:** GitHub Actions
- **Priority:** P1
- **Role / permissions:** Repository maintainer
- **References:** `.github/workflows/ci.yml` (push to `main`/`master`, pull requests; PHP 8.3, Node 20, sqlite in-memory, `php artisan test --no-interaction`); `.github/workflows/deploy.yml` (`workflow_dispatch`, input `environment`, GitHub environment `production`, steps use secrets named `SFTP_HOST`, `SFTP_USERNAME`, `SFTP_PRIVATE_KEY`, `SFTP_PORT`, `APP_REMOTE_PATH`, `PUBLIC_REMOTE_PATH`); `.github/dependabot.yml`
- **Preconditions / setup:** Access to the GitHub repository settings (read-only is enough for most steps).
- **Test data:** A throwaway branch `uat/ci-check` with a trivial docs change.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open a pull request from `uat/ci-check`. | CI workflow starts automatically, installs dependencies, builds assets and runs the test suite. Green when all tests pass. |
  | 2 | Push a commit that breaks one test (on the throwaway branch). | CI turns red and blocks the merge if branch protection is enabled. Record whether branch protection exists. |
  | 3 | Check the repository for known failing tests: the repository root contains `test-failure-full.txt` (a saved failure of `PaymentWorkflowTest` "checkout keeps last selected payment method accordion open by default"). | Run history shows whether that test passes in CI at the current head. Record the result. |
  | 4 | Open the Actions tab for the deploy workflow. | Only manual dispatch; never triggered on push. |
  | 5 | Check the `production` environment settings. | Required reviewers configured? Secrets named as above exist (names only; never open values). Record each answer. |
  | 6 | On staging only: dispatch the deploy workflow. | The release is built with `scripts/build-release.sh`, `release/app/*` and `release/public/*` uploaded to the configured paths. |
  | 7 | Inspect workflow logs. | No secret values are printed (masked). |
  | 8 | Check Dependabot configuration. | Update entries exist for the ecosystems in use; record which. |
- **Acceptance criteria:** CI is the quality gate for merges; deploys are manual and gated.
- **Evidence to capture:** Screenshots of workflow runs and environment settings (secrets list names only).
- **Notes / assumptions / open questions:** The deploy workflow does not run post-deploy validation or migrations; the operator must run `post-deploy-validate.sh` on the server (README and script). The CI uses PHP 8.3 while the README references PHP 8.5 locally; confirm the hosting PHP version. The workflow builds `release/` but does not run the test suite itself before uploading.

---

## UAT-OPS-016: Production configuration, HTTPS and security headers
- **Feature / journey:** Production hardening
- **Priority:** P0
- **Role / permissions:** Operator; tester with a browser
- **References:** `.env.example` (`APP_ENV`, `APP_DEBUG`, `APP_URL`, `SESSION_DRIVER`, `SESSION_ENCRYPT`, `SESSION_DOMAIN`, `LOG_LEVEL`); `config/session.php`; `app/Http/Middleware/SecurityHeaders.php` (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `X-XSS-Protection`, `Strict-Transport-Security`); `AppServiceProvider` (forces HTTPS scheme in production); `tests/Feature/ProductionSecurityTest.php`; `resources/views/errors/*.blade.php`
- **Preconditions / setup:** Staging environment mirroring production (`APP_ENV=production`, `APP_DEBUG=false`, HTTPS active).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Compare `.env.example` defaults (`APP_ENV=local`, `APP_DEBUG=true`) with the staging `.env` (read only the names and these two values). | Staging has `APP_ENV=production`, `APP_DEBUG=false`. A deploy that copies `.env.example` as is would run in debug mode; record how `.env` is created on the host. |
  | 2 | `curl -I http://<host>/`. | Redirect to HTTPS (host-level rule) or the app still serves over HTTP; record which. |
  | 3 | `curl -I https://<host>/`. | Headers present: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security: max-age=31536000; includeSubDomains`. Record the absence of `Content-Security-Policy` and `Permissions-Policy`. |
  | 4 | Inspect cookies (browser dev tools) after login. | `HttpOnly` set; `SameSite` value recorded; `Secure` flag set on HTTPS (record: `SESSION_SECURE_COOKIE` is not listed in `.env.example`). |
  | 5 | Open a non-existent URL and a forbidden URL. | Custom 404 and 403 pages in the active locale; no stack trace, framework version or file paths. |
  | 6 | Force a server error on staging (for example by temporarily stopping the database and opening a DB-backed page). | Custom 500 page; no exception details; the error is written to the error log (UAT-OPS-018). |
  | 7 | Trigger 419 (submit a form after deleting the session cookie) and 429 (send 6 feedback posts in a minute). | Custom 419 and 429 pages. |
  | 8 | Check `robots.txt` and `sitemap.xml`. | The repository `robots.txt` has an empty `Disallow:` (everything allowed) and a sitemap URL on `https://afrikappart.com`; `sitemap.xml` is a static file listing `https://afrikappart.com/...` URLs. Record the live content: the domain must equal the real production domain (`docs/content-todo.md` lists the domain as to be provided), and admin/account/checkout pages are protected only by `noindex` meta tags (step 9). |
  | 9 | Check `noindex` meta on admin, account, checkout and dashboard pages. | `noindex,nofollow` present. |
- **Acceptance criteria:** Production runs without debug output, behind HTTPS, with the documented headers.
- **Evidence to capture:** `curl -I` outputs, cookie screenshots, error page screenshots.
- **Notes / assumptions / open questions:** HSTS is sent whenever the middleware runs, including on non-HTTPS responses; the master prompt allows HSTS only after HTTPS is verified. A Content-Security-Policy is required by the master prompt and is absent in the code reviewed (see requirements-gaps.md). `public/.user.ini` sets `upload_max_filesize=3M` and `post_max_size=4M`, which is lower than the 5 MB limits in the offline-proof and review-import forms: test a 4 MB upload on staging (UAT-INT-035/040) and record the result.

---

## UAT-OPS-017: Private files and development artifacts are not reachable from the web
- **Feature / journey:** Web exposure check
- **Priority:** P0
- **Role / permissions:** Anonymous caller against the deployed site
- **References:** `docs/architecture.md` section 7; `docs/requirements-baseline.md` ("isolate application code, `.env`, and private files outside the public web root"); `public/.htaccess` (`-Indexes`); `scripts/deploy-bluehost.ps1` (separate `appPath` and `publicPath`); `.gitignore`
- **Preconditions / setup:** Staging deployment with the same folder layout as production.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Request `https://<host>/.env`, `/.env.example`, `/.git/config`, `/composer.json`, `/composer.lock`, `/artisan`, `/phpunit.xml`, `/package.json`. | Each returns 404 or 403; none returns file content. |
  | 2 | Request `/storage/logs/laravel.log`, `/storage/logs/error-<today>.log`, `/storage/framework/`, `/bootstrap/cache/config.php`, `/vendor/autoload.php`, `/database/schema.sql`, `/tests/`. | 404 or 403 each. |
  | 3 | Request `/uploads/`, `/uploads/properties/`, `/build/`. | No directory listing (`-Indexes`); 403 or 404. |
  | 4 | Request an existing uploaded property image URL. | 200 with correct content type. |
  | 5 | Request an existing payment-proof file URL (`/uploads/reservations/<id>/<file>`) without logging in. | Record the result. If it returns 200, private payment proofs are publicly retrievable by anyone with the URL (file names contain 12 random characters) and this is a finding for the owner. |
  | 6 | Upload a file named `test.php` as a proof or photo (rejected by validation) and a `.png` that contains PHP code; request it. | Upload rejected or served as an image only; never executed as PHP. |
  | 7 | Check the web root content (hosting file manager or FTP listing). | Contains only public files (`index.php`, `build/`, `uploads/`, `.htaccess`, SEO files) and no `.env`; `index.php` points to the application folder outside the web root. |
- **Acceptance criteria:** No private file or source is downloadable; no PHP executes from `uploads/`.
- **Evidence to capture:** curl status table for each URL; listing screenshot.
- **Notes / assumptions / open questions:** Whether the host applies PHP execution rules to `public_html/uploads` depends on the hosting; the repository ships no `.htaccess` inside `public/uploads`.

---

## UAT-OPS-018: Error logging, log viewer and retention
- **Feature / journey:** Error logs and admin log viewer
- **Priority:** P1
- **Role / permissions:** Admin; host; concierge; operator
- **References:** `bootstrap/app.php` (reports every exception to the `errors` channel); `config/logging.php` (`errors` channel: daily file `storage/logs/error.log`, retention `LOG_ERROR_DAILY_DAYS` default 30; `daily` retention `LOG_DAILY_DAYS` 14); `GET /admin/logs` (`admin.logs`, `AdminLogController`); `tests/Feature/ErrorLoggingTest.php`, `AdminLogTest.php`
- **Preconditions / setup:** Staging with `APP_DEBUG=false`. Ability to trigger a harmless error (for example POST invalid JSON to `/webhooks/fedapay` with a valid signature or request a route that throws on a test build).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Trigger a server error. | User sees the generic 500 page. |
  | 2 | Open `storage/logs/` on the server. | A file `error-YYYY-MM-DD.log` exists and contains the exception message and class. |
  | 3 | Log in as admin and open `/admin/logs`. | Page lists log files; shows a bounded tail (default 300 lines, between 50 and 1000 via `lines=`); file selection works only for files that exist in `storage/logs` (try `?file=../../.env`: no content is returned). |
  | 4 | Search the visible lines for secrets: passwords, tokens, API keys, full payment payloads, signed URLs with tokens. | None visible. Record anything sensitive (for example reset tokens or checkout tokens inside logged URLs or exception messages). |
  | 5 | Open `/admin/logs` as host, concierge, customer and anonymous. | Denied (403) or redirected to login; only admin sees logs. |
  | 6 | Check the retention settings in `.env` (`LOG_ERROR_DAILY_DAYS`, `LOG_DAILY_DAYS`). | Values present or defaults documented; old files beyond the retention period are deleted by Laravel when a new daily file is written. |
  | 7 | Fill a test host's disk quota (or simulate by making `storage/logs` read-only on staging) and trigger an error. | Record the behavior (error page still shown; app does not crash further). Restore. |
  | 8 | Check whether errors carry a correlation/request ID that the user can quote. | Record: the master prompt asks for correlation IDs; none seen in the error pages. |
- **Acceptance criteria:** Exceptions are persisted, viewable only by admins, without secrets, with retention.
- **Evidence to capture:** Log file name list, redacted excerpts, screenshots of `/admin/logs` access results.
- **Notes / assumptions / open questions:** In a multi-tenant deployment `/admin/logs` shows platform-wide log files to any tenant admin (the route is under the `admin` group, not `platform-admin`). Record whether a tenant B admin can read lines that include tenant A data (see UAT-PLAT-008).

---

## UAT-OPS-019: Rollback and runbook documentation acceptance
- **Feature / journey:** Rollback and operational procedures
- **Priority:** P1
- **Role / permissions:** Release engineer; product owner
- **References:** Master prompt sections "Operations and observability" and "Required documentation" (`docs/runbook.md`, `docs/bluehost-deployment.md`); README "Bluehost deployment & optimization"; `scripts/deploy-bluehost.ps1` manifest; `docs/` folder
- **Preconditions / setup:** Staging deployment of version N and a new build N+1.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Search the repository for `docs/runbook.md` and `docs/bluehost-deployment.md`. | Both required by the master prompt; at review time neither file exists. Record found / not found. |
  | 2 | Search the README for a rollback section. | The README documents deployment options but no rollback steps. Record. |
  | 3 | Deploy N+1 to staging, then redeploy N using the same script (a previous build kept locally). | Files revert to N (checksum manifest); `.env` and uploads preserved. |
  | 4 | If N+1 included a database migration, attempt to roll back. | Record what is possible: no documented `migrate:rollback` procedure or backup step exists; restore from backup (UAT-OPS-010) is the only path. |
  | 5 | Check that each scenario listed in the master prompt (expired SSL, failed calendar feeds, failed email, stuck queue, payment provider outage, invalid or missed webhook, late payment, full disk, compromised credentials, staff offboarding) has a documented procedure. | Record per scenario: documented or missing. |
- **Acceptance criteria:** Product owner accepts the documented procedures or logs each missing document as a launch blocker or accepted risk.
- **Evidence to capture:** Checklist with found/missing per document and scenario.
- **Notes / assumptions / open questions:** This case measures documentation, not code. "Staff offboarding" can be tested in the app by deactivating a user (UAT-PLAT-005 step 6).

---

## UAT-OPS-020: Environment variable inventory and repository hygiene
- **Feature / journey:** Configuration and secrets hygiene
- **Priority:** P0
- **Role / permissions:** Release engineer; security reviewer
- **References:** `.env.example` (names only); `config/services.php`, `config/platform.php`, `config/mail.php`, `config/logging.php`, `config/filesystems.php`; `.gitignore`; tracked files `afrikappart_db.txt`, `test-failure-full.txt`; local files `.env`, `.phpunit.result.cache`, `afrik-appart-mysql-seed-export.sql`; `.bluehost-credentials.template.json`
- **Preconditions / setup:** Clone of the repository; `git ls-files` available. Never print or record values.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | `git ls-files .env .bluehost-credentials.json afrik-appart-mysql-seed-export.sql afrik-appart-mysql-export.sql`. | No output (none tracked). |
  | 2 | `git ls-files afrikappart_db.txt test-failure-full.txt .phpunit.result.cache`. | Record which are tracked. `afrikappart_db.txt` is tracked and contains a single short token-like string (16 characters). Without printing it, the reviewer decides whether it is a credential. If it is, rotate it, remove it from history and treat it as exposed (P0). |
  | 3 | Review `.bluehost-credentials.template.json`. | Contains placeholders only (host, username, password, port, useTls, allowInvalidCertificate, appPath, publicPath); no real value. |
  | 4 | Compare the variable names read by the code with `.env.example`. Names read in `config/*.php` and `app/` include: `PAYMENT_PROVIDER`, `PAYMENT_HOLD_MINUTES`, `PAYMENT_WEBHOOK_SECRET`, `FEDAPAY_*`, `PAYPAL_*`, `CINETPAY_*`, `MPESA_*`, `RECAPTCHA_*`, `GROQ_API_KEY`, `PROPERTY_COPY_*`, `HELP_CHAT_MODEL`, `ANALYTICS_ENABLED`, `GA4_MEASUREMENT_ID`, `GTM_CONTAINER_ID`, `SPRINTPAY_FEEDBACK_EMAIL`, `PLATFORM_MODE`, `APP_BRAND_NAME`, `CHECKOUT_COMPLETION_GRACE_HOURS`, `PUBLIC_UPLOAD_PATH`, `SEED_ADMIN_PASSWORD`, `SEED_GUEST_PASSWORD`, `LOG_ERROR_DAILY_DAYS`, `MAIL_*`. | List any name used by code and missing in `.env.example` (for example `CHECKOUT_COMPLETION_GRACE_HOURS`, `HELP_CHAT_MODEL`, `LOG_DAILY_DAYS`) and any name in `.env.example` that no code reads (for example `MOBILE_MONEY_PROVIDER`, `APP_PAYMENT_RETURN_URL`, `APP_PAYMENT_CANCEL_URL`, `APP_PAYMENT_WEBHOOK_URL`). Record each. |
  | 5 | Check `.env.example` for hazardous defaults: `APP_ENV=local`, `APP_DEBUG=true`, `PAYPAL_BASE_URL` set to the live PayPal host while `PAYPAL_ENVIRONMENT=sandbox`, `PAYMENT_PROVIDER=pay_later`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=database`. | Record each as a note for the production checklist. |
  | 6 | On staging, run a minimal "variable present" check without printing values (e.g. `php artisan tinker` to print `filled(config('services.payments.webhook_secret'))`). | Booleans only: `PAYMENT_WEBHOOK_SECRET` set: yes/no; `MAIL_*` set: yes/no; `SPRINTPAY_FEEDBACK_EMAIL` set: yes/no; provider credentials for each enabled provider: yes/no. |
  | 7 | Disable one required variable at a time on staging (`PAYMENT_WEBHOOK_SECRET`, `SPRINTPAY_FEEDBACK_EMAIL`, `GROQ_API_KEY`, SMTP host) and open the related feature. | Webhooks return 403 (UAT-INT-001); feedback shows the unavailable text (UAT-PLAT-017); help chat shows unavailable (UAT-INT-044); email commands mark rows failed (UAT-INT-037). No raw error pages. |
  | 8 | Check which logs or pages print configuration (`php artisan about` output in the post-deploy script, `/admin/logs`). | No secrets printed. |
- **Acceptance criteria:** No secret is tracked or shipped; the variable inventory is complete and documented; missing configuration degrades gracefully.
- **Evidence to capture:** `git ls-files` output; variable present/absent table (booleans only).
- **Notes / assumptions / open questions:** Other local files (`.env`, `.phpunit.result.cache`, SQL exports) are ignored by `.gitignore` but exist in the working folder; they must not be zipped or shared with the repository. `.gitignore` lists `.phpunit.result.cache` yet the file is present locally.
