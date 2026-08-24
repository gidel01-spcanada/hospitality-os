<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstablishmentTranslation extends Model
{
    protected $fillable = ['locale', 'name', 'description', 'features'];

    protected $casts = ['features' => 'array'];

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}