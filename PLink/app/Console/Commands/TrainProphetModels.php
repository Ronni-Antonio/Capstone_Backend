<?php

namespace App\Console\Commands;

use App\Services\ProphetService;
use Illuminate\Console\Command;

class TrainProphetModels extends Command
{
    protected $signature = 'prophet:train';

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

            $this->info('Prophet training completed successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Prophet training failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
