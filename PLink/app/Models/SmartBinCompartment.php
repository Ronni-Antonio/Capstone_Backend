<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartBinCompartment extends Model
{
    protected $table = 'smart_bin_compartments';
    protected $primaryKey = 'compartment_id';

    protected $fillable = [
        'smart_bin_id',
        'name',
        'material_category',
        'status',
        'current_distance_cm',
        'current_fill_percentage',
        'full_threshold_cm',
        'empty_threshold_cm',
        'last_active_at',
    ];

    protected $casts = [
        'current_distance_cm' => 'integer',
        'current_fill_percentage' => 'integer',
        'full_threshold_cm' => 'integer',
        'empty_threshold_cm' => 'integer',
        'last_active_at' => 'datetime',
    ];

    public function smartBin(): BelongsTo
    {
        return $this->belongsTo(SmartBin::class, 'smart_bin_id', 'smart_bin_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SmartBinCompartmentLog::class, 'compartment_id', 'compartment_id');
    }
}
