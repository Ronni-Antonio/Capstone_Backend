<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Students;
use Illuminate\Support\Facades\Validator;

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


            return response()->json($student, 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import students from CSV file
     */
    public function importCSV(Request $request)
    {
        // Validate that a file was uploaded
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return response()->json(['error' => 'Failed to open CSV file'], 500);
        }

        $header = fgetcsv($handle); // Get the first row as headers
        if (!$header) {
            fclose($handle);
            return response()->json(['error' => 'CSV file is empty'], 400);
        }

        $expectedHeaders = ['student_number', 'first_name', 'last_name', 'grade_level', 'section'];
        $normalizedHeader = array_map('strtolower', array_map('trim', $header));
        $missingColumns = array_diff($expectedHeaders, $normalizedHeader);

        if (!empty($missingColumns)) {
            fclose($handle);
            return response()->json([
                'error' => 'Missing required columns in CSV',
                'missing' => array_values($missingColumns)
            ], 400);
        }

        // Create a map for header positions
        $headerMap = array_flip($normalizedHeader);

        $successCount = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Map CSV row to data array using headerMap
            $studentData = [
                'student_number' => trim($row[$headerMap['student_number']] ?? ''),
                'first_name' => trim($row[$headerMap['first_name']] ?? ''),
                'last_name' => trim($row[$headerMap['last_name']] ?? ''),
                'grade_level' => trim($row[$headerMap['grade_level']] ?? ''),
                'section' => trim($row[$headerMap['section']] ?? ''),
            ];

            // Validate the student data
            $validator = Validator::make($studentData, [
                'student_number' => 'required|numeric|unique:students,student_number|max_digits:12',
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'grade_level' => 'required|integer',
                'section' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $studentData,
                    'errors' => $validator->errors()->all()
                ];
                continue;
            }

            // Add default points balance
            $studentData['points_balance'] = 0;

            // Create the student
            try {
                Students::create($studentData);
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'data' => $studentData,
                    'errors' => [$e->getMessage()]
                ];
            }
        }

        fclose($handle);

        return response()->json([
            'success' => true,
            'imported' => $successCount,
            'errors' => $errors
        ], 200);
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
