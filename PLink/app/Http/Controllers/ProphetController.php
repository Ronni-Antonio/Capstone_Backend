<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\RecyclingTransaction;
use App\Models\Redemptions;
use App\Models\SmartBinCompartmentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProphetController extends Controller
{
    public function getForecastData()
    {
        return response()->json($this->historicalData());
    }

    public function runForecast(Request $request)
    {
        $validated = $request->validate([
            'periods' => 'nullable|integer|min:1|max:30',
        ]);

        $periods = (int) ($validated['periods'] ?? 7);
        $serviceUrl = rtrim(config('services.prophet.url'), '/');

        try {
            $response = Http::connectTimeout(3)
                ->timeout(120)
                ->post($serviceUrl . '/forecast', [
                    'periods' => $periods,
                    'series' => $this->historicalData(),
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Prophet service returned an error.',
                    'details' => $response->json() ?: $response->body(),
                ], 502);
            }

            $result = $response->json();
            $forecasts = $result['forecasts'] ?? [];
            $generatedOn = now()->toDateString();

            DB::transaction(function () use ($forecasts, $generatedOn) {
                foreach ($forecasts as $predictionType => $forecastData) {
                    foreach (($forecastData['forecast'] ?? []) as $point) {
                        if (empty($point['ds'])) {
                            continue;
                        }

                        Prediction::updateOrCreate(
                            [
                                'prediction_type' => $predictionType,
                                'prediction_date' => $generatedOn,
                                'target_date' => $point['ds'],
                            ],
                            [
                                'model_id' => null,
                                'predicted_value' => $point['yhat'] ?? null,
                                'actual_value' => null,
                                'confidence' => null,
                                'input_summary' => [
                                    'history_points' => count($forecastData['history'] ?? []),
                                    'periods' => count($forecastData['forecast'] ?? []),
                                ],
                                'output' => [
                                    'yhat_lower' => $point['yhat_lower'] ?? null,
                                    'yhat_upper' => $point['yhat_upper'] ?? null,
                                ],
                            ]
                        );
                    }
                }
            });

            return response()->json([
                'message' => 'Prophet forecasts generated and stored successfully.',
                'generated_on' => $generatedOn,
                'forecasts' => $forecasts,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to contact the Prophet service.',
                'details' => $e->getMessage(),
                'service_url' => $serviceUrl,
            ], 502);
        }
    }

    private function historicalData(): array
    {
        $recycling = RecyclingTransaction::query()
            ->where('status', 'completed')
            ->selectRaw('DATE(started_at) as ds, SUM(total_items) as y')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => ['ds' => $row->ds, 'y' => (float) $row->y])
            ->values()
            ->all();

        $participation = RecyclingTransaction::query()
            ->where('status', 'completed')
            ->whereNotNull('student_id')
            ->selectRaw('DATE(started_at) as ds, COUNT(DISTINCT student_id) as y')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => ['ds' => $row->ds, 'y' => (float) $row->y])
            ->values()
            ->all();

        $redemptions = Redemptions::query()
            ->whereNotNull('redeemed_at')
            ->selectRaw('DATE(redeemed_at) as ds, COUNT(*) as y')
            ->groupByRaw('DATE(redeemed_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => ['ds' => $row->ds, 'y' => (float) $row->y])
            ->values()
            ->all();

        $compartmentSeries = SmartBinCompartmentLog::query()
            ->join(
                'smart_bin_compartments as compartments',
                'compartments.compartment_id',
                '=',
                'smart_bin_compartment_logs.compartment_id'
            )
            ->whereIn('compartments.material_category', ['plastic', 'paper'])
            ->select('compartments.material_category')
            ->selectRaw('DATE(smart_bin_compartment_logs.created_at) as ds')
            ->selectRaw('AVG(smart_bin_compartment_logs.fill_percentage) as y')
            ->groupBy('compartments.material_category', DB::raw('DATE(smart_bin_compartment_logs.created_at)'))
            ->orderBy('ds')
            ->get()
            ->groupBy('material_category');

        $plasticFullness = $compartmentSeries->get('plastic', collect())
            ->map(fn ($row) => ['ds' => $row->ds, 'y' => round((float) $row->y, 2)])
            ->values()
            ->all();

        $paperFullness = $compartmentSeries->get('paper', collect())
            ->map(fn ($row) => ['ds' => $row->ds, 'y' => round((float) $row->y, 2)])
            ->values()
            ->all();

        return [
            'recycling_volume' => $recycling,
            'student_participation' => $participation,
            'reward_redemptions' => $redemptions,
            'plastic_fullness' => $plasticFullness,
            'paper_fullness' => $paperFullness,
        ];
    }
}
