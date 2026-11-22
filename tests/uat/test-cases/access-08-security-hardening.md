# Access 08: Security headers and production hardening visible to users

Source of truth: `app/Http/Middleware/SecurityHeaders.php` (appended globally in `bootstrap/app.php`), `app/Http/Middleware/EncryptCookies.php`, `config/session.php`, `routes/web.php` (`/health`, throttled routes), `resources/views/errors/{403,404,419,422,429,500}.blade.php`, `tests/Feature/ProductionSecurityTest.php`, `tests/Feature/HealthTest.php`.

Run these cases against a production-like environment (`APP_ENV=production`, `APP_DEBUG=false`, HTTPS). Cases that need `local` behaviour are labelled.

---

## UAT-SEC-001: Security response headers on every kind of response
- **Feature / journey:** Browser hardening headers
- **Priority:** P0
- **Role / permissions:** Guest; customer; admin
- **References:** `SecurityHeaders` sets `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, `X-XSS-Protection: 1; mode=block`, and `Strict-Transport-Security: max-age=31536000; includeSubDomains` only when `APP_ENV=production`; `ProductionSecurityTest::test_security_headers_are_present`
- **Preconditions / setup:** Browser dev tools or `curl -I` against the production-like host over HTTPS.
- **Test data:** URLs: `/`, `/login`, `/dashboard` (signed in), `/admin` (admin), `/properties`, `/missing-page` (404), `/api/v1/properties`, `/health`, `/sitemap.xml`, a redirect response (`POST /logout`).
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Request each URL and read response headers | Each response (HTML, JSON, XML, redirect, error) carries the five headers above. |
  | 2 | On production over HTTPS | `Strict-Transport-Security` present with `max-age=31536000; includeSubDomains`. |
  | 3 | On a non-production environment | `Strict-Transport-Security` absent. |
  | 4 | Try to embed `/login` in an iframe on a different origin (local HTML test page) | Browser blocks the frame (`SAMEORIGIN`). Same-origin framing is allowed. |
  | 5 | Check the response `Server`/`X-Powered-By` headers | Record (hosting dependent; not set by the app). |
- **Acceptance criteria:** Headers present on all responses; HSTS only in production.
- **Evidence to capture:** Header dumps per URL; iframe test screenshot.
- **Notes / assumptions / open questions:** No `Content-Security-Policy` header is set by the application; record as an observation, not a failure.

## UAT-SEC-002: Custom error pages, localized, with no technical details
- **Feature / journey:** 403, 404, 419, 422, 429, 500 pages
- **Priority:** P1
- **Role / permissions:** Guest; customer
- **References:** `resources/views/errors/*.blade.php`, `routes/web.php` (`Route::fallback(fn () => abort(404))`), `ProductionSecurityTest::test_404_error_page_renders_a_custom_response`, `test_404_error_page_uses_the_selected_locale`, `bootstrap/app.php` (errors logged to the `errors` channel)
- **Preconditions / setup:** `APP_DEBUG=false`.
- **Test data:** `/this-page-does-not-exist`, `/missing-page-for-locale-test`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | French UI: open `/this-page-does-not-exist` | HTTP 404, custom page containing "Page introuvable". |
  | 2 | English UI: open `/missing-page-for-locale-test` | HTTP 404, `<html lang="en">`, "Error 404" and "Page not found". |
  | 3 | As customer open `/admin` | HTTP 403 page showing "Reservation staff access is required." in the selected language, with navigation back. |
  | 4 | Trigger 419 (UAT-AUTH-011) | Custom 419 page. |
  | 5 | Trigger 429 (UAT-SEC-006) | Custom 429 page. |
  | 6 | Trigger 422 (UAT-ACCT-007) | Custom 422 page with the abort message. |
  | 7 | Inspect each page source | No stack trace, file paths, SQL or environment values. |
  | 8 | If a 500 can be produced safely by the lead (for example via a disabled dependency in staging) | Custom 500 page, no details. |
- **Acceptance criteria:** Every error status renders a branded, localized page without technical detail.
- **Evidence to capture:** Screenshot and page source snippet for each status.
- **Notes / assumptions / open questions:** Each static page is localized by the session/cookie locale.

## UAT-SEC-003: Cookie flags and session protection
- **Feature / journey:** Session cookie hardening
- **Priority:** P1
- **Role / permissions:** Guest; customer
- **References:** `config/session.php` (`http_only` default true, `same_site` default `lax`, `secure` from `SESSION_SECURE_COOKIE`, driver `database`, lifetime 120), `.env.example` (`SESSION_SECURE_COOKIE` not set), `EncryptCookies` (encryption disabled for `XSRF-TOKEN` and the session cookie only when `APP_ENV=local`)
- **Preconditions / setup:** Production-like HTTPS environment. Dev tools Application/Storage tab.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/login` and list cookies | Session cookie and `XSRF-TOKEN` exist. |
  | 2 | Inspect session cookie flags | `HttpOnly` set; `SameSite=Lax`; `Secure` flag set only if `SESSION_SECURE_COOKIE=true` (record actual). |
  | 3 | Try to read the session cookie from the console (`document.cookie`) | Session cookie not visible (HttpOnly); `XSRF-TOKEN` visible (needed by JS). |
  | 4 | Log in; compare session cookie value before and after login | Value changes (session regenerated). |
  | 5 | After logout, replay the old session cookie value | Treated as guest. |
  | 6 | Tamper the cookie value | Request treated as a new guest session; no 500. |
- **Acceptance criteria:** Session cookie is HttpOnly and regenerated on login; Secure flag state recorded.
- **Evidence to capture:** Cookie table screenshots.
- **Notes / assumptions / open questions:** OPEN QUESTION: `Secure` is not forced by code; confirm production sets `SESSION_SECURE_COOKIE=true` (not in `.env.example`).

## UAT-SEC-004: User-supplied text is escaped everywhere it is shown
- **Feature / journey:** Stored XSS check across customer-entered fields
- **Priority:** P0
- **Role / permissions:** Customer; staff
- **References:** Blade `{{ }}` escaping in `dashboard.blade.php`, `messages/thread.blade.php`, `reviews/submit.blade.php`, admin lists; fields: profile `name`, message `body`, review `review_text`, reservation guest fields
- **Preconditions / setup:** Customer account; a completed reservation with a signed review link.
- **Test data:** Payload `"><img src=x onerror=alert('uat')><script>alert('uat')</script>`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Set the payload as profile name (UAT-ACCT-011) | Name shown as literal text in the customer dashboard header, `/account`, admin `/admin/users`, thread sender labels, and emails in the outbox preview. No alert. |
  | 2 | Send the payload as a message body | Literal text in the customer thread and in the staff thread. |
  | 3 | Submit the payload as review text | Literal text in `/admin/reviews`, `/reviews`, property page. |
  | 4 | Register with the payload as name (fresh email) | Literal text after redirect to `/dashboard`. |
  | 5 | Put the payload in query strings such as `/dashboard?status=` and `/properties?destination=` | Not reflected unescaped. |
- **Acceptance criteria:** No script runs and no markup is injected anywhere.
- **Evidence to capture:** Screenshots; page source snippets.
- **Notes / assumptions / open questions:** Email templates (`resources/views/emails`) should be checked too if mail content can be viewed.

## UAT-SEC-005: Public health endpoint and information disclosure
- **Feature / journey:** `/health` and `/up`
- **Priority:** P2
- **Role / permissions:** Anonymous
- **References:** `routes/web.php` `/health` returns JSON `status`, `application`, `environment`, `php_version`, `timestamp_utc`; `bootstrap/app.php` health route `/up`; `tests/Feature/HealthTest.php`
- **Preconditions / setup:** Production-like host.
- **Test data:** n/a
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/health` without login | HTTP 200 JSON `{"status":"ok","application":"afrikappart","environment":"production","php_version":"...","timestamp_utc":"..."}`. |
  | 2 | GET `/up` | HTTP 200 (framework health). |
  | 3 | Review the fields exposed | Record that environment name and PHP version are public. |
- **Acceptance criteria:** Endpoint works; disclosure is recorded for the security owner.
- **Evidence to capture:** Response body.
- **Notes / assumptions / open questions:** OPEN QUESTION: is exposing PHP version publicly acceptable?

## UAT-SEC-006: User-visible rate limits (429)
- **Feature / journey:** Throttled public endpoints
- **Priority:** P2
- **Role / permissions:** Guest
- **References:** `POST /feedback` `throttle:5,1`; `POST /help/chat` `throttle:10,1`; `POST /cleaning-schedule/{token}/visits/{visit}/status` `throttle:30,1`; custom `resources/views/errors/429.blade.php`
- **Preconditions / setup:** Non-production host (to avoid blocking real users). For the feedback form, use `/feedback`; for help chat, use the chat widget or POST `/help/chat`.
- **Test data:** Feedback message `UAT feedback n` (synthetic); chat question `Hello`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the feedback form 5 times within a minute | Each is accepted. |
  | 2 | Submit a 6th time | HTTP 429 custom page ("Too Many Requests" style, record text and `Retry-After` header). |
  | 3 | Wait one minute and submit again | Accepted. |
  | 4 | Send 11 chat requests within a minute | The 11th returns 429 (JSON or page, record). |
  | 5 | Compare with login and password reset | No throttle on those routes (UAT-AUTH-015). |
- **Acceptance criteria:** Limits apply at the documented counts and recover after the window.
- **Evidence to capture:** Status codes with timestamps.
- **Notes / assumptions / open questions:** Throttling is per client IP/route; behind a proxy the shared IP may affect results. Feedback and chat features belong to other suites.

## UAT-SEC-007: Sensitive files and directories are not served
- **Feature / journey:** Web root exposure
- **Priority:** P0
- **Role / permissions:** Anonymous
- **References:** Repo root contains `.env`, `.git`, `.bluehost-credentials.template.json`, `afrikappart_db.txt`, `afrik-appart-mysql-seed-export.sql`, `.phpunit.result.cache`, `composer.json`; `public/` is the intended document root; deployment on Bluehost (`docs/adr/0001-laravel-bluehost.md`, `.github/workflows/deploy.yml`)
- **Preconditions / setup:** Deployed production-like site. Tester only requests URLs; never reads or stores returned secrets.
- **Test data:** Paths below appended to the site base URL.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | GET `/.env`, `/public/.env`, `/.git/config`, `/.git/HEAD` | 403 or 404; no file content. |
  | 2 | GET `/storage/logs/laravel.log`, `/storage/logs/errors.log`, `/storage/framework/sessions/` | 403 or 404. |
  | 3 | GET `/composer.json`, `/composer.lock`, `/artisan`, `/vendor/autoload.php`, `/phpunit.xml` | 403 or 404 (or no execution of PHP). |
  | 4 | GET `/afrikappart_db.txt`, `/afrik-appart-mysql-seed-export.sql`, `/.bluehost-credentials.template.json` | 403 or 404. |
  | 5 | GET `/uploads/reservations/1/` directory listing | No directory listing. |
  | 6 | GET `/index.php/../.env` and `//.env` variants | 403 or 404. |
- **Acceptance criteria:** None of the paths returns file content.
- **Evidence to capture:** Status code per path (no bodies).
- **Notes / assumptions / open questions:** The web root must be `public/`; if the host serves the repository root, files above may be exposed. Payment-proof files are stored under `public/uploads/reservations/{id}` and are served through `ReceiptController::downloadProof` with authorization; check that a direct static URL to a known proof file is also not readable (record result; a direct path is guessable only if the file name is known).
