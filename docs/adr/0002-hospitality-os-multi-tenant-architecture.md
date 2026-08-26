# ADR 0002: Hospitality OS — multi-tenant cloud mode alongside the existing on-premise deployment

## Status

Proposed. Not yet implemented — this is the plan to review before any migrations or code land.

## Context

Today the application is single-tenant: one `establishments` table, one set of admin users, one amenity catalog, one `settings` row set, all owned implicitly by whoever installed it (Afrik Appart). The user wants the same codebase to also run as **"Hospitality OS"**: a cloud SaaS where multiple, unrelated property-management companies ("tenants") each manage their own establishments, properties, staff, amenities, reviews, and reservations in an isolated admin panel — while travelers on the public site see **one unified catalog of every tenant's properties**, with no visible indication of which company owns what.

Decisions already made (via clarifying questions):

| Question | Decision |
|---|---|
| What is a tenant? | One property-management company/org (can own many establishments/properties, has its own staff) |
| How is a tenant resolved? | Single domain; resolved from the logged-in admin user's `tenant_id` (no subdomains for now) |
| What gets segregated? | Everything admin-managed: establishments, properties, amenities catalog, reviews, reservations, users, settings |
| Public site branding | Fully unified — public pages only ever show "Hospitality OS", never which tenant owns a property |
| Tenant provisioning | Self-serve signup — anyone can register and immediately get their own tenant workspace |
| On-premise mode | Must still exist, unchanged in spirit: a single organization, no multi-tenant chrome, keeps its own branding (e.g. "Afrik Appart") |

## Decision

Add a `tenants` table and a `tenant_id` column to every admin-managed table, enforced by a global Eloquent scope so **existing admin controllers require no per-query changes** — the scope filters automatically once a tenant context is bound. Public-facing controllers explicitly opt out of that scope so they keep aggregating across all tenants. A single config flag (`PLATFORM_MODE=on_premise|cloud`) switches the multi-tenant chrome (signup, branding, tenant switch) on or off; on-premise installs get exactly one auto-created tenant and behave exactly as today.

### 1. Data model

