<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlasticType;
use App\Models\systemSettings;

class PlasticTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plasticType = PlasticType::all();
        return response()->json($plasticType);

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
        $validated = $request->validate([
            'name' => 'required|string',
            'multiplier' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        // Calculate points_per_item based on system settings
        $settings = systemSettings::first();
        $pointConversion = $settings ? $settings->point_conversion : 5;
        $validated['points_per_item'] = (int) round($pointConversion * $validated['multiplier']);

        $plasticType = PlasticType::create($validated);
        return response()->json($plasticType, 201);
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
        $plasticType = PlasticType::findOrFail($id);
        $validated = $request->validate([
            'name' => 'string',
            'multiplier' => 'numeric|min:0',
            'is_active' => 'boolean',
        ]);

        // If multiplier changed, recalculate points_per_item
        if (isset($validated['multiplier'])) {
            $settings = systemSettings::first();
            $pointConversion = $settings ? $settings->point_conversion : 5;
            $validated['points_per_item'] = (int) round($pointConversion * $validated['multiplier']);
        }

        $plasticType->update($validated);
        return response()->json($plasticType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $plasticType = PlasticType::findOrFail($id);
        $plasticType->delete();
        return response()->json(null, 204);
    }
}
