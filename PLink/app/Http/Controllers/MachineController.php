<?php
namespace App\Http\Controllers;

use App\Models\SmartBin;
use App\Models\Notification;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function index()
    {
        return response()->json(SmartBin::with(['logs', 'transactions'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'status' => 'nullable|in:online,offline,maintenance,full',
            'current_distance_cm' => 'nullable|integer|min:0',
            'current_fill_percentage' => 'nullable|integer|min:0|max:100',
            'full_threshold_cm' => 'nullable|integer|min:80',
            'empty_threshold_cm' => 'nullable|integer|min:0',
            'last_maintenance_at' => 'nullable|date',
            'last_active_at' => 'nullable|date',
        ]);
        $bin = SmartBin::create($validated);
        return response()->json($bin, 201);
    }

    public function show(string $id)
    {
        return response()->json(SmartBin::with(['logs', 'transactions'])->findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $bin = SmartBin::findOrFail($id);
        $oldPct = $bin->current_fill_percentage > 0 ? $bin->current_distance_cm / $bin->current_fill_percentage : 0;
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:online,offline,maintenance,full',

            'last_maintenance_at' => 'nullable|date',
            'last_active_at' => 'nullable|date',
        ]);
        $bin->update($validated);
        $newPct = $bin->current_fill_percentage > 0 ? $bin->current_distance_cm / $bin->current_fill_percentage : 0;
        if ($newPct >= 100 && $oldPct < 100) {
            Notification::create([
                'smart_bin_id' => $bin->smart_bin_id,
                'notification_type' => 'machine_full',
                'title' => 'Smart bin full',
                'message' => "{$bin->name} at {$bin->location} has reached full capacity.",
                'data' => ['capacity_percentage' => $newPct]
            ]);
        }
        return response()->json($bin);
    }

    public function destroy(string $id)
    {
        $bin = SmartBin::findOrFail($id);
        if ($bin->transactions()->exists())
            return response()->json(['error' => 'Smart bin has transaction history and cannot be deleted.'], 409);
        $bin->delete();
        return response()->json(null, 204);
    }
}
