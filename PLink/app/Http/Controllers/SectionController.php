<?php
namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $studentCounts = DB::table('students')
            ->select('section_id')
            ->selectRaw('COUNT(*) as student_count')
            ->groupBy('section_id');

        $pointTotals = DB::table('point_transactions')
            ->join('students', 'students.student_id', '=', 'point_transactions.student_id')
            ->where('point_transactions.transaction_type', 'earned')
            ->select('students.section_id')
            ->selectRaw('SUM(point_transactions.points) as total_points')
            ->groupBy('students.section_id');

        $itemTotals = DB::table('recycling_transactions')
            ->join('students', 'students.student_id', '=', 'recycling_transactions.student_id')
            ->where('recycling_transactions.status', 'completed')
            ->select('students.section_id')
            ->selectRaw('SUM(recycling_transactions.total_items) as total_bottles')
            ->groupBy('students.section_id');

        $sections = DB::table('sections')
            ->leftJoinSub($studentCounts, 'student_counts', fn ($join) =>
                $join->on('sections.section_id', '=', 'student_counts.section_id'))
            ->leftJoinSub($pointTotals, 'point_totals', fn ($join) =>
                $join->on('sections.section_id', '=', 'point_totals.section_id'))
            ->leftJoinSub($itemTotals, 'item_totals', fn ($join) =>
                $join->on('sections.section_id', '=', 'item_totals.section_id'))
            ->select('sections.section_id', 'sections.name as section_name')
            ->selectRaw('COALESCE(student_counts.student_count, 0) as student_count')
            ->selectRaw('COALESCE(point_totals.total_points, 0) as total_points')
            ->selectRaw('COALESCE(item_totals.total_bottles, 0) as total_bottles')
            ->get();

        $pointsRanks = $sections->sortByDesc('total_points')->values();
        $bottlesRanks = $sections->sortByDesc('total_bottles')->values();
        $bottleRankById = $bottlesRanks->mapWithKeys(
            fn ($row, $index) => [$row->section_id => $index + 1]
        );

        $ranking = $pointsRanks->map(function ($row, $index) use ($bottleRankById) {
            return [
                'section_id' => $row->section_id,
                'section_name' => $row->section_name,
                'student_count' => (int) $row->student_count,
                'total_points' => (int) $row->total_points,
                'total_bottles' => (int) $row->total_bottles,
                'points_rank' => $index + 1,
                'bottles_rank' => (int) $bottleRankById[$row->section_id],
            ];
        });

        return response()->json(['ranking_type' => 'sections', 'ranking' => $ranking]);
    }
}
