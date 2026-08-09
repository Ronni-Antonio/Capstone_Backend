<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlasticType extends Model
{
    protected $table = 'plastic_types';
    protected $primaryKey = 'plastic_type_id';
    protected $fillable = ['code','name','points_value','is_accepted','is_active'];
    protected $casts = ['points_value'=>'integer','is_accepted'=>'boolean','is_active'=>'boolean'];

    public function classifications(): HasMany
    {
        return $this->hasMany(AiClassification::class, 'plastic_type_id', 'plastic_type_id');
    }
}
