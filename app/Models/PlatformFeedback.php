<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformFeedback extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'category', 'message'];
}