<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'establishment_id',
        'name',
        'property_type',
        'slug',
        'status',
        'is_published',
        'is_active',
        'currency',
        'nightly_rate_xof',
        'nightly_rate_eur',
        'max_guests',
        'bedrooms',
        'bathrooms',
        'beds',
        'area',
        'area_unit',
        'floor',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'summary',
        'description',
        'cover_image',
        'video_urls',
        'minimum_stay',
        'metadata',
        'calendar_export_token',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_published' => 'boolean',
        'is_active' => 'boolean',
        'nightly_rate_xof' => 'decimal:2',
        'nightly_rate_eur' => 'decimal:2',
        'area' => 'decimal:2',
        'floor' => 'integer',
        'video_urls' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Property $property): void {
            $property->calendar_export_token ??= bin2hex(random_bytes(24));
        });
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('is_active', true)
            ->whereHas('establishment', fn (Builder $establishmentQuery) => $establishmentQuery->where('is_active', true));
    }

    /**
     * Properties have no tenant_id of their own (they inherit it through their establishment),
     * so route-model binding is the enforcement point: a property owned by another tenant
     * resolves to null here, which Laravel treats exactly like a scoped-out model -- a 404.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $property = parent::resolveRouteBinding($value, $field);

        if ($property && ($tenantId = app(\App\Support\CurrentTenant::class)->id()) && $property->establishment->tenant_id !== $tenantId) {
            return null;
        }

        return $property;
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PropertyTranslation::class);
    }

    public function localized(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $translation = $this->translations->firstWhere('locale', $locale);

        return $translation?->{$field} ?: $this->{$field};
    }

    public function displaySummary(): string
    {
        $summary = trim((string) $this->localized('summary'));

        return $this->isPlaceholderContent($summary)
            ? __('messages.properties.summary_fallback', [
                'name' => $this->localized('name'),
                'city' => $this->city,
            ])
            : ($summary ?: (string) $this->localized('name'));
    }

    public function displayDescription(): string
    {
        $description = trim((string) $this->localized('description'));

        return $this->isPlaceholderContent($description)
            ? __('messages.properties.description_fallback', [
                'name' => $this->localized('name'),
                'city' => $this->city,
                'guests' => $this->max_guests,
            ])
            : ($description ?: $this->displaySummary());
    }

    private function isPlaceholderContent(string $content): bool
    {
        $normalized = strtolower($content);

        return $content === ''
            || str_starts_with($normalized, 'draft ')
            || str_contains($normalized, 'to be replaced')
            || str_contains($normalized, 'owner-supplied');
    }

    public function amenities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities');
    }

    public function favoritedByUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_favorites')->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function rateRules(): HasMany
    {
        return $this->hasMany(RateRule::class);
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AdminAvailabilityBlock::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function calendarFeeds(): HasMany
    {
        return $this->hasMany(ExternalCalendarFeed::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(PropertyFeature::class);
    }

    public function cleaningVisits(): HasMany
    {
        return $this->hasMany(CleaningVisit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SiteReview::class);
    }
}
