<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\SmartBin;
use App\Models\SmartBinCompartment;
use App\Models\SmartBinCompartmentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MachineController extends Controller
{
    /**
     * Machine Monitoring payload.
     *
     * A Smart Bin may contain multiple physical compartments. Each compartment
     * owns its own HC-SR04 distance, thresholds, and fullness percentage.
     */
    public function index()
    {
        $bins = SmartBin::query()
            ->with(['compartments' => fn ($query) => $query
                ->orderByRaw("FIELD(material_category, 'plastic', 'paper')")
                ->orderBy('compartment_id')])
            ->select([
                'smart_bin_id',
                'name',
                'location',
                'status',
                // Legacy overall fields are retained for dashboard/backward compatibility.
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
        $tomorrowStart = now()->copy()->addDay()->startOfDay();

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

        // Accepted items by material category (plastic / paper) for today's UI.
        $acceptedByMaterial = DB::table('recycling_transactions as rt')
            ->join('recycling_items as ri', 'ri.transaction_id', '=', 'rt.transaction_id')
            ->join('ai_classifications as ac', 'ac.recycling_item_id', '=', 'ri.recycling_item_id')
            ->leftJoin('recyclable_types as type', 'type.recyclable_type_id', '=', 'ac.recyclable_type_id')
            ->where('rt.status', 'completed')
            ->where('ri.status', 'accepted')
            ->where('rt.started_at', '>=', $todayStart)
            ->where('rt.started_at', '<', $tomorrowStart)
            ->select('rt.smart_bin_id')
            ->selectRaw("COALESCE(type.material_category, 'other') as material_category")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('rt.smart_bin_id', 'type.material_category')
            ->get()
            ->groupBy('smart_bin_id');

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

        $payload = $bins->map(function ($bin) use ($dailyStats, $acceptedByMaterial, $rejectedHourly) {
            $stats = $dailyStats->get($bin->smart_bin_id);
            $accepted = (int) ($stats->accepted_today ?? 0);
            $rejected = (int) ($stats->rejected_today ?? 0);
            $classified = $accepted + $rejected;

            $byMaterial = collect($acceptedByMaterial->get($bin->smart_bin_id, collect()))
                ->mapWithKeys(fn ($row) => [
                    $row->material_category => (int) $row->count,
                ]);

            $hourly = collect($rejectedHourly->get($bin->smart_bin_id, collect()))
                ->map(fn ($row) => [
                    'hour' => (int) $row->hour,
                    'count' => (int) $row->count,
                ])
                ->values();

            $compartments = $bin->compartments->map(function ($compartment) {
                $fill = $this->calculateCompartmentFillPercentage($compartment);

                return array_merge($compartment->toArray(), [
                    'current_fill_percentage' => (int) round($fill),
                    'fill_state' => $this->fillState($fill),
                ]);
            })->values();

            return array_merge($bin->withoutRelations()->toArray(), [
                'compartments' => $compartments,
                'today_stats' => [
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'total_classified' => $classified,
                    'acceptance_rate' => $classified > 0
                        ? round(($accepted / $classified) * 100, 1)
                        : 0,
                    'accepted_by_material' => [
                        'plastic' => (int) ($byMaterial->get('plastic', 0)),
                        'paper' => (int) ($byMaterial->get('paper', 0)),
                        'other' => (int) ($byMaterial->get('other', 0)),
                    ],
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
        return response()->json(
            SmartBin::with('compartments')->findOrFail($id)
        );
    }

    public function update(Request $request, string $id)
    {
        $bin = SmartBin::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:online,offline,maintenance,full',
            'last_maintenance_at' => 'nullable|date',
            'last_active_at' => 'nullable|date',
        ]);

        $bin->update($validated);
        return response()->json($bin->fresh('compartments'));
    }

    /**
     * Receive one HC-SR04 reading for one physical compartment.
     * Example: PATCH /api/machines/1/compartments/1/sensor
     * body: { "distance_cm": 42 }
     */
    public function updateCompartmentSensor(Request $request, string $machine, string $compartment)
    {
        $bin = SmartBin::findOrFail($machine);
        $target = SmartBinCompartment::query()
            ->where('smart_bin_id', $bin->smart_bin_id)
            ->where('compartment_id', $compartment)
            ->firstOrFail();

        $validated = $request->validate([
            'distance_cm' => 'required|integer|min:0|max:1000',
            'full_threshold_cm' => 'sometimes|integer|min:0|max:1000',
            'empty_threshold_cm' => 'sometimes|integer|min:1|max:1000',
        ]);

        $oldFill = $this->calculateCompartmentFillPercentage($target);
        $full = $validated['full_threshold_cm'] ?? $target->full_threshold_cm;
        $empty = $validated['empty_threshold_cm'] ?? $target->empty_threshold_cm;

        if ($empty <= $full) {
            return response()->json([
                'message' => 'The empty threshold must be greater than the full threshold.',
            ], 422);
        }

        $fill = $this->calculateFill(
            (float) $validated['distance_cm'],
            (float) $empty,
            (float) $full
        );

        $compartmentStatus = $fill >= 100 ? 'full' : 'online';

        $target->update([
            'current_distance_cm' => $validated['distance_cm'],
            'current_fill_percentage' => (int) round($fill),
            'full_threshold_cm' => $full,
            'empty_threshold_cm' => $empty,
            'status' => $compartmentStatus,
            'last_active_at' => now(),
        ]);

        SmartBinCompartmentLog::create([
            'compartment_id' => $target->compartment_id,
            'distance_cm' => $validated['distance_cm'],
            'fill_percentage' => (int) round($fill),
            'status' => $this->fillState($fill),
        ]);

        // Keep the legacy SmartBin overall fields synchronized with the fullest
        // compartment so existing dashboard cards continue to work.
        $freshCompartments = $bin->compartments()->get();
        $fullest = $freshCompartments->sortByDesc(
            fn ($c) => $this->calculateCompartmentFillPercentage($c)
        )->first();
        $overallFill = $fullest
            ? (int) round($this->calculateCompartmentFillPercentage($fullest))
            : 0;

        $bin->update([
            'current_fill_percentage' => $overallFill,
            'current_distance_cm' => $fullest?->current_distance_cm ?? $bin->current_distance_cm,
            'status' => $overallFill >= 100 ? 'full' : ($bin->status === 'offline' ? 'offline' : 'online'),
            'last_active_at' => now(),
        ]);

        if ($fill >= 100 && $oldFill < 100) {
            Notification::create([
                'smart_bin_id' => $bin->smart_bin_id,
                'notification_type' => 'machine_full',
                'title' => $target->name . ' is full',
                'message' => "{$target->name} in {$bin->name} at {$bin->location} has reached full capacity.",
                'data' => [
                    'compartment_id' => $target->compartment_id,
                    'material_category' => $target->material_category,
                    'capacity_percentage' => (int) round($fill),
                ],
            ]);
        }

        return response()->json([
            'message' => 'Compartment sensor updated.',
            'compartment' => array_merge($target->fresh()->toArray(), [
                'fill_state' => $this->fillState($fill),
            ]),
            'smart_bin' => $bin->fresh(),
        ]);
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

    private function calculateCompartmentFillPercentage(SmartBinCompartment $compartment): float
    {
        return $this->calculateFill(
            (float) ($compartment->current_distance_cm ?? $compartment->empty_threshold_cm),
            (float) $compartment->empty_threshold_cm,
            (float) $compartment->full_threshold_cm,
        );
    }

    private function calculateFill(float $distance, float $empty, float $full): float
    {
        $range = $empty - $full;
        if ($range <= 0) {
            return 0;
        }

        return max(0, min(100, (($empty - $distance) / $range) * 100));
    }

    private function fillState(float $fill): string
    {
        if ($fill >= 100) return 'full';
        if ($fill >= 80) return 'almost_full';
        return 'normal';
    }
}
