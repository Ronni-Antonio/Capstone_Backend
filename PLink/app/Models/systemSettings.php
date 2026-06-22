<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class systemSettings extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'setting_id'; // Tells Eloquent to search by setting_id instead of 'id'
    public $timestamps = true;

    protected $fillable = [
        'school_name',
        'school_address',
        'school_year',
        'school_email',
        'point_conversion',
        'penalty_rejected',
        'penalty_invalid',
        'penalty_non_pet',
        'penalty_custom',
        'notify_machine_full',
        'notify_scanner_errors',
        'notify_machine_offline',
        'notify_maintenance',
        'notify_weekly_summary',
        'notify_milestones',
        'auto_backup'
    ];
}