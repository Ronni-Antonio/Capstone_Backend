<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecyclingItem extends Model
{
    protected $table = 'recycling_items';
    protected $primaryKey = 'recycling_item_id';
    protected $fillable = ['transaction_id','item_number','image_path','weight_kg','status'];
    protected $casts = ['weight_kg' => 'decimal:3'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RecyclingTransaction::class, 'transaction_id', 'transaction_id');
    }

    public function classification(): HasOne
    {
        return $this->hasOne(AiClassification::class, 'recycling_item_id', 'recycling_item_id');
    }
}
