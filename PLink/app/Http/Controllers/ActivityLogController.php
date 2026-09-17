<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogController extends Controller
{
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('module')) {
            $query->forModule($request->input('module'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('action', 'like', $term)
                  ->orWhere('description', 'like', $term)
                  ->orWhere('module', 'like', $term);
            });
        }

        if ($request->filled('dateFrom')) {
            $from = $request->input('dateFrom');
            if ($request->filled('dateTo')) {
                $to = $request->input('dateTo');
                $query->whereBetween('created_at', [$from, $to . ' 23:59:59']);
            } else {
                $query->whereDate('created_at', '>=', $from);
            }
        } elseif ($request->filled('dateTo')) {
            $to = $request->input('dateTo');
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->with([
                'user:id,name,email',
                'student:student_id,first_name,last_name,student_number',
            ]);

        $this->applyFilters($query, $request);

        $perPage = $request->input('per_page', 100);
        if ($perPage > 500) $perPage = 500;

        $logs = $query
            ->newestFirst()
            ->paginate($perPage);

        $transformed = $logs->through(function (ActivityLog $log) {
            return [
                'activity_log_id' => $log->activity_log_id,
                'date_time' => $log->created_at?->format('m/d/Y h:i A'),
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at,
                'user_id' => $log->user_id,
                'student_id' => $log->student_id,
                'actor' => $log->actor,
                'actor_type' => $log->actor_type,
                'actor_details' => $log->user
                    ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ]
                    : ($log->student
                        ? [
                            'student_id' => $log->student->student_id,
                            'first_name' => $log->student->first_name,
                            'last_name' => $log->student->last_name,
                            'student_number' => $log->student->student_number,
                        ]
                        : null),
                'action' => $log->action,
                'description' => $log->description,
                'module' => $log->module,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
            ];
        });

        return response()->json([
            'data' => $transformed->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ]);
    }

    public function statistics(Request $request)
    {
        $baseQuery = ActivityLog::query();
        $this->applyFilters($baseQuery, $request);

        $totalActivities = (clone $baseQuery)->count();
        $totalModules = (clone $baseQuery)->distinct('module')->count('module');

        $actionCounts = (clone $baseQuery)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $moduleCounts = (clone $baseQuery)
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->orderBy('module')
            ->pluck('count', 'module')
            ->toArray();

        $added   = 0;
        $updated = 0;
        $deleted = 0;
        foreach ($actionCounts as $action => $count) {
            $upper = strtoupper((string) $action);
            if (str_starts_with($upper, 'ADD_') || str_starts_with($upper, 'CREATE_') || str_contains($upper, '_ADDED')) {
                $added += $count;
            } elseif (str_starts_with($upper, 'UPDATE_') || str_contains($upper, '_UPDATED')) {
                $updated += $count;
            } elseif (str_starts_with($upper, 'DELETE_') || str_contains($upper, '_DELETED')) {
                $deleted += $count;
            }
        }

        $inventoryActivity = ($actionCounts['RESTOCK_INVENTORY'] ?? 0)
            + ($actionCounts['UPDATE_INVENTORY_QTY'] ?? 0);

        $redemptionActivity = ($actionCounts['REDEEM_REWARD'] ?? 0)
            + ($actionCounts['POINTS_DEDUCTED'] ?? 0);

        $pointsAdded   = $actionCounts['POINTS_ADDED'] ?? 0;
        $pointsDeducted = $actionCounts['POINTS_DEDUCTED'] ?? 0;

        $machineActivity = ($actionCounts['ADD_MACHINE'] ?? 0)
            + ($actionCounts['UPDATE_MACHINE'] ?? 0)
            + ($actionCounts['DELETE_MACHINE'] ?? 0)
            + ($actionCounts['COLLECTION_SCHEDULED'] ?? 0)
            + ($actionCounts['UPDATE_COLLECTION'] ?? 0)
            + ($actionCounts['DELETE_COLLECTION'] ?? 0)
            + ($actionCounts['PLASTIC_SCANNED'] ?? 0)
            + ($actionCounts['ASSIGN_RFID_CARD'] ?? 0);

        $userManagement = ($actionCounts['CREATE_USER'] ?? 0)
            + ($actionCounts['UPDATE_USER'] ?? 0)
            + ($actionCounts['DELETE_USER'] ?? 0)
            + ($actionCounts['CREATE_STUDENT'] ?? 0)
            + ($actionCounts['UPDATE_STUDENT'] ?? 0)
            + ($actionCounts['DELETE_STUDENT'] ?? 0)
            + ($actionCounts['PASSWORD_UPDATED'] ?? 0)
            + ($actionCounts['BULK_IMPORT_STUDENTS'] ?? 0)
            + ($actionCounts['ASSIGN_RFID_CARD'] ?? 0);

        $rewardManagement = ($actionCounts['ADD_REWARD'] ?? 0)
            + ($actionCounts['UPDATE_REWARD'] ?? 0)
            + ($actionCounts['DELETE_REWARD'] ?? 0)
            + ($actionCounts['RESTOCK_INVENTORY'] ?? 0)
            + ($actionCounts['UPDATE_INVENTORY_QTY'] ?? 0);

        $recently = now()->subHours(24);
        $last24hBaseQuery = clone $baseQuery;
        $last24HoursCount = $last24hBaseQuery->where('created_at', '>=', $recently)->count();

        return response()->json([
            'total_activities'    => $totalActivities,
            'total_modules'       => $totalModules,
            'last_24h_count'      => $last24HoursCount,

            'total_added'         => $added,
            'total_updated'       => $updated,
            'total_deleted'       => $deleted,

            'added'               => $added,
            'updated'             => $updated,
            'deleted'             => $deleted,
            'totalActivities'     => $totalActivities,

            'inventory_activity'  => $inventoryActivity,
            'redemption_activity' => $redemptionActivity,
            'points_added_count'  => $pointsAdded,
            'points_deducted_count' => $pointsDeducted,
            'machine_activity'    => $machineActivity,
            'user_management'     => $userManagement,
            'reward_management'   => $rewardManagement,

            'action_breakdown'    => $actionCounts,
            'module_breakdown'    => $moduleCounts,
        ]);
    }
}
