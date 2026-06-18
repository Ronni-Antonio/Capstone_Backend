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
        //
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
                'points_spent' => 'required|integer|min:1',
                'redemption_date' => 'nullable|date'
            ]);

            // Set redemption date to current time if not provided
            if (!isset($validated['redemption_date'])) {
                $validated['redemption_date'] = now();
            }

            // Check if student has enough points
            $student = Students::findOrFail($validated['student_id']);
            if ($student->points_balance < $validated['points_spent']) {
                return response()->json(['error' => 'Insufficient points balance'], 422);
            }

            // Check if reward has enough stock
            $reward = Rewards::findOrFail($validated['reward_id']);
            if ($reward->stock_quantity <= 0) {
                return response()->json(['error' => 'Reward out of stock'], 422);
            }

            // Create redemption
            $redemption = Redemptions::create($validated);

            // Update student's points balance
            $student->points_balance -= $validated['points_spent'];
            $student->save();

            // Update reward's stock quantity
            $reward->stock_quantity -= 1;
            $reward->save();

            return response()->json($redemption, 201);
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
