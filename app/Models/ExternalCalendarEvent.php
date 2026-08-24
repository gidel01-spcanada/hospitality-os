<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalCalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_id',
        'uid',
        'summary',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(ExternalCalendarFeed::class, 'feed_id');
    }
}
