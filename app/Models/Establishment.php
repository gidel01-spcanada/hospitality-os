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
}
