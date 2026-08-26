<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Amenity extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'category_id',
        'name_en',
        'name_fr',
        'slug',
        'sort_order',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AmenityCategory::class, 'category_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'fr' ? $this->name_fr : $this->name_en;
    }
}
