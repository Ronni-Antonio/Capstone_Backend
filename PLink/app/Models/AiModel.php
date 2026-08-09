<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    protected $table = 'ai_models';
    protected $primaryKey = 'model_id';
    protected $fillable = ['name','version','framework','accuracy','model_path','is_active'];
    protected $casts = ['accuracy'=>'decimal:3','is_active'=>'boolean'];

    public function classifications(): HasMany
    {
        return $this->hasMany(AiClassification::class, 'model_id', 'model_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'model_id', 'model_id');
    }
}
