<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineLog extends Model
{
    protected $table = 'machine_logs';
    protected $primaryKey = 'machine_log_id';
    public $timestamps = true;
    protected $fillable = [
        'machine_id',
        'log_type',
        'message',
        'details'
    ];
    protected $casts = [
        'details' => 'array'
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id', 'machine_id');
    }
}
