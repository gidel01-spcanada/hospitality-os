# Public UAT 05: Locale and currency, legal pages, cookie consent and analytics, accessibility and responsive, error pages, SEO and health

All cases are **Not Run**. Language files: resources/lang/fr/messages.php and resources/lang/en/messages.php (flat dotted keys, e.g. `errors.404_title`). Locale middleware: app/Http/Middleware/SetLocale.php (`locale` alias). Locale switch: `GET /language/{locale}` (`AuthController@switchLanguage`).

**Locale resolution order in `SetLocale` (code):** logged-in user's `locale` > `?lang=fr|en` query > `locale` cookie > session `locale` > brand setting `default_locale` > `config('app.locale')`.

**Shared fixtures:** customer `customer.uat1@example.test` with profile locale `en`; customer `customer.uat2@example.test` with locale `fr`; published property P-A; brand `default_locale` recorded (baseline says `fr`).

---

## UAT-I18N-001: First visit uses the default locale
- **Feature / journey:** Default language
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `SetLocale` middleware, `BrandSettings::defaultLocale()`, docs/requirements-baseline.md (default locale `fr`, secondary `en`), layouts/app.blade.php (`<html lang>`)
- **Preconditions / setup:** Fresh browser profile with cookies and local storage cleared; browser Accept-Language set to `en-US`.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/`. | Page is shown in the brand default locale (French per baseline), regardless of the browser language (the middleware does not read Accept-Language). `<html lang="fr">`. |
  | 2 | Check navigation, search form, footer, error and flash texts. | All in French; no English key fragments. |
  | 3 | Open `/properties`, `/properties/<slug>`, `/faq`, `/privacy`. | Same locale. |
- **Acceptance criteria:** Default locale equals the brand setting; consistent across pages.
- **Evidence to capture:** Screenshots, `<html lang>` value.
- **Notes / assumptions / open questions:** Browser language is not used for auto-detection (no code found); confirm that this is intended.

---

## UAT-I18N-002: Guest switches language with the header switch and it persists
- **Feature / journey:** FR/EN switching
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** `GET /language/{locale}` (route `language.switch`), `AuthController@switchLanguage` (sets session `locale`, cookie `locale` for 525 600 minutes, `redirect()->back()`), layouts/app.blade.php (`.locale-switch` shows the alternate locale code, guests only)
- **Preconditions / setup:** Fresh browser, default `fr`.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | On `/properties/<slug>` click the header link labelled `EN`. | Redirect back to the same page, now in English; `<html lang="en">`; the link now reads `FR`. |
  | 2 | Navigate to checkout, `/faq`, `/privacy`. | English everywhere. |
  | 3 | Close and reopen the browser tab (not the profile) and open `/`. | English remains (cookie `locale`). |
  | 4 | Delete the `locale` cookie only and reload. | Falls back to session value, then to the default locale after the session is cleared. |
  | 5 | Inspect the `Set-Cookie` header on `/language/en`. | Cookie `locale=en` with long expiry. The app's `EncryptCookies` middleware subclass determines whether the cookie is encrypted/excepted; record. |
  | 6 | Observe the switch control on a 390 px screen. | Control is reachable inside the mobile menu and has a visible label (`EN`/`FR`). |
  | 7 | Open `/language/en` directly with no referrer. | `redirect()->back()` goes to a previous or home URL; no error. |
- **Acceptance criteria:** The chosen language persists across pages and sessions; no error without referrer.
- **Evidence to capture:** Screenshots, cookie details.
- **Notes / assumptions / open questions:** The switch is a GET that changes state (can be triggered by an image tag); low risk, informational.

---

## UAT-I18N-003: Unsupported locale falls back to the application locale
- **Feature / journey:** Locale validation
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `switchLanguage` (`in_array($locale, ['fr','en'])`, else `config('app.locale')`), `SetLocale` (`?lang=` accepted only for fr/en)
- **Preconditions / setup:** `config('app.locale')` value recorded.
- **Test data:** `/language/de`, `/language/EN`, `/language/%3Cscript%3E`, `/?lang=es`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/language/de` from a page. | Redirected back; page in the application default locale (`config('app.locale')`); cookie set to that value. |
  | 2 | Open `/language/EN` (uppercase). | Treated as unsupported (case-sensitive check): falls back to the default. |
  | 3 | Open `/language/%3Cscript%3E`. | No script execution; falls back to default. |
  | 4 | Open `/?lang=es`. | Ignored; locale unchanged. |
