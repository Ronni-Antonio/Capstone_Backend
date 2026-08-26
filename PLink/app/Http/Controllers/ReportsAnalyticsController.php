<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsReport;
use App\Models\Prediction;
use App\Models\RecyclingTransaction;
use App\Models\Redemptions;
use App\Models\SmartBinCompartment;
use App\Services\SimplePdfReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json($this->buildPayload($from, $to));
    }

    public function pdf(Request $request, SimplePdfReportService $pdfService)
    {
        [$from, $to] = $this->resolveRange($request);
        $payload = $this->buildPayload($from, $to);

        $report = AnalyticsReport::create([
            'generated_by_user_id' => optional($request->user())->id,
            'report_type' => 'sustainability_predictive',
            'title' => 'Recycling Sustainability & Predictive Analytics Report',
            'report_date_start' => $from->toDateString(),
            'report_date_end' => $to->toDateString(),
            'total_items_collected' => $payload['summary']['total_items'],
            'total_points_awarded' => $payload['summary']['total_points'],
            'total_rewards_redeemed' => $payload['summary']['rewards_redeemed'],
            'total_students_participated' => $payload['summary']['participating_students'],
            'summary' => [
                'waste_types' => $payload['waste_types'],
                'top_sections' => array_slice($payload['section_performance'], 0, 3),
                'top_recyclers' => $payload['top_recyclers'],
                'current_compartments' => $payload['compartments']['current'],
            ],
            'predictive_insights' => $payload['predictive'],
        ]);

        $pdf = $pdfService->render($payload, $report);
        $filename = sprintf(
            'plink-sustainability-report-%s-to-%s.pdf',
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($pdf),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:7|max:365',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        if (!empty($validated['from'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $to = !empty($validated['to'])
                ? Carbon::parse($validated['to'])->endOfDay()
                : now()->endOfDay();
        } else {
            $days = (int) ($validated['days'] ?? 30);
            $to = now()->endOfDay();
            $from = now()->subDays($days - 1)->startOfDay();
        }

        return [$from, $to];
    }

    private function buildPayload(Carbon $from, Carbon $to): array
    {
        $completed = RecyclingTransaction::query()
            ->where('status', 'completed')
            ->whereBetween('started_at', [$from, $to]);

        $summary = [
            'total_items' => (int) (clone $completed)->sum('total_items'),
            'total_points' => (int) (clone $completed)->sum('total_points'),
            'participating_students' => (int) (clone $completed)
                ->whereNotNull('student_id')
                ->distinct('student_id')
                ->count('student_id'),
            'rewards_redeemed' => (int) Redemptions::query()
                ->whereNotNull('redeemed_at')
                ->whereBetween('redeemed_at', [$from, $to])
                ->count(),
            'transactions' => (int) (clone $completed)->count(),
        ];

        $dailyCollection = RecyclingTransaction::query()
            ->where('status', 'completed')
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('DATE(started_at) as ds')
            ->selectRaw('SUM(total_items) as items')
            ->selectRaw('SUM(total_points) as points')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => [
                'ds' => $row->ds,
                'items' => (int) $row->items,
                'points' => (int) $row->points,
            ])
            ->values();

        $participationTrend = RecyclingTransaction::query()
            ->where('status', 'completed')
            ->whereBetween('started_at', [$from, $to])
            ->whereNotNull('student_id')
            ->selectRaw('DATE(started_at) as ds')
            ->selectRaw('COUNT(DISTINCT student_id) as students')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => [
                'ds' => $row->ds,
                'students' => (int) $row->students,
            ])
            ->values();

        $wasteTypes = DB::table('recycling_items as ri')
            ->join('recycling_transactions as tx', 'tx.transaction_id', '=', 'ri.transaction_id')
            ->join('ai_classifications as ac', 'ac.recycling_item_id', '=', 'ri.recycling_item_id')
            ->join('recyclable_types as rt', 'rt.recyclable_type_id', '=', 'ac.recyclable_type_id')
            ->where('tx.status', 'completed')
            ->where('ri.status', 'accepted')
            ->where('rt.is_accepted', true)
            ->whereBetween('tx.started_at', [$from, $to])
            ->select([
                'rt.recyclable_type_id',
                'rt.code',
                'rt.name',
                'rt.material_category',
            ])
            ->selectRaw('COUNT(*) as total_items')
            ->groupBy('rt.recyclable_type_id', 'rt.code', 'rt.name', 'rt.material_category')
            ->orderByDesc('total_items')
            ->get()
            ->map(fn ($row) => [
                'recyclable_type_id' => (int) $row->recyclable_type_id,
                'code' => $row->code,
                'name' => $row->name,
                'label' => $this->typeLabel($row->name, $row->code),
                'material_category' => $row->material_category,
                'total_items' => (int) $row->total_items,
            ])
            ->values();

        $sectionPerformance = DB::table('sections as sec')
            ->leftJoin('students as s', 's.section_id', '=', 'sec.section_id')
            ->leftJoin('recycling_transactions as tx', function ($join) use ($from, $to) {
                $join->on('tx.student_id', '=', 's.student_id')
                    ->where('tx.status', '=', 'completed')
                    ->whereBetween('tx.started_at', [$from, $to]);
            })
            ->select('sec.section_id', 'sec.name')
            ->selectRaw('COALESCE(SUM(tx.total_items), 0) as total_items')
            ->selectRaw('COALESCE(SUM(tx.total_points), 0) as total_points')
            ->selectRaw('COUNT(DISTINCT tx.student_id) as participants')
            ->groupBy('sec.section_id', 'sec.name')
            ->orderByDesc('total_items')
            ->get()
            ->map(fn ($row) => [
                'section_id' => (int) $row->section_id,
                'name' => $row->name,
                'total_items' => (int) $row->total_items,
                'total_points' => (int) $row->total_points,
                'participants' => (int) $row->participants,
            ])
            ->values();

        $topRecyclers = DB::table('students as s')
            ->join('recycling_transactions as tx', function ($join) use ($from, $to) {
                $join->on('tx.student_id', '=', 's.student_id')
                    ->where('tx.status', '=', 'completed')
                    ->whereBetween('tx.started_at', [$from, $to]);
            })
            ->select('s.student_id', 's.first_name', 's.last_name')
            ->selectRaw('SUM(tx.total_points) as points_earned')
            ->selectRaw('SUM(tx.total_items) as items_recycled')
            ->groupBy('s.student_id', 's.first_name', 's.last_name')
            ->orderByDesc('points_earned')
            ->orderByDesc('items_recycled')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'name' => trim($row->first_name . ' ' . $row->last_name),
                'points_earned' => (int) $row->points_earned,
                'items_recycled' => (int) $row->items_recycled,
            ])
            ->values();

        $rewardTrend = Redemptions::query()
            ->whereNotNull('redeemed_at')
            ->whereBetween('redeemed_at', [$from, $to])
            ->selectRaw('DATE(redeemed_at) as ds')
            ->selectRaw('COUNT(*) as redemptions')
            ->groupByRaw('DATE(redeemed_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => [
                'ds' => $row->ds,
                'redemptions' => (int) $row->redemptions,
            ])
            ->values();

        $rewardBreakdown = DB::table('redemptions as red')
            ->join('rewards as r', 'r.reward_id', '=', 'red.reward_id')
            ->whereNotNull('red.redeemed_at')
            ->whereBetween('red.redeemed_at', [$from, $to])
            ->select('r.reward_id', 'r.reward_name')
            ->selectRaw('COUNT(*) as redemptions')
            ->groupBy('r.reward_id', 'r.reward_name')
            ->orderByDesc('redemptions')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'reward_id' => (int) $row->reward_id,
                'name' => $row->reward_name,
                'redemptions' => (int) $row->redemptions,
            ])
            ->values();

        $currentCompartments = SmartBinCompartment::query()
            ->with('smartBin:smart_bin_id,name,location')
            ->orderBy('smart_bin_id')
            ->orderBy('compartment_id')
            ->get()
            ->map(fn ($compartment) => [
                'compartment_id' => $compartment->compartment_id,
                'smart_bin_id' => $compartment->smart_bin_id,
                'bin_name' => optional($compartment->smartBin)->name,
                'name' => $compartment->name,
                'material_category' => $compartment->material_category,
                'fill_percentage' => (int) ($compartment->current_fill_percentage ?? 0),
                'distance_cm' => $compartment->current_distance_cm,
                'status' => $compartment->status,
            ])
            ->values();

        $compartmentHistory = DB::table('smart_bin_compartment_logs as logs')
            ->join('smart_bin_compartments as comp', 'comp.compartment_id', '=', 'logs.compartment_id')
            ->whereBetween('logs.created_at', [$from, $to])
            ->select('comp.material_category')
            ->selectRaw('DATE(logs.created_at) as ds')
            ->selectRaw('AVG(logs.fill_percentage) as fill_percentage')
            ->groupBy('comp.material_category', DB::raw('DATE(logs.created_at)'))
            ->orderBy('ds')
            ->get()
            ->groupBy('material_category')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'ds' => $row->ds,
                'fill_percentage' => round((float) $row->fill_percentage, 1),
            ])->values())
            ->toArray();

        $predictive = $this->buildPredictivePayload(
            $dailyCollection->all(),
            $participationTrend->all(),
            $rewardTrend->all(),
            $compartmentHistory
        );

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $from->diffInDays($to) + 1,
            ],
            'summary' => $summary,
            'daily_collection' => $dailyCollection,
            'participation_trend' => $participationTrend,
            'waste_types' => $wasteTypes,
            'section_performance' => $sectionPerformance,
            'top_recyclers' => $topRecyclers,
            'reward_trend' => $rewardTrend,
            'reward_breakdown' => $rewardBreakdown,
            'compartments' => [
                'current' => $currentCompartments,
                'history' => $compartmentHistory,
            ],
            'predictive' => $predictive,
        ];
    }

    private function buildPredictivePayload(
        array $dailyCollection,
        array $participationTrend,
        array $rewardTrend,
        array $compartmentHistory
    ): array {
        $definitions = [
            'recycling_volume' => [
                'title' => 'Recycling Volume',
                'unit' => 'items',
                'history' => array_map(fn ($row) => ['ds' => $row['ds'], 'y' => $row['items']], $dailyCollection),
            ],
            'student_participation' => [
                'title' => 'Student Participation',
                'unit' => 'students',
                'history' => array_map(fn ($row) => ['ds' => $row['ds'], 'y' => $row['students']], $participationTrend),
            ],
            'reward_redemptions' => [
                'title' => 'Reward Redemptions',
                'unit' => 'redemptions',
                'history' => array_map(fn ($row) => ['ds' => $row['ds'], 'y' => $row['redemptions']], $rewardTrend),
            ],
            'plastic_fullness' => [
                'title' => 'Plastic Compartment Fullness',
                'unit' => '%',
                'history' => array_map(
                    fn ($row) => ['ds' => $row['ds'], 'y' => $row['fill_percentage']],
                    $compartmentHistory['plastic'] ?? []
                ),
            ],
            'paper_fullness' => [
                'title' => 'Paper Compartment Fullness',
                'unit' => '%',
                'history' => array_map(
                    fn ($row) => ['ds' => $row['ds'], 'y' => $row['fill_percentage']],
                    $compartmentHistory['paper'] ?? []
                ),
            ],
        ];

        $futurePredictions = Prediction::query()
            ->whereIn('prediction_type', array_keys($definitions))
            ->whereNotNull('target_date')
            ->whereDate('target_date', '>=', today())
            ->orderByDesc('prediction_date')
            ->orderBy('target_date')
            ->get()
            ->groupBy('prediction_type');

        foreach ($definitions as $type => &$definition) {
            $records = $futurePredictions->get($type, collect());
            $latestPredictionDate = optional($records->first())->prediction_date?->toDateString();

            $latest = $latestPredictionDate
                ? $records->filter(fn ($record) => $record->prediction_date?->toDateString() === $latestPredictionDate)
                : collect();

            $definition['forecast_generated_at'] = $latestPredictionDate;
            $definition['forecast'] = $latest->map(fn ($record) => [
                'ds' => optional($record->target_date)->toDateString(),
                'yhat' => round((float) $record->predicted_value, 2),
                'yhat_lower' => isset($record->output['yhat_lower']) ? round((float) $record->output['yhat_lower'], 2) : null,
                'yhat_upper' => isset($record->output['yhat_upper']) ? round((float) $record->output['yhat_upper'], 2) : null,
            ])->values()->all();

            $definition['next_7_total'] = in_array($type, ['recycling_volume', 'student_participation', 'reward_redemptions'], true)
                ? round(array_sum(array_column($definition['forecast'], 'yhat')), 1)
                : null;

            if (in_array($type, ['plastic_fullness', 'paper_fullness'], true) && count($definition['forecast'])) {
                $definition['peak_forecast'] = max(array_column($definition['forecast'], 'yhat'));
            } else {
                $definition['peak_forecast'] = null;
            }
        }
        unset($definition);

        return $definitions;
    }

    private function typeLabel(?string $name, ?string $code): string
    {
        return match ($name) {
            'PET Bottle' => 'PET',
            'HDPE Bottle' => 'HDPE',
            'PVC Bottle' => 'PVC',
            'LDPE Bottle' => 'LDPE',
            'PP Bottle' => 'PP',
            'PS Bottle' => 'PS',
            'PC Bottle' => 'PC',
            'PLA Plastic' => 'PLA',
            'White Paper' => 'Paper',
            'Contaminated PET Bottle' => 'Contaminated PET',
            default => $name ?: ($code ?: 'Unknown'),
        };
    }
}
