<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartBinCompartmentLog extends Model
{
    protected $table = 'smart_bin_compartment_logs';
    protected $primaryKey = 'compartment_log_id';

    protected $fillable = [
        'compartment_id',
        'distance_cm',
        'fill_percentage',
        'status',
    ];

    protected $casts = [
        'distance_cm' => 'integer',
        'fill_percentage' => 'integer',
    ];

    public function compartment(): BelongsTo
    {
        return $this->belongsTo(SmartBinCompartment::class, 'compartment_id', 'compartment_id');
    }
}
