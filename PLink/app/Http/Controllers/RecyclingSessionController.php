<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecyclingSession;

class RecyclingSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sessions = RecyclingSession::with('student', 'machine', 'transaction')->get();
        return response()->json($sessions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'nullable|exists:students,student_id',
            'machine_id' => 'required|exists:machines,machine_id',
            'transaction_id' => 'nullable|exists:transactions,transaction_id',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date',
            'status' => 'string|default:in_progress',
            'items_processed' => 'integer|default:0',
            'total_weight_kg' => 'numeric|default:0',
            'total_points_earned' => 'integer|default:0',
            'items_details' => 'nullable|array',
            'notes' => 'nullable|string'
        ]);

        $session = RecyclingSession::create($validated);
        $session->load('student', 'machine', 'transaction');
        return response()->json($session, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $session = RecyclingSession::with('student', 'machine', 'transaction')->findOrFail($id);
        return response()->json($session);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $session = RecyclingSession::findOrFail($id);
        $validated = $request->validate([
            'student_id' => 'nullable|exists:students,student_id',
            'machine_id' => 'exists:machines,machine_id',
            'transaction_id' => 'nullable|exists:transactions,transaction_id',
            'started_at' => 'date',
            'ended_at' => 'nullable|date',
            'status' => 'string',
            'items_processed' => 'integer',
            'total_weight_kg' => 'numeric',
            'total_points_earned' => 'integer',
            'items_details' => 'nullable|array',
            'notes' => 'nullable|string'
        ]);

        $session->update($validated);
        $session->load('student', 'machine', 'transaction');
        return response()->json($session);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $session = RecyclingSession::findOrFail($id);
        $session->delete();
        return response()->json(null, 204);
    }
}

