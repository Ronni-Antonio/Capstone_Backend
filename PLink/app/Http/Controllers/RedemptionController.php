<?php
namespace App\Http\Controllers;

use App\Models\Students;
use App\Models\RfidCard;
use App\Models\Rewards;
use App\Models\Redemptions;
use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedemptionController extends Controller
{
    public function index(){ return response()->json(Redemptions::with(['student','reward'])->latest('redeemed_at')->get()); }
    public function show(string $id){ return response()->json(Redemptions::with(['student','reward'])->findOrFail($id)); }

    public function store(Request $request)
    {
        $v=$request->validate([
            'student_id'=>'required|exists:students,student_id',
            'card_uid'=>'required|string|max:100',
            'reward_id'=>'nullable|exists:rewards,reward_id',
            'reward_ids'=>'nullable|array|min:1',
            'reward_ids.*'=>'integer|exists:rewards,reward_id',
        ]);
        $rewardIds=$v['reward_ids']??(isset($v['reward_id'])?[(int)$v['reward_id']]:[]);
        if(!$rewardIds) return response()->json(['error'=>'Provide reward_id or reward_ids.'],422);

        $result=DB::transaction(function()use($v,$rewardIds){
            $student=Students::lockForUpdate()->findOrFail($v['student_id']);
            $card=RfidCard::where('student_id',$student->student_id)->where('card_uid',strtoupper(trim($v['card_uid'])))->where('status','active')->first();
            if(!$card) throw new \RuntimeException('RFID card does not belong to this student.');

            $counts=array_count_values(array_map('intval',$rewardIds));
            $rewards=Rewards::whereIn('reward_id',array_keys($counts))->lockForUpdate()->get()->keyBy('reward_id');
            $total=0;
            foreach($counts as $rid=>$qty){
                $r=$rewards->get($rid);
                if(!$r || $r->stock_quantity<$qty) throw new \RuntimeException("Insufficient stock for reward {$rid}.");
                $total += $r->points_cost*$qty;
            }
            if($student->points_balance<$total) throw new \RuntimeException('Insufficient points balance.');

            $group=(string)Str::uuid(); $created=[];
            foreach($counts as $rid=>$qty){
                $r=$rewards[$rid];
                for($i=0;$i<$qty;$i++){
                    $red=Redemptions::create([
                        'student_id'=>$student->student_id,'reward_id'=>$rid,
                        'redemption_code'=>(string)Str::uuid(),
                        'points_spent'=>$r->points_cost,'redeemed_at'=>now()
                    ]);
                    PointTransaction::create([
                        'student_id'=>$student->student_id,'redemption_id'=>$red->redemption_id,
                        'points'=>-$r->points_cost,'transaction_type'=>'redeemed',
                        'description'=>"Reward redemption group {$group}"
                    ]);
                    $created[]=$red;
                }
                $r->decrement('stock_quantity',$qty);
            }
            $student->decrement('points_balance',$total);
            return [$group,$total,$created,$student->fresh()];
        });
        return response()->json(['success'=>true,'redemption_group'=>$result[0],'total_points_spent'=>$result[1],
            'redemptions'=>collect($result[2])->load('reward'),'student'=>$result[3]],201);
    }

    public function initiateRedemptionProcess($student_id,$reward_id)
    {
        $student=Students::findOrFail($student_id); $reward=Rewards::findOrFail($reward_id);
        if($student->points_balance<$reward->points_cost) return response()->json(['error'=>'Insufficient points'],422);
        return response()->json(['message'=>'Please tap the student RFID card.','student_id'=>$student->student_id,'reward_id'=>$reward->reward_id]);
    }

    public function checkRedemptionStatus($student_id,$reward_id)
    {
        $found=Redemptions::where('student_id',$student_id)->where('reward_id',$reward_id)->where('redeemed_at','>=',now()->subMinutes(2))->exists();
        return response()->json(['status'=>$found?'success':'pending','completed'=>$found]);
    }

    public function update(Request $request,string $id){ return response()->json(['error'=>'Redemptions are immutable.'],405); }
    public function destroy(string $id){ return response()->json(['error'=>'Redemptions are immutable.'],405); }
}
