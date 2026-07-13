<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClassificationHistory;

class ClassificationController extends Controller
{
    /**
     * Display a listing of classification histories.
     */
    public function index()
    {
        $classifications = ClassificationHistory::with('plasticType', 'machine', 'transaction')->get();
        return response()->json($classifications);
    }

    /**
     * Store a newly created classification history in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plastic_type_id' => 'nullable|exists:plastic_types,plastic_type_id',
            'machine_id' => 'required|exists:machines,machine_id',
            'transaction_id' => 'nullable|exists:transactions,transaction_id',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
            'image_path' => 'nullable|string',
            'ai_model_version' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_verified' => 'boolean|default:false',
            'status' => 'string|default:valid',
            'points_change' => 'nullable|integer'
        ]);

        $classification = ClassificationHistory::create($validated);
        $classification->load('plasticType', 'machine', 'transaction');
        return response()->json($classification, 201);
    }

    /**
     * Display the specified classification history.
     */
    public function show(string $id)
    {
        $classification = ClassificationHistory::with('plasticType', 'machine', 'transaction')->findOrFail($id);
        return response()->json($classification);
    }

    /**
     * Update the specified classification history in storage.
     */
    public function update(Request $request, string $id)
    {
        $classification = ClassificationHistory::findOrFail($id);
        $validated = $request->validate([
            'plastic_type_id' => 'nullable|exists:plastic_types,plastic_type_id',
            'machine_id' => 'exists:machines,machine_id',
            'transaction_id' => 'nullable|exists:transactions,transaction_id',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
            'image_path' => 'nullable|string',
            'ai_model_version' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_verified' => 'boolean',
            'status' => 'string',
            'points_change' => 'nullable|integer'
        ]);

        $classification->update($validated);
        $classification->load('plasticType', 'machine', 'transaction');
        return response()->json($classification);
    }

    /**
     * Remove the specified classification history from storage.
     */
    public function destroy(string $id)
    {
        $classification = ClassificationHistory::findOrFail($id);
        $classification->delete();
        return response()->json(null, 204);
    }
}

