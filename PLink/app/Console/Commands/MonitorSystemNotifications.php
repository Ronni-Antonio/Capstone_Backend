<?php

namespace App\Console\Commands;

use App\Models\IotDeviceConfig;
use App\Models\SmartBin;
use App\Services\NotificationPreferenceService;
use Illuminate\Console\Command;

class MonitorSystemNotifications extends Command
{
    protected $signature = 'notifications:monitor';

    protected $description = 'Generate enabled machine-offline and maintenance reminder notifications';

    public function handle(NotificationPreferenceService $notifications): int
    {
        $smartBinId = (int) config('services.iot.controller_1_smart_bin_id', 1);
        $bin = SmartBin::find($smartBinId);

        if (!$bin) {
            $this->warn("Smart bin {$smartBinId} was not found.");
            return self::SUCCESS;
        }

        // Controller 1 polls Laravel periodically, and those requests update
        // iot_device_configs.last_seen_at. This is a better connectivity signal
        // than waiting for a recyclable to be deposited.
        $controller = IotDeviceConfig::query()
            ->where('controller_code', 'controller-1')
            ->first();

        $offline = !$controller?->last_seen_at
            || $controller->last_seen_at->lt(now()->subMinutes(3));

        if ($offline) {
            $notifications->createIfEnabled(
                'notify_machine_offline',
                [
                    'smart_bin_id' => $bin->smart_bin_id,
                    'notification_type' => 'machine_offline',
                    'title' => $bin->name . ' is offline',
                    'message' => "{$bin->name} at {$bin->location} has not contacted the backend for more than 3 minutes.",
                    'data' => [
                        'controller_code' => 'controller-1',
                        'last_seen_at' => optional($controller?->last_seen_at)->toISOString(),
                    ],
                ],
                30
            );

            if ($bin->status !== 'maintenance') {
                $bin->update(['status' => 'offline']);
            }
        } elseif ($bin->status === 'offline') {
            // A fresh Controller 1 heartbeat means the machine has recovered.
            $bin->update(['status' => 'online']);
        }

        $maintenanceDue = !$bin->last_maintenance_at
            || $bin->last_maintenance_at->lt(now()->subDays(30));

        if ($maintenanceDue) {
            $notifications->createIfEnabled(
                'notify_maintenance',
                [
                    'smart_bin_id' => $bin->smart_bin_id,
                    'notification_type' => 'maintenance_reminder',
                    'title' => 'Smart bin maintenance reminder',
                    'message' => "{$bin->name} at {$bin->location} is due for its routine maintenance check.",
                    'data' => [
                        'last_maintenance_at' => optional($bin->last_maintenance_at)->toISOString(),
                        'recommended_interval_days' => 30,
                    ],
                ],
                7 * 24 * 60
            );
        }

        return self::SUCCESS;
    }
}
