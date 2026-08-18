<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReport extends Model
{
    protected $table = 'analytics_reports';
    protected $primaryKey = 'analytics_report_id';
    protected $fillable = [
        'generated_by_user_id','report_type','title','report_date_start','report_date_end',
        'total_items_collected','total_points_awarded',
        'total_rewards_redeemed','total_students_participated','summary','predictive_insights'
    ];
    protected $casts = [
        'report_date_start'=>'date','report_date_end'=>'date',
        'total_items_collected'=>'integer',
        'total_points_awarded'=>'integer','total_rewards_redeemed'=>'integer',
        'total_students_participated'=>'integer','summary'=>'array','predictive_insights'=>'array'
    ];

    public function generatedBy(): BelongsTo { return $this->belongsTo(User::class,'generated_by_user_id','id'); }
}
