<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->with([
                'user:id,name,email',
                'student:student_id,first_name,last_name,student_number',
            ]);

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
}
