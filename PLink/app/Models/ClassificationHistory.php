<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationHistory extends Model
{
    protected $table = 'classification_histories';
    protected $primaryKey = 'classification_id';
    public $timestamps = true;
    protected $fillable = [
        'plastic_type_id',
        'machine_id',
        'transaction_id',
        'confidence_score',
        'image_path',
        'ai_model_version',
        'notes',
        'is_verified',
        'status',
        'points_change'
    ];
    protected $casts = [
        'confidence_score' => 'decimal:2',
        'is_verified' => 'boolean'
    ];

    public function plasticType(): BelongsTo
    {
        return $this->belongsTo(PlasticType::class, 'plastic_type_id', 'plastic_type_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id', 'machine_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transactions::class, 'transaction_id', 'transaction_id');
    }
}
