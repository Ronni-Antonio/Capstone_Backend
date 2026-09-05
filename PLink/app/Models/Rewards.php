<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rewards extends Model
{
    protected $table='rewards';
    protected $primaryKey='reward_id';
    protected $fillable=['reward_name','points_cost','unit_price','stock_quantity','is_active','last_restock'];
    protected $casts=[
        'points_cost'=>'integer',
        'unit_price'=>'decimal:2',
        'stock_quantity'=>'integer',
        'is_active'=>'boolean',
        'last_restock'=>'datetime',
    ];
    protected $appends=['points_value','stocks','total_price'];

    public function redemptions(): HasMany { return $this->hasMany(Redemptions::class,'reward_id','reward_id'); }

    public function getPointsValueAttribute()
    {
        return $this->points_cost;
    }

    public function getStocksAttribute()
    {
        return $this->stock_quantity;
    }

    public function getTotalPriceAttribute()
    {
        return (string) number_format(
            (float) $this->stock_quantity * (float) ($this->unit_price ?? 0),
            2,
            '.',
            ''
        );
    }
}
