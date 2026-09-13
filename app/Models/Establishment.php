<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establishment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'country_code',
        'city',
        'currency',
        'description',
        'cover_image',
        'address',
        'latitude',
        'longitude',
        'google_maps_url',
        'features',
        'payment_methods',
        'cancellation_fee_percent',
        'cancellation_fee_days',
        'vat_percent',
        'vat_included',
        'city_tax_type',
        'city_tax_amount',
        'service_fee_percent',
        'email',
        'phone',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'features' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'payment_methods' => 'array',
        'cancellation_fee_percent' => 'decimal:2',
        'cancellation_fee_days' => 'integer',
        'vat_percent' => 'decimal:2',
        'vat_included' => 'boolean',
        'city_tax_amount' => 'decimal:2',
        'service_fee_percent' => 'decimal:2',
    ];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(EstablishmentTranslation::class);
    }

    public function localized(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $translation = $this->translations->firstWhere('locale', $locale);

        return $translation?->{$field} ?: $this->{$field};
    }

    /** Non-refundable fee charged when a stay is cancelled inside the configured window. */
    public function cancellationFeeFor(Reservation $reservation): float
    {
        $percent = (float) $this->cancellation_fee_percent;

        if ($percent <= 0 || ! $reservation->check_in) {
            return 0.0;
        }

        if (now()->startOfDay()->diffInDays($reservation->check_in->startOfDay(), false) >= (int) $this->cancellation_fee_days) {
            return 0.0;
        }

        return round((float) $reservation->total_amount * $percent / 100, 2);
    }

    /** Guarantee amount required to be held in reserve when paying on site. */
    public function cancellationFeeHoldAmount(Reservation $reservation): float
    {
        $percent = (float) $this->cancellation_fee_percent;

        if ($percent <= 0 || ! $reservation->total_amount) {
            return 0.0;
        }

        return round((float) $reservation->total_amount * $percent / 100, 2);
    }
}
