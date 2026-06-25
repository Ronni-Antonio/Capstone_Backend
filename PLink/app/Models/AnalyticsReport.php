<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsReport extends Model
{
    protected $table = 'analytics_reports';
    protected $primaryKey = 'analytics_report_id';
    public $timestamps = true;
    protected $fillable = [
        'report_type',
        'title',
        'report_date_start',
        'report_date_end',
        'total_items_collected',
        'total_weight_kg',
        'total_points_awarded',
        'total_rewards_redeemed',
        'total_students_participated',
        'plastic_type_breakdown',
        'grade_level_breakdown',
        'predictive_insights',
        'generated_by'
    ];
    protected $casts = [
        'plastic_type_breakdown' => 'array',
        'grade_level_breakdown' => 'array',
        'predictive_insights' => 'array',
        'generated_by' => 'array',
        'report_date_start' => 'date',
        'report_date_end' => 'date'
    ];
}
