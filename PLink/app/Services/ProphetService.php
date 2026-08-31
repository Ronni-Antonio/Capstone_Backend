<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\RecyclingTransaction;
use App\Models\Redemptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProphetService
{
    public const SUPPORTED_TYPES = [
        'recycling_volume',
        'student_participation',
        'reward_redemptions',
        'plastic_fullness',
        'paper_fullness',
    ];

    /**
     * Gather the latest historical data from Laravel/RDS and train all
     * Prophet models. Python persists the fitted models on the EC2 instance.
     */
    public function trainAll(): array
    {
        $datasets = $this->buildHistoricalDatasets();

        Log::info('Starting Prophet training.', [
            'dataset_sizes' => collect($datasets)
                ->map(fn (array $dataset) => count($dataset))
                ->all(),
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(300)
                ->post($this->baseUrl() . '/train-all', [
                    'datasets' => $datasets,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to connect to the Prophet service: ' . $e->getMessage(),
                previous: $e
            );
        }

        if ($response->failed()) {
            Log::error('Prophet training failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'Prophet training failed (HTTP ' . $response->status() . '): ' . $response->body()
            );
        }

        $result = $response->json() ?? [];

        Log::info('Prophet training completed.', [
            'models' => array_keys($result['models'] ?? []),
            'errors' => $result['errors'] ?? [],
        ]);

        return $result;
    }

    /**
     * Generate forecasts from the already-saved Python models.
     * This method deliberately does NOT send historical data or call fit().
     */
    public function forecastAll(int $periods = 7): array
    {
        $periods = max(1, min($periods, 90));

        try {
            $response = Http::acceptJson()
                ->timeout(120)
                ->post($this->baseUrl() . '/forecast-all', [
                    'periods' => $periods,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to connect to the Prophet service: ' . $e->getMessage(),
                previous: $e
            );
        }

        if ($response->failed()) {
            Log::error('Prophet forecasting failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'Prophet forecasting failed (HTTP ' . $response->status() . '): ' . $response->body()
            );
        }

        $result = $response->json() ?? [];
        $forecasts = $result['forecasts'] ?? [];

        $this->storePredictions($forecasts);

        return $result;
    }

    public function health(): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($this->baseUrl() . '/health');
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to reach Prophet service: ' . $e->getMessage(),
                previous: $e
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Prophet service returned HTTP ' . $response->status() . '.'
            );
        }

        return $response->json() ?? [];
    }

    public function models(): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($this->baseUrl() . '/models');
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Unable to reach Prophet service: ' . $e->getMessage(),
                previous: $e
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Unable to retrieve Prophet model information (HTTP ' . $response->status() . ').'
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Public mainly for diagnostics/tests. Normal callers should use trainAll().
     */
    public function buildHistoricalDatasets(): array
    {
        return [
            'recycling_volume' => $this->getRecyclingTransactions(),
            'student_participation' => $this->getStudentParticipation(),
            'reward_redemptions' => $this->getRedemptions(),
            'plastic_fullness' => $this->getCompartmentFullness('plastic'),
            'paper_fullness' => $this->getCompartmentFullness('paper'),
        ];
    }

    private function getRecyclingTransactions(): array
    {
        return RecyclingTransaction::query()
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->selectRaw('DATE(started_at) as ds')
            ->selectRaw('SUM(total_items) as y')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => [
                'ds' => $row->ds,
                'y' => (float) $row->y,
            ])
            ->values()
            ->all();
    }

    private function getStudentParticipation(): array
    {
        return RecyclingTransaction::query()
            ->where('status', 'completed')
            ->whereNotNull('student_id')
            ->whereNotNull('started_at')
            ->selectRaw('DATE(started_at) as ds')
            ->selectRaw('COUNT(DISTINCT student_id) as y')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => [
                'ds' => $row->ds,
                'y' => (float) $row->y,
            ])
            ->values()
            ->all();
    }

    private function getRedemptions(): array
    {
        return Redemptions::query()
            ->whereNotNull('redeemed_at')
            ->selectRaw('DATE(redeemed_at) as ds')
            ->selectRaw('COUNT(*) as y')
            ->groupByRaw('DATE(redeemed_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => [
                'ds' => $row->ds,
                'y' => (float) $row->y,
            ])
            ->values()
            ->all();
    }

    private function getCompartmentFullness(string $category): array
    {
        return DB::table('smart_bin_compartment_logs as logs')
            ->join(
                'smart_bin_compartments as compartments',
                'compartments.compartment_id',
                '=',
                'logs.compartment_id'
            )
            ->where('compartments.material_category', $category)
            ->selectRaw('DATE(logs.created_at) as ds')
            ->selectRaw('AVG(logs.fill_percentage) as y')
            ->groupByRaw('DATE(logs.created_at)')
            ->orderBy('ds')
            ->get()
            ->map(fn ($row) => [
                'ds' => $row->ds,
                'y' => round((float) $row->y, 2),
            ])
            ->values()
            ->all();
    }

    private function storePredictions(array $forecasts): void
    {
        $predictionDate = now()->toDateString();

        DB::transaction(function () use ($forecasts, $predictionDate) {
            foreach ($forecasts as $predictionType => $rows) {
                if (!in_array($predictionType, self::SUPPORTED_TYPES, true) || !is_array($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (!is_array($row) || !isset($row['ds'], $row['yhat'])) {
                        continue;
                    }

                    Prediction::updateOrCreate(
                        [
                            'prediction_type' => $predictionType,
                            'prediction_date' => $predictionDate,
                            'target_date' => $row['ds'],
                        ],
                        [
                            'predicted_value' => (float) $row['yhat'],
                            'actual_value' => null,
                            'confidence' => null,
                            'input_summary' => [
                                'forecast_source' => 'saved_prophet_model',
                            ],
                            'output' => [
                                'yhat_lower' => isset($row['yhat_lower']) ? (float) $row['yhat_lower'] : null,
                                'yhat_upper' => isset($row['yhat_upper']) ? (float) $row['yhat_upper'] : null,
                            ],
                        ]
                    );
                }
            }
        });

        Log::info('Prophet forecasts stored.', [
            'prediction_date' => $predictionDate,
            'prediction_types' => array_keys($forecasts),
        ]);
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config('services.prophet.url', 'http://127.0.0.1:5001'),
            '/'
        );
    }
}
