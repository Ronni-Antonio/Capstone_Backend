<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rewards;

class RewardController extends Controller
{
    private function formatReward(Rewards $reward): array
    {
        return [
            'reward_id' => $reward->reward_id,
            'id' => $reward->reward_id,
            'reward_name' => $reward->reward_name,
            'name' => $reward->reward_name,
            'points_cost' => $reward->points_cost,
            'points' => $reward->points_cost,
            'points_required' => $reward->points_cost,
            'stock_quantity' => $reward->stock_quantity,
            'stock' => $reward->stock_quantity,
            'unit_price' => $reward->unit_price,
            'price' => $reward->unit_price,
            'is_active' => $reward->is_active,
            'status' => $reward->is_active ? 'Active' : 'Inactive',
            'created_at' => $reward->created_at,
            'updated_at' => $reward->updated_at,
        ];
    }

    public function index(Request $request)
    {
        $query = Rewards::query();
        if (!$request->has('all')) {
            $query->where('is_active', true);
        }
        return response()->json(
            $query->get()->map(fn($r) => $this->formatReward($r))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reward_name' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'points_cost' => 'sometimes|integer|min:1',
            'points' => 'sometimes|integer|min:1',
            'points_required' => 'sometimes|integer|min:1',
            'stock_quantity' => 'sometimes|integer|min:0',
            'stock' => 'sometimes|integer|min:0',
            'unit_price' => 'sometimes|numeric|min:0',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|string',
        ]);

        $data = [];
        $data['reward_name'] = $validated['reward_name'] ?? $validated['name'] ?? null;
        $data['points_cost'] = $validated['points_cost'] ?? $validated['points'] ?? $validated['points_required'] ?? null;
        $data['stock_quantity'] = $validated['stock_quantity'] ?? $validated['stock'] ?? 0;
        $data['unit_price'] = $validated['unit_price'] ?? $validated['price'] ?? 0;
        $data['is_active'] = $validated['is_active'] ?? (isset($validated['status']) ? $validated['status'] === 'Active' : true);

        $v = validator($data, [
            'reward_name' => 'required|string|max:255',
            'points_cost' => 'required|integer|min:1',
            'stock_quantity' => 'required|integer|min:0',
            'unit_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ])->validate();

        $reward = Rewards::create($v);
        return response()->json($this->formatReward($reward), 201);
    }

    public function show(string $id)
    {
        return response()->json($this->formatReward(Rewards::findOrFail($id)));
    }

    public function update(Request $request, string $id)
    {
        $reward = Rewards::findOrFail($id);

        $validated = $request->validate([
            'reward_name' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'points_cost' => 'sometimes|integer|min:1',
            'points' => 'sometimes|integer|min:1',
            'points_required' => 'sometimes|integer|min:1',
            'stock_quantity' => 'sometimes|integer|min:0',
            'stock' => 'sometimes|integer|min:0',
            'unit_price' => 'sometimes|numeric|min:0',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|string',
        ]);

        $data = [];
        if (isset($validated['reward_name']) || isset($validated['name'])) {
            $data['reward_name'] = $validated['reward_name'] ?? $validated['name'];
        }
        if (isset($validated['points_cost']) || isset($validated['points']) || isset($validated['points_required'])) {
            $data['points_cost'] = $validated['points_cost'] ?? $validated['points'] ?? $validated['points_required'];
        }
        if (isset($validated['stock_quantity']) || isset($validated['stock'])) {
            $data['stock_quantity'] = $validated['stock_quantity'] ?? $validated['stock'];
        }
        if (isset($validated['unit_price']) || isset($validated['price'])) {
            $data['unit_price'] = $validated['unit_price'] ?? $validated['price'];
        }
        if (isset($validated['is_active']) || isset($validated['status'])) {
            $data['is_active'] = $validated['is_active'] ?? ($validated['status'] === 'Active');
        }

        if (!empty($data)) {
            $v = validator($data, [
                'reward_name' => 'sometimes|string|max:255',
                'points_cost' => 'sometimes|integer|min:1',
                'stock_quantity' => 'sometimes|integer|min:0',
                'unit_price' => 'sometimes|numeric|min:0',
                'is_active' => 'sometimes|boolean',
            ])->validate();
            $reward->update($v);
        }

        return response()->json($this->formatReward($reward));
    }

    public function destroy(string $id)
    {
        $reward = Rewards::findOrFail($id);
        if ($reward->redemptions()->exists()) {
            return response()->json(['error' => 'Reward has redemption history. Deactivate it instead.'], 409);
        }
        $reward->delete();
        return response()->json(null, 204);
    }
}
