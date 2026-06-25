<?php

namespace App\Http\Controllers;

use App\Models\Students;
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
}