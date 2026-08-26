<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartBin extends Model
{
    protected $table = 'smart_bins';
    protected $primaryKey = 'smart_bin_id';
    protected $fillable = [
        'name',
        'location',
        'status',
        'current_fill_percentage',
        'current_distance_cm',
        'full_threshold_cm',
        'empty_threshold_cm',
        'last_maintenance_at',
        'last_active_at'
    ];
    protected $casts = [
        'current_fill_percentage' => 'integer',
        'current_distance_cm' => 'integer',
        'full_threshold_cm' => 'integer',
        'empty_threshold_cm' => 'integer',
        'last_maintenance_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function compartments(): HasMany
    {
        return $this->hasMany(SmartBinCompartment::class, 'smart_bin_id', 'smart_bin_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RecyclingTransaction::class, 'smart_bin_id', 'smart_bin_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SmartBinLog::class, 'smart_bin_id', 'smart_bin_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'smart_bin_id', 'smart_bin_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'smart_bin_id', 'smart_bin_id');
    }
}
