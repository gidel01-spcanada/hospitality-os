<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_admin',
        'is_active',
        'tenant_id',
        'is_platform_admin',
        'locale',
        'theme',
        'timezone',
        'email_booking_updates',
        'email_message_updates',
        'email_marketing',
        'email_newsletter',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'email_booking_updates' => 'boolean',
            'email_message_updates' => 'boolean',
            'email_marketing' => 'boolean',
            'email_newsletter' => 'boolean',
        ];
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function favoriteProperties(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_favorites')->withTimestamps();
    }

    public function establishments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Establishment::class, 'establishment_host')->withTimestamps();
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || (bool) $this->is_admin;
    }

    public function isConcierge(): bool
    {
        return $this->role === 'concierge';
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    /** Manages establishments but not site-wide settings, users, or the amenity catalog. */
    public function isEstablishmentManager(): bool
    {
        return $this->isAdmin() || $this->isHost();
    }

    /** Admins manage every establishment in their tenant; hosts only their assigned ones. */
    public function managesEstablishment(?int $establishmentId): bool
    {
        if (! $establishmentId) {
            return false;
        }

        return $this->isAdmin() || ($this->isHost() && $this->establishments()->whereKey($establishmentId)->exists());
    }

    public function canManageReservations(): bool
    {
        return $this->isAdmin() || $this->isConcierge() || $this->isHost();
    }

    /**
     * Customers (tenant_id null) stay visible to any admin -- they're shared platform users, not
     * tenant-owned. Staff (tenant_id set) from another tenant resolve to null, i.e. a 404.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $user = parent::resolveRouteBinding($value, $field);

        if ($user && $user->tenant_id && ($tenantId = app(\App\Support\CurrentTenant::class)->id()) && $user->tenant_id !== $tenantId) {
            return null;
        }

        return $user;
    }
}
