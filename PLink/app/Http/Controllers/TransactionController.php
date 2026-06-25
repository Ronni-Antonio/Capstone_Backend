<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transactions;
use App\Models\Students;
use App\Models\PlasticType;
use App\Models\systemSettings;
use App\Models\ClassificationHistory;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactions = Transactions::with(['student', 'machine', 'classificationHistories.plasticType'])->get();
        return response()->json($transactions);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Process a detailed transaction with classification data
     */
    public function processTransaction(Request $request)
    {
        try {
            // Validate incoming request
            $validated = $request->validate([
                'student_id' => 'required|exists:students,student_id',
                'machine_id' => 'nullable|exists:machines,machine_id',
                'bottles' => 'required|array|min:1',
                'bottles.*.plastic_type_id' => 'nullable|exists:plastic_types,plastic_type_id',
                'bottles.*.status' => 'required|in:valid,contaminated,non_pet,rejected,invalid',
                'bottles.*.confidence_score' => 'nullable|numeric|min:0|max:100',
                'bottles.*.image_path' => 'nullable|string',
                'bottles.*.ai_model_version' => 'nullable|string',
                'bottles.*.notes' => 'nullable|string'
            ]);

            $settings = systemSettings::first();
            if (!$settings) {
                return response()->json(['error' => 'System settings not found'], 400);
            }

            // Calculate transaction totals
            $totalBottles = count($validated['bottles']);
            $validQty = 0;
            $contaminatedQty = 0;
            $nonPetQty = 0;
            $rejectedQty = 0;
            $totalPointsEarned = 0;
            $breakdown = [];

            // Start DB transaction
            DB::beginTransaction();

            // Create the main transaction record
            $transaction = Transactions::create([
                'student_id' => $validated['student_id'],
                'machine_id' => $validated['machine_id'] ?? null,
                'bottle_qty' => $totalBottles,
                'valid_qty' => 0, // We'll update this as we process bottles
                'contaminated_qty' => 0,
                'non_pet_qty' => 0,
                'rejected_qty' => 0,
                'points_earned' => 0,
                'transaction_date' => now(),
                'breakdown' => []
            ]);

            // Process each bottle
            foreach ($validated['bottles'] as $bottleData) {
                // Get plastic type points if available
                $plasticType = null;
                $pointsChange = 0;

                if (!empty($bottleData['plastic_type_id'])) {
                    $plasticType = PlasticType::find($bottleData['plastic_type_id']);
                }

                // Determine points change based on status
                switch ($bottleData['status']) {
                    case 'valid':
                        $validQty++;
                        $pointsChange = $plasticType ? $plasticType->points_per_item : $settings->point_conversion;
                        break;
                    case 'contaminated':
                        $contaminatedQty++;
                        $pointsChange = $settings->penalty_rejected;
                        break;
                    case 'non_pet':
                        $nonPetQty++;
                        $pointsChange = $settings->penalty_non_pet;
                        break;
                    case 'rejected':
                    case 'invalid':
                        $rejectedQty++;
                        $pointsChange = $settings->penalty_invalid;
                        break;
                }

                $totalPointsEarned += $pointsChange;

                // Add to breakdown
                $breakdown[] = [
                    'plastic_type_id' => $bottleData['plastic_type_id'],
                    'plastic_type_name' => $plasticType ? $plasticType->name : 'Unknown',
                    'status' => $bottleData['status'],
                    'points_change' => $pointsChange,
                    'confidence_score' => $bottleData['confidence_score'] ?? null
                ];

                // Create classification history entry
                ClassificationHistory::create([
                    'plastic_type_id' => $bottleData['plastic_type_id'],
                    'machine_id' => $validated['machine_id'] ?? null,
                    'transaction_id' => $transaction->transaction_id,
                    'confidence_score' => $bottleData['confidence_score'] ?? null,
                    'image_path' => $bottleData['image_path'] ?? null,
                    'ai_model_version' => $bottleData['ai_model_version'] ?? null,
                    'notes' => $bottleData['notes'] ?? null,
                    'is_verified' => false,
                    'status' => $bottleData['status'],
                    'points_change' => $pointsChange
                ]);
            }

            // Update transaction with final totals
            $transaction->update([
                'valid_qty' => $validQty,
                'contaminated_qty' => $contaminatedQty,
                'non_pet_qty' => $nonPetQty,
                'rejected_qty' => $rejectedQty,
                'points_earned' => $totalPointsEarned,
                'breakdown' => $breakdown
            ]);

            // Update student's points balance
            $student = Students::find($validated['student_id']);
            $student->points_balance += $totalPointsEarned;
            $student->save();

            // Commit DB transaction
            DB::commit();

            // Load relationships for response
            $transaction->load(['student', 'machine', 'classificationHistories.plasticType']);

            return response()->json($transaction, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = Transactions::with(['student', 'machine', 'classificationHistories.plasticType'])->findOrFail($id);
        return response()->json($transaction);
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