New table:

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');                     // e.g. "Afrik Appart" or a host's company name
    $table->string('slug')->unique();
    $table->string('contact_email')->nullable();
    $table->string('status')->default('active'); // active | suspended
    $table->timestamps();
});
```

`tenant_id` (`foreignId('tenant_id')->constrained()->cascadeOnDelete()`, indexed) added to:

- `establishments` — the real ownership boundary; `properties` inherit their tenant through `establishment_id` rather than duplicating the column, to avoid two sources of truth that could drift.
- `amenity_categories`, `amenities` — per the decision, each tenant gets its **own** copy of the feature catalog (seeded from the current Booking.com-derived list when the tenant is created), not a shared library. This matches "everything admin-managed" and lets a tenant freely rename/delete categories without affecting anyone else.
- `site_reviews` — reviews are tied to whichever tenant curated them for their own establishments.
- `settings` — becomes tenant-scoped (site name, contact email, review source URLs, etc.). Public pages will **not** read a tenant's `settings.site_name`; they always render "Hospitality OS" in cloud mode. Settings remain relevant for tenant-internal use (e.g. what shows on a booking confirmation email footer).
- `users` — nullable `tenant_id`. Staff/admin/concierge accounts require one; pure `customer` accounts (travelers who book) stay `tenant_id = null` since a traveler can book properties across multiple tenants and isn't a member of any of them.

Tables that don't need their own `tenant_id` because they already chain to a table that has one (kept as-is, just reached through their existing FK): `properties` (via `establishments`), `property_images`, `property_features`, `property_amenities`, `rate_rules`, `admin_availability_blocks`, `external_calendar_feeds`, `external_calendar_events`, `reservations`/`reservation_guests` (via `properties`), `payment_attempts`, `reservation_price_lines`.

Tables that intentionally stay global (not tenant data at all): `currency_configs`, `email_outbox` (queue, not tenant-owned content), `audit_logs` (records `user_id`, which already implies a tenant), `cache`, `jobs`.

### 2. Scoping mechanism

A `BelongsToTenant` trait + global scope, applied to `Establishment`, `AmenityCategory`, `Amenity`, `SiteReview`, `Setting`:

```php
protected static function booted(): void
{
    static::addGlobalScope('tenant', function (Builder $query) {
        if ($tenantId = app(CurrentTenant::class)->id()) {
            $query->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
        }
    });

    static::creating(function ($model) {
        $model->tenant_id ??= app(CurrentTenant::class)->id();
    });
}
```

`CurrentTenant` is a small singleton bound per-request by `ResolveTenant` middleware: reads `auth()->user()->tenant_id` when authenticated as staff/admin, otherwise stays empty (no tenant context — the scope becomes a no-op, so guest/public queries naturally see every tenant's data with zero code changes in `PublicPropertyController`, `home.blade.php`, etc.).

Models reached only through a parent (e.g. `Property` via `Establishment`) don't need the trait themselves — `Establishment::with('properties')` is already tenant-filtered at the establishment level, and direct `Property::find()` lookups in admin routes are additionally guarded (see §3) since a global scope on `Establishment` doesn't stop someone from guessing a `Property` ID that belongs to a different tenant's establishment.

### 3. Authorization defense-in-depth

Route-model-bound admin resources (`AdminPropertyController@edit`, `AdminEstablishmentController@edit`, etc.) get an explicit ownership check in addition to the global scope, so a crafted URL with another tenant's numeric ID gets a 403/404, not just an empty result set:

```php
abort_unless($property->establishment->tenant_id === app(CurrentTenant::class)->id(), 404);
```

This is cheap insurance against the global scope being accidentally bypassed (e.g. `withoutGlobalScopes()` used somewhere for an unrelated reason).

### 4. Public site stays unified

`PublicPropertyController`, `home.blade.php`, sitemap generation, and search/filtering are **not changed** — they run with no authenticated tenant context, so the global scope is a no-op and they continue querying every published property regardless of tenant, exactly as today. No tenant identifier is exposed in public HTML, JSON, or the sitemap.

Payment webhooks (`CheckoutController@webhook`) resolve their reservation/property directly by ID from the provider payload, not through an authenticated tenant session, so they're unaffected by the scope either way.

### 5. Deployment modes, one codebase

`config/platform.php`:

```php
return [
    'mode' => env('PLATFORM_MODE', 'on_premise'), // on_premise | cloud
    'brand_name' => env('PLATFORM_MODE') === 'cloud' ? 'Hospitality OS' : env('APP_BRAND_NAME', 'Afrik Appart'),
];
```

- **On-premise**: `PLATFORM_MODE=on_premise`. A single `tenants` row is created by the installer/seeder and every admin user is assigned to it. Self-serve tenant signup and any "which workspace am I in" UI are hidden. Branding stays whatever the operator configures (unchanged from today).
- **Cloud**: `PLATFORM_MODE=cloud`. A "Start hosting on Hospitality OS" registration flow (distinct from the existing traveler registration) creates a `Tenant` + its first `admin` `User` in one transaction, seeds that tenant's own amenity catalog from the standard list, and logs them into their new, empty admin panel. Public pages hard-code "Hospitality OS" as the brand regardless of any tenant's `settings.site_name`.

### 6. Rollout plan (phased, independently reviewable/testable)

1. **Foundation** — `tenants` migration, `tenant_id` columns + backfill migration that creates one `"Default"` tenant and assigns every existing row to it (so current dev/production data keeps working unchanged), `BelongsToTenant` trait, `CurrentTenant` singleton, `ResolveTenant` middleware, `config/platform.php`. No behavior change yet: with one tenant and no signup flow, on-premise mode is 100% today's behavior.
2. **Admin scoping** — apply the trait to the five tenant-owned models, add the defense-in-depth ownership checks to the property/establishment/amenity admin controllers, update `DatabaseSeeder`/factories/tests to create/attach a tenant.
3. **Cloud signup** — the "Start hosting" registration flow, tenant + first-admin creation, per-tenant amenity seeding, `PLATFORM_MODE=cloud` branding.
4. **Verification** — feature tests proving: tenant A cannot see or edit tenant B's establishments/properties/amenities/reviews/settings via the admin UI; the public property list and a property detail page still return properties from multiple tenants with no tenant identifier in the response; existing on-premise (single-tenant) test suite still passes unchanged.
5. **Docs** — update `README.md`/`docs/architecture.md` with the two deployment modes, and note the current `docs/database.md`/SQL dumps will need regenerating once this lands (they predate this ADR).

Deliberately **not** in this first pass (flagged for a later decision, not required by the stated scope): a platform-owner ("super admin") screen to list/suspend tenants, billing/subscription limits, subdomain-per-tenant routing. Self-serve signup as chosen doesn't strictly require a platform-admin screen to function, but one will likely be wanted for support/abuse handling before a real public launch.

## Consequences

- Every admin query for establishments/properties/amenities/reviews/settings is automatically tenant-filtered by the global scope — controllers don't need to remember to add `where('tenant_id', ...)` themselves, which is the main way this kind of feature gets accidentally broken later.
- Public browsing genuinely can't leak a tenant identifier by omission, because it never has a tenant context to filter by in the first place — it isn't "tenant A's data with the filter turned off," it's simply outside the scope's reach.
- On-premise customers (the current Afrik Appart deployment) see no behavior change: one tenant, no signup UI, their own branding.
- This does add a join/lookup (`establishment.tenant_id`) to every property-related admin query and a middleware pass on every authenticated admin request — negligible at this app's scale, but worth knowing.
- Reservations and payments are reachable across tenants only through their owning property, never directly `tenant_id`-tagged; if a future requirement needs tenant-level payment/payout reporting, that will need its own column at that point.