- **Acceptance criteria:** Only `fr` and `en` are applied; no error pages or injected content.
- **Evidence to capture:** Screenshots, cookie value.
- **Notes / assumptions / open questions:** Note the `config('app.locale')` fallback may differ from the brand `default_locale`.

---

## UAT-I18N-004: `?lang=` links from emails set the language for the flow
- **Feature / journey:** Locale in links
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** `SetLocale` (`$linkLocale` and `cookie('locale', ..., 525600)`), reservation emails with `locale` stored on `reservations.locale`
- **Preconditions / setup:** Fresh browser with default `fr`.
- **Test data:** `/reservations/<id>/checkout?token=<token>&lang=en`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open the link with `lang=en`. | Checkout page in English. |
  | 2 | Click an internal link without `lang` (e.g. "Back to home"). | Still English (cookie). |
  | 3 | Open a link with `lang=fr` afterwards. | Switches to French and rewrites the cookie. |
  | 4 | Log in as `customer.uat2@example.test` (profile `fr`) and open a link with `lang=en`. | Profile locale (fr) wins over `?lang=`. |
- **Acceptance criteria:** The `lang` parameter works for guests only and persists.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** Confirm that emails actually contain `lang` (email area).

---

## UAT-I18N-005: Logged-in customer language follows the profile preference
- **Feature / journey:** Locale precedence for authenticated users
- **Priority:** P1
- **Role / permissions:** Customer
- **References:** `SetLocale` (user locale first), `switchLanguage` (for authenticated users the target locale is ignored and the profile locale is reapplied), `POST /dashboard/preferences` (`locale` in fr,en), layouts (switch hidden for authenticated users)
- **Preconditions / setup:** `customer.uat1@example.test` with locale `en`.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Log in and open `/`. | English. No header language switch is displayed (only guests see it). |
  | 2 | Open `/language/fr` directly. | Redirect back; page remains English (profile locale reapplied). |
  | 3 | Open `/account/preferences`, change language to French, save. | Success message; pages now French. |
  | 4 | Log out. | Public pages follow the cookie/session or default locale. |
  | 5 | Submit an invalid `locale` (`de`) to `POST /dashboard/preferences`. | Validation error (must be fr or en). |
- **Acceptance criteria:** Profile locale is authoritative when logged in.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** There is no way for a logged-in user to switch language from the header; only the account preferences page (UX observation).

---

