# Database foundation

## Overview

The database foundation for Afrik Appart is intentionally normalized and aligned with the requirements in the master prompt. Tables are designed for MySQL 8.x with utf8mb4, foreign keys, indexes, and a clean migration path.

## Core domain tables

- `establishments`: owning property groups and legal entity information.
- `properties`: each rentable unit with metadata, occupancy, pricing, and state.
- `amenities` and `property_amenities`: storefront amenity metadata and join records.
- `property_images`: files for each property, including cover image and ordering.
- `rate_rules`: nightly override pricing and minimum-stay logic.
- `reservation_guests`: guest snapshot information for declarations/privacy minimization.
- `reservations`: booking lifecycle record with check-in, check-out, pricing, and status.
- `payment_attempts`: payment provider references and normalized status history.
- `reservation_price_lines`: price breakdowns for the reservation total.
- `currency_configs`: configuration for conversion and supported currencies.
- `admin_availability_blocks`: staff-maintained dates unavailable for booking.
- `external_calendar_feeds` and `external_calendar_events`: iCalendar import and feed synchronization records.
- `cleaning_visits`: scheduled housekeeping/concierge visits linked to properties and optionally reservations.
- `cleaning_schedule_shares`: revocable, expiring, property-scoped public week/month schedule links.
- `settings`: app configuration values for locale, pricing, and internal toggles.
- `email_outbox`: transactional email queue and status monitoring.
- `audit_logs`: staff action tracking and operational review.

## Money and dates

- Monetary fields use `DECIMAL(12,2)` for amounts and `DECIMAL(12,6)` for exchange-rate configuration.
- Currency codes are stored as uppercase ISO codes such as `XOF` and `EUR`.
- Booking dates are stored as `DATE` values for date-range logic and calendar checks.

## Indexing strategy

The schema includes indexes for the fields required by the project brief, including property/date availability checks, reservation references, slug lookups, and calendar freshness checks.

## Schema installation

Two installation modes are provided:

1. MySQL clean install via `database/schema.sql` for phpMyAdmin or direct import.
2. Laravel migration-based install via the files in `database/migrations`.

For local validation, the project uses SQLite by default via Laravel, while the SQL schema remains MySQL-compatible and deployable to BlueHost.

## Seed data

- `database/seed.sql` contains reference data such as amenities, app settings, and currency parity.
- `database/sample-data.sql` contains the draft initial establishment and four apartment entries based on the supplied source set.

## Notes

- The initial property records are intentionally marked as draft content pending owner confirmation.
- The project stores only relative file paths for media, not database blobs.
- All key values and provider settings remain environment-driven and must not be committed to source control.
