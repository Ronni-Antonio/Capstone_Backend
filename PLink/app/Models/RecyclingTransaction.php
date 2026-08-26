<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecyclingTransaction extends Model
{
    protected $table = 'recycling_transactions';
    protected $primaryKey = 'transaction_id';
    protected $fillable = [
        'student_id',
        'rfid_card_id',
        'smart_bin_id',
        'transaction_code',
        'status',
        'total_items',
        'total_points',
        'started_at',
        'completed_at'
    ];
    protected $casts = [
        'total_items' => 'integer',
        'total_points' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function rfidCard(): BelongsTo
    {
        return $this->belongsTo(RfidCard::class, 'rfid_card_id', 'rfid_card_id');
    }

    public function smartBin(): BelongsTo
    {
        return $this->belongsTo(SmartBin::class, 'smart_bin_id', 'smart_bin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecyclingItem::class, 'transaction_id', 'transaction_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'recycling_transaction_id', 'transaction_id');
    }
}