## UAT-I18N-006: Currency display (XOF primary, secondary currency approximation)
- **Feature / journey:** Currency presentation
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** docs/requirements-baseline.md (supported currencies XOF and EUR, canonical XOF), `Property.currency`, `properties.nightly` (`:price :currency / night`), `Establishment::secondary_currency|secondary_currency_rate`, home/properties/show/checkout views that reference `secondary_currency`, `legal.terms_art3_body` ("quoted in XOF and displayed with an equivalent in Euros")
- **Preconditions / setup:** E1 with `secondary_currency` = EUR and a rate; E-EUR (EUR property) ; P-A XOF 40 000.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/properties` and the detail page for P-A. | Price shown as `40 000 XOF / night` (FR and EN use the same number format `40 000`). Record whether a secondary-currency equivalent is displayed on listing/detail (views reference `secondary_currency`). |
  | 2 | Open `/properties` with a EUR property (E-EUR). | Price shown with `EUR`; listing price filter labels use the current currency (`Minimum price (EUR)`), based on the selected establishment or first property. |
  | 3 | Mix XOF and EUR properties in one listing and use the price filters. | Filters apply to the numeric `nightly_rate_xof` column regardless of currency. Record the effect (EUR properties would be filtered against their stored XOF-denominated field). |
  | 4 | Look for a visitor-controlled currency switch (header, footer, listing). | None found in layouts/app.blade.php; record absence. |
  | 5 | Open checkout of an XOF reservation of E1. | "≈ x EUR" line (see PAY-017). |
- **Acceptance criteria:** Currencies are displayed consistently with property/establishment settings; no mixing in a single amount.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** Open question: baseline mentions currency switching/preferences; the only code evidence is establishment-level `secondary_currency`. The terms text says every rate is displayed with a EUR equivalent, but the equivalent is conditional on admin configuration.

---

## UAT-I18N-007: Hard-coded French validation messages on the reservation form in English mode
- **Feature / journey:** Localization of validation messages
- **Priority:** P1
- **Role / permissions:** Anonymous guest, English locale
- **References:** `PublicPropertyController@reserve` (custom messages `check_in.after_or_equal` and `check_out.after` written in French), resources/lang/en/validation.php
- **Preconditions / setup:** English locale selected (I18N-002).
- **Test data:** POST with `check_in` = yesterday, then `check_out` earlier than `check_in`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Submit the reservation form with a past check-in (bypass picker). | The error under the date shows the French sentence "La date d'arrivée doit être aujourd'hui ou plus tard." (hard-coded). |
  | 2 | Submit `check_out` before `check_in`. | "La date de départ doit être après la date d'arrivée." |
  | 3 | Other validation errors (e.g. invalid email) in English. | Use English Laravel messages from resources/lang/en/validation.php. |
  | 4 | Switch to French and repeat. | Same French text (correct language). Check accents render properly (no mojibake `Ã©`). |
- **Acceptance criteria:** Record whether the French text appears in English mode (expected per code: yes, a localization defect to log).
- **Evidence to capture:** Screenshots in both languages.
- **Notes / assumptions / open questions:** The listing/search routes use default Laravel messages for the same rules, so wording differs between search and booking.

---

## UAT-I18N-008: Hard-coded English system messages shown to French users
- **Feature / journey:** Localization completeness on checkout
- **Priority:** P2
- **Role / permissions:** Guest/customer, French locale
- **References:** `CheckoutController` (hard-coded English: 'Payment is awaiting confirmation.', 'Payment is still awaiting verification.', 'Checkout access denied.', 'This payment method is not available for this establishment.', 'PayPal is not available for this establishment.', 'Unsupported provider'/'Invalid webhook signature.'), `ReceiptController`
- **Preconditions / setup:** French locale; reservation with a pending provider attempt; environment where the simulate button exists (local/testing) or staff user.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/reservations/<id>/checkout/fedapay/return?token=..` while payment is pending. | Flash error in English "Payment is awaiting confirmation." (hard-coded). Record. |
  | 2 | POST checkout with a wrong token. | 403 page; the abort message "Checkout access denied." may not be displayed (403 view shows `errors.403_message`); record which. |
  | 3 | POST `provider=paypal` while disabled. | 422 page. Record the visible message language. |
- **Acceptance criteria:** Findings logged; no key leakage such as `messages.checkout...` is visible.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** Low severity localization gaps.

---

## UAT-LEGAL-001: Privacy policy page
- **Feature / journey:** Legal page
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `GET /privacy` (route `privacy`), resources/views/legal/privacy.blade.php, lang keys `legal.privacy*`
- **Preconditions / setup:** None.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Click "Privacy policy" in the footer. | `/privacy` opens, HTTP 200, title "Privacy policy" (FR localized). |
  | 2 | Read the content. | Six articles: data collection, purpose, retention, sharing and security, security measures, user rights, with lists for items; no lorem ipsum or placeholder. |
  | 3 | Check the user-rights article. | Directs to the contact page or email to exercise rights; verify the contact page (`/contact`) works. |
  | 4 | Compare FR and EN versions. | Same number of articles; no missing translation. |
  | 5 | View on 390 px. | No horizontal scrolling; headings readable. |
- **Acceptance criteria:** Page complete in both languages and reachable from every public page footer.
- **Evidence to capture:** Screenshots, full-page captures FR/EN.
- **Notes / assumptions / open questions:** Legal review of content is outside UAT; the text states data is shared with payment partners; mentions no analytics/tracking processing (see LEGAL-004).

---

