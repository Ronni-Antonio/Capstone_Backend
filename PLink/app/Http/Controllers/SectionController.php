<?php
namespace App\Http\Controllers;

use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    private const MANAGED_GRADES = ['Grade 4', 'Grade 5', 'Grade 6'];

    private function managedGradeIds()
    {
        return GradeLevel::query()
            ->whereIn('name', self::MANAGED_GRADES)
            ->pluck('grade_level_id');
    }

    private function sectionRows()
    {
        $managedGradeIds = $this->managedGradeIds();

        return Section::query()
            ->select(['section_id', 'grade_level_id', 'name'])
            ->with('gradeLevel:grade_level_id,name')
            ->withCount('students')
            ->where(function ($query) use ($managedGradeIds) {
                $query->whereIn('grade_level_id', $managedGradeIds)
                    ->orWhereNull('grade_level_id');
            })
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($section) => [
                'section_id' => (int) $section->section_id,
                'grade_level_id' => $section->grade_level_id ? (int) $section->grade_level_id : null,
                'grade_level' => $section->gradeLevel?->name,
                'name' => $section->name,
                'students' => (int) ($section->students_count ?? 0),
                'student_count' => (int) ($section->students_count ?? 0),
                'students_count' => (int) ($section->students_count ?? 0),
            ]);
    }

    public function index()
    {
        return response()->json($this->sectionRows());
    }

    public function list()
    {
        return response()->json($this->sectionRows());
    }

    public function store(Request $request)
    {
        $managedGradeIds = $this->managedGradeIds()->all();

        $validated = $request->validate([
            'grade_level_id' => ['required', 'integer', Rule::in($managedGradeIds)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sections', 'name')->where(
                    fn ($query) => $query->where('grade_level_id', $request->integer('grade_level_id'))
                ),
            ],
        ]);

        $section = Section::create($validated)->load('gradeLevel:grade_level_id,name');

        return response()->json([
            ...$section->toArray(),
            'grade_level' => $section->gradeLevel?->name,
            'students' => 0,
            'student_count' => 0,
            'students_count' => 0,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $section = Section::findOrFail($id);
        $managedGradeIds = $this->managedGradeIds()->all();
        $targetGradeId = (int) $request->input('grade_level_id', $section->grade_level_id);

        $validated = $request->validate([
            'grade_level_id' => ['required', 'integer', Rule::in($managedGradeIds)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sections', 'name')
                    ->where(fn ($query) => $query->where('grade_level_id', $targetGradeId))
                    ->ignore($section->section_id, 'section_id'),
            ],
        ]);

        // A section that already has students cannot be moved to another grade,
        // because that would make those students' grade/section pair inconsistent.
        if (
            $section->grade_level_id &&
            (int) $section->grade_level_id !== (int) $validated['grade_level_id'] &&
            $section->students()->exists()
        ) {
            return response()->json([
                'message' => 'This section already has students. Reassign the students before changing its grade level.',
            ], 409);
        }

        $section->update($validated);
        $section->load('gradeLevel:grade_level_id,name');

        return response()->json([
            ...$section->toArray(),
            'grade_level' => $section->gradeLevel?->name,
            'students' => $section->students()->count(),
            'student_count' => $section->students()->count(),
            'students_count' => $section->students()->count(),
        ]);
    }

    public function destroy(string $id)
    {
        $section = Section::findOrFail($id);
        if ($section->students()->exists()) {
            return response()->json(['error' => 'Section still has students. Reassign them first.'], 409);
        }

        $section->delete();
        return response()->json(null, 204);
    }

    public function sectionRanking(string $id)
    {
        $section = Section::with('gradeLevel')->findOrFail($id);
        $students = Students::where('section_id', $section->section_id)
            ->with('gradeLevel')
            ->withSum(['pointTransactions as total_points' => fn ($q) => $q->where('transaction_type', 'earned')], 'points')
            ->get();

        $ranking = $students->sortByDesc('total_points')->values()->map(function ($s, $i) use ($section) {
            $totalRecyclables = \App\Models\RecyclingItem::whereHas(
                'transaction',
                fn ($q) => $q->where('student_id', $s->student_id)
            )->count();

            return [
                'rank' => $i + 1,
                'student_id' => $s->student_id,
                'student_number' => $s->student_number,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'grade_level' => $s->gradeLevel?->name,
                'section' => $section->name,
                'points_balance' => (int) $s->points_balance,
                'total_points' => (int) ($s->total_points ?? 0),
                'total_recyclables' => $totalRecyclables,
                'total_bottles' => $totalRecyclables, // compatibility alias
            ];
        });

        return response()->json([
            'section' => $section->name,
            'grade_level' => $section->gradeLevel?->name,
            'ranking' => $ranking,
        ]);
    }

    public function overallRanking()
    {
        $students = Students::with(['gradeLevel', 'section'])
            ->withSum(['pointTransactions as total_points' => fn ($q) => $q->where('transaction_type', 'earned')], 'points')
            ->get()->sortByDesc('total_points')->values()
            ->map(fn ($s, $i) => [
                'rank' => $i + 1,
                'student_id' => $s->student_id,
                'student_number' => $s->student_number,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'grade_level' => $s->gradeLevel?->name,
                'section' => $s->section?->name,
                'points_balance' => (int) $s->points_balance,
                'total_points' => (int) ($s->total_points ?? 0),
            ]);

        return response()->json(['ranking_type' => 'overall', 'ranking' => $students]);
    }

    public function sectionRankingOverall()
    {
        $managedGradeIds = $this->managedGradeIds();

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
            ->selectRaw('SUM(recycling_transactions.total_items) as total_recyclables')
            ->groupBy('students.section_id');

        $sections = DB::table('sections')
            ->join('grade_levels', 'grade_levels.grade_level_id', '=', 'sections.grade_level_id')
            ->leftJoinSub($studentCounts, 'student_counts', fn ($join) =>
                $join->on('sections.section_id', '=', 'student_counts.section_id'))
            ->leftJoinSub($pointTotals, 'point_totals', fn ($join) =>
                $join->on('sections.section_id', '=', 'point_totals.section_id'))
            ->leftJoinSub($itemTotals, 'item_totals', fn ($join) =>
                $join->on('sections.section_id', '=', 'item_totals.section_id'))
            ->whereIn('sections.grade_level_id', $managedGradeIds)
            ->select(
                'sections.section_id',
                'sections.grade_level_id',
                'grade_levels.name as grade_level',
                'sections.name as section_name'
            )
            ->selectRaw('COALESCE(student_counts.student_count, 0) as student_count')
            ->selectRaw('COALESCE(point_totals.total_points, 0) as total_points')
            ->selectRaw('COALESCE(item_totals.total_recyclables, 0) as total_recyclables')
            ->get();

        $pointsRanks = $sections->sortByDesc('total_points')->values();
        $recyclablesRanks = $sections->sortByDesc('total_recyclables')->values();
        $recyclablesRankById = $recyclablesRanks->mapWithKeys(
            fn ($row, $index) => [$row->section_id => $index + 1]
        );

        $ranking = $pointsRanks->map(function ($row, $index) use ($recyclablesRankById) {
            return [
                'section_id' => (int) $row->section_id,
                'grade_level_id' => (int) $row->grade_level_id,
                'grade_level' => $row->grade_level,
                'section_name' => $row->section_name,
                'student_count' => (int) $row->student_count,
                'total_points' => (int) $row->total_points,
                'total_recyclables' => (int) $row->total_recyclables,
                'total_bottles' => (int) $row->total_recyclables, // compatibility alias
                'points_rank' => $index + 1,
                'recyclables_rank' => (int) $recyclablesRankById[$row->section_id],
                'bottles_rank' => (int) $recyclablesRankById[$row->section_id], // compatibility alias
            ];
        });

        return response()->json(['ranking_type' => 'sections', 'ranking' => $ranking]);
    }
}
