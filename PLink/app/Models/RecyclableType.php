<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecyclableType extends Model
{
    protected $table = 'recyclable_types';
    protected $primaryKey = 'recyclable_type_id';
    protected $fillable = ['code', 'name', 'material_category', 'points_value', 'is_accepted', 'is_active'];
    protected $casts = ['points_value' => 'integer', 'is_accepted' => 'boolean', 'is_active' => 'boolean'];

    public function classifications(): HasMany
    {
        return $this->hasMany(AiClassification::class, 'recyclable_type_id', 'recyclable_type_id');
    }
}
