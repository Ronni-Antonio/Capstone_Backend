<?php

namespace App\Http\Controllers;

use App\Models\AiClassification;
use App\Models\AiModel;
use App\Models\RecyclableType;
use App\Models\RecyclingItem;
use App\Models\RecyclingTransaction;
use App\Models\SmartBin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IotClassificationController extends Controller
{
    /**
     * ESP32 Controller 1 sends one raw JPEG per detected recyclable.
     *
     * Request:
     *   POST /api/iot/classify
     *   Content-Type: image/jpeg
     *   X-Device-Key: <controller-1 secret>
     *
     * Laravel:
     *   1. authenticates Controller 1
     *   2. finds/creates the current unclaimed recycling transaction
     *   3. forwards the JPEG to the private CNN service
     *   4. maps the CNN label to recyclable_types
     *   5. records RecyclingItem + AiClassification
     *   6. returns the running points total and target compartment
     */
    public function classify(Request $request)
    {
        $this->authorizeController1($request);

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        if (!str_starts_with($contentType, 'image/jpeg')) {
            return response()->json([
                'success' => false,
                'message' => 'Controller 1 must send a JPEG body using Content-Type: image/jpeg.',
            ], 415);
        }

        $jpeg = $request->getContent();
        if ($jpeg === '') {
            return response()->json([
                'success' => false,
                'message' => 'Image body is empty.',
            ], 422);
        }

        $maxBytes = (int) config('services.cnn.max_image_bytes', 5242880);
        if (strlen($jpeg) > $maxBytes) {
            return response()->json([
                'success' => false,
                'message' => 'Captured JPEG is too large.',
            ], 413);
        }

        $smartBinId = (int) config('services.iot.controller_1_smart_bin_id', 1);
        $smartBin = SmartBin::find($smartBinId);

        if (!$smartBin) {
            return response()->json([
                'success' => false,
                'message' => "Configured Controller 1 smart_bin_id {$smartBinId} does not exist.",
            ], 422);
        }

        $cnnUrl = rtrim((string) config('services.cnn.url', 'http://127.0.0.1:5002'), '/');

        try {
            $cnnResponse = Http::timeout((int) config('services.cnn.timeout_seconds', 30))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Controller-Code' => 'controller-1',
                ])
                ->withBody($jpeg, 'image/jpeg')
                ->post($cnnUrl . '/predict');
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'CNN service is unavailable.',
            ], 502);
        }

        if (!$cnnResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'CNN service rejected the image.',
                'cnn_status' => $cnnResponse->status(),
                'cnn_response' => $cnnResponse->json() ?: $cnnResponse->body(),
            ], 502);
        }

        $prediction = $cnnResponse->json();
        $rawLabel = trim((string) ($prediction['class'] ?? $prediction['label'] ?? ''));
        $confidence = (float) ($prediction['confidence'] ?? 0);

        // Accept either 0..1 or 0..100 confidence from the Python service.
        if ($confidence <= 1.0) {
            $confidencePercent = $confidence * 100.0;
        } else {
            $confidencePercent = $confidence;
        }

        $minimumConfidence = (float) config('services.cnn.min_confidence', 0.70);
        $minimumConfidencePercent = $minimumConfidence <= 1.0
            ? $minimumConfidence * 100.0
            : $minimumConfidence;

        $type = $this->resolveRecyclableType($rawLabel);
        $isConfident = $confidencePercent >= $minimumConfidencePercent;

        $classificationStatus = ($type && $type->is_active && $isConfident)
            ? ($type->is_accepted ? 'valid' : 'rejected')
            : 'uncertain';

        // Do not award points for inactive, rejected, unknown, or uncertain classes.
        $earnedPoints = (
            $classificationStatus === 'valid' &&
            $type &&
            $type->is_accepted &&
            $type->is_active
        ) ? (int) $type->points_value : 0;

        $transaction = DB::transaction(function () use (
            $smartBin,
            $rawLabel,
            $confidencePercent,
            $type,
            $classificationStatus,
            $earnedPoints
        ) {
            $transaction = $this->getOrCreateOpenTransaction((int) $smartBin->smart_bin_id);

            // Lock the current session before choosing the next item number.
            $transaction = RecyclingTransaction::whereKey($transaction->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            $nextItemNumber = ((int) $transaction->items()->max('item_number')) + 1;

            $item = RecyclingItem::create([
                'transaction_id' => $transaction->transaction_id,
                'item_number' => $nextItemNumber,
                'image_path' => null,
                'weight_kg' => null,
                'status' => $classificationStatus === 'valid' ? 'accepted' : 'rejected',
            ]);

            $model = AiModel::firstOrCreate(
                [
                    'name' => (string) config('services.cnn.model_name', 'Recyclable Classification CNN'),
                    'version' => (string) config('services.cnn.model_version', '1.0.0'),
                ],
                [
                    'framework' => 'TensorFlow/Keras',
                    'is_active' => true,
                ]
            );

            AiClassification::create([
                'recycling_item_id' => $item->recycling_item_id,
                'recyclable_type_id' => $type?->recyclable_type_id,
                'model_id' => $model->model_id,
                'confidence_score' => round($confidencePercent, 2),
                'status' => $classificationStatus,
                'notes' => $type
                    ? "CNN label: {$rawLabel}"
                    : "CNN label was not mapped to a recyclable type: {$rawLabel}",
                'is_verified' => false,
                'classified_at' => now(),
            ]);

            $runningItems = (int) $transaction->items()->count();

            // Total points are calculated from the authoritative current
            // recyclable_types configuration, not from the ESP32.
            $runningPoints = (int) RecyclingItem::query()
                ->where('transaction_id', $transaction->transaction_id)
                ->whereHas('classification', function ($query) {
                    $query->where('status', 'valid');
                })
                ->with('classification.recyclableType')
                ->get()
                ->sum(function ($sessionItem) {
                    $classification = $sessionItem->classification;
                    $sessionType = $classification?->recyclableType;

                    return ($sessionType && $sessionType->is_active && $sessionType->is_accepted)
                        ? (int) $sessionType->points_value
                        : 0;
                });

            $transaction->update([
                'status' => 'waiting_for_rfid',
                'total_items' => $runningItems,
                'total_points' => $runningPoints,
            ]);

            $smartBin->update([
                'last_active_at' => now(),
                'status' => 'online',
            ]);

            return $transaction->fresh();
        });

        $compartment = $this->compartmentFor($type, $classificationStatus);

        return response()->json([
            'success' => true,
            'classification' => [
                'label' => $rawLabel,
                'mapped_type_id' => $type?->recyclable_type_id,
                'mapped_type_name' => $type?->name,
                'confidence' => round($confidencePercent / 100, 4),
                'confidence_percent' => round($confidencePercent, 2),
                'status' => $classificationStatus,
                'accepted' => $classificationStatus === 'valid',
                'points' => $earnedPoints,
                'compartment' => $compartment,
            ],
            'session' => [
                'transaction_code' => $transaction->transaction_code,
                'items' => (int) $transaction->total_items,
                'pending_points' => (int) $transaction->total_points,
                'status' => $transaction->status,
            ],
        ]);
    }

    private function getOrCreateOpenTransaction(int $smartBinId): RecyclingTransaction
    {
        $timeoutSeconds = max(30, (int) config('services.iot.deposit_session_timeout_seconds', 180));
        $cutoff = now()->subSeconds($timeoutSeconds);

        // Expire abandoned sessions before creating/reusing a current one.
        RecyclingTransaction::query()
            ->where('smart_bin_id', $smartBinId)
            ->whereNull('student_id')
            ->whereIn('status', ['classifying', 'waiting_for_rfid'])
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

        $existing = RecyclingTransaction::query()
            ->where('smart_bin_id', $smartBinId)
            ->whereNull('student_id')
            ->whereIn('status', ['classifying', 'waiting_for_rfid'])
            ->where('updated_at', '>=', $cutoff)
            ->latest('transaction_id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return RecyclingTransaction::create([
            'student_id' => null,
            'rfid_card_id' => null,
            'smart_bin_id' => $smartBinId,
            'transaction_code' => (string) Str::uuid(),
            'status' => 'classifying',
            'total_items' => 0,
            'total_points' => 0,
            'started_at' => now(),
        ]);
    }

    private function resolveRecyclableType(string $rawLabel): ?RecyclableType
    {
        if ($rawLabel === '') {
            return null;
        }

        $normalized = strtoupper(trim(preg_replace('/[_\-]+/', ' ', $rawLabel)));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Aliases cover common CNN class-name styles.
        $aliases = [
            'PET' => 'PET Bottle',
            'PET BOTTLE' => 'PET Bottle',
            'HDPE' => 'HDPE Bottle',
            'HDPE BOTTLE' => 'HDPE Bottle',
            'PVC' => 'PVC Bottle',
            'PVC BOTTLE' => 'PVC Bottle',
            'LDPE' => 'LDPE Bottle',
            'LDPE BOTTLE' => 'LDPE Bottle',
            'PP' => 'PP Bottle',
            'PP BOTTLE' => 'PP Bottle',
            'PS' => 'PS Bottle',
            'PS BOTTLE' => 'PS Bottle',
            'PC' => 'PC Bottle',
            'PC BOTTLE' => 'PC Bottle',
            'PLA' => 'PLA Plastic',
            'PLA PLASTIC' => 'PLA Plastic',
            'PAPER' => 'White Paper',
            'WHITE PAPER' => 'White Paper',
            'DIRTY' => 'Dirty / Damaged Recyclable Material',
            'DAMAGED' => 'Dirty / Damaged Recyclable Material',
            'DEMOLISHED' => 'Dirty / Damaged Recyclable Material',
            'CONTAMINATED' => 'Dirty / Damaged Recyclable Material',
            'DIRTY / DAMAGED RECYCLABLE MATERIAL' => 'Dirty / Damaged Recyclable Material',
            'INVALID' => 'Invalid / Non-PET',
            'REJECTED' => 'Invalid / Non-PET',
            'NON PET' => 'Invalid / Non-PET',
            'NON-PET' => 'Invalid / Non-PET',
        ];

        $targetName = $aliases[$normalized] ?? $rawLabel;

        return RecyclableType::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($targetName)])
            ->orWhereRaw('LOWER(code) = ?', [strtolower($rawLabel)])
            ->first();
    }

    private function compartmentFor(?RecyclableType $type, string $classificationStatus): string
    {
        if (!$type || $classificationStatus !== 'valid') {
            return 'reject';
        }

        return match (strtolower((string) $type->material_category)) {
            'paper' => 'paper',
            'plastic' => 'plastic',
            default => 'reject',
        };
    }

    private function authorizeController1(Request $request): void
    {
        $expected = (string) config('services.iot.controller_1_key');
        $provided = (string) $request->header('X-Device-Key', '');

        abort_if(
            $expected === '' ||
            $provided === '' ||
            !hash_equals($expected, $provided),
            401,
            'Invalid Controller 1 device key.'
        );
    }
}
