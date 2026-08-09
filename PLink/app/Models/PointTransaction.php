<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    protected $table = 'point_transactions';
    protected $primaryKey = 'point_transaction_id';
    protected $fillable = [
        'student_id','recycling_transaction_id','redemption_id','points',
        'transaction_type','description'
    ];
    protected $casts = ['points'=>'integer'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function recyclingTransaction(): BelongsTo
    {
        return $this->belongsTo(RecyclingTransaction::class, 'recycling_transaction_id', 'transaction_id');
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemptions::class, 'redemption_id', 'redemption_id');
    }
}
