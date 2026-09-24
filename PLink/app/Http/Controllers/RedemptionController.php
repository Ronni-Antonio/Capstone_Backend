<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\IotControllerCommand;
use App\Models\PointTransaction;
use App\Models\Redemptions;
use App\Models\Rewards;
use App\Models\RfidCard;
use App\Models\Students;
use App\Services\IotCommandService;
use App\Services\RewardStockAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RedemptionController extends Controller
{
    public function index()
    {
        return response()->json(
            Redemptions::with(['student', 'reward'])
                ->latest('redeemed_at')
                ->get()
        );
    }

    public function show(string $id)
    {
        return response()->json(
            Redemptions::with(['student', 'reward'])->findOrFail($id)
        );
    }

    /**
     * Finalizes a redemption after Controller 2 reads the RFID card.
     *
     * For IoT redemption flow, command_id is required by the ESP32 and is used
     * as the idempotency key. If the same command is retried, points and stock
     * are NOT deducted a second time.
     */
    public function store(Request $request, RewardStockAlertService $stockAlerts)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,student_id',
            'card_uid' => 'required|string|max:100',
            'reward_id' => 'nullable|exists:rewards,reward_id',
            'reward_ids' => 'nullable|array|min:1',
            'reward_ids.*' => 'integer|exists:rewards,reward_id',
            'command_id' => 'nullable|integer|exists:iot_controller_commands,command_id',
        ]);

        $rewardIds = $validated['reward_ids']
            ?? (isset($validated['reward_id']) ? [(int) $validated['reward_id']] : []);

        if (!$rewardIds) {
            return response()->json([
                'error' => 'Provide reward_id or reward_ids.',
            ], 422);
        }

        $commandId = isset($validated['command_id'])
            ? (int) $validated['command_id']
            : null;

        try {
            $result = DB::transaction(function () use ($validated, $rewardIds, $commandId, $request, $stockAlerts) {
                $command = null;

                if ($commandId !== null) {
                    $command = IotControllerCommand::query()
                        ->where('command_id', $commandId)
                        ->where('controller_code', 'controller-2')
                        ->where('command_type', 'confirm_redemption')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $payload = $command->payload ?? [];
                    $payloadStudentId = (int) ($payload['student_id'] ?? 0);
                    $payloadRewardId = (int) ($payload['reward_id'] ?? 0);

                    if (
                        $payloadStudentId !== (int) $validated['student_id'] ||
                        count($rewardIds) !== 1 ||
                        $payloadRewardId !== (int) $rewardIds[0]
                    ) {
                        throw ValidationException::withMessages([
                            'command_id' => ['The redemption command does not match the submitted student/reward.'],
                        ]);
                    }

                    // Critical idempotency guard: a completed command never redeems again.
                    if ($command->status === 'completed') {
                        return [
                            'already_processed' => true,
                            'command_id' => $commandId,
                            'command_status' => 'completed',
                        ];
                    }

                    if (in_array($command->status, ['failed', 'cancelled', 'expired'], true)) {
                        throw ValidationException::withMessages([
                            'command_id' => ["This redemption command is already {$command->status}."],
                        ]);
                    }
                }

                $student = Students::lockForUpdate()->findOrFail($validated['student_id']);

                $card = RfidCard::query()
                    ->where('student_id', $student->student_id)
                    ->where('card_uid', strtoupper(trim($validated['card_uid'])))
                    ->where('status', 'active')
                    ->first();

                if (!$card) {
                    throw ValidationException::withMessages([
                        'card_uid' => ['RFID card does not belong to this student.'],
                    ]);
                }

                $counts = array_count_values(array_map('intval', $rewardIds));

                $rewards = Rewards::query()
                    ->whereIn('reward_id', array_keys($counts))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('reward_id');

                $total = 0;

                foreach ($counts as $rewardId => $quantity) {
                    $reward = $rewards->get($rewardId);

                    if (!$reward) {
                        throw ValidationException::withMessages([
                            'reward_id' => ["Reward {$rewardId} was not found."],
                        ]);
                    }

                    if (!$reward->is_active) {
                        throw ValidationException::withMessages([
                            'reward_id' => ["Reward \"{$reward->reward_name}\" is currently deactivated and cannot be claimed."],
                        ]);
                    }

                    if ($reward->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'reward_id' => ["Insufficient stock for reward {$rewardId}."],
                        ]);
                    }

                    $total += $reward->points_cost * $quantity;
                }

                if ($student->points_balance < $total) {
                    throw ValidationException::withMessages([
                        'student_id' => ['Insufficient points balance.'],
                    ]);
                }

                $group = (string) Str::uuid();
                $created = [];
                $redeemedItems = [];

                foreach ($counts as $rewardId => $quantity) {
                    $reward = $rewards[$rewardId];

                    for ($i = 0; $i < $quantity; $i++) {
                        $redemption = Redemptions::create([
                            'student_id' => $student->student_id,
                            'reward_id' => $rewardId,
                            'redemption_code' => (string) Str::uuid(),
                            'points_spent' => $reward->points_cost,
                            'redeemed_at' => now(),
                        ]);

                        PointTransaction::create([
                            'student_id' => $student->student_id,
                            'redemption_id' => $redemption->redemption_id,
                            'points' => -$reward->points_cost,
                            'transaction_type' => 'redeemed',
                            'description' => "Reward redemption group {$group}",
                        ]);

                        $created[] = $redemption;
                    }

                    $oldStock = (int) $reward->stock_quantity;
                    $oldThreshold = (int) ($reward->low_stock_threshold ?? 10);
                    $reward->decrement('stock_quantity', $quantity);
                    $reward->refresh();
                    $stockAlerts->sync($reward, $oldStock, $oldThreshold);

                    $redeemedItems[] = [
                        'name' => $reward->reward_name,
                        'qty' => $quantity,
                        'points' => $reward->points_cost,
                    ];
                }

                $student->decrement('points_balance', $total);

                $studentName = trim(
                    ($student->first_name ?? '') . ' ' . ($student->last_name ?? '')
                ) ?: "Student #{$student->student_id}";

                foreach ($redeemedItems as $item) {
                    ActivityLog::record(
                        'REDEEM_REWARD',
                        "{$studentName} redeemed \"{$item['name']}\" for {$item['points']} points (qty: {$item['qty']}).",
                        'Redemptions',
                        $request->user()?->id,
                        $student->student_id,
                        [
                            'redemption_group' => $group,
                            'reward_name' => $item['name'],
                            'quantity' => $item['qty'],
                            'points_spent_per_item' => $item['points'],
                            'total_group_points' => $total,
                            'iot_command_id' => $commandId,
                        ]
                    );
                }

                ActivityLog::record(
                    'POINTS_DEDUCTED',
                    "{$studentName} had {$total} points deducted via redemption group {$group}.",
                    'Points',
                    $request->user()?->id,
                    $student->student_id,
                    [
                        'points' => -$total,
                        'redemption_group' => $group,
                        'iot_command_id' => $commandId,
                    ]
                );

                // Mark the exact controller command complete in the SAME transaction
                // as the redemption. This is what prevents duplicate deductions.
                if ($command) {
                    $command->update([
                        'status' => 'completed',
                        'result_message' => json_encode([
                            'message' => 'Reward redemption completed successfully.',
                            'redemption_group' => $group,
                            'redemption_ids' => collect($created)
                                ->pluck('redemption_id')
                                ->values()
                                ->all(),
                        ]),
                        'completed_at' => now(),
                    ]);
                }

                return [
                    'already_processed' => false,
                    'command_id' => $commandId,
                    'command_status' => $command ? 'completed' : null,
                    'redemption_group' => $group,
                    'total_points_spent' => $total,
                    'redemptions' => collect($created)
                        ->map(fn ($redemption) => $redemption->load('reward'))
                        ->values(),
                    'student' => $student->fresh(),
                ];
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        // Same command retried after an ambiguous network response.
        if (($result['already_processed'] ?? false) === true) {
            return response()->json([
                'success' => true,
                'already_processed' => true,
                'command_id' => $result['command_id'],
                'status' => 'completed',
                'message' => 'This redemption command was already completed. No additional points or stock were deducted.',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'already_processed' => false,
            'command_id' => $result['command_id'],
            'status' => $result['command_status'] ?? 'completed',
            'redemption_group' => $result['redemption_group'],
            'total_points_spent' => $result['total_points_spent'],
            'redemptions' => $result['redemptions'],
            'student' => $result['student'],
        ], 201);
    }

    /**
     * Queue one exact Controller 2 confirmation command.
     */
    public function initiateRedemptionProcess($student_id, $reward_id, IotCommandService $commands)
    {
        $student = Students::findOrFail($student_id);
        $reward = Rewards::findOrFail($reward_id);

        if (!$reward->is_active) {
            return response()->json([
                'error' => 'This reward is currently deactivated and cannot be claimed.',
            ], 422);
        }

        if ($student->points_balance < $reward->points_cost) {
            return response()->json([
                'error' => 'Insufficient points',
            ], 422);
        }

        if ($reward->stock_quantity < 1) {
            return response()->json([
                'error' => 'This reward is currently out of stock.',
            ], 422);
        }

        $command = $commands->queue(
            'controller-2',
            'confirm_redemption',
            [
                'student_id' => (int) $student->student_id,
                'reward_id' => (int) $reward->reward_id,
                'reward_name' => $reward->reward_name,
                'points_cost' => (int) $reward->points_cost,
            ],
            180
        );

        return response()->json([
            'message' => 'Redemption confirmation queued. Please tap the student RFID card on Controller 2.',
            'student_id' => (int) $student->student_id,
            'reward_id' => (int) $reward->reward_id,
            'command_id' => (int) $command->command_id,
            'status' => $command->status,
        ]);
    }

    /**
     * Poll the EXACT redemption attempt by command_id.
     *
     * This replaces the old "any matching redemption in the last 2 minutes"
     * logic, which could mistake an older redemption for the current attempt.
     */
    public function checkRedemptionStatus(Request $request, $student_id, $reward_id)
    {
        $validated = $request->validate([
            'command_id' => 'required|integer|exists:iot_controller_commands,command_id',
        ]);

        $command = IotControllerCommand::query()
            ->where('command_id', (int) $validated['command_id'])
            ->where('controller_code', 'controller-2')
            ->where('command_type', 'confirm_redemption')
            ->firstOrFail();

        $payload = $command->payload ?? [];

        if (
            (int) ($payload['student_id'] ?? 0) !== (int) $student_id ||
            (int) ($payload['reward_id'] ?? 0) !== (int) $reward_id
        ) {
            return response()->json([
                'error' => 'The command does not belong to this student/reward.',
            ], 409);
        }

        $completed = $command->status === 'completed';
        $terminalFailure = in_array(
            $command->status,
            ['failed', 'cancelled', 'expired'],
            true
        );

        return response()->json([
            'status' => $command->status,
            'completed' => $completed,
            'failed' => $terminalFailure,
            'command_id' => (int) $command->command_id,
            'message' => $command->result_message,
        ]);
    }

    /**
     * Cancel one exact queued redemption command.
     * This prevents a stale command from redeeming on a later RFID tap.
     */
    public function cancelRedemptionProcess(string $command_id)
    {
        $command = IotControllerCommand::query()
            ->where('command_id', $command_id)
            ->where('controller_code', 'controller-2')
            ->where('command_type', 'confirm_redemption')
            ->firstOrFail();

        if ($command->status === 'completed') {
            return response()->json([
                'success' => false,
                'status' => 'completed',
                'message' => 'The redemption has already completed and cannot be cancelled.',
            ], 409);
        }

        if (in_array($command->status, ['cancelled', 'failed', 'expired'], true)) {
            return response()->json([
                'success' => true,
                'status' => $command->status,
                'message' => 'The redemption command is already closed.',
            ]);
        }

        $command->update([
            'status' => 'cancelled',
            'result_message' => 'Redemption cancelled by the application.',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => 'cancelled',
            'command_id' => (int) $command->command_id,
            'message' => 'Redemption cancelled successfully.',
        ]);
    }

    public function update(Request $request, string $id)
    {
        return response()->json([
            'error' => 'Redemptions are immutable.',
        ], 405);
    }

    public function destroy(string $id)
    {
        return response()->json([
            'error' => 'Redemptions are immutable.',
        ], 405);
    }
}
