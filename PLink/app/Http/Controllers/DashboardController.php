<?php

namespace App\Http\Controllers;

use App\Models\RecyclingTransaction;
use App\Models\Students;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $payload = Cache::remember('dashboard.summary.v8', 15, function () {
            $weekStart = now()->subDays(6)->startOfDay();
            $totalStudents = Students::count();

            $participatingStudents = RecyclingTransaction::query()
                ->where('status', 'completed')
                ->whereNotNull('student_id')
                ->distinct('student_id')
                ->count('student_id');

            $completed = RecyclingTransaction::query()->where('status', 'completed');
            $totalItems = (int) (clone $completed)->sum('total_items');
            $totalPoints = (int) (clone $completed)->sum('total_points');

            $sevenDays = collect(range(0, 6))->map(function ($offset) {
                $date = now()->subDays(6 - $offset)->startOfDay();

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('M j'),
                ];
            });

            // Daily waste collection report + collection-trend source.
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

            // Waste category statistics by actual CNN-classified recyclable type.
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
                        'Dirty / Damaged Recyclable Material' => 'Crushed / Dirty',
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

            // User ranking: top five students by points earned from recycling.
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

            // Reward history: latest completed redemptions.
            $rewardHistory = DB::table('redemptions as rd')
                ->join('students as s', 's.student_id', '=', 'rd.student_id')
                ->join('rewards as r', 'r.reward_id', '=', 'rd.reward_id')
                ->whereNotNull('rd.redeemed_at')
                ->select([
                    'rd.redemption_id',
                    'rd.redeemed_at',
                    'rd.points_spent',
                    'r.reward_name',
                    's.student_number',
                    's.first_name',
                    's.last_name',
                ])
                ->orderByDesc('rd.redeemed_at')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'redemption_id' => (int) $row->redemption_id,
                    'redeemed_at' => $row->redeemed_at,
                    'points_spent' => (int) $row->points_spent,
                    'reward_name' => $row->reward_name,
                    'student_number' => $row->student_number,
                    'student_name' => trim($row->first_name . ' ' . $row->last_name),
                ]);

            return [
                'summary' => [
                    'total_students' => (int) $totalStudents,
                    'participating_students' => (int) $participatingStudents,
                    'total_items' => $totalItems,
                    'total_points' => $totalPoints,
                ],
                'daily_recycling' => $dailyRecycling,
                'collection_trends' => $dailyRecycling,
                'waste_categories' => $wasteCategories->values(),
                'top_recyclers' => $topRecyclers,
                'user_ranking' => $topRecyclers,
                'reward_history' => $rewardHistory->values(),
            ];
        });

        return response()->json($payload);
    }
}
