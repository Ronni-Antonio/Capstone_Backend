<?php

namespace App\Console\Commands;

use App\Services\ProphetService;
use Illuminate\Console\Command;

class TrainProphetModels extends Command
{
    protected $signature = 'prophet:train {--forecast-days=30 : Generate and store this many forecast days after successful training}';

    protected $description = 'Retrain all Prophet forecasting models using the latest historical data';

    public function handle(ProphetService $prophetService): int
    {
        $this->info('Starting Prophet model training...');

        try {
            $result = $prophetService->trainAll();
            $models = $result['models'] ?? [];

            foreach ($models as $name => $info) {
                if ($info['trained'] ?? false) {
                    $this->info("[OK] {$name} trained");
                    continue;
                }

                $reason = $info['reason'] ?? $info['error'] ?? 'Unknown reason';
                $this->warn("[SKIP] {$name}: {$reason}");
            }

            if (!empty($result['errors']) || !($result['success'] ?? true)) {
                $this->warn('Training completed with one or more model errors.');
                return self::FAILURE;
            }

            $forecastDays = max(1, min(90, (int) $this->option('forecast-days')));

            $this->info("Prophet training completed successfully. Generating {$forecastDays}-day forecasts...");
            $forecastResult = $prophetService->forecastAll($forecastDays);

            if (!empty($forecastResult['errors']) || !($forecastResult['success'] ?? true)) {
                $this->warn('Models were trained, but one or more forecasts could not be generated.');
                return self::FAILURE;
            }

            $this->info('Prophet forecasts generated and stored successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Prophet training failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
