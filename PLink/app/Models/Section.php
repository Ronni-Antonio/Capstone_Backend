<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Section extends Model
{
    protected $table = 'sections';
    protected $primaryKey = 'section_id';
    protected $fillable = ['grade_level_id', 'name'];

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level_id', 'grade_level_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'section_id', 'section_id');
    }
}
