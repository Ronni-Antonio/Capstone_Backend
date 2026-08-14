<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rewards extends Model
{
    protected $table='rewards';
    protected $primaryKey='reward_id';
    protected $fillable=['reward_name','points_cost','stock_quantity','unit_price','is_active'];
    protected $casts=['points_cost'=>'integer','stock_quantity'=>'integer','unit_price'=>'decimal:2','is_active'=>'boolean'];
    public function redemptions(): HasMany { return $this->hasMany(Redemptions::class,'reward_id','reward_id'); }
}
