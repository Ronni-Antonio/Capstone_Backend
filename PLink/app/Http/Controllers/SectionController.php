<?php

namespace App\Http\Controllers;

use App\Models\Students; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{
    /**
     * GET api/sections
     * Grabs unique sections dynamically and counts how many students are in them
     */
    public function index()
    {
        $sections = Students::select('section as name', DB::raw('count(*) as students'))
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->groupBy('section')
            ->get();

        return response()->json($sections);
    }

    /**
     * POST api/sections
     * FIXES 500: Uses raw array insertion to prevent schema property mismatches
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        try {
            // We use direct DB query insertion to bypass Model properties safely
            DB::table('students')->insertOrIgnore([
                'student_number' => 'SEC-' . time(), 
                'first_name'     => 'Section',
                'last_name'      => 'Placeholder',
                'grade_level'    => 'N/A',
                'section'        => $request->name,
                'points_balance' => 0,
            ]);

            return response()->json([
                'name' => $request->name,
                'students' => 1
            ]);

        } catch (\Exception $e) {
            // Dynamic fallback: if your table has strict missing columns, we catch it gracefully 
            return response()->json([
                'error' => 'Database constraint mismatch', 
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT api/sections/{oldSectionName}
     */
    public function update(Request $request, $oldSectionName)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        Students::where('section', $oldSectionName)->update([
            'section' => $request->name,
        ]);

        return response()->json(['success' => true, 'message' => 'Section renamed successfully!']);
    }

    /**
     * DELETE api/sections/{sectionName}
     */
    public function destroy($sectionName)
    {
        Students::where('section', $sectionName)->update([
            'section' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Section removed!']);
    }
}