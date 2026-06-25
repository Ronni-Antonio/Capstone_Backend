<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Redemptions;
use App\Models\Students;
use App\Models\Rewards;

class RedemptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $redemptions = Redemptions::all();
        return response()->json($redemptions);
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
                'student_id' => 'required|exists:students,student_id',
                'reward_id' => 'required|exists:rewards,reward_id',
                'redemption_date' => 'nullable|date'
            ]);

            // Set redemption date to current time if not provided
            if (!isset($validated['redemption_date'])) {
                $validated['redemption_date'] = now();
            }

            // Get the student
            $student = Students::findOrFail($validated['student_id']);
            
            // Get the reward and its points cost
            $reward = Rewards::findOrFail($validated['reward_id']);
            
            // Check if student has enough points
            if ($student->points_balance < $reward->points_cost) {
                return response()->json([
                    'error' => 'Insufficient points balance',
                    'required' => $reward->points_cost,
                    'current' => $student->points_balance
                ], 422);
            }

            // Check if reward has enough stock
            if ($reward->stock_quantity <= 0) {
                return response()->json(['error' => 'Reward out of stock'], 422);
            }

            // Add the actual points cost to validated data for creating redemption
            $validated['points_spent'] = $reward->points_cost;

            // Create redemption
            $redemption = Redemptions::create($validated);

            // Update student's points balance
            $student->points_balance -= $reward->points_cost;
            $student->save();

            // Update reward's stock quantity
            $reward->stock_quantity -= 1;
            $reward->save();

            return response()->json([
                'success' => true,
                'redemption' => $redemption,
                'student' => $student
            ], 201);
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
        //
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
