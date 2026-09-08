<?php

namespace App\Http\Controllers;

use App\Models\IotControllerCommand;
use App\Models\IotDeviceConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IotControllerCommandController extends Controller
{
    /**
     * ESP32 polls this endpoint. It returns at most one outstanding command.
     * Claimed commands are intentionally returned again until ACKed so a lost
     * HTTP response or ESP32 reboot does not permanently lose the operation.
     */
    public function next(Request $request, string $controllerCode)
    {
        $this->authorizeDevice($request, $controllerCode);

        IotDeviceConfig::where('controller_code', $controllerCode)
            ->update(['last_seen_at' => now()]);

        IotControllerCommand::query()
            ->where('controller_code', $controllerCode)
            ->whereIn('status', ['pending', 'claimed'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'result_message' => 'Command expired before completion.',
                'completed_at' => now(),
            ]);

        $command = DB::transaction(function () use ($controllerCode) {
            $row = IotControllerCommand::query()
                ->where('controller_code', $controllerCode)
                ->whereIn('status', ['pending', 'claimed'])
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderBy('command_id')
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            if ($row->status === 'pending') {
                $row->update([
                    'status' => 'claimed',
                    'claimed_at' => now(),
                ]);
                $row->refresh();
            }

            return $row;
        });

        if (!$command) {
            return response()->json([
                'has_command' => false,
                'command' => null,
            ]);
        }

        return response()->json([
            'has_command' => true,
            'command' => [
                'command_id' => (int) $command->command_id,
                'controller_code' => $command->controller_code,
                'command_type' => $command->command_type,
                'payload' => $command->payload ?? [],
                'status' => $command->status,
                'expires_at' => optional($command->expires_at)->toISOString(),
            ],
        ]);
    }

    public function acknowledge(Request $request, string $controllerCode, string $commandId)
    {
        $this->authorizeDevice($request, $controllerCode);

        $validated = $request->validate([
            'status' => 'required|in:completed,failed,cancelled',
            'message' => 'nullable|string|max:2000',
        ]);

        $command = IotControllerCommand::query()
            ->where('controller_code', $controllerCode)
            ->where('command_id', $commandId)
            ->firstOrFail();

        // ACK is idempotent. A duplicate ACK after a retry returns success.
        if (in_array($command->status, ['completed', 'failed', 'cancelled', 'expired'], true)) {
            return response()->json([
                'success' => true,
                'command_id' => (int) $command->command_id,
                'status' => $command->status,
            ]);
        }

        $command->update([
            'status' => $validated['status'],
            'result_message' => $validated['message'] ?? null,
            'completed_at' => now(),
        ]);

        IotDeviceConfig::where('controller_code', $controllerCode)
            ->update(['last_seen_at' => now()]);

        return response()->json([
            'success' => true,
            'command_id' => (int) $command->command_id,
            'status' => $command->status,
        ]);
    }

    private function authorizeDevice(Request $request, string $controllerCode): void
    {
        $expected = match ($controllerCode) {
            'controller-1' => (string) config('services.iot.controller_1_key'),
            'controller-2' => (string) config('services.iot.controller_2_key'),
            default => '',
        };

        $provided = (string) $request->header('X-Device-Key', '');

        abort_if(
            $expected === '' || $provided === '' || !hash_equals($expected, $provided),
            401,
            'Invalid device key.'
        );
    }
}
