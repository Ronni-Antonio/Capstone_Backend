<?php
namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\SmartBin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
    public function index(){ return response()->json(Collection::with(['smartBin','collectedBy'])->latest('collection_date')->get()); }

    public function store(Request $request)
    {
        $v=$request->validate([
            'smart_bin_id'=>'required|exists:smart_bins,smart_bin_id',
            'collected_by_user_id'=>'nullable|exists:users,id',
            'bottles_collected'=>'required|integer|min:0',
            'weight_kg'=>'required|numeric|min:0',
            'collection_date'=>'nullable|date',
            'notes'=>'nullable|string',
        ]);
        $collection=DB::transaction(function()use($v){
            $c=Collection::create($v+['collection_date'=>$v['collection_date']??now()]);
            $bin=SmartBin::lockForUpdate()->findOrFail($c->smart_bin_id);
            $bin->update(['current_weight_kg'=>0,'status'=>'online']);
            return $c;
        });
        return response()->json($collection->load('smartBin'),201);
    }

    public function show(string $id){ return response()->json(Collection::with(['smartBin','collectedBy'])->findOrFail($id)); }
    public function update(Request $request,string $id){ $c=Collection::findOrFail($id); $c->update($request->validate(['notes'=>'nullable|string','collection_date'=>'nullable|date'])); return response()->json($c); }
    public function destroy(string $id){ Collection::findOrFail($id)->delete(); return response()->json(null,204); }
}
