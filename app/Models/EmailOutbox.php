<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailOutbox extends Model
{
    use HasFactory;

    protected $table = 'email_outbox';

    protected $fillable = [
        'template',
        'recipient_email',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
