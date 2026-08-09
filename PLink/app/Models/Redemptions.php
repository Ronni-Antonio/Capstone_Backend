<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Redemptions extends Model
{
    protected $table = 'redemptions';
    protected $primaryKey = 'redemption_id';
    protected $fillable = ['student_id','reward_id','redemption_code','points_spent','redeemed_at'];
    protected $casts = ['points_spent'=>'integer','redeemed_at'=>'datetime'];

    public function student(): BelongsTo { return $this->belongsTo(Students::class,'student_id','student_id'); }
    public function reward(): BelongsTo { return $this->belongsTo(Rewards::class,'reward_id','reward_id'); }
    public function pointTransactions(): HasMany { return $this->hasMany(PointTransaction::class,'redemption_id','redemption_id'); }
}
