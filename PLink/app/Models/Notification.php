<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table='notifications';
    protected $primaryKey='notification_id';
    protected $fillable=['student_id','smart_bin_id','notification_type','title','message','data','is_read','read_at'];
    protected $casts=['data'=>'array','is_read'=>'boolean','read_at'=>'datetime'];
    public function student(): BelongsTo { return $this->belongsTo(Students::class,'student_id','student_id'); }
    public function smartBin(): BelongsTo { return $this->belongsTo(SmartBin::class,'smart_bin_id','smart_bin_id'); }
}
