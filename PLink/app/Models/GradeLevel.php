<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    protected $table = 'grade_levels';
    protected $primaryKey = 'grade_level_id';
    protected $fillable = ['name'];

    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'grade_level_id', 'grade_level_id');
    }
}
