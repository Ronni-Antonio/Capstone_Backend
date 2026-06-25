<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transactions extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'transaction_id';
    public $timestamps = true;

    protected $fillable = [
        'student_id',
        'machine_id',
        'bottle_qty',
        'valid_qty',
        'contaminated_qty',
        'non_pet_qty',
        'rejected_qty',
        'points_earned',
        'transaction_date',
        'breakdown'
    ];
    protected $casts = [
        'breakdown' => 'array',
        'transaction_date' => 'datetime'
    ];

    // A transaction belongs to one student
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    // A transaction belongs to one machine
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id', 'machine_id');
    }

    // A transaction may have one recycling session
    public function recyclingSession(): HasOne
    {
        return $this->hasOne(RecyclingSession::class, 'transaction_id', 'transaction_id');
    }

    // A transaction has many classification histories
    public function classificationHistories(): HasMany
    {
        return $this->hasMany(ClassificationHistory::class, 'transaction_id', 'transaction_id');
    }
}
