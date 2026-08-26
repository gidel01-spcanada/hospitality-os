# Input inventory

## 1. Supplied design and source files

### Customer-facing prototype HTML

- `home_preview.html` — home-page mockup for a premium rental experience using the Afrik Appart brand language.
- `details_preview.html` — detailed property page with a premium booking panel and supporting copy blocks.

These files establish the intended visual direction:

- Inter/system sans-serif typography
- Cream / warm neutral background (`#f6f3ec`)
- Deep emerald primary (`#0f5b4c`)
- Warm gold accent (`#c89b3c`)
- Soft shadows, pill badges, large rounded cards, and photographic hero treatment

### Property photo sources

The supplied source media is organized by apartment numbering.

| Apartment folder | Count | Notes |
| --- | ---: | --- |
| `photo/appartement 401` | 14 | 401 property imagery source |
| `photo/appartement 402` | 12 | 402 property imagery source |
| `photo/appartement 403` | 19 | 403 property imagery source |
| `photo/appartement 404` | 13 | 404 property imagery source |
| Total | 58 | Combined supplied property photo count |

The photo mapping confirms the expected source totals and is valid for the first implementation seed set.

## 2. Safely derived facts

The following facts are supported by the supplied files and are suitable for use as draft defaults:

- Site name: `Afrik Appart`
- Brand direction: premium short-term rental in a West African city context
- Default language direction is French-first with English support
- Default display currency: `XOF`
- Secondary currency display: `EUR`
- Location context is consistent with Cotonou-like marketing language and property imagery, but the exact address list remains missing
- The first release is intended for four apartment units, each with a unique gallery and booking flow
- The public site should mimic the sample visual hierarchy without reproducing static prototype copy verbatim

## 3. Missing or unverified owner content

The following items are required before production-ready content is safe to publish:

- Exact establishment name(s)
- Exact property names and unit numbers
- Full property addresses
- Building/street/area descriptions
- Occupancy and room counts
- Exact nightly pricing
- Fees, taxes, and security deposit rules
- Cancellation and house rules
- Local contact information and support address
- Admin notification email address
- Domain and Bluehost deployment details
- SMTP credentials and mail configuration
- Payment credentials and test/production modes
- Real property metadata for amenities and booking policies

## 4. Prototype-only claims that must not be reused in production

These prototype patterns are unsupported and should be removed from the production implementation unless the owner later provides verification:

- “verified stays” metrics
- country count or global audience claims
- review scores and guest review quotes not sourced from a real database
- “ready for integration” messaging
- “receive the next version” or designer commentary
- replacement-photo notices or design explanation copy
- generic unsplash placeholder imagery in final property data

## 5. Current confirmation status

- The supplied HTML prototypes are design examples rather than production pages.
- The supplied apartment photo directories are valid and count as 58 total images.
- No metadata beyond the design direction and visual language has been verified as owner-authored.
- Missing content should be recorded as draft placeholders and flagged for replacement in `docs/content-todo.md`.
