<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;

class GradeLevelController extends Controller
{
    private const MANAGED_GRADES = ['Grade 4', 'Grade 5', 'Grade 6'];

    public function index()
    {
        $grades = GradeLevel::query()
            ->whereIn('name', self::MANAGED_GRADES)
            ->orderBy('name')
            ->get(['grade_level_id', 'name']);

        return response()->json($grades);
    }
}