## UAT-LEGAL-002: Terms and conditions page and consistency with checkout
- **Feature / journey:** Terms
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `GET /terms`, resources/views/legal/terms.blade.php, lang `legal.terms_art1..10`
- **Preconditions / setup:** None.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/terms`. | Ten articles are shown, FR/EN. |
  | 2 | Check Article 3 (pricing and payment) against the checkout. | Lists "pay on arrival, FedaPay, PayPal, CinetPay, M-Pesa"; checkout also supports Interac, Wise, Revolut. Record the gap. |
  | 3 | Check Article 5 (cancellation) against PAY-010/011. | Matches the behavior (fee inside window; guests can cancel unfinalized bookings). |
  | 4 | Check Article 2 against the flow. | "A booking is firm upon receipt of email confirmation or validation of the required payment." Compare with the `pending` -> `confirmed` flow. |
  | 5 | Look for a terms acceptance checkbox on the booking form. | None found in properties/show.blade.php; record absence. |
- **Acceptance criteria:** Page complete; list inconsistencies for the product owner.
- **Evidence to capture:** Screenshots, notes.
- **Notes / assumptions / open questions:** Articles state rates are in XOF with EUR equivalent; see I18N-006. No explicit acceptance step means the "full acceptance by booking" clause has no UI evidence.

---

## UAT-LEGAL-003: Cookie policy page
- **Feature / journey:** Cookie policy
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `GET /cookies`, resources/views/legal/cookies.blade.php, lang `legal.cookies_art1..4`
- **Preconditions / setup:** Browser dev tools open (Application tab).
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/cookies`. | Four articles: what is a cookie, essential cookies (session, preference language/currency, security CSRF), no third-party ad tracking, managing settings. |
  | 2 | Browse home, listing, detail and switch language; list cookies in dev tools. | Cookies are limited to those described: Laravel session, `XSRF-TOKEN`, `locale`; note names and lifetimes. |
  | 3 | Check local storage. | No analytics keys until consent (see LEGAL-005). |
  | 4 | Check outgoing requests. | Third-party requests limited to maps (Google Maps iframe on property/establishment pages), images, fonts, and the reCAPTCHA script when enabled. Record all third-party hosts. |
- **Acceptance criteria:** Cookies set match the policy; third parties are listed.
- **Evidence to capture:** Cookie list screenshot, network host list.
- **Notes / assumptions / open questions:** The Google Maps embed and reCAPTCHA (when enabled) may set third-party cookies that the policy does not mention.

---

## UAT-LEGAL-004: Cookie policy wording vs analytics tracking
- **Feature / journey:** Consistency of consent disclosures
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** lang `legal.cookies_art2_body` ("exclusively cookies strictly necessary"), `legal.cookies_art3_body` ("no third-party advertising cookies, behavioral profiling"), `analytics.consent_message`, README.md (GA4/GTM optional, tracking after consent), resources/js/app.js
- **Preconditions / setup:** Environment A: `ANALYTICS_ENABLED=false` (default). Environment B: `ANALYTICS_ENABLED=true` with a test GA4 id or GTM container.
- **Test data:** Synthetic measurement id `G-UAT0000000`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | In environment B read `/cookies`. | The policy says only essential cookies are used. |
  | 2 | Accept analytics in the banner and observe. | Analytics script loads and may set analytics cookies. |
  | 3 | Compare 1 and 2. | Log as a legal/consistency finding if the policy does not mention optional analytics cookies. |
  | 4 | In environment A confirm no banner and no analytics requests. | None. |
- **Acceptance criteria:** Document whether the policy covers the analytics behavior.
- **Evidence to capture:** Screenshots of policy and cookies.
- **Notes / assumptions / open questions:** Needs product/legal decision; recorded as an open question.

---

## UAT-LEGAL-005: Analytics consent banner accept and decline
- **Feature / journey:** Cookie consent
- **Priority:** P0
- **Role / permissions:** Anonymous guest
- **References:** resources/views/partials/analytics-consent.blade.php (`role="dialog"`, `aria-live="polite"`), resources/js/app.js (localStorage key `afrik_analytics_consent` = `accepted` | `declined`), README.md (analytics section), `messages.analytics.*`, config `services.analytics.enabled|ga4_measurement_id|gtm_container_id`
- **Preconditions / setup:** Environment B with analytics enabled; fresh profile; dev tools network and storage open.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/`. | Banner "Analytics" with "We use measurement tools to improve the public journey. Tracking is disabled until you consent." (FR "Mesure d'audience"), buttons "Accept" and "Decline". No requests to `googletagmanager.com`/`google-analytics.com` yet. |
  | 2 | Click "Decline". | Banner hides; localStorage `afrik_analytics_consent=declined`; still no analytics requests on subsequent pages. |
  | 3 | Reload and navigate. | Banner does not return. |
  | 4 | Clear localStorage; open `/`; click "Accept". | Key set to `accepted`; analytics script loads (GTM preferred when both ids exist); page views are tracked. |
  | 5 | Reload. | No banner; analytics loads automatically. |
  | 6 | Open `/reservations/<id>/checkout?...`, `/dashboard`, `/admin`. | `window.__ANALYTICS_CONFIG__` is `null` on private pages (checkout, account, dashboard, messages, admin) and no analytics requests are made even after consent. |
  | 7 | Search with personal data in the URL (e.g. `?destination=...`). | No personal fields sent (README: excludes personal fields); inspect the hit payload. |
  | 8 | Is there a way to change the decision later? | No control found other than clearing browser storage; record (no "manage cookies" link). |
- **Acceptance criteria:** No tracking before consent; decline respected; private pages never tracked.
- **Evidence to capture:** Network capture before/after, localStorage screenshot.
- **Notes / assumptions / open questions:** Consent is stored in localStorage only (not a cookie, not server side). With JS disabled the banner remains `hidden`. The banner has `role="dialog"` but is not modal; check keyboard focus order.

---

## UAT-LEGAL-006: Analytics disabled by default shows no banner and loads nothing
- **Feature / journey:** Default privacy posture
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** partials/analytics-consent.blade.php (only rendered if `services.analytics.enabled` and an id is configured), `config/services.php` (`ANALYTICS_ENABLED` default false), README.md
- **Preconditions / setup:** Environment A (default), also environment with `ANALYTICS_ENABLED=true` but no ids.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/` in environment A. | No consent banner; `window.__ANALYTICS_CONFIG__` is `null`. |
  | 2 | Enabled but no ids. | No banner. |
  | 3 | Check network. | No analytics hosts contacted. |
