<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\systemSettings;
use Illuminate\Support\Facades\Cache;

class NotificationPreferenceService
{
    public function enabled(string $setting): bool
    {
        $settings = systemSettings::query()->first();

        // If the settings row has not been created yet, keep alerts enabled by
        // default so important operational events are not silently discarded.
        if (!$settings) {
            return true;
        }

        return (bool) ($settings->{$setting} ?? true);
    }

    public function createIfEnabled(
        string $setting,
        array $attributes,
        int $cooldownMinutes = 0
    ): ?Notification {
        if (!$this->enabled($setting)) {
            return null;
        }

        if ($cooldownMinutes > 0) {
            $query = Notification::query()
                ->where('notification_type', $attributes['notification_type'] ?? 'info')
                ->where('created_at', '>=', now()->subMinutes($cooldownMinutes));

            if (array_key_exists('smart_bin_id', $attributes)) {
                $attributes['smart_bin_id'] === null
                    ? $query->whereNull('smart_bin_id')
                    : $query->where('smart_bin_id', $attributes['smart_bin_id']);
            }

            if (array_key_exists('student_id', $attributes)) {
                $attributes['student_id'] === null
                    ? $query->whereNull('student_id')
                    : $query->where('student_id', $attributes['student_id']);
            }

            // A title lets independent alerts of the same type coexist while
            // still suppressing repeated copies of the exact same warning.
            if (!empty($attributes['title'])) {
                $query->where('title', $attributes['title']);
            }

            if ($query->exists()) {
                return null;
            }
        }

        return Notification::create($attributes);
    }

    /**
     * Scanner errors should represent a repeated failure, not a single transient
     * network hiccup. Three failures inside ten minutes trigger one alert.
     */
    public function recordScannerError(int $smartBinId, string $message): void
    {
        if (!$this->enabled('notify_scanner_errors')) {
            return;
        }

        $key = "plink.scanner-errors.{$smartBinId}";
        $count = ((int) Cache::get($key, 0)) + 1;
        Cache::put($key, $count, now()->addMinutes(10));

        if ($count < 3) {
            return;
        }

        $this->createIfEnabled(
            'notify_scanner_errors',
            [
                'smart_bin_id' => $smartBinId,
                'notification_type' => 'scanner_error',
                'title' => 'Repeated scanner errors detected',
                'message' => 'The recycling classifier failed repeatedly. Check Controller 1, the CNN service, and network connectivity.',
                'data' => [
                    'failure_count' => $count,
                    'latest_error' => $message,
                ],
            ],
            30
        );

        Cache::forget($key);
    }
}
