<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailChangeOtp extends Model
{
    protected $fillable = [
        'user_id',
        'new_email',
        'otp',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
