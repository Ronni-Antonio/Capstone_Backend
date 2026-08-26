<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    protected $table = 'predictions';
    protected $primaryKey = 'prediction_id';
    protected $fillable = [
        'model_id','prediction_type','prediction_date','target_date',
        'predicted_value','actual_value','confidence','input_summary','output'
    ];
    protected $casts = [
        'prediction_date'=>'date','target_date'=>'date',
        'predicted_value'=>'decimal:4','actual_value'=>'decimal:4',
        'confidence'=>'decimal:4','input_summary'=>'array','output'=>'array'
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id', 'model_id');
    }
}
