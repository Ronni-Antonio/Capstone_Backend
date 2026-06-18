<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Students;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all students with their transactions and redemptions
        $students = Students::with(['transactions', 'redemptions'])->get();
        return response()->json($students);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_number' => 'required|numeric|unique:students,student_number|max_digits:12',
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'grade_level' => 'required|integer',
                'section' => 'required|string',
                'points_balance' => 'nullable|integer',
            ]);

            $validated['points_balance'] = $validated['points_balance'] ?? 0;

            $student = Students::create($validated);

            // Optionally, if you want to create default related records, do it here!
            // Example: Create an initial welcome transaction
            // $student->transactions()->create([
            //     'bottle_qty' => 0,
            //     'points_earned' => 10,
            //     'transaction_date' => now()
            // ]);

            return response()->json($student, 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $student = Students::with(['transactions', 'redemptions'])->findOrFail($id);
        return response()->json($student);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
