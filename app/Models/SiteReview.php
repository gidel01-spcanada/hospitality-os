<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteReview extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'site_reviews';

    protected $fillable = [
        'tenant_id',
        'establishment_id',
        'property_id',
        'reservation_id',
        'source',
        'reviewer_name',
        'rating',
        'review_text',
        'source_url',
        'reviewed_at',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'reviewed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            'booking' => 'Booking.com',
            'google' => 'Google',
            'airbnb' => 'Airbnb',
            default => ucfirst((string) $this->source),
        };
    }
}
