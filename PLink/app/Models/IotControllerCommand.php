<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotControllerCommand extends Model
{
    protected $table = 'iot_controller_commands';
    protected $primaryKey = 'command_id';

    protected $fillable = [
        'controller_code',
        'command_type',
        'payload',
        'status',
        'result_message',
        'claimed_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
