<?php
namespace App\Http\Controllers;

use App\Models\Prediction;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function index(){ return response()->json(Prediction::with('model')->latest()->get()); }
    public function store(Request $request)
    {
        $v=$request->validate([
            'model_id'=>'nullable|exists:ai_models,model_id','prediction_type'=>'required|string|max:100',
            'prediction_date'=>'required|date','target_date'=>'nullable|date',
            'predicted_value'=>'nullable|numeric','actual_value'=>'nullable|numeric',
            'confidence'=>'nullable|numeric|min:0|max:1','input_summary'=>'nullable|array','output'=>'nullable|array'
        ]);
        return response()->json(Prediction::create($v),201);
    }
    public function show(string $id){ return response()->json(Prediction::with('model')->findOrFail($id)); }
    public function update(Request $request,string $id){ $p=Prediction::findOrFail($id); $p->update($request->validate(['actual_value'=>'nullable|numeric','confidence'=>'nullable|numeric|min:0|max:1','output'=>'nullable|array'])); return response()->json($p); }
    public function destroy(string $id){ Prediction::findOrFail($id)->delete(); return response()->json(null,204); }
}
