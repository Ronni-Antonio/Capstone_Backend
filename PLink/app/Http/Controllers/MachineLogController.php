<?php
namespace App\Http\Controllers;

use App\Models\SmartBinLog;
use Illuminate\Http\Request;

class MachineLogController extends Controller
{
    public function index()
    {
        return response()->json(SmartBinLog::with('smartBin')->latest()->get());
    }
    public function store(Request $request)
    {
        $v = $request->validate([
            'smart_bin_id' => 'required|exists:smart_bins,smart_bin_id',
            'distance_cm' => 'required|integer',
            'fill_percentage' => 'required|integer',
            'status' => 'required|string|max:100',
        ]);
        return response()->json(SmartBinLog::create($v), 201);
    }
    public function show(string $id)
    {
        return response()->json(SmartBinLog::with('smartBin')->findOrFail($id));
    }
    public function update(Request $request, string $id)
    {
        $log = SmartBinLog::findOrFail($id);
        $log->update($request->validate(['distance_cm' => 'sometimes|integer', 'fill_percentage' => 'sometimes|integer', 'status' => 'sometimes|string|max:100']));
        return response()->json($log);
    }
    public function destroy(string $id)
    {
        SmartBinLog::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
