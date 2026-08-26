<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteReview extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'site_reviews';

    protected $fillable = [
        'source',
        'reviewer_name',
        'rating',
        'review_text',
        'source_url',
        'reviewed_at',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'reviewed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