- **Acceptance criteria:** No tracking and no banner when disabled.
- **Evidence to capture:** Screenshot, network log.
- **Notes / assumptions / open questions:** None.

---

## UAT-A11Y-001: Keyboard navigation and focus on global layout
- **Feature / journey:** Keyboard accessibility
- **Priority:** P1
- **Role / permissions:** Anonymous guest, keyboard only
- **References:** resources/views/layouts/app.blade.php (`nav aria-label="Main navigation"`, `.mobile-menu-toggle` with `aria-expanded` and `aria-controls="mobile-nav-actions"`), partials/confirm-dialog, partials/help-chat (`details`/`summary`), analytics consent dialog
- **Preconditions / setup:** Desktop browser, mouse unplugged/not used; screen reader optional.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Press Tab from the page start on `/`. | First stop is a visible focused element; check if a "skip to content" link exists (none found in layout; record). |
  | 2 | Tab through header, search, cards, footer. | Logical order, always visible focus indicator, no keyboard traps. |
  | 3 | At 390 px width Tab to the menu button and press Enter/Space. | Menu opens; `aria-expanded` toggles to `true`; links inside reachable; Esc behavior recorded. |
  | 4 | Open the help chat launcher (`summary`) with Enter, type, Tab to Send, close with the close button. | Works by keyboard; focus returns sensibly. |
  | 5 | Open the cancel confirmation on checkout with keyboard. | Dialog is focusable; Escape/Cancel closes; Confirm submits. |
  | 6 | With the consent banner visible, Tab to Accept/Decline. | Reachable and operable. |
- **Acceptance criteria:** All interactive controls are operable by keyboard with visible focus.
- **Evidence to capture:** Screen recording or notes with screenshots of focus states.
- **Notes / assumptions / open questions:** `aria-label="Main navigation"` and `"Language switcher"`/`"Menu"` are hard-coded English (not translated), inconsistent with the translated mobile nav label.

---

## UAT-A11Y-002: Form labels, errors and live regions on search, booking, feedback and help forms
- **Feature / journey:** Form accessibility
- **Priority:** P1
- **Role / permissions:** Anonymous guest, screen reader or accessibility inspector
- **References:** properties/show.blade.php (labels with `for`, `data-availability-result role="status" aria-live="polite"`), properties/index.blade.php (labels), home.blade.php (search inputs with `aria-label`), feedback/create.blade.php (`feedback-name`, `feedback-email`...), partials/help-chat.blade.php (`label.sr-only`, `data-help-chat-status role="status"`)
- **Preconditions / setup:** Axe/WAVE/Lighthouse available.
- **Test data:** Submit invalid data to produce errors.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Run an automated check on `/`, `/properties`, `/properties/<slug>`, `/feedback`, checkout page. | Record issues; no critical violations such as missing form labels, duplicate ids, or missing alt text. |
  | 2 | Check each field has an associated label (booking: `full_name`, `email`, `adults`, `country`, `children`, `infants`, `phone`, `customer_note`). | Each has a `<label for>` or `aria-label`. |
  | 3 | Trigger a server validation error on the booking form. | Error text is visible near the field or summary; record whether errors are announced (role alert/aria-describedby). |
  | 4 | Click "Check availability" with a screen reader. | The result text is announced (`role="status"`). |
  | 5 | Date range picker: operate with keyboard. | Dates can be chosen without a mouse; hidden inputs `check_in`/`check_out` update; record limitations. |
  | 6 | Inspect colour contrast of buttons and badges (emerald/gold theme). | Meets WCAG AA 4.5:1 for text; record failures. |
  | 7 | Zoom the page to 200 % and test text resizing. | No clipped or overlapping content. |
