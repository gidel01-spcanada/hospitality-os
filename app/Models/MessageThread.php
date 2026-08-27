<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'establishment_id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}