<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AmenityCategory extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'slug',
        'name_en',
        'name_fr',
        'sort_order',
    ];

    public function amenities(): HasMany
    {
        return $this->hasMany(Amenity::class, 'category_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'fr' ? $this->name_fr : $this->name_en;
    }
}
