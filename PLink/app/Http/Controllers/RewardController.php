<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rewards;
use App\Models\ActivityLog;

class RewardController extends Controller
{
    public function index(Request $request)
    {
        $query = Rewards::query();
        if ($request->missing('include_inactive')) {
            $query->where('is_active', true);
        }
        return response()->json($query->latest('reward_id')->get());
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'reward_name'    => 'required|string|max:255',
            'points_cost'    => 'required|integer|min:1',
            'unit_price'     => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'is_active'      => 'sometimes|boolean',
        ]);

        $initialStock = (int) ($v['stock_quantity'] ?? 0);
        $v['last_restock'] = $initialStock > 0 ? now() : null;

        $reward = Rewards::create($v);

        ActivityLog::record(
            'ADD_REWARD',
            "Admin added reward \"{$reward->reward_name}\" with {$reward->stock_quantity} stocks, unit price ₱{$reward->unit_price}, {$reward->points_cost} points.",
            'Rewards',
            $request->user()?->id,
            null,
            ['reward_id' => $reward->reward_id, 'reward' => $reward->toArray()]
        );

        if ($initialStock > 0) {
            ActivityLog::record(
                'RESTOCK_INVENTORY',
                "Inventory for \"{$reward->reward_name}\" was initialized with {$initialStock} units.",
                'Inventory',
                $request->user()?->id,
                null,
                ['reward_id' => $reward->reward_id, 'quantity_added' => $initialStock]
            );
        }

        return response()->json($reward, 201);
    }

    public function inventory()
    {
        $rows = Rewards::query()
            ->orderBy('reward_name')
            ->get()
            ->map(function (Rewards $reward) {
                $unitPrice = (float) ($reward->unit_price ?? 0);
                $stock = (int) $reward->stock_quantity;

                return [
                    'reward_id' => $reward->reward_id,
                    'reward_name' => $reward->reward_name,
                    'points_cost' => (int) $reward->points_cost,
                    'points_value' => (int) $reward->points_cost,
                    'item_price' => $unitPrice,
                    'unit_price' => $unitPrice,
                    'remaining_stocks' => $stock,
                    'stock_quantity' => $stock,
                    'total_stocks_on_hand' => $stock,
                    'total_price' => round($stock * $unitPrice, 2),
                    'last_restock' => optional($reward->last_restock)->toIso8601String(),
                    'is_active' => (bool) $reward->is_active,
                    'status' => $reward->is_active ? 'Active' : 'Inactive',
                    'created_at' => optional($reward->created_at)->toIso8601String(),
                    'updated_at' => optional($reward->updated_at)->toIso8601String(),
                ];
            });

        return response()->json($rows);
    }

    public function show(string $id)
    {
        return response()->json(Rewards::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $r = Rewards::findOrFail($id);
        $oldStock = (int) $r->stock_quantity;

        $validated = $request->validate([
            'reward_name'    => 'sometimes|string|max:255',
            'points_cost'    => 'sometimes|integer|min:1',
            'unit_price'     => 'sometimes|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
            'is_active'      => 'sometimes|boolean',
        ]);

        $changes = [];
        if (isset($validated['reward_name']) && $validated['reward_name'] !== $r->reward_name) {
            $changes['reward_name'] = ['old' => $r->reward_name, 'new' => $validated['reward_name']];
        }
        if (isset($validated['points_cost']) && (int) $validated['points_cost'] !== (int) $r->points_cost) {
            $changes['points_cost'] = ['old' => $r->points_cost, 'new' => $validated['points_cost']];
        }
        if (isset($validated['unit_price']) && (float) $validated['unit_price'] !== (float) $r->unit_price) {
            $changes['unit_price'] = ['old' => $r->unit_price, 'new' => $validated['unit_price']];
        }
        if (isset($validated['stock_quantity']) && (int) $validated['stock_quantity'] !== $oldStock) {
            $changes['stock_quantity'] = ['old' => $oldStock, 'new' => (int) $validated['stock_quantity']];
        }
        if (isset($validated['is_active']) && (bool) $validated['is_active'] !== (bool) $r->is_active) {
            $changes['is_active'] = ['old' => $r->is_active, 'new' => $validated['is_active']];
        }

        if (isset($validated['stock_quantity'])) {
            $newStock = (int) $validated['stock_quantity'];
            if ($newStock > $oldStock) {
                $added = $newStock - $oldStock;
                $validated['last_restock'] = now();
            }
        }

        $r->update($validated);

        $name = $r->reward_name;
        $userId = $request->user()?->id;

        if (!empty($changes)) {
            ActivityLog::record(
                'UPDATE_REWARD',
                "Admin updated reward \"{$name}\". Changes: " . json_encode($changes),
                'Rewards',
                $userId,
                null,
                ['reward_id' => $r->reward_id, 'changes' => $changes]
            );
        }

        if (isset($changes['stock_quantity'])) {
            $newQty = (int) $changes['stock_quantity']['new'];
            $oldQty = (int) $changes['stock_quantity']['old'];
            if ($newQty > $oldQty) {
                $diff = $newQty - $oldQty;
                ActivityLog::record(
                    'RESTOCK_INVENTORY',
                    "Inventory for \"{$name}\" was restocked by {$diff} units ({$oldQty} → {$newQty}).",
                    'Inventory',
                    $userId,
                    null,
                    ['reward_id' => $r->reward_id, 'quantity_added' => $diff, 'old' => $oldQty, 'new' => $newQty]
                );
            } elseif ($newQty < $oldQty) {
                $diff = $oldQty - $newQty;
                ActivityLog::record(
                    'UPDATE_INVENTORY_QTY',
                    "Inventory quantity for \"{$name}\" was updated ({$oldQty} → {$newQty}, -{$diff}).",
                    'Inventory',
                    $userId,
                    null,
                    ['reward_id' => $r->reward_id, 'quantity_removed' => $diff, 'old' => $oldQty, 'new' => $newQty]
                );
            }
        }

        return response()->json($r);
    }

    public function destroy(string $id)
    {
        $r = Rewards::findOrFail($id);
        if ($r->redemptions()->exists()) {
            return response()->json(['error' => 'Reward has redemption history. Deactivate it instead.'], 409);
        }
        $name = $r->reward_name;
        $rewardId = $r->reward_id;
        $r->delete();

        ActivityLog::record(
            'DELETE_REWARD',
            "Admin deleted reward \"{$name}\".",
            'Rewards',
            request()->user()?->id,
            null,
            ['reward_id' => $rewardId, 'reward_name' => $name]
        );

        return response()->json(null, 204);
    }
}
