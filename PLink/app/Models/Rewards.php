<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rewards extends Model
{
    protected $table = 'rewards';
    protected $primaryKey = 'reward_id';
    public $timestamps = true;

    protected $fillable = [
        'reward_name',
        'points_cost',
        'stock_quantity'
    ];

    // One reward has many redemptions
    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemptions::class, 'reward_id', 'reward_id');
    }
}
