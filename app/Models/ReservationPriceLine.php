<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPriceLine extends Model
{
    use HasFactory;

    protected $table = 'reservation_price_lines';

    protected $fillable = [
        'reservation_id',
        'label',
        'amount',
        'currency',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
