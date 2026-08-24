<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'file_path',
        'file_name',
        'room_tag',
        'mime_type',
        'width',
        'height',
        'sort_order',
        'is_cover',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_cover' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
