<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id', 'reservation_id', 'created_by', 'assignee_name',
        'scheduled_at', 'duration_minutes', 'status', 'started_at',
        'completed_at', 'instructions',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function isOverdue(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at->isPast();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}