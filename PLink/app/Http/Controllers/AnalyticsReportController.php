<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AnalyticsReport;

class AnalyticsReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reports = AnalyticsReport::get();
        return response()->json($reports);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|string',
            'title' => 'required|string',
            'report_date_start' => 'nullable|date',
            'report_date_end' => 'nullable|date',
            'total_items_collected' => 'integer|default:0',
            'total_weight_kg' => 'numeric|default:0',
            'total_points_awarded' => 'integer|default:0',
            'total_rewards_redeemed' => 'integer|default:0',
            'total_students_participated' => 'integer|default:0',
            'plastic_type_breakdown' => 'nullable|array',
            'grade_level_breakdown' => 'nullable|array',
            'predictive_insights' => 'nullable|array',
            'generated_by' => 'nullable|array'
        ]);

        $report = AnalyticsReport::create($validated);
        return response()->json($report, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $report = AnalyticsReport::findOrFail($id);
        return response()->json($report);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $report = AnalyticsReport::findOrFail($id);
        $validated = $request->validate([
            'report_type' => 'string',
            'title' => 'string',
            'report_date_start' => 'nullable|date',
            'report_date_end' => 'nullable|date',
            'total_items_collected' => 'integer',
            'total_weight_kg' => 'numeric',
            'total_points_awarded' => 'integer',
            'total_rewards_redeemed' => 'integer',
            'total_students_participated' => 'integer',
            'plastic_type_breakdown' => 'nullable|array',
            'grade_level_breakdown' => 'nullable|array',
            'predictive_insights' => 'nullable|array',
            'generated_by' => 'nullable|array'
        ]);

        $report->update($validated);
        return response()->json($report);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $report = AnalyticsReport::findOrFail($id);
        $report->delete();
        return response()->json(null, 204);
    }
}

