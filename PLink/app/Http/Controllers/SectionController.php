<?php

namespace App\Http\Controllers;

use App\Models\Students;
use App\Models\Transactions;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{
    /**
     * GET api/sections
     * Grabs sections with student counts
     */
    public function index()
    {
        $sections = Section::withCount('students')->get();
        return response()->json($sections->map(function ($section) {
            return [
                'name' => $section->name,
                'students' => $section->students_count
            ];
        }));
    }

    /**
     * GET api/sections/list
     * Simple list for dropdown (just section names)
     */
    public function list()
    {
        $sections = Section::select('name')->orderBy('name')->pluck('name');
        return response()->json($sections);
    }

    /**
     * POST api/sections
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:sections,name',
        ]);

        $section = Section::create([
            'name' => $request->name
        ]);

        return response()->json([
            'name' => $section->name,
            'students' => 0
        ]);
    }

    /**
     * PUT api/sections/{oldSectionName}
     */
    public function update(Request $request, $oldSectionName)
    {
        $request->validate([
            'name' => 'required|string|unique:sections,name',
        ]);

        $section = Section::where('name', $oldSectionName)->first();
        if (!$section) {
            // If section not in sections table, check if it exists in students
            $exists = Students::where('section', $oldSectionName)->exists();
            if ($exists) {
                $section = Section::create(['name' => $oldSectionName]);
            } else {
                return response()->json(['error' => 'Section not found'], 404);
            }
        }

        // Update students first
        Students::where('section', $oldSectionName)->update([
            'section' => $request->name,
        ]);
        // Then update section
        $section->name = $request->name;
        $section->save();

        return response()->json(['success' => true, 'message' => 'Section renamed successfully!']);
    }

    /**
     * DELETE api/sections/{sectionName}
     */
    public function destroy($sectionName)
    {
        $section = Section::where('name', $sectionName)->first();
        if ($section) {
            Students::where('section', $sectionName)->update([
                'section' => null,
            ]);
            $section->delete();
        }
        return response()->json(['success' => true, 'message' => 'Section removed!']);
    }

    /**
     * GET api/sections/{sectionName}/ranking
     * Get student ranking for a specific section with total points and bottles
     */
    public function sectionRanking($sectionName)
    {
        $students = Students::where('section', $sectionName)
            ->get()
            ->map(function ($student) {
                // Calculate totals directly from transactions
                $totalPoints = $student->transactions()->sum('points_earned');
                $totalBottles = $student->transactions()->sum('bottle_qty');
                
                return [
                    'student_id' => $student->student_id,
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'grade_level' => $student->grade_level,
                    'section' => $student->section,
                    'points_balance' => $student->points_balance,
                    'total_points' => $totalPoints,
                    'total_bottles' => $totalBottles
                ];
            })
            ->sortByDesc('total_points') // Sort by total points descending
            ->values() // Reindex keys
            ->map(function ($student, $index) {
                $student['rank'] = $index + 1; // Add rank number
                return $student;
            });

        return response()->json([
            'section' => $sectionName,
            'ranking' => $students
        ]);
    }

    /**
     * GET api/sections/ranking/overall
     * Get overall student ranking across all sections
     */
    public function overallRanking()
    {
        $students = Students::get()
            ->map(function ($student) {
                $totalPoints = $student->transactions()->sum('points_earned');
                $totalBottles = $student->transactions()->sum('bottle_qty');
                
                return [
                    'student_id' => $student->student_id,
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'grade_level' => $student->grade_level,
                    'section' => $student->section,
                    'points_balance' => $student->points_balance,
                    'total_points' => $totalPoints,
                    'total_bottles' => $totalBottles
                ];
            })
            ->sortByDesc('total_points')
            ->values()
            ->map(function ($student, $index) {
                $student['rank'] = $index + 1;
                return $student;
            });

        return response()->json([
            'ranking_type' => 'overall',
            'ranking' => $students
        ]);
    }

    /**
     * GET api/sections/ranking
     * Get ranking of sections by total points and bottles recycled
     */
    public function sectionRankingOverall()
    {
        // Get all sections first
        $allSections = Section::pluck('name')->toArray();
        
        // Get all section stats in a single query
        $sectionStats = DB::table('transactions')
            ->join('students', 'transactions.student_id', '=', 'students.student_id')
            ->whereIn('students.section', $allSections)
            ->groupBy('students.section')
            ->selectRaw('
                students.section as section_name,
                COUNT(DISTINCT students.student_id) as student_count,
                SUM(transactions.points_earned) as total_points,
                SUM(transactions.bottle_qty) as total_bottles
            ')
            ->get()
            ->keyBy('section_name');

        // Create array with all sections, including those with no transactions
        $sections = collect($allSections)->map(function ($sectionName) use ($sectionStats) {
            $stats = $sectionStats->get($sectionName);
            return [
                'section_name' => $sectionName,
                'student_count' => $stats->student_count ?? 0,
                'total_points' => $stats->total_points ?? 0,
                'total_bottles' => $stats->total_bottles ?? 0
            ];
        });

        // Sort by points first, then bottles
        $sortedByPoints = $sections->sortByDesc('total_points')
            ->values()
            ->map(function ($section, $index) {
                $section['points_rank'] = $index + 1;
                return $section;
            });

        $sortedByBottles = $sections->sortByDesc('total_bottles')
            ->values()
            ->map(function ($section, $index) {
                $section['bottles_rank'] = $index + 1;
                return $section;
            });

        // Merge both rankings
        $rankedSections = $sortedByPoints->map(function ($section) use ($sortedByBottles) {
            $bottleRankItem = $sortedByBottles->firstWhere('section_name', $section['section_name']);
            $section['bottles_rank'] = $bottleRankItem['bottles_rank'];
            return $section;
        });

        return response()->json([
            'ranking_type' => 'sections',
            'ranking' => $rankedSections
        ]);
    }
}