<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlasticType extends Model
{
    protected $table = 'plastic_types';
    protected $primaryKey = 'plastic_type_id';
    public $timestamps = true;
    protected $fillable = [
        'name',
        'points_per_item',
        'multiplier',
        'is_active'
    ];

    // A plastic type has many classification histories
    public function classificationHistories(): HasMany
    {
        return $this->hasMany(ClassificationHistory::class, 'plastic_type_id', 'plastic_type_id');
    }
}
