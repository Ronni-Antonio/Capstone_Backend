<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Students extends Model
{
    protected $table = 'students';
    protected $primaryKey = 'student_id';
    protected $fillable = [
        'student_number','first_name','last_name','grade_level_id','section_id',
        'status','points_balance'
    ];
    protected $casts = ['points_balance' => 'integer'];

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level_id', 'grade_level_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    public function rfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class, 'student_id', 'student_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RecyclingTransaction::class, 'student_id', 'student_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemptions::class, 'student_id', 'student_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'student_id', 'student_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'student_id', 'student_id');
    }
}
