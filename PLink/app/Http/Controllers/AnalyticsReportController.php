<?php
namespace App\Http\Controllers;

use App\Models\AnalyticsReport;
use Illuminate\Http\Request;

class AnalyticsReportController extends Controller
{
    public function index(){ return response()->json(AnalyticsReport::with('generatedBy')->latest()->get()); }
    public function store(Request $request)
    {
        $v=$request->validate([
            'report_type'=>'required|string|max:100','title'=>'required|string|max:255',
            'report_date_start'=>'nullable|date','report_date_end'=>'nullable|date',
            'total_items_collected'=>'nullable|integer|min:0','total_weight_kg'=>'nullable|numeric|min:0',
            'total_points_awarded'=>'nullable|integer','total_rewards_redeemed'=>'nullable|integer|min:0',
            'total_students_participated'=>'nullable|integer|min:0','summary'=>'nullable|array',
            'predictive_insights'=>'nullable|array','generated_by_user_id'=>'nullable|exists:users,id'
        ]);
        return response()->json(AnalyticsReport::create($v),201);
    }
    public function show(string $id){ return response()->json(AnalyticsReport::with('generatedBy')->findOrFail($id)); }
    public function update(Request $request,string $id)
    {
        $r=AnalyticsReport::findOrFail($id);
        $r->update($request->validate([
            'report_type'=>'sometimes|string|max:100','title'=>'sometimes|string|max:255',
            'report_date_start'=>'nullable|date','report_date_end'=>'nullable|date',
            'total_items_collected'=>'sometimes|integer|min:0','total_weight_kg'=>'sometimes|numeric|min:0',
            'total_points_awarded'=>'sometimes|integer','total_rewards_redeemed'=>'sometimes|integer|min:0',
            'total_students_participated'=>'sometimes|integer|min:0','summary'=>'nullable|array',
            'predictive_insights'=>'nullable|array'
        ]));
        return response()->json($r);
    }
    public function destroy(string $id){ AnalyticsReport::findOrFail($id)->delete(); return response()->json(null,204); }
}
