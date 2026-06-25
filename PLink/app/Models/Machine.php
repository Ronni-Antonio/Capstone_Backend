<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    protected $table = 'machines';
    protected $primaryKey = 'machine_id';
    public $timestamps = true;
    protected $fillable = [
        'name',
        'location',
        'status',
        'current_weight_kg',
        'max_capacity_kg',
        'last_maintenance_at',
        'last_active_at'
    ];

    public function machineLogs(): HasMany
    {
        return $this->hasMany(MachineLog::class, 'machine_id', 'machine_id');
    }

    public function classificationHistories(): HasMany
    {
        return $this->hasMany(ClassificationHistory::class, 'machine_id', 'machine_id');
    }

    public function recyclingSessions(): HasMany
    {
        return $this->hasMany(RecyclingSession::class, 'machine_id', 'machine_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transactions::class, 'machine_id', 'machine_id');
    }
}
