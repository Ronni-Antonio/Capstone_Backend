<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $notifications = Notification::with('student')->get();
        return response()->json($notifications);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'nullable|exists:students,student_id',
            'notification_type' => 'required|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'is_read' => 'boolean|default:false',
            'read_at' => 'nullable|date'
        ]);

        $notification = Notification::create($validated);
        $notification->load('student');
        return response()->json($notification, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $notification = Notification::with('student')->findOrFail($id);
        return response()->json($notification);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $notification = Notification::findOrFail($id);
        $validated = $request->validate([
            'student_id' => 'nullable|exists:students,student_id',
            'notification_type' => 'string',
            'title' => 'string',
            'message' => 'string',
            'data' => 'nullable|array',
            'is_read' => 'boolean',
            'read_at' => 'nullable|date'
        ]);

        $notification->update($validated);
        $notification->load('student');
        return response()->json($notification);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();
        return response()->json(null, 204);
    }
}

