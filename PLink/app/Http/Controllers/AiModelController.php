<?php
namespace App\Http\Controllers;

use App\Models\AiModel;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    public function index(){ return response()->json(AiModel::latest()->get()); }
    public function store(Request $request)
    {
        $v=$request->validate([
            'name'=>'required|string|max:255','version'=>'required|string|max:100',
            'framework'=>'nullable|string|max:100','accuracy'=>'nullable|numeric|min:0|max:100',
            'model_path'=>'nullable|string|max:255','is_active'=>'boolean'
        ]);
        return response()->json(AiModel::create($v),201);
    }
    public function show(string $id){ return response()->json(AiModel::findOrFail($id)); }
    public function update(Request $request,string $id)
    {
        $m=AiModel::findOrFail($id);
        $m->update($request->validate([
            'name'=>'sometimes|string|max:255','version'=>'sometimes|string|max:100',
            'framework'=>'nullable|string|max:100','accuracy'=>'nullable|numeric|min:0|max:100',
            'model_path'=>'nullable|string|max:255','is_active'=>'boolean'
        ]));
        return response()->json($m);
    }
    public function destroy(string $id){ $m=AiModel::findOrFail($id); if($m->classifications()->exists()) return response()->json(['error'=>'Model has classification history. Deactivate it instead.'],409); $m->delete(); return response()->json(null,204); }
}
