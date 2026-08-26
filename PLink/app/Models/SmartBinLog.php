<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartBinLog extends Model
{
    protected $table = 'smart_bin_logs';
    protected $primaryKey = 'smart_bin_log_id';
    protected $fillable = ['smart_bin_id', 'distance_cm', 'fill_percentage', 'status'];

    public function smartBin(): BelongsTo
    {
        return $this->belongsTo(SmartBin::class, 'smart_bin_id', 'smart_bin_id');
    }
}
