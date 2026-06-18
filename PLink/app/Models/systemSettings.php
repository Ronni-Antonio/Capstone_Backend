<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class systemSettings extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'setting_id';
    public $timestamps = true;

    protected $fillable = [
        'school_name',
        'school_address',
        'school_year',
        'school_email'
    ];
}
