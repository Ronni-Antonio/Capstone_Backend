<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class systemSettings extends Model
{
    protected $table='system_settings';
    protected $primaryKey='setting_id';
    protected $fillable=[
        'school_name','school_address','school_year','school_email',
        'notify_machine_full','notify_scanner_errors','notify_machine_offline',
        'notify_maintenance','notify_weekly_summary','notify_milestones','auto_backup'
    ];
    protected $casts=[
        'notify_machine_full'=>'boolean','notify_scanner_errors'=>'boolean',
        'notify_machine_offline'=>'boolean','notify_maintenance'=>'boolean',
        'notify_weekly_summary'=>'boolean','notify_milestones'=>'boolean','auto_backup'=>'boolean'
    ];
}
