<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class Amenity extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
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

    public function scopeOrdered(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        if (Schema::hasColumn($table, 'sort_order')) {
            $query->orderBy($table.'.sort_order');
        }

        return $query->orderBy(Schema::hasColumn($table, 'name_fr') ? $table.'.name_fr' : $table.'.name');
    }

    public function getNameAttribute(): string
    {
        if (array_key_exists('name_fr', $this->attributes) || array_key_exists('name_en', $this->attributes)) {
            return app()->getLocale() === 'fr'
                ? (string) ($this->attributes['name_fr'] ?? $this->attributes['name_en'] ?? '')
                : (string) ($this->attributes['name_en'] ?? $this->attributes['name_fr'] ?? '');
        }

        return (string) ($this->attributes['name'] ?? '');
    }
}
