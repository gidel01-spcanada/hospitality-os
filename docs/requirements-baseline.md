# Requirements baseline

## Product and business defaults

These values are used as the working baseline because they are expressed in the master prompt and are safe to use until owner-supplied values arrive:

- Site name: `Afrik Appart`
- Application code name: `afrikappart`
- Default locale: `fr`
- Secondary locale: `en`
- Default timezone: `Africa/Porto-Novo`
- Supported customer currencies: `XOF` and `EUR`
- Default display/canonical property pricing currency: `XOF`
- XOF/EUR reference conversion: `1 EUR = 655.957 XOF`
- Booking mode: instant confirmation when availability checks pass
- Payment requirement: online payment required to confirm a booking
- Payment methods: Mobile Money in `XOF`, PayPal in `EUR`
- Version 1 payment collection: 100% of booking total at checkout
- FedaPay sandbox configuration is the confirmed Mobile Money provider for this build phase
- Payment hold duration: 20 minutes, configurable
- External calendar freshness threshold before final booking: 15 minutes
- External calendar sync schedule: every 15 minutes

## Public-facing implementation direction

The first implementation should preserve the visual direction already present in the sample HTML pages while using database-driven templates and actual property content from the supplied media set and later owner metadata.

Minimum design expectations:

- Neutral cream backgrounds around `#f6f3ec`
- Deep emerald primary around `#0f5b4c`
- Warm gold accent around `#c89b3c`
- Premium listing cards, large image tiles, large rounded geometry, and pill badges
- Responsive search panel and booking panel for desktop and mobile
- Clean typography and strong contrast

## Production constraints that must be respected

- Laravel 12 with PHP 8.3 and MySQL 8.x
- Blade-based templates with a lightweight front-end stack
- No hard-coded production secrets committed in the repository
- Payment providers and SMTP credentials must live in env or hosting secrets only
- BlueHost deployment must isolate application code, `.env`, and private files outside the public web root
- Uploaded property media must be stored as files, not database BLOBs
- Photo sources must be optimized for web delivery and kept outside the web root during source processing

## Explicitly out of scope for M00

The following are not to be scaffolded yet:

- Laravel application repository generation
- Database schema and seeders
- Payment flow implementation
- Admin authentication and authorization
- Operational deployment scripts
- Media processing pipeline

The checkpoint only establishes a verified starting baseline and documents missing production data.
