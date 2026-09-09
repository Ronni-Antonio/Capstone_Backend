<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiClassification extends Model
{
    protected $table = 'ai_classifications';
    protected $primaryKey = 'classification_id';
    protected $fillable = [
        'recycling_item_id',
        'recyclable_type_id',
        'model_id',
        'confidence_score',
        'status',
        'notes',
        'is_verified',
        'classified_at'
    ];
    protected $casts = [
        'confidence_score' => 'decimal:2',
        'is_verified' => 'boolean',
        'classified_at' => 'datetime'
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RecyclingItem::class, 'recycling_item_id', 'recycling_item_id');
    }

    public function recyclableType(): BelongsTo
    {
        return $this->belongsTo(RecyclableType::class, 'recyclable_type_id', 'recyclable_type_id');
    }

    // Compatibility alias used by older transaction/report code.
    public function plasticType(): BelongsTo
    {
        return $this->recyclableType();
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id', 'model_id');
    }
}
