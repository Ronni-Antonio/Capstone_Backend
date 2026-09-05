<?php
namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\SmartBin;
use App\Models\ActivityLog;
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

        $binName = $collection->smartBin?->name ?? "Smart Bin #{$v['smart_bin_id']}";
        ActivityLog::record(
            'COLLECTION_SCHEDULED',
            "Collection scheduled for {$binName}: {$v['bottles_collected']} bottles, {$v['weight_kg']} kg collected.",
            'Machine',
            $request->user()?->id,
            null,
            ['collection_id' => $collection->collection_id, 'bottles_collected' => $v['bottles_collected'], 'weight_kg' => $v['weight_kg']]
        );

        return response()->json($collection->load('smartBin'),201);
    }

    public function show(string $id){ return response()->json(Collection::with(['smartBin','collectedBy'])->findOrFail($id)); }

    public function update(Request $request,string $id)
    {
        $c=Collection::findOrFail($id);
        $oldNotes = $c->notes;
        $oldDate = $c->collection_date;
        $c->update($request->validate(['notes'=>'nullable|string','collection_date'=>'nullable|date']));

        $changes = [];
        if ($oldNotes !== $c->notes) $changes[] = 'notes';
        if ($oldDate !== $c->collection_date) $changes[] = 'collection_date';
        if (!empty($changes)) {
            $binName = $c->smartBin?->name ?? "Smart Bin #{$c->smart_bin_id}";
            ActivityLog::record(
                'UPDATE_COLLECTION',
                "Collection record updated for {$binName}. Changes: " . implode(', ', $changes),
                'Machine',
                $request->user()?->id,
                null,
                ['collection_id' => $c->collection_id, 'changes' => $changes]
            );
        }
        return response()->json($c);
    }

    public function destroy(string $id)
    {
        $c=Collection::findOrFail($id);
        $binName = $c->smartBin?->name ?? "Smart Bin #{$c->smart_bin_id}";
        $collectionId = $c->collection_id;
        $c->delete();

        ActivityLog::record(
            'DELETE_COLLECTION',
            "Collection record deleted for {$binName}.",
            'Machine',
            request()->user()?->id,
            null,
            ['collection_id' => $collectionId]
        );

        return response()->json(null,204);
    }
}
