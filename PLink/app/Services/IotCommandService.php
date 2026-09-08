<?php

namespace App\Services;

use App\Models\IotControllerCommand;
use Illuminate\Support\Facades\DB;

class IotCommandService
{
    /**
     * Controller 2 has one RFID reader, so only one interactive command should
     * be outstanding at a time. Queueing a new command cancels any older
     * pending/claimed command for the same controller.
     */
    public function queue(
        string $controllerCode,
        string $commandType,
        array $payload = [],
        int $ttlSeconds = 120
    ): IotControllerCommand {
        return DB::transaction(function () use ($controllerCode, $commandType, $payload, $ttlSeconds) {
            IotControllerCommand::query()
                ->where('controller_code', $controllerCode)
                ->whereIn('status', ['pending', 'claimed'])
                ->update([
                    'status' => 'cancelled',
                    'result_message' => 'Superseded by a newer controller command.',
                    'completed_at' => now(),
                ]);

            return IotControllerCommand::create([
                'controller_code' => $controllerCode,
                'command_type' => $commandType,
                'payload' => $payload,
                'status' => 'pending',
                'expires_at' => now()->addSeconds($ttlSeconds),
            ]);
        });
    }

    public function cancelOutstanding(string $controllerCode, ?string $commandType = null): int
    {
        $query = IotControllerCommand::query()
            ->where('controller_code', $controllerCode)
            ->whereIn('status', ['pending', 'claimed']);

        if ($commandType !== null) {
            $query->where('command_type', $commandType);
        }

        return $query->update([
            'status' => 'cancelled',
            'result_message' => 'Cancelled by the application.',
            'completed_at' => now(),
        ]);
    }
}
