<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyTranslation extends Model
{
    protected $fillable = ['locale', 'name', 'summary', 'description'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}