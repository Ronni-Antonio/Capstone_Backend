<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index(){ return response()->json(Notification::with(['student','smartBin'])->latest()->get()); }
    public function store(Request $request)
    {
        $v=$request->validate([
            'student_id'=>'nullable|exists:students,student_id',
            'smart_bin_id'=>'nullable|exists:smart_bins,smart_bin_id',
            'notification_type'=>'required|string|max:100','title'=>'required|string|max:255',
            'message'=>'required|string','data'=>'nullable|array','is_read'=>'boolean','read_at'=>'nullable|date'
        ]);
        return response()->json(Notification::create($v),201);
    }
    public function show(string $id){ return response()->json(Notification::with(['student','smartBin'])->findOrFail($id)); }
    public function update(Request $request,string $id)
    {
        $n=Notification::findOrFail($id);
        $n->update($request->validate([
            'is_read'=>'sometimes|boolean','read_at'=>'nullable|date',
            'title'=>'sometimes|string|max:255','message'=>'sometimes|string','data'=>'nullable|array'
        ]));
        if($n->is_read && !$n->read_at) $n->update(['read_at'=>now()]);
        return response()->json($n);
    }
    public function destroy(string $id){ Notification::findOrFail($id)->delete(); return response()->json(null,204); }
}
