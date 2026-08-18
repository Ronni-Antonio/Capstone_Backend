<?php

namespace App\Http\Controllers;

use App\Models\RecyclingTransaction;
use App\Models\SmartBin;
use App\Models\Students;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // A short cache keeps the dashboard fast without making IoT/transaction
        // changes feel stale for long. Bump the key whenever the payload changes.
        $payload = Cache::remember('dashboard.summary.v7', 15, function () {
            $weekStart = now()->subDays(6)->startOfDay();
            $monthStart = now()->subDays(29)->startOfDay();

            $totalStudents = Students::count();

            // "Participating" means the student has completed at least one
            // recycling transaction. This is more meaningful than account status.
            $participatingStudents = RecyclingTransaction::query()
                ->where('status', 'completed')
                ->whereNotNull('student_id')
                ->distinct('student_id')
                ->count('student_id');

            $completed = RecyclingTransaction::query()->where('status', 'completed');
            $totalItems = (int) (clone $completed)->sum('total_items');
            $totalPoints = (int) (clone $completed)->sum('total_points');

            // -----------------------------------------------------------------
            // Reusable seven-day date skeleton. It guarantees that graphs show
            // all seven days even when a day has zero transactions/redemptions.
            // -----------------------------------------------------------------
            $sevenDays = collect(range(0, 6))->map(function ($offset) {
                $date = now()->subDays(6 - $offset)->startOfDay();

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('M j'),
                ];
            });

            // -----------------------------------------------------------------
            // Daily waste collection (all accepted/rejected items represented by
            // completed transaction totals). This is intentionally material-neutral
            // now that the bin accepts both plastic and paper.
            // -----------------------------------------------------------------
            $dailyRaw = RecyclingTransaction::query()
                ->where('status', 'completed')
                ->where('started_at', '>=', $weekStart)
                ->selectRaw('DATE(started_at) as date')
                ->selectRaw('SUM(total_items) as items')
                ->selectRaw('SUM(total_points) as points')
                ->groupByRaw('DATE(started_at)')
                ->get()
                ->keyBy('date');

            $dailyRecycling = $sevenDays->map(function ($day) use ($dailyRaw) {
                $row = $dailyRaw->get($day['date']);

                return [
                    'date' => $day['date'],
                    'items' => (int) ($row->items ?? 0),
                    'points' => (int) ($row->points ?? 0),
                ];
            });

            // -----------------------------------------------------------------
            // Student participation trend: unique students who completed at least
            // one recycling transaction on each day during the last seven days.
            // -----------------------------------------------------------------
            $participationRaw = RecyclingTransaction::query()
                ->where('status', 'completed')
                ->where('started_at', '>=', $weekStart)
                ->whereNotNull('student_id')
                ->selectRaw('DATE(started_at) as date')
                ->selectRaw('COUNT(DISTINCT student_id) as participants')
                ->groupByRaw('DATE(started_at)')
                ->get()
                ->keyBy('date');

            $participationTrend = $sevenDays->map(function ($day) use ($participationRaw) {
                return [
                    'date' => $day['date'],
                    'participants' => (int) ($participationRaw->get($day['date'])->participants ?? 0),
                ];
            });

            // -----------------------------------------------------------------
            // Recyclable type statistics. Group by the actual CNN-classified
            // recyclable type (PET, HDPE, PP, Paper, etc.) instead of collapsing
            // everything into only Plastic and Paper.
            // -----------------------------------------------------------------
            $wasteCategories = DB::table('recycling_items as ri')
                ->join('recycling_transactions as rt_tx', 'rt_tx.transaction_id', '=', 'ri.transaction_id')
                ->join('ai_classifications as ac', 'ac.recycling_item_id', '=', 'ri.recycling_item_id')
                ->join('recyclable_types as rtype', 'rtype.recyclable_type_id', '=', 'ac.recyclable_type_id')
                ->where('rt_tx.status', 'completed')
                ->where('ri.status', 'accepted')
                ->where('rtype.is_accepted', true)
                ->select([
                    'rtype.recyclable_type_id',
                    'rtype.code',
                    'rtype.name',
                    'rtype.material_category',
                ])
                ->selectRaw('COUNT(*) as total_items')
                ->groupBy(
                    'rtype.recyclable_type_id',
                    'rtype.code',
                    'rtype.name',
                    'rtype.material_category'
                )
                ->orderByDesc('total_items')
                ->get()
                ->map(function ($row) {
                    $label = match ($row->name) {
                        'PET Bottle' => 'PET',
                        'HDPE Bottle' => 'HDPE',
                        'PVC Bottle' => 'PVC',
                        'LDPE Bottle' => 'LDPE',
                        'PP Bottle' => 'PP',
                        'PS Bottle' => 'PS',
                        'PC Bottle' => 'PC',
                        'PLA Plastic' => 'PLA',
                        'White Paper' => 'Paper',
                        'Contaminated PET Bottle' => 'Contaminated PET',
                        default => $row->name,
                    };

                    return [
                        'recyclable_type_id' => (int) $row->recyclable_type_id,
                        'code' => $row->code,
                        'name' => $row->name,
                        'label' => $label,
                        'material_category' => $row->material_category,
                        'total_items' => (int) $row->total_items,
                    ];
                });

            // -----------------------------------------------------------------
            // Weekly waste collection for every section.
            // -----------------------------------------------------------------
            $weeklySectionActivity = RecyclingTransaction::query()
                ->join('students', 'students.student_id', '=', 'recycling_transactions.student_id')
                ->where('recycling_transactions.status', 'completed')
                ->where('recycling_transactions.started_at', '>=', $weekStart)
                ->select('students.section_id')
                ->selectRaw('SUM(recycling_transactions.total_items) as total_items')
                ->selectRaw('SUM(recycling_transactions.total_points) as total_points')
                ->groupBy('students.section_id');

            $sectionStats = DB::table('sections')
                ->leftJoinSub($weeklySectionActivity, 'weekly_activity', function ($join) {
                    $join->on('sections.section_id', '=', 'weekly_activity.section_id');
                })
                ->select('sections.section_id', 'sections.name')
                ->selectRaw('COALESCE(weekly_activity.total_items, 0) as total_items')
                ->selectRaw('COALESCE(weekly_activity.total_points, 0) as total_points')
                ->orderBy('sections.name')
                ->get()
                ->map(fn ($row) => [
                    'section_id' => (int) $row->section_id,
                    'name' => $row->name,
                    'total_items' => (int) $row->total_items,
                    'total_points' => (int) $row->total_points,
                ]);

            $topSection = $sectionStats->sortByDesc('total_items')->first();

            // -----------------------------------------------------------------
            // Top five recyclers by POINTS EARNED, not by current balance. Current
            // balance can decrease after reward redemptions and is not a ranking of
            // recycling performance.
            // -----------------------------------------------------------------
            $topRecyclers = RecyclingTransaction::query()
                ->join('students', 'students.student_id', '=', 'recycling_transactions.student_id')
                ->where('recycling_transactions.status', 'completed')
                ->select([
                    'students.student_id',
                    'students.student_number',
                    'students.first_name',
                    'students.last_name',
                ])
                ->selectRaw('SUM(recycling_transactions.total_points) as points_earned')
                ->selectRaw('SUM(recycling_transactions.total_items) as total_items')
                ->groupBy(
                    'students.student_id',
                    'students.student_number',
                    'students.first_name',
                    'students.last_name'
                )
                ->orderByDesc('points_earned')
                ->orderByDesc('total_items')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'student_id' => (int) $row->student_id,
                    'student_number' => $row->student_number,
                    'student_name' => trim($row->first_name . ' ' . $row->last_name),
                    'points_earned' => (int) $row->points_earned,
                    'total_items' => (int) $row->total_items,
                ]);

            // -----------------------------------------------------------------
            // Recent reward redemption graph: seven-day frequency + points spent.
            // -----------------------------------------------------------------
            $redemptionRaw = DB::table('redemptions')
                ->whereNotNull('redeemed_at')
                ->where('redeemed_at', '>=', $weekStart)
                ->selectRaw('DATE(redeemed_at) as date')
                ->selectRaw('COUNT(*) as redemptions')
                ->selectRaw('COALESCE(SUM(points_spent), 0) as points_spent')
                ->groupByRaw('DATE(redeemed_at)')
                ->get()
                ->keyBy('date');

            $rewardRedemptionsTrend = $sevenDays->map(function ($day) use ($redemptionRaw) {
                $row = $redemptionRaw->get($day['date']);

                return [
                    'date' => $day['date'],
                    'redemptions' => (int) ($row->redemptions ?? 0),
                    'points_spent' => (int) ($row->points_spent ?? 0),
                ];
            });

            // Which rewards have been redeemed most during the recent 30-day window.
            $rewardBreakdown = DB::table('redemptions')
                ->join('rewards', 'rewards.reward_id', '=', 'redemptions.reward_id')
                ->whereNotNull('redemptions.redeemed_at')
                ->where('redemptions.redeemed_at', '>=', $monthStart)
                ->select('rewards.reward_id', 'rewards.reward_name')
                ->selectRaw('COUNT(*) as redemption_count')
                ->selectRaw('COALESCE(SUM(redemptions.points_spent), 0) as points_spent')
                ->groupBy('rewards.reward_id', 'rewards.reward_name')
                ->orderByDesc('redemption_count')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'reward_id' => (int) $row->reward_id,
                    'reward_name' => $row->reward_name,
                    'redemption_count' => (int) $row->redemption_count,
                    'points_spent' => (int) $row->points_spent,
                ]);

            // -----------------------------------------------------------------
            // Smart bin + BOTH physical compartment fullness values.
            // -----------------------------------------------------------------
            $smartBin = SmartBin::query()
                ->with(['compartments' => function ($query) {
                    $query->select([
                        'compartment_id', 'smart_bin_id', 'name',
                        'material_category', 'status', 'current_distance_cm',
                        'current_fill_percentage', 'full_threshold_cm',
                        'empty_threshold_cm', 'last_active_at',
                    ])->orderBy('compartment_id');
                }])
                ->select([
                    'smart_bin_id', 'name', 'location', 'status', 'last_active_at',
                ])
                ->first();

            return [
                'summary' => [
                    'total_students' => (int) $totalStudents,
                    'participating_students' => (int) $participatingStudents,
                    'total_items' => $totalItems,
                    'total_points' => $totalPoints,
                ],
                'smart_bin' => $smartBin,
                'waste_categories' => $wasteCategories->values(),
                'participation_trend' => $participationTrend,
                'section_stats' => $sectionStats->values(),
                'top_section' => $topSection ?: null,
                'top_recyclers' => $topRecyclers,
                // Backward-compatible alias for older dashboard code.
                'user_ranking' => $topRecyclers,
                'daily_recycling' => $dailyRecycling,
                'reward_redemptions_trend' => $rewardRedemptionsTrend,
                'reward_breakdown' => $rewardBreakdown,
            ];
        });

        return response()->json($payload);
    }
}
