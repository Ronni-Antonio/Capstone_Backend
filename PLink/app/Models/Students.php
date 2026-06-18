<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Students extends Model
{
    protected $table = 'students';
    protected $primaryKey = 'student_id';
    public $timestamps = true;
    protected $fillable = [
        'student_number',
        'first_name',
        'last_name',
        'grade_level',
        'section',
        'points_balance'
    ];
}
