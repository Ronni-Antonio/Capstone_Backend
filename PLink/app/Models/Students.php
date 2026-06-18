<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Students extends Model
{
    protected $table = 'students';
    protected $primaryKey = 'student_id';
    public $timestamps = true;
    protected $fillable = [
        'student_number',
        'first_name',
        'last_name',
        'grade_level',
        'section',
        'points_balance'
    ];

    // One student has many transactions
    public function transactions(): HasMany
    {
        return $this->hasMany(Transactions::class, 'student_id', 'student_id');
    }

    // One student has many redemptions
    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemptions::class, 'student_id', 'student_id');
    }

        public function transactions_points(): BelongsTo
    {
        return $this->belongsTo(Transactions::class, 'points_earned', 'points_balance');
    }
}
