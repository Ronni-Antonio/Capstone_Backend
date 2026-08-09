<?php
namespace App\Http\Controllers;

use App\Models\PlasticType;
use Illuminate\Http\Request;

class PlasticTypeController extends Controller
{
    public function index(){ return response()->json(PlasticType::orderBy('name')->get()); }

    public function store(Request $request)
    {
        $v=$request->validate([
            'code'=>'required|string|max:50|unique:plastic_types,code',
            'name'=>'required|string|max:255|unique:plastic_types,name',
            'points_value'=>'required|integer|min:0',
            'is_accepted'=>'boolean',
            'is_active'=>'boolean',
        ]);
        return response()->json(PlasticType::create($v),201);
    }

    public function show(string $id){ return response()->json(PlasticType::findOrFail($id)); }

    public function update(Request $request,string $id)
    {
        $type=PlasticType::findOrFail($id);
        $v=$request->validate([
            'code'=>'sometimes|string|max:50|unique:plastic_types,code,'.$type->plastic_type_id.',plastic_type_id',
            'name'=>'sometimes|string|max:255|unique:plastic_types,name,'.$type->plastic_type_id.',plastic_type_id',
            'points_value'=>'sometimes|integer|min:0',
            'is_accepted'=>'sometimes|boolean','is_active'=>'sometimes|boolean'
        ]);
        $type->update($v); return response()->json($type);
    }

    public function destroy(string $id)
    {
        $type=PlasticType::findOrFail($id);
        if($type->classifications()->exists()) return response()->json(['error'=>'Plastic type has classification history. Deactivate it instead.'],409);
        $type->delete(); return response()->json(null,204);
    }
}
