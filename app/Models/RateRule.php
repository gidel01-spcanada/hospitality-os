<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'effective_from',
        'effective_to',
        'nightly_rate_xof',
        'nightly_rate_eur',
        'minimum_stay',
        'rule_type',
        'metadata',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'nightly_rate_xof' => 'decimal:2',
        'nightly_rate_eur' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
