<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\RecyclingTransaction;
use App\Models\RecyclingItem;
use App\Models\AiClassification;
use App\Models\AiModel;
use App\Models\PlasticType;
use App\Models\Students;
use App\Models\RfidCard;
use App\Models\SmartBin;
use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // List screens only need transaction totals and student identity.
        // Full item/classification relationships remain available from show().
        if ($request->boolean('summary', true)) {
            return response()->json(
                RecyclingTransaction::query()
                    ->select([
                        'transaction_id','transaction_code','student_id','smart_bin_id',
                        'status','total_items','total_points','started_at','completed_at','created_at'
                    ])
                    ->with([
                        'student:student_id,first_name,last_name,grade_level_id,section_id',
                        'student.section:section_id,name',
                        'smartBin:smart_bin_id,name,location',
                    ])
                    ->latest('started_at')
                    ->limit(500)
                    ->get()
            );
        }

        return response()->json(
            RecyclingTransaction::with([
                'student.gradeLevel','student.section','rfidCard',
                'smartBin','items.classification.plasticType','items.classification.model'
            ])->latest('started_at')->paginate(50)
        );
    }

    public function show(string $id)
    {
        $tx=RecyclingTransaction::with([
            'student.gradeLevel','student.section','rfidCard',
            'smartBin','items.classification.plasticType','items.classification.model'
        ])->findOrFail($id);
        return response()->json($tx);
    }

    /**
     * Start a transaction when the ESP32-CAM begins a recycling event.
     * Student is intentionally unknown until RFID is tapped.
     */
    public function start(Request $request)
    {
        $validated=$request->validate([
            'smart_bin_id'=>'required|exists:smart_bins,smart_bin_id',
        ]);
        $tx=RecyclingTransaction::create([
            'smart_bin_id'=>$validated['smart_bin_id'],
            'transaction_code'=>(string)Str::uuid(),
            'status'=>'classifying',
            'started_at'=>now(),
        ]);
        SmartBin::whereKey($validated['smart_bin_id'])->update(['last_active_at'=>now()]);
        return response()->json(['success'=>true,'transaction'=>$tx],201);
    }

    /**
     * CNN service sends one or more classified bottles before RFID.
     */
    public function addClassification(Request $request,string $transactionCode)
    {
        $validated=$request->validate([
            'item_number'=>'required|integer|min:1',
            'image_path'=>'nullable|string|max:255',
            'weight_kg'=>'nullable|numeric|min:0',
            'plastic_type_id'=>'nullable|exists:plastic_types,plastic_type_id',
            'model_id'=>'nullable|exists:ai_models,model_id',
            'model_name'=>'nullable|string|max:255',
            'model_version'=>'nullable|string|max:100',
            'confidence_score'=>'nullable|numeric|min:0|max:100',
            'status'=>'required|in:valid,invalid,uncertain,rejected,manually_verified',
            'notes'=>'nullable|string',
        ]);

        $tx=RecyclingTransaction::where('transaction_code',$transactionCode)->firstOrFail();
        if(in_array($tx->status,['completed','cancelled','rejected'],true)){
            return response()->json(['error'=>'Transaction is already closed.'],409);
        }

        $item=DB::transaction(function() use($tx,$validated){
            $item=RecyclingItem::updateOrCreate(
                ['transaction_id'=>$tx->transaction_id,'item_number'=>$validated['item_number']],
                [
                    'image_path'=>$validated['image_path']??null,
                    'weight_kg'=>$validated['weight_kg']??null,
                    'status'=>$validated['status']==='valid'?'accepted':$validated['status'],
                ]
            );

            $modelId=$validated['model_id']??null;
            if(!$modelId && !empty($validated['model_name']) && !empty($validated['model_version'])){
                $model=AiModel::firstOrCreate(
                    ['name'=>$validated['model_name'],'version'=>$validated['model_version']],
                    ['framework'=>'TensorFlow/Keras','is_active'=>true]
                );
                $modelId=$model->model_id;
            }

            AiClassification::updateOrCreate(
                ['recycling_item_id'=>$item->recycling_item_id],
                [
                    'plastic_type_id'=>$validated['plastic_type_id']??null,
                    'model_id'=>$modelId,
                    'confidence_score'=>$validated['confidence_score']??null,
                    'status'=>$validated['status'],
                    'notes'=>$validated['notes']??null,
                    'is_verified'=>$validated['status']==='manually_verified',
                    'classified_at'=>now(),
                ]
            );

            $tx->update([
                'status'=>'waiting_for_rfid',
                'total_items'=>$tx->items()->count(),
            ]);
            return $item;
        });

        return response()->json([
            'success'=>true,
            'transaction_code'=>$tx->transaction_code,
            'item'=>$item->load('classification.plasticType','classification.model')
        ],201);
    }

    /**
     * RFID reader identifies the student and completes the transaction.
     * Points are calculated by Laravel, not by the CNN.
     */
    public function completeWithRfid(Request $request,string $transactionCode)
    {
        $validated=$request->validate(['card_uid'=>'required|string|max:100']);

        $result=DB::transaction(function() use($transactionCode,$validated){
            $tx=RecyclingTransaction::with('items.classification.plasticType')
                ->where('transaction_code',$transactionCode)->lockForUpdate()->firstOrFail();

            if($tx->status==='completed'){
                return ['already_completed'=>true,'transaction'=>$tx->load('student','rfidCard')];
            }

            $card=RfidCard::with('student')->where('card_uid',strtoupper(trim($validated['card_uid'])))
                ->where('status','active')->lockForUpdate()->first();

            if(!$card) throw new \RuntimeException('Unrecognized or inactive RFID card.');

            $student=Students::lockForUpdate()->findOrFail($card->student_id);
            $items=$tx->items()->with('classification.plasticType')->get();

            $totalPoints=0;
            foreach($items as $item){
                $classification=$item->classification;
                if(!$classification || $classification->status!=='valid') continue;
                $type=$classification->plasticType;
                if($type && $type->is_accepted) $totalPoints += (int)$type->points_value;
            }

            $tx->update([
                'student_id'=>$student->student_id,
                'rfid_card_id'=>$card->rfid_card_id,
                'total_items'=>$items->count(),
                'total_points'=>$totalPoints,
                'status'=>'completed',
                'completed_at'=>now(),
            ]);

            if($totalPoints!==0){
                PointTransaction::create([
                    'student_id'=>$student->student_id,
                    'recycling_transaction_id'=>$tx->transaction_id,
                    'points'=>$totalPoints,
                    'transaction_type'=>'earned',
                    'description'=>'Points earned from recycling transaction '.$tx->transaction_code,
                ]);
                $student->increment('points_balance',$totalPoints);
            }

            return [
                'already_completed'=>false,
                'transaction'=>$tx->load('student','rfidCard','smartBin','items.classification.plasticType'),
                'student'=>$student,
                'itemsCount'=>$items->count(),
                'totalPoints'=>$totalPoints,
            ];
        });

        if (empty($result['already_completed'])) {
            $student = $result['student'];
            $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: 'Student';
            $itemsCount = $result['itemsCount'];
            $totalPoints = $result['totalPoints'];

            ActivityLog::record(
                'PLASTIC_SCANNED',
                "Student {$studentName} completed recycling {$itemsCount} plastic/bottle item(s) via smart bin.",
                'Machine',
                null,
                $student->student_id
            );

            if ($totalPoints > 0) {
                ActivityLog::record(
                    'POINTS_ADDED',
                    "{$studentName} earned {$totalPoints} points from recycling.",
                    'Points',
                    null,
                    $student->student_id
                );
            }
        }

        return response()->json(['success'=>true]+$result,200);
    }

    /**
     * Backward-compatible endpoint for React. Prefer start + addClassification + completeWithRfid.
     * This accepts a full classification payload but still keeps student association through RFID when supplied.
     */
    public function processTransaction(Request $request)
    {
        $validated=$request->validate([
            'student_id'=>'nullable|exists:students,student_id',
            'card_uid'=>'nullable|string|max:100',
            'smart_bin_id'=>'required|exists:smart_bins,smart_bin_id',
            'bottles'=>'required|array|min:1',
            'bottles.*.item_number'=>'nullable|integer|min:1',
            'bottles.*.plastic_type_id'=>'nullable|exists:plastic_types,plastic_type_id',
            'bottles.*.status'=>'required|in:valid,invalid,uncertain,rejected,manually_verified',
            'bottles.*.confidence_score'=>'nullable|numeric|min:0|max:100',
            'bottles.*.image_path'=>'nullable|string',
            'bottles.*.weight_kg'=>'nullable|numeric|min:0',
            'bottles.*.model_id'=>'nullable|exists:ai_models,model_id',
            'bottles.*.model_name'=>'nullable|string',
            'bottles.*.model_version'=>'nullable|string',
            'bottles.*.notes'=>'nullable|string',
        ]);

        $tx=$this->start(new Request(['smart_bin_id'=>$validated['smart_bin_id']]))->getData(true);
        $code=$tx['transaction']['transaction_code'];

        foreach($validated['bottles'] as $i=>$bottle){
            $payload=$bottle;
            $payload['item_number']=$bottle['item_number']??($i+1);
            $this->addClassification(new Request($payload),$code);
        }

        if(!empty($validated['card_uid'])){
            return $this->completeWithRfid(new Request(['card_uid'=>$validated['card_uid']]),$code);
        }

        if(!empty($validated['student_id'])){
            // Explicit student_id is accepted only for trusted/admin use.
            $student=Students::findOrFail($validated['student_id']);
            $card=$student->rfidCards()->where('status','active')->first();
            if(!$card) return response()->json(['success'=>true,'message'=>'Classification saved; waiting for RFID.','transaction_code'=>$code],201);
            return $this->completeWithRfid(new Request(['card_uid'=>$card->card_uid]),$code);
        }

        return response()->json([
            'success'=>true,'message'=>'Classification saved; waiting for RFID.',
            'transaction_code'=>$code
        ],201);
    }

    public function destroy(string $id)
    {
        $tx=RecyclingTransaction::findOrFail($id);
        if($tx->status==='completed') return response()->json(['error'=>'Completed transactions cannot be deleted.'],409);
        $tx->delete();
        return response()->json(null,204);
    }
}
