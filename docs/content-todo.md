# Content todo

This file records content and configuration items that were not supplied with the sample assets and must be replaced with owner-approved values before production publication.

## Required property metadata

- Establishment name and official legal name
- Property names for apartments 401, 402, 403, and 404
- Full street addresses and geographic location details
- Building descriptions and neighborhood context
- Maximum occupancy per apartment
- Number of bedrooms, bathrooms, and beds
- Amenities list per apartment
- Nightly rate for each apartment in XOF and EUR
- Taxes, cleaning fees, and any mandatory booking charges
- Cancellation policy and house rules
- Minimum and maximum stay rules
- Check-in and check-out policy details

## Required operational configuration

- Primary domain name and canonical production URL
- Bluehost cPanel username and application/public paths
- Admin notification email address
- Customer support email address
- SMTP provider, port, username, and security settings
- Payment gateway live credentials and merchant account status
- Fallback contact or staff escalation address
- iCalendar feed source URLs for external availability if used

## Draft placeholders to use until supplied

The following draft values should be used only in seed configuration and should be clearly flagged as placeholders:

- `property_name` = `Draft apartment 401` etc. until the owner supplies the final name
- `address` = `Draft address to be confirmed`
- `description` = `Draft description to be confirmed`
- `nightly_rate_xof` = `0` or a temporary placeholder until official pricing is approved
- `support_email` = `[TO BE PROVIDED]`
- `admin_email` = `[TO BE PROVIDED]`
- `domain` = `[TO BE PROVIDED]`

## Actions required before M01

- Confirm the exact property and content data from the owner
- Replace all draft placeholders with real property metadata
- Validate any claims matching the actual data source used for features such as amenities, cleaning fees, and house rules
- Keep this file updated after each milestone to track what is replaced or deferred
