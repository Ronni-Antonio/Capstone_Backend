<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PointTransaction;
use App\Models\RecyclingTransaction;
use App\Models\RfidCard;
use App\Models\Students;
use Illuminate\Support\Facades\DB;

class RecyclingClaimService
{
    /**
     * Claims the most recent Controller-1 recycling transaction when a student
     * taps their active RFID card on Controller 2.
     *
     * Returns null when there is no recent pending recycling transaction,
     * allowing the same RFID tap to behave as a normal student-identification tap.
     */
    public function claimLatestForCard(string $cardUid): ?array
    {
        $normalizedUid = strtoupper(trim($cardUid));
        $smartBinId = (int) config('services.iot.controller_1_smart_bin_id', 1);
        $timeoutSeconds = max(30, (int) config('services.iot.deposit_claim_timeout_seconds', 300));
        $cutoff = now()->subSeconds($timeoutSeconds);

        return DB::transaction(function () use ($normalizedUid, $smartBinId, $cutoff) {
            $card = RfidCard::with('student')
                ->where('card_uid', $normalizedUid)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$card) {
                return null;
            }

            $transaction = RecyclingTransaction::query()
                ->where('smart_bin_id', $smartBinId)
                ->whereNull('student_id')
                ->where('status', 'waiting_for_rfid')
                ->where('total_items', '>', 0)
                ->where('updated_at', '>=', $cutoff)
                ->latest('transaction_id')
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return null;
            }

            $student = Students::lockForUpdate()->findOrFail($card->student_id);

            // Recalculate from current DB relationships as a final integrity check.
            $items = $transaction->items()
                ->with('classification.recyclableType')
                ->get();

            $totalPoints = (int) $items->sum(function ($item) {
                $classification = $item->classification;
                $type = $classification?->recyclableType;

                if (!$classification || $classification->status !== 'valid') {
                    return 0;
                }

                return ($type && $type->is_active && $type->is_accepted)
                    ? (int) $type->points_value
                    : 0;
            });

            $transaction->update([
                'student_id' => $student->student_id,
                'rfid_card_id' => $card->rfid_card_id,
                'total_items' => $items->count(),
                'total_points' => $totalPoints,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            if ($totalPoints > 0) {
                PointTransaction::create([
                    'student_id' => $student->student_id,
                    'recycling_transaction_id' => $transaction->transaction_id,
                    'points' => $totalPoints,
                    'transaction_type' => 'earned',
                    'description' => 'Points claimed by RFID for recycling transaction ' . $transaction->transaction_code,
                ]);

                $student->increment('points_balance', $totalPoints);
            }

            $student = $student->fresh();

            $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: 'Student';

            ActivityLog::record(
                'RECYCLING_SESSION_CLAIMED',
                "{$studentName} claimed {$totalPoints} recycling point(s) for {$items->count()} classified item(s).",
                'Machine',
                null,
                $student->student_id,
                [
                    'transaction_code' => $transaction->transaction_code,
                    'items' => $items->count(),
                    'points' => $totalPoints,
                ]
            );

            if ($totalPoints > 0) {
                ActivityLog::record(
                    'POINTS_ADDED',
                    "{$studentName} earned {$totalPoints} points from recycling.",
                    'Points',
                    null,
                    $student->student_id,
                    [
                        'transaction_code' => $transaction->transaction_code,
                        'points' => $totalPoints,
                    ]
                );
            }

            return [
                'claimed' => true,
                'transaction_code' => $transaction->transaction_code,
                'items' => $items->count(),
                'points_awarded' => $totalPoints,
                'new_points_balance' => (int) $student->points_balance,
            ];
        });
    }
}
