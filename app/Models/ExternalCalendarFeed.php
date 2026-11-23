<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalCalendarFeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'provider',
        'url',
        'is_enabled',
        'last_sync_at',
        'last_successful_sync_at',
        'sync_interval_minutes',
        'last_sync_error',
        'status',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_successful_sync_at' => 'datetime',
    ];

    protected $hidden = ['url', 'encrypted_url'];

    public function getUrlAttribute(?string $value): ?string
    {
        $encryptedUrl = $this->attributes['encrypted_url'] ?? null;

        return filled($encryptedUrl) ? Crypt::decryptString($encryptedUrl) : $value;
    }

    public function setUrlAttribute(?string $value): void
    {
        $this->attributes['url'] = '';
        $this->attributes['encrypted_url'] = filled($value) ? Crypt::encryptString($value) : null;
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExternalCalendarEvent::class, 'feed_id');
    }

    public function isFresh(): bool
    {
        if (! $this->last_successful_sync_at) {
            return false;
        }

        return $this->last_successful_sync_at->diffInHours(now()) <= max(12, (int) $this->sync_interval_minutes / 60);
    }
}
