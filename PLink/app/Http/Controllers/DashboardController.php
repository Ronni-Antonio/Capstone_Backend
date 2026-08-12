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
        // Keep the dashboard fast while still allowing near-real-time updates.
        $payload = Cache::remember('dashboard.summary.v3', 15, function () {
            $totalStudents = Students::count();
            $activeStudents = Students::where('status', 'active')->count();

            $completed = RecyclingTransaction::query()->where('status', 'completed');
            $totalItems = (int) (clone $completed)->sum('total_items');
            $totalPoints = (int) (clone $completed)->sum('total_points');

            $grade3Participants = Students::query()
                ->whereHas('gradeLevel', fn ($q) => $q->where('name', 'Grade 3'))
                ->count();

            $weekStart = now()->subDays(6)->startOfDay();

            $dailyRecycling = RecyclingTransaction::query()
                ->where('status', 'completed')
                ->where('started_at', '>=', $weekStart)
                ->selectRaw('DATE(started_at) as date')
                ->selectRaw('SUM(total_items) as items')
                ->selectRaw('SUM(total_points) as points')
                ->groupByRaw('DATE(started_at)')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'items' => (int) $row->items,
                    'points' => (int) $row->points,
                ]);

            $recentActivity = RecyclingTransaction::query()
                ->where('status', 'completed')
                ->with('student:student_id,first_name,last_name')
                ->latest('completed_at')
                ->limit(5)
                ->get([
                    'transaction_id', 'student_id', 'total_items',
                    'total_points', 'completed_at',
                ])
                ->map(fn ($tx) => [
                    'transaction_id' => $tx->transaction_id,
                    'student_id' => $tx->student_id,
                    'student_name' => trim(($tx->student?->first_name ?? '') . ' ' . ($tx->student?->last_name ?? '')) ?: 'Unknown Student',
                    'total_items' => (int) $tx->total_items,
                    'total_points' => (int) $tx->total_points,
                    'completed_at' => optional($tx->completed_at)->toISOString(),
                ]);

            /*
             * Weekly bottle collection for EVERY section.
             *
             * The previous optimized endpoint returned only the top section, which
             * is why the dashboard chart showed one bar. This LEFT JOIN starts from
             * sections so even a section with zero activity this week is returned.
             */
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

            $topSection = $sectionStats
                ->sortByDesc('total_items')
                ->first();

            $smartBin = SmartBin::query()
                ->select([
                    'smart_bin_id', 'name', 'location', 'status',
                    'current_distance_cm', 'current_fill_percentage',
                    'full_threshold_cm', 'empty_threshold_cm', 'last_active_at',
                ])
                ->first();

            return [
                'summary' => [
                    'total_students' => $totalStudents,
                    'active_students' => $activeStudents,
                    'inactive_students' => max(0, $totalStudents - $activeStudents),
                    'total_items' => $totalItems,
                    'total_points' => $totalPoints,
                    'grade_3_participants' => $grade3Participants,
                ],
                'smart_bin' => $smartBin,
                'section_stats' => $sectionStats->values(),
                'top_section' => $topSection ?: null,
                'daily_recycling' => $dailyRecycling,
                'recent_activity' => $recentActivity,
            ];
        });

        return response()->json($payload);
    }
}
