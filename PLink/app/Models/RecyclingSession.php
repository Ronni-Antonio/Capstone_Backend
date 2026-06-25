<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecyclingSession extends Model
{
    protected $table = 'recycling_sessions';
    protected $primaryKey = 'recycling_session_id';
    public $timestamps = true;
    protected $fillable = [
        'student_id',
        'machine_id',
        'transaction_id',
        'started_at',
        'ended_at',
        'status',
        'items_processed',
        'total_weight_kg',
        'total_points_earned',
        'items_details',
        'notes'
    ];
    protected $casts = [
        'items_details' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
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
