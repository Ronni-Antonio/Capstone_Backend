<?php
namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Students;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index()
    {
        return response()->json(Section::withCount('students')->get()->map(fn($s)=>[
            'section_id'=>$s->section_id,'name'=>$s->name,'students'=>$s->students_count
        ]));
    }

    public function list(){ return response()->json(Section::orderBy('name')->get(['section_id','name'])); }

    public function store(Request $request)
    {
        $v=$request->validate(['name'=>'required|string|max:255|unique:sections,name']);
        return response()->json(Section::create($v),201);
    }

    public function update(Request $request,string $id)
    {
        $section=Section::findOrFail($id);
        $v=$request->validate(['name'=>'required|string|max:255|unique:sections,name,'.$section->section_id.',section_id']);
        $section->update($v);
        return response()->json($section);
    }

    public function destroy(string $id)
    {
        $section=Section::findOrFail($id);
        if($section->students()->exists()) return response()->json(['error'=>'Section still has students. Reassign them first.'],409);
        $section->delete();
        return response()->json(null,204);
    }

    public function sectionRanking(string $id)
    {
        $section=Section::findOrFail($id);
        $students=Students::where('section_id',$section->section_id)
            ->with('gradeLevel')
            ->withSum(['pointTransactions as total_points'=>fn($q)=>$q->where('transaction_type','earned')],'points')
            ->get();

        $ranking=$students->sortByDesc('total_points')->values()->map(function($s,$i)use($section){
            $totalBottles=\App\Models\RecyclingItem::whereHas(
                'transaction',
                fn($q)=>$q->where('student_id',$s->student_id)
            )->count();

            return [
                'rank'=>$i+1,'student_id'=>$s->student_id,'student_number'=>$s->student_number,
                'first_name'=>$s->first_name,'last_name'=>$s->last_name,
                'grade_level'=>$s->gradeLevel?->name,'section'=>$section->name,
                'points_balance'=>(int)$s->points_balance,'total_points'=>(int)($s->total_points??0),
                'total_bottles'=>$totalBottles,
            ];
        });
        return response()->json(['section'=>$section->name,'ranking'=>$ranking]);
    }

    public function overallRanking()
    {
        $students=Students::with(['gradeLevel','section'])
            ->withSum(['pointTransactions as total_points'=>fn($q)=>$q->where('transaction_type','earned')],'points')
            ->get()->sortByDesc('total_points')->values()
            ->map(fn($s,$i)=>[
                'rank'=>$i+1,'student_id'=>$s->student_id,'student_number'=>$s->student_number,
                'first_name'=>$s->first_name,'last_name'=>$s->last_name,
                'grade_level'=>$s->gradeLevel?->name,'section'=>$s->section?->name,
                'points_balance'=>(int)$s->points_balance,'total_points'=>(int)($s->total_points??0)
            ]);
        return response()->json(['ranking_type'=>'overall','ranking'=>$students]);
    }

    public function sectionRankingOverall()
    {
        $sections=Section::withCount('students')->get()->map(function($section){
            $studentIds=$section->students()->pluck('student_id');
            $points=\App\Models\PointTransaction::whereIn('student_id',$studentIds)->where('transaction_type','earned')->sum('points');
            $bottles=\App\Models\RecyclingItem::whereHas('transaction',fn($q)=>$q->whereIn('student_id',$studentIds))->count();
            return ['section_id'=>$section->section_id,'section_name'=>$section->name,'student_count'=>$section->students_count,
                'total_points'=>(int)$points,'total_bottles'=>$bottles];
        });
        $byPoints=$sections->sortByDesc('total_points')->values()->map(fn($s,$i)=>$s+['points_rank'=>$i+1]);
        $byBottles=$sections->sortByDesc('total_bottles')->values()->keyBy('section_id');
        $result=$byPoints->map(function($s)use($byBottles){$s['bottles_rank']=$byBottles[$s['section_id']]['bottles_rank'];return $s;});
        return response()->json(['ranking_type'=>'sections','ranking'=>$result]);
    }
}
