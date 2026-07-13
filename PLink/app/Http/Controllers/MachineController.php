<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Machine;

class MachineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $machines = Machine::with('machineLogs', 'classificationHistories', 'recyclingSessions', 'transactions')->get();
        return response()->json($machines);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'location' => 'nullable|string',
            'status' => 'string|default:online',
            'current_weight_kg' => 'numeric|default:0',
            'max_capacity_kg' => 'numeric|default:100',
            'last_maintenance_at' => 'nullable|date',
            'last_active_at' => 'nullable|date'
        ]);

        $machine = Machine::create($validated);
        return response()->json($machine, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $machine = Machine::with('machineLogs', 'classificationHistories', 'recyclingSessions', 'transactions')->findOrFail($id);
        return response()->json($machine);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $machine = Machine::findOrFail($id);
        $validated = $request->validate([
            'name' => 'string',
            'location' => 'nullable|string',
            'status' => 'string',
            'current_weight_kg' => 'numeric',
            'max_capacity_kg' => 'numeric',
            'last_maintenance_at' => 'nullable|date',
            'last_active_at' => 'nullable|date'
        ]);

        $machine->update($validated);
        return response()->json($machine);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $machine = Machine::findOrFail($id);
        $machine->delete();
        return response()->json(null, 204);
    }
}