- **Acceptance criteria:** Target: no critical or serious automated violations; manual findings recorded. (No WCAG target is stated in the repository docs; AA is used as the reference.)
- **Evidence to capture:** Axe/Lighthouse reports, screenshots.
- **Notes / assumptions / open questions:** Open question: is WCAG 2.1 AA a formal requirement? The documents reviewed do not say.

---

## UAT-A11Y-003: Responsive layout of the public journey at phone, tablet and desktop widths
- **Feature / journey:** Responsive design
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** docs/requirements-baseline.md ("Responsive search panel and booking panel for desktop and mobile"), resources/views/layouts/app.blade.php (`mobile-menu-toggle`), checkout/show.blade.php (`.checkout-mobile-summary`), `resources/css/app.css`
- **Preconditions / setup:** Browsers or emulation at 360x640, 390x844, 768x1024, 1280x800, 1920x1080; also landscape phone.
- **Test data:** P-A with several photos.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Home at each width. | Header collapses to a menu button on small screens; search panel stacks; no horizontal scrolling. |
  | 2 | Listing with filters open/closed. | Filter panel is collapsible (`details`/`summary` with active filter count); cards reflow; compare toolbar usable. |
  | 3 | Property detail. | Gallery works with touch/swipe or buttons; booking panel is reachable and not covered by sticky elements; date picker fits the screen. |
  | 4 | Checkout. | The mobile summary bar appears with the total and "Continue to payment" jumping to `#checkout-payment`; PayPal buttons and the proof upload field fit. |
  | 5 | Legal pages, FAQ, contact, feedback. | Text wraps, tables/lists not clipped. |
  | 6 | Open the help chat on 360 px. | Panel fits within the viewport; the textarea and Send button are usable with the on-screen keyboard. |
  | 7 | Tap target sizes. | Buttons and links at least about 44x44 px where feasible; record shortfalls. |
- **Acceptance criteria:** All journeys usable at every listed width without horizontal scroll or hidden controls.
- **Evidence to capture:** Screenshots per width and page.
- **Notes / assumptions / open questions:** Real-device test (iOS Safari, Android Chrome) recommended for the date picker and PayPal popups.

---

## UAT-A11Y-004: Images, gallery and map accessibility
- **Feature / journey:** Non-text content
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** properties/show.blade.php (gallery buttons "View photo :number", "Previous/Next photo"), establishments/show.blade.php (`iframe title="Location"`, `aria-label` on property links), home/properties cards
- **Preconditions / setup:** P-A with photos; E1 with coordinates.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Inspect the gallery images. | Each `img` has meaningful `alt` text (property name or caption); decorative ones `alt=""`. |
  | 2 | Use thumbnails with the keyboard. | Buttons labelled "View photo N". |
  | 3 | Inspect the map iframe. | Has a `title` ("Location"); external map link opens in a new tab with `rel="noopener noreferrer"`. |
  | 4 | Disable images. | Page remains understandable. |
  | 5 | Missing image case (property without photos). | A placeholder or no gallery; no broken image icons. |
- **Acceptance criteria:** Informational images have text alternatives; controls labelled.
- **Evidence to capture:** Inspector screenshots.
- **Notes / assumptions / open questions:** The default SEO image is an external Unsplash URL (`asset('https://images.unsplash.com/...')`) used when a page gives no image; a third-party dependency worth noting.

---

