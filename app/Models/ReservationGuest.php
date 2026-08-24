<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'country',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'guest_id');
    }
}
