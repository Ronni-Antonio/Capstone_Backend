<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Students;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

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
            $studentData['status'] = 'inactive';

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
        try {
            $student = Students::findOrFail($id);
            
            $validated = $request->validate([
                'student_number' => 'numeric|unique:students,student_number,' . $id . ',student_id|max_digits:12',
                'first_name' => 'string',
                'last_name' => 'string',
                'grade_level' => 'integer',
                'section' => 'string',
                'status' => 'in:active,inactive',
                'card_uid' => 'nullable|string',
                'points_balance' => 'integer',
            ]);

            $student->update($validated);

            return response()->json($student, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a student's card
     */
    public function activate(string $id)
    {
        try {
        $student = Students::findOrFail($id);
        
        $esp32Url = env('ESP32_URL'); // Ensure this points to http://<esp32-ip>/prepare-activation
        if ($esp32Url) {
            $client = new \GuzzleHttp\Client();
            $client->post($esp32Url, [
                'json' => [
                    'student_id' => $student->student_id
                ],
                'timeout' => 5
            ]);
        }
        
        return response()->json(['message' => 'Please tap card on reader now.'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

        public function assignCard(Request $request)
    {
        $request->validate([
            'student_id' => 'required',
            'card_uid' => 'required|string|unique:students,card_uid'
        ]);

        try {
            $student = Students::findOrFail($request->student_id);
            
            // Save the hardware card UID to the user and mark active
            $student->update([
                'card_uid' => $request->card_uid,
                'status' => 'active'
            ]);

            return response()->json(['status' => 'success', 'message' => 'Card paired successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function activateStatus(string $id)
    {
        try {
            $student = Students::findOrFail($id);
            
            // If student has card_uid and is active, return success
            if ($student->card_uid && $student->status === 'active') {
                return response()->json(['status' => 'success'], 200);
            }
            
            // Otherwise, still pending
            return response()->json(['status' => 'pending'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    public function cancelActivation(string $id)
    {
        // You can implement this to tell ESP32 to cancel the activation session
        return response()->json(['message' => 'Activation cancelled'], 200);
    }

    public function identifyCard(Request $request)
    {
        $request->validate(['card_uid' => 'required|string']);

        // 1. Reset any old active scan flags left over in the database
        Students::where('is_currently_scanned', true)->update(['is_currently_scanned' => false]);

        // 2. Find the student who tapped the card
        $student = Students::where('card_uid', $request->card_uid)->first();

        if (!$student) {
            return response()->json(['error' => 'Unrecognized card.'], 404);
        }

        // 3. Set their database active scan flag to true
        $student->update(['is_currently_scanned' => true]);

        return response()->json([
            'success' => true,
            'student_id' => $student->student_id,
            'points_balance' => $student->points_balance
        ], 200);
    }

    /**
     * Hit by your Frontend Long Polling Loop
     */
    public function checkActiveScanSession()
    {
        // Search the database for whichever student has an active scanning flag right now
        $scannedStudent = Students::where('is_currently_scanned', true)->first();

        if ($scannedStudent) {
            return response()->json([
                'student_found' => true, // Enforces exact lowercase boolean match for React
                'student' => [
                    'id' => $scannedStudent->student_id,
                    'student_id' => $scannedStudent->student_id,
                    'name' => $scannedStudent->name ?? $scannedStudent->first_name . ' ' . $scannedStudent->last_name,
                    'points_balance' => (int) $scannedStudent->points_balance
                ]
            ], 200);
        }

        return response()->json([
            'student_found' => false,
            'student' => null
        ], 200);
    }

    /**
     * Hit by React when resetting or completing a checkout session
     */
    public function clearScanSession()
    {
        // Purge all scanning active flags cleanly
        Students::where('is_currently_scanned', true)->update(['is_currently_scanned' => false]);
        
        return response()->json(['success' => true], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $student = Students::findOrFail($id);
            $student->delete();
            
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
}