## UAT-PUB-060: 404 page for unknown URLs and hidden resources (FR and EN)
- **Feature / journey:** Error pages
- **Priority:** P1
- **Role / permissions:** Anonymous guest
- **References:** `Route::fallback(fn () => abort(404))->middleware('locale')`, resources/views/errors/404.blade.php, lang `errors.404_title|404_message|back_home|code`
- **Preconditions / setup:** None.
- **Test data:** `/no/such/page`, `/properties/unknown-slug`, `/establishments/unknown`, `/reservations/999999/checkout`.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | Open `/no/such/page` in English. | Page with brand link, "Error 404", heading "Page not found", text "The requested page does not exist or has been moved.", button "Back to home". |
  | 2 | Open the same in French. | "Page introuvable" and French text. |
  | 3 | Open the other three URLs. | Same 404 page. |
  | 4 | Click "Back to home". | `/` opens. |
  | 5 | Check the page `<title>` and `<html lang>`. | Title "Page not found | <platform name>"; lang follows locale. |
  | 6 | Check stack traces/exception details. | None exposed (`APP_DEBUG=false` in UAT). |
  | 7 | Check that the 404 page is not styled as broken (CSS loaded via Vite). | Layout intact. |
- **Acceptance criteria:** Friendly localized 404 with no technical details.
- **Evidence to capture:** Screenshots EN/FR.
- **Notes / assumptions / open questions:** Error views are standalone (not the app layout), so the footer and help chat are absent. 404 for a paths outside the `locale` middleware group may use the default locale.

---

## UAT-PUB-061: 403, 419, 422 and 429 error pages
- **Feature / journey:** Error pages
- **Priority:** P2
- **Role / permissions:** Anonymous guest
- **References:** resources/views/errors/403|419|422|429|500.blade.php, lang `errors.403_*`, `419_*`, `422_*`, `429_*`, `500_*`
- **Preconditions / setup:** UAT environment with `APP_DEBUG=false`.
- **Test data:** See steps.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | 403: POST checkout start with wrong token (PAY-003). | "Access denied" / "You do not have permission to access this page." |
  | 2 | 419: open the property page, delete the `XSRF`/session cookie, then submit the booking form. | "Session expired" / "Your session has expired. Refresh the page and try again." with "Back to home". |
  | 3 | 422: open `/properties/compare?properties[]=<P-A>&properties[]=<P-E>` (different currencies). | "Invalid data" / "The form contains incorrect or incomplete information." |
  | 4 | 429: send 6 feedback POSTs within a minute (FEED-005) or 11 help-chat POSTs within a minute (HELP-006). | "Too many requests" / "You sent too many requests. Please wait before trying again." (for XHR the JSON response is Laravel's default). |
  | 5 | 500: not triggerable without code change; if a staging fault exists, verify "Something went wrong"/"The service is temporarily unavailable. Please try again shortly." | N/A unless a safe fault is available. |
  | 6 | Check each page in FR. | Localized strings present. |
- **Acceptance criteria:** Each code shows its localized page with a way back.
- **Evidence to capture:** Screenshots.
- **Notes / assumptions / open questions:** Authentication-related 401 is not defined as a custom view.

---

## UAT-PUB-062: SEO metadata, noindex on private pages, and health endpoints
- **Feature / journey:** SEO and monitoring endpoints
- **Priority:** P2
- **Role / permissions:** Anonymous
- **References:** layouts/app.blade.php (`robots` meta: `noindex,nofollow` for `admin.*`, `account.*`, `dashboard`, `dashboard.reservations.*`, `messages.*`, `checkout.*`; canonical; og/twitter), `GET /health` JSON, `/up` (Laravel health), tests/Feature/HealthTest.php, ProductionSecurityTest.php
- **Preconditions / setup:** None.
- **Test data:** None.
- **Steps and expected results:**
  | # | Action | Expected result |
  |---|--------|-----------------|
  | 1 | View source of `/`, `/properties`, `/properties/<slug>`. | `robots` = `index,follow`; canonical equals the current URL (without query string, `url()->current()`); og/twitter tags present. |
  | 2 | View source of a checkout page and `/login` (if applicable), `/dashboard`. | Checkout and dashboard: `noindex,nofollow`, and no og/twitter tags. Record `/login` value (not in the private list). |
  | 3 | GET `/health`. | JSON `status: ok`, `application: afrikappart`, environment, PHP version and UTC timestamp. Record that the endpoint is public and discloses the PHP version and environment (security observation). |
  | 4 | GET `/up`. | HTTP 200 (Laravel built-in health). |
  | 5 | Response headers on any page. | `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security` present (over HTTPS). |
- **Acceptance criteria:** Private pages are noindex; health endpoints respond; headers present.
- **Evidence to capture:** HTML source excerpts, response headers.
- **Notes / assumptions / open questions:** `/health` discloses runtime info publicly; decide if acceptable for production. No `robots.txt` was checked in this area.
