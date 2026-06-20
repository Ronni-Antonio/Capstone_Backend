<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transactions;
use App\Models\Students;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactions = Transactions::with(['student'])->get();
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|exists:students,student_id',
                'bottle_qty' => 'required|integer|min:1',
                'points_earned' => 'required|integer|min:0',
                'transaction_date' => 'nullable|date'
            ]);

            // Set transaction date to current time if not provided
            if (!isset($validated['transaction_date'])) {
                $validated['transaction_date'] = now();
            }

            $transaction = Transactions::create($validated);

            // Optionally update student's points balance here
            $student = Students::find($validated['student_id']);
            $student->points_balance += $validated['points_earned'];
            $student->save();

            return response()->json($transaction, 201);
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
