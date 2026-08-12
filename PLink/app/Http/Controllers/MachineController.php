<?php
namespace App\Http\Controllers;

use App\Models\SmartBin;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MachineController extends Controller
{
    /**
     * Return a lightweight Smart Bin payload for Machine Monitoring.
     *
     * In addition to the HC-SR04 state, each bin includes today's accepted /
     * rejected bottle totals and rejected counts by hour. These values are
     * derived from recycling_items, which is the source of truth for whether
     * an individual bottle was accepted or rejected.
     */
    public function index()
    {
        $bins = SmartBin::query()
            ->select([
                'smart_bin_id',
                'name',
                'location',
                'status',
                'current_distance_cm',
                'current_fill_percentage',
                'full_threshold_cm',
                'empty_threshold_cm',
                'last_maintenance_at',
                'last_active_at',
                'created_at',
                'updated_at',
            ])
            ->get();

        $todayStart = now()->startOfDay();
        $tomorrowStart = now()->addDay()->startOfDay();

        $dailyStats = DB::table('recycling_transactions as rt')
            ->join('recycling_items as ri', 'ri.transaction_id', '=', 'rt.transaction_id')
            ->where('rt.status', 'completed')
            ->where('rt.started_at', '>=', $todayStart)
            ->where('rt.started_at', '<', $tomorrowStart)
            ->select('rt.smart_bin_id')
            ->selectRaw("SUM(CASE WHEN ri.status = 'accepted' THEN 1 ELSE 0 END) as accepted_today")
            ->selectRaw("SUM(CASE WHEN ri.status = 'rejected' THEN 1 ELSE 0 END) as rejected_today")
            ->groupBy('rt.smart_bin_id')
            ->get()
            ->keyBy('smart_bin_id');

        $rejectedHourly = DB::table('recycling_transactions as rt')
            ->join('recycling_items as ri', 'ri.transaction_id', '=', 'rt.transaction_id')
            ->where('rt.status', 'completed')
            ->where('ri.status', 'rejected')
            ->where('rt.started_at', '>=', $todayStart)
            ->where('rt.started_at', '<', $tomorrowStart)
            ->select('rt.smart_bin_id')
            ->selectRaw('HOUR(rt.started_at) as hour')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('rt.smart_bin_id', DB::raw('HOUR(rt.started_at)'))
            ->orderBy('hour')
            ->get()
            ->groupBy('smart_bin_id');

        $payload = $bins->map(function ($bin) use ($dailyStats, $rejectedHourly) {
            $stats = $dailyStats->get($bin->smart_bin_id);
            $accepted = (int) ($stats->accepted_today ?? 0);
            $rejected = (int) ($stats->rejected_today ?? 0);
            $classified = $accepted + $rejected;

            $hourly = collect($rejectedHourly->get($bin->smart_bin_id, collect()))
                ->map(fn ($row) => [
                    'hour' => (int) $row->hour,
                    'count' => (int) $row->count,
                ])
                ->values();

            return array_merge($bin->toArray(), [
                'today_stats' => [
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'total_classified' => $classified,
                    'acceptance_rate' => $classified > 0
                        ? round(($accepted / $classified) * 100, 1)
                        : 0,
                ],
                'rejected_by_hour' => $hourly,
            ]);
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'status' => 'nullable|in:online,offline,maintenance,full',
            'current_distance_cm' => 'nullable|integer|min:0',
            'current_fill_percentage' => 'nullable|integer|min:0|max:100',
            'full_threshold_cm' => 'nullable|integer|min:0',
            'empty_threshold_cm' => 'nullable|integer|min:1',
            'last_maintenance_at' => 'nullable|date',
            'last_active_at' => 'nullable|date',
        ]);

        if (
            isset($validated['full_threshold_cm'], $validated['empty_threshold_cm']) &&
            $validated['empty_threshold_cm'] <= $validated['full_threshold_cm']
        ) {
            return response()->json([
                'message' => 'The empty threshold must be greater than the full threshold.',
            ], 422);
        }

        $bin = SmartBin::create($validated);
        return response()->json($bin, 201);
    }

    public function show(string $id)
    {
        return response()->json(SmartBin::findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        $bin = SmartBin::findOrFail($id);
        $oldPct = $this->calculateFillPercentage($bin);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:online,offline,maintenance,full',
            'current_distance_cm' => 'sometimes|nullable|integer|min:0',
            'current_fill_percentage' => 'sometimes|nullable|integer|min:0|max:100',
            'full_threshold_cm' => 'sometimes|nullable|integer|min:0',
            'empty_threshold_cm' => 'sometimes|nullable|integer|min:1',
            'last_maintenance_at' => 'nullable|date',
            'last_active_at' => 'nullable|date',
        ]);

        $candidateFull = $validated['full_threshold_cm'] ?? $bin->full_threshold_cm;
        $candidateEmpty = $validated['empty_threshold_cm'] ?? $bin->empty_threshold_cm;

        if ($candidateFull !== null && $candidateEmpty !== null && $candidateEmpty <= $candidateFull) {
            return response()->json([
                'message' => 'The empty threshold must be greater than the full threshold.',
            ], 422);
        }

        // When a new sensor distance is supplied, calculate fullness on the server
        // so the stored snapshot and frontend use the same HC-SR04 interpretation.
        if (array_key_exists('current_distance_cm', $validated)) {
            $distance = (float) $validated['current_distance_cm'];
            $range = (float) $candidateEmpty - (float) $candidateFull;
            if ($range > 0) {
                $validated['current_fill_percentage'] = (int) round(
                    max(0, min(100, (($candidateEmpty - $distance) / $range) * 100))
                );
            }
        }

        $bin->update($validated);
        $newPct = $this->calculateFillPercentage($bin);

        if ($newPct >= 100 && $oldPct < 100) {
            Notification::create([
                'smart_bin_id' => $bin->smart_bin_id,
                'notification_type' => 'machine_full',
                'title' => 'Smart bin full',
                'message' => "{$bin->name} at {$bin->location} has reached full capacity.",
                'data' => ['capacity_percentage' => $newPct]
            ]);
        }

        return response()->json($bin->fresh());
    }

    public function destroy(string $id)
    {
        $bin = SmartBin::findOrFail($id);
        if ($bin->transactions()->exists()) {
            return response()->json([
                'error' => 'Smart bin has transaction history and cannot be deleted.'
            ], 409);
        }

        $bin->delete();
        return response()->json(null, 204);
    }

    private function calculateFillPercentage(SmartBin $bin): float
    {
        $empty = (float) $bin->empty_threshold_cm;
        $full = (float) $bin->full_threshold_cm;
        $distance = (float) $bin->current_distance_cm;
        $range = $empty - $full;

        if ($range <= 0) {
            return (float) ($bin->current_fill_percentage ?? 0);
        }

        return max(0, min(100, (($empty - $distance) / $range) * 100));
    }
}
