<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidCard extends Model
{
    protected $table = 'rfid_cards';
    protected $primaryKey = 'rfid_card_id';
    protected $fillable = ['student_id','card_uid','status','assigned_at'];
    protected $casts = ['assigned_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function recyclingTransactions(): HasMany
    {
        return $this->hasMany(RecyclingTransaction::class, 'rfid_card_id', 'rfid_card_id');
    }
}
