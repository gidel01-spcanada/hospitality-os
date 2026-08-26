<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'guest_id',
        'user_id',
        'reservation_ref',
        'checkout_token',
        'status',
        'check_in',
        'check_out',
        'adults',
        'children',
        'infants',
        'currency',
        'email',
        'subtotal',
        'fees',
        'taxes',
        'total_amount',
        'source',
        'notes',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'subtotal' => 'decimal:2',
        'fees' => 'decimal:2',
        'taxes' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            $reservation->checkout_token ??= bin2hex(random_bytes(32));
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * No tenant_id of its own; reached through property -> establishment. Only enforced when
     * a tenant context exists (staff/admin), so guest checkout and a customer's own dashboard
     * lookup -- both tenant-less -- are unaffected.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $reservation = parent::resolveRouteBinding($value, $field);

        if ($reservation && ($tenantId = app(\App\Support\CurrentTenant::class)->id()) && $reservation->property->establishment->tenant_id !== $tenantId) {
            return null;
        }

        return $reservation;
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(ReservationGuest::class, 'guest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function priceLines(): HasMany
    {
        return $this->hasMany(ReservationPriceLine::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function emailOutbox(): HasMany
    {
        return $this->hasMany(EmailOutbox::class, 'recipient_email', 'email');
    }
}
