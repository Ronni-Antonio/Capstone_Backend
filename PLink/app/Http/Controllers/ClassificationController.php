<?php
namespace App\Http\Controllers;

use App\Models\AiClassification;
use Illuminate\Http\Request;

class ClassificationController extends Controller
{
    public function index()
    {
        return response()->json(
            AiClassification::with(['item.transaction','plasticType','model'])->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated=$request->validate([
            'recycling_item_id'=>'required|exists:recycling_items,recycling_item_id',
            'plastic_type_id'=>'nullable|exists:plastic_types,plastic_type_id',
            'model_id'=>'nullable|exists:ai_models,model_id',
            'confidence_score'=>'nullable|numeric|min:0|max:100',
            'status'=>'required|in:valid,invalid,uncertain,rejected,manually_verified',
            'notes'=>'nullable|string',
            'is_verified'=>'boolean',
        ]);
        $c=AiClassification::create($validated+['classified_at'=>now()]);
        return response()->json($c->load(['item.transaction','plasticType','model']),201);
    }

    public function show(string $id)
    {
        return response()->json(
            AiClassification::with(['item.transaction','plasticType','model'])->findOrFail($id)
        );
    }

    public function update(Request $request,string $id)
    {
        $c=AiClassification::findOrFail($id);
        $validated=$request->validate([
            'plastic_type_id'=>'nullable|exists:plastic_types,plastic_type_id',
            'model_id'=>'nullable|exists:ai_models,model_id',
            'confidence_score'=>'nullable|numeric|min:0|max:100',
            'status'=>'sometimes|in:valid,invalid,uncertain,rejected,manually_verified',
            'notes'=>'nullable|string',
            'is_verified'=>'boolean',
        ]);
        $c->update($validated);
        return response()->json($c->load(['item.transaction','plasticType','model']));
    }

    public function destroy(string $id)
    {
        AiClassification::findOrFail($id)->delete();
        return response()->json(null,204);
    }
}
