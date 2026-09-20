<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningScheduleShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'created_by', 'token', 'period_start', 'period_end',
        'view_mode', 'property_ids', 'assignee_name', 'expires_at',
    ];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date',
        'property_ids' => 'array', 'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}