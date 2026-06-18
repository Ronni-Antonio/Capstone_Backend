<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redemptions extends Model
{
    protected $table = 'redemptions';
    protected $primaryKey = 'redemption_id';
    public $timestamps = false; // The table doesn't have timestamps from migration

    protected $fillable = [
        'student_id',
        'reward_id',
        'points_spent',
        'redemption_date'
    ];

    // A redemption belongs to one student
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    // A redemption belongs to one reward
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Rewards::class, 'reward_id', 'reward_id');
    }
}
