<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotDeviceConfig extends Model
{
    protected $table = 'iot_device_configs';
    protected $primaryKey = 'device_config_id';

    protected $fillable = [
        'controller_code',
        'device_name',
        'wifi_ssid',
        'wifi_password',
        'config_version',
        'applied_version',
        'last_seen_at',
    ];

    protected $casts = [
        'wifi_password' => 'encrypted',
        'config_version' => 'integer',
        'applied_version' => 'integer',
        'last_seen_at' => 'datetime',
    ];
}
