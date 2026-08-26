<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collection extends Model
{
    protected $table = 'collections';
    protected $primaryKey = 'collection_id';
    protected $fillable = ['smart_bin_id','collected_by_user_id','bottles_collected','weight_kg','collection_date','notes'];
    protected $casts = ['bottles_collected'=>'integer','weight_kg'=>'decimal:2','collection_date'=>'datetime'];

    public function smartBin(): BelongsTo { return $this->belongsTo(SmartBin::class,'smart_bin_id','smart_bin_id'); }
    public function collectedBy(): BelongsTo { return $this->belongsTo(User::class,'collected_by_user_id','id'); }
}
