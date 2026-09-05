<?php
namespace App\Http\Controllers;

use App\Models\Students;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\RfidCard;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index()
    {
        $students = Students::query()
            ->select([
                'student_id','student_number','first_name','last_name',
                'grade_level_id','section_id','status','points_balance','created_at'
            ])
            ->with([
                'gradeLevel:grade_level_id,name',
                'section:section_id,name',
                'rfidCards' => fn ($q) => $q
                    ->where('status','active')
                    ->select('rfid_card_id','student_id','card_uid','status','assigned_at'),
            ])
            ->withSum([
                'transactions as total_items_recycled' => fn ($q) => $q->where('status','completed')
            ], 'total_items')
            ->latest('student_id')
            ->get();

        return response()->json($students);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_number'=>'required|string|max:255|unique:students,student_number',
            'first_name'=>'required|string|max:255',
            'last_name'=>'required|string|max:255',
            'grade_level_id'=>'required|exists:grade_levels,grade_level_id',
            'section_id'=>'required|exists:sections,section_id',
            'status'=>'nullable|in:active,inactive',
        ]);
        $validated['status']=$validated['status'] ?? 'inactive';
        $validated['points_balance']=0;

        $student = Students::create($validated)->load(['gradeLevel','section','rfidCards']);

        $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: "Student #{$student->student_id}";
        ActivityLog::record(
            'CREATE_STUDENT',
            "User/student account created for {$studentName} ({$student->student_number}).",
            'Users',
            $request->user()?->id,
            $student->student_id,
            ['student' => $student->toArray()]
        );

        return response()->json($student, 201);
    }

    public function show(string $id)
    {
        $student=Students::with([
            'gradeLevel','section','rfidCards',
            'transactions.items.classification.plasticType',
            'redemptions.reward','pointTransactions'
        ])->findOrFail($id);

        return response()->json($student);
    }

    public function update(Request $request,string $id)
    {
        $student=Students::findOrFail($id);
        $validated=$request->validate([
            'student_number'=>['sometimes','string','max:255',Rule::unique('students','student_number')->ignore($student->student_id,'student_id')],
            'first_name'=>'sometimes|string|max:255',
            'last_name'=>'sometimes|string|max:255',
            'grade_level_id'=>'sometimes|exists:grade_levels,grade_level_id',
            'section_id'=>'sometimes|exists:sections,section_id',
            'status'=>'sometimes|in:active,inactive',
        ]);

        $changes = [];
        foreach (['student_number','first_name','last_name','grade_level_id','section_id','status'] as $field) {
            if (isset($validated[$field]) && $validated[$field] != $student->$field) {
                $changes[$field] = ['old' => $student->$field, 'new' => $validated[$field]];
            }
        }

        $student->update($validated);

        if (!empty($changes)) {
            $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: "Student #{$student->student_id}";
            ActivityLog::record(
                'UPDATE_STUDENT',
                "User/student information updated for {$studentName}. Changes: " . json_encode($changes),
                'Users',
                $request->user()?->id,
                $student->student_id,
                ['changes' => $changes]
            );
        }

        return response()->json($student->load(['gradeLevel','section','rfidCards']));
    }

    public function importCSV(Request $request)
    {
        $request->validate(['csv_file'=>'required|file|mimes:csv,txt']);
        $handle=fopen($request->file('csv_file')->getRealPath(),'r');
        if(!$handle) return response()->json(['error'=>'Failed to open CSV file'],500);

        $header=fgetcsv($handle);
        if(!$header){ fclose($handle); return response()->json(['error'=>'CSV file is empty'],400); }
        $header=array_map(fn($v)=>strtolower(trim($v)), $header);
        $required=['student_number','first_name','last_name','grade_level','section'];
        $missing=array_values(array_diff($required,$header));
        if($missing){ fclose($handle); return response()->json(['error'=>'Missing required columns','missing'=>$missing],422); }

        $map=array_flip($header); $imported=0; $errors=[]; $rowNo=1;
        DB::beginTransaction();
        try {
            while(($row=fgetcsv($handle))!==false){
                $rowNo++;
                if(!count(array_filter($row,fn($v)=>trim((string)$v)!==''))) continue;
                $data=[
                    'student_number'=>trim($row[$map['student_number']]??''),
                    'first_name'=>trim($row[$map['first_name']]??''),
                    'last_name'=>trim($row[$map['last_name']]??''),
                    'grade_level'=>trim($row[$map['grade_level']]??''),
                    'section'=>trim($row[$map['section']]??''),
                ];
                $v=Validator::make($data,[
                    'student_number'=>'required|string|max:255|unique:students,student_number',
                    'first_name'=>'required|string|max:255',
                    'last_name'=>'required|string|max:255',
                    'grade_level'=>'required|string|max:255',
                    'section'=>'required|string|max:255',
                ]);
                if($v->fails()){ $errors[]=['row'=>$rowNo,'data'=>$data,'errors'=>$v->errors()->all()]; continue; }

                $grade=GradeLevel::firstOrCreate(['name'=>$data['grade_level']]);
                $section=Section::firstOrCreate(['name'=>$data['section']]);

                $student = Students::create([
                    'student_number'=>$data['student_number'],
                    'first_name'=>$data['first_name'],
                    'last_name'=>$data['last_name'],
                    'grade_level_id'=>$grade->grade_level_id,
                    'section_id'=>$section->section_id,
                    'status'=>'inactive',
                    'points_balance'=>0,
                ]);

                $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: "Student #{$student->student_id}";
                ActivityLog::record(
                    'CREATE_STUDENT',
                    "Student account created via CSV import for {$studentName} ({$student->student_number}).",
                    'Users',
                    $request->user()?->id,
                    $student->student_id,
                    ['imported_via' => 'csv']
                );

                $imported++;
            }
            DB::commit();
        } catch(\Throwable $e){
            DB::rollBack(); fclose($handle);
            return response()->json(['error'=>$e->getMessage()],500);
        }
        fclose($handle);

        if ($imported > 0) {
            ActivityLog::record(
                'BULK_IMPORT_STUDENTS',
                "CSV import completed. {$imported} students imported successfully.",
                'Users',
                $request->user()?->id,
                null,
                ['imported_count' => $imported, 'error_count' => count($errors)]
            );
        }

        return response()->json(['success'=>true,'imported'=>$imported,'errors'=>$errors]);
    }

    public function assignCard(Request $request)
    {
        $validated=$request->validate([
            'student_id'=>'required|exists:students,student_id',
            'card_uid'=>'required|string|max:100|unique:rfid_cards,card_uid',
        ]);
        $card=DB::transaction(function() use($validated,$request){
            $student=Students::lockForUpdate()->findOrFail($validated['student_id']);
            RfidCard::where('student_id',$student->student_id)->where('status','active')->update(['status'=>'unassigned']);
            $card=RfidCard::create([
                'student_id'=>$student->student_id,'card_uid'=>strtoupper(trim($validated['card_uid'])),
                'status'=>'active','assigned_at'=>now()
            ]);
            $oldStatus = $student->status;
            $student->update(['status'=>'active']);

            $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: "Student #{$student->student_id}";
            ActivityLog::record(
                'ASSIGN_RFID_CARD',
                "RFID card assigned to {$studentName}. Card UID: {$validated['card_uid']}.",
                'Machine',
                $request->user()?->id,
                $student->student_id,
                ['card_uid' => $validated['card_uid'], 'old_status' => $oldStatus, 'new_status' => 'active']
            );

            return $card;
        });
        return response()->json(['status'=>'success','message'=>'Card paired successfully','card'=>$card],201);
    }

    public function activate(string $id)
    {
        $student=Students::findOrFail($id);
        $esp32Url=env('ESP32_URL');
        if($esp32Url){
            try {
                (new \GuzzleHttp\Client())->post(rtrim($esp32Url,'/').'/prepare-activation',[
                    'json'=>['student_id'=>$student->student_id],
                    'timeout'=>5
                ]);
            } catch(\Throwable $e) {
                return response()->json(['error'=>'Unable to contact ESP32','details'=>$e->getMessage()],502);
            }
        }
        return response()->json(['message'=>'Please tap card on reader now.']);
    }

    public function activateStatus(string $id)
    {
        $student=Students::with('rfidCards')->findOrFail($id);
        $active=$student->rfidCards->contains(fn($card)=>$card->status==='active');
        return response()->json(['status'=>$active?'success':'pending']);
    }

    public function cancelActivation(string $id)
    {
        return response()->json(['message'=>'Activation cancelled']);
    }

    public function identifyCard(Request $request)
    {
        $validated=$request->validate(['card_uid'=>'required|string|max:100']);
        $card=RfidCard::with('student')
            ->where('card_uid',strtoupper(trim($validated['card_uid'])))
            ->where('status','active')->first();

        if(!$card) return response()->json(['error'=>'Unrecognized or inactive card.'],404);

        return response()->json([
            'success'=>true,
            'student_id'=>$card->student_id,
            'rfid_card_id'=>$card->rfid_card_id,
            'points_balance'=>(int)$card->student->points_balance,
            'student'=>$card->student
        ]);
    }

    public function checkActiveScanSession()
    {
        return response()->json(['student_found'=>false,'student'=>null]);
    }

    public function clearScanSession()
    {
        return response()->json(['success'=>true]);
    }

    public function destroy(string $id)
    {
        $student=Students::findOrFail($id);
        if($student->transactions()->exists() || $student->pointTransactions()->exists() || $student->redemptions()->exists()){
            return response()->json(['error'=>'Student has historical records and cannot be deleted. Set status to inactive instead.'],409);
        }
        $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: "Student #{$student->student_id}";
        $studentNumber = $student->student_number;
        $studentId = $student->student_id;
        $student->delete();

        ActivityLog::record(
            'DELETE_STUDENT',
            "User/student account deleted for {$studentName} ({$studentNumber}).",
            'Users',
            request()->user()?->id,
            null,
            ['student_id' => $studentId, 'student_number' => $studentNumber, 'student_name' => $studentName]
        );

        return response()->json(null,204);
    }
}
