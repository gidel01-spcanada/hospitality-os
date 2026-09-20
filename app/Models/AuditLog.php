<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'action',
        'details',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo(null, 'model_type', 'model_id');
    }
}