<?php

namespace App\Http\Controllers;

use App\Services\ProphetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProphetController extends Controller
{
    public function __construct(
        private readonly ProphetService $prophetService
    ) {
    }

    /** Manual/first-deployment training. Weekly retraining is handled by the scheduler. */
    public function train()
    {
        try {
            $result = $this->prophetService->trainAll();

            $success = (bool) ($result['success'] ?? true);

            return response()->json([
                'success' => $success,
                'message' => $success
                    ? 'Prophet models trained successfully.'
                    : 'Prophet training completed with one or more model errors.',
                'models' => $result['models'] ?? [],
                'errors' => $result['errors'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('ProphetController training failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to train Prophet models.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /** Generate predictions from saved models; this does not retrain Prophet. */
    public function forecast(Request $request)
    {
        $validated = $request->validate([
            'periods' => 'nullable|integer|min:1|max:90',
        ]);

        $periods = (int) ($validated['periods'] ?? 7);

        try {
            $result = $this->prophetService->forecastAll($periods);

            $success = (bool) ($result['success'] ?? true);

            return response()->json([
                'success' => $success,
                'message' => $success
                    ? "{$periods}-day forecast generated successfully."
                    : "{$periods}-day forecast completed with one or more model errors.",
                'periods' => $periods,
                'forecasts' => $result['forecasts'] ?? [],
                'errors' => $result['errors'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('ProphetController forecast failed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate Prophet forecasts.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function status()
    {
        try {
            return response()->json($this->prophetService->health());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prophet service is unavailable.',
                'details' => $e->getMessage(),
            ], 502);
        }
    }

    public function models()
    {
        try {
            return response()->json($this->prophetService->models());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve Prophet model information.',
                'details' => $e->getMessage(),
            ], 502);
        }
    }
}
