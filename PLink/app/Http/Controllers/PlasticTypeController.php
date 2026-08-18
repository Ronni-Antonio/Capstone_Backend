<?php
namespace App\Http\Controllers;

use App\Models\RecyclableType;
use Illuminate\Http\Request;

/**
 * Legacy /plastictypes route kept for frontend compatibility.
 * The underlying table/model is now recyclable_types / RecyclableType so it
 * can represent plastic and paper materials.
 */
class PlasticTypeController extends Controller
{
    public function index()
    {
        return response()->json(
            RecyclableType::orderBy('material_category')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:recyclable_types,code',
            'name' => 'required|string|max:255|unique:recyclable_types,name',
            'material_category' => 'required|in:plastic,paper,other',
            'points_value' => 'required|integer|min:0',
            'is_accepted' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return response()->json(RecyclableType::create($validated), 201);
    }

    public function show(string $id)
    {
        return response()->json(RecyclableType::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $type = RecyclableType::findOrFail($id);
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:recyclable_types,code,' . $type->recyclable_type_id . ',recyclable_type_id',
            'name' => 'sometimes|string|max:255|unique:recyclable_types,name,' . $type->recyclable_type_id . ',recyclable_type_id',
            'material_category' => 'sometimes|in:plastic,paper,other',
            'points_value' => 'sometimes|integer|min:0',
            'is_accepted' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $type->update($validated);
        return response()->json($type);
    }

    public function destroy(string $id)
    {
        $type = RecyclableType::findOrFail($id);
        if ($type->classifications()->exists()) {
            return response()->json([
                'error' => 'Recyclable type has classification history. Deactivate it instead.'
            ], 409);
        }

        $type->delete();
        return response()->json(null, 204);
    }
}
