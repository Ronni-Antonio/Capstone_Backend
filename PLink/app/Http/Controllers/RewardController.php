<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rewards;

class RewardController extends Controller
{
    public function index(){ return response()->json(Rewards::where('is_active',true)->get()); }
    public function store(Request $request)
    {
        $v=$request->validate([
            'reward_name'=>'required|string|max:255','points_cost'=>'required|integer|min:1',
            'stock_quantity'=>'required|integer|min:0','is_active'=>'boolean'
        ]);
        return response()->json(Rewards::create($v),201);
    }
    public function show(string $id){ return response()->json(Rewards::findOrFail($id)); }
    public function update(Request $request,string $id)
    {
        $r=Rewards::findOrFail($id);
        $r->update($request->validate([
            'reward_name'=>'sometimes|string|max:255','points_cost'=>'sometimes|integer|min:1',
            'stock_quantity'=>'sometimes|integer|min:0','is_active'=>'sometimes|boolean'
        ]));
        return response()->json($r);
    }
    public function destroy(string $id){ $r=Rewards::findOrFail($id); if($r->redemptions()->exists()) return response()->json(['error'=>'Reward has redemption history. Deactivate it instead.'],409); $r->delete(); return response()->json(null,204); }
}
