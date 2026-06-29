<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MachineLog;

class MachineLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $logs = MachineLog::with('machine')->get();
        return response()->json($logs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,machine_id',
            'log_type' => 'required|string',
            'message' => 'required|string',
            'details' => 'nullable|array'
        ]);

        $log = MachineLog::create($validated);
        $log->load('machine');
        return response()->json($log, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $log = MachineLog::with('machine')->findOrFail($id);
        return response()->json($log);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $log = MachineLog::findOrFail($id);
        $validated = $request->validate([
            'machine_id' => 'exists:machines,machine_id',
            'log_type' => 'string',
            'message' => 'string',
            'details' => 'nullable|array'
        ]);

        $log->update($validated);
        $log->load('machine');
        return response()->json($log);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $log = MachineLog::findOrFail($id);
        $log->delete();
        return response()->json(null, 204);
    }
}

