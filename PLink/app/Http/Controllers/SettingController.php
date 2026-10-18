<?php

namespace App\Http\Controllers;

use App\Models\systemSettings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = $this->getOrCreateSettings();

        return response()->json($this->payload($settings));
    }

    public function store(Request $request)
    {
        return $this->updateSettings($request);
    }

    public function update(Request $request)
    {
        return $this->updateSettings($request);
    }

    private function updateSettings(Request $request)
    {
        $settings = $this->getOrCreateSettings();
        $allData = array_merge($request->all(), $request->json()->all());
        $schoolInfo = is_array($allData['schoolInfo'] ?? null)
            ? $allData['schoolInfo']
            : [];
        $notifications = is_array($allData['notifications'] ?? null)
            ? $allData['notifications']
            : [];

        $updates = [];

        $this->copyIfProvided(
            $updates,
            'school_name',
            $allData,
            'school_name',
            $schoolInfo,
            'name'
        );
        $this->copyIfProvided(
            $updates,
            'school_address',
            $allData,
            'school_address',
            $schoolInfo,
            'address'
        );
        $this->copyIfProvided(
            $updates,
            'school_year',
            $allData,
            'school_year',
            $schoolInfo,
            'year'
        );
        $this->copyIfProvided(
            $updates,
            'school_email',
            $allData,
            'school_email',
            $schoolInfo,
            'email'
        );

        $notificationMap = [
            'notify_machine_full' => 'machineFull',
            'notify_scanner_errors' => 'scannerErrors',
            'notify_machine_offline' => 'machineOffline',
            'notify_maintenance' => 'maintenance',
            'notify_milestones' => 'milestones',
        ];

        foreach ($notificationMap as $field => $nestedKey) {
            if (array_key_exists($field, $allData)) {
                $updates[$field] = (bool) $allData[$field];
            } elseif (array_key_exists($nestedKey, $notifications)) {
                $updates[$field] = (bool) $notifications[$nestedKey];
            }
        }

        if (array_key_exists('auto_backup', $allData)) {
            $updates['auto_backup'] = (bool) $allData['auto_backup'];
        } elseif (array_key_exists('autoBackup', $allData)) {
            $updates['auto_backup'] = (bool) $allData['autoBackup'];
        }

        if ($updates) {
            $settings->update($updates);
            $settings->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'Global configurations updated successfully!',
            'data' => $this->payload($settings),
        ]);
    }

    private function getOrCreateSettings(): systemSettings
    {
        return systemSettings::firstOrCreate(
            ['setting_id' => 1],
            [
                'school_name' => '',
                'school_address' => '',
                'school_year' => '',
                'school_email' => '',
                'notify_machine_full' => true,
                'notify_scanner_errors' => true,
                'notify_machine_offline' => true,
                'notify_maintenance' => true,
                'notify_milestones' => true,
                'auto_backup' => true,
            ]
        );
    }

    private function payload(systemSettings $settings): array
    {
        return [
            'schoolInfo' => [
                'name' => $settings->school_name,
                'address' => $settings->school_address,
                'year' => $settings->school_year,
                'email' => $settings->school_email,
            ],
            'notifications' => [
                'machineFull' => (bool) $settings->notify_machine_full,
                'scannerErrors' => (bool) $settings->notify_scanner_errors,
                'machineOffline' => (bool) $settings->notify_machine_offline,
                'maintenance' => (bool) $settings->notify_maintenance,
                'milestones' => (bool) $settings->notify_milestones,
            ],
            'autoBackup' => (bool) $settings->auto_backup,
            'school_name' => $settings->school_name,
            'school_address' => $settings->school_address,
            'school_year' => $settings->school_year,
            'school_email' => $settings->school_email,
            'notify_machine_full' => (bool) $settings->notify_machine_full,
            'notify_scanner_errors' => (bool) $settings->notify_scanner_errors,
            'notify_machine_offline' => (bool) $settings->notify_machine_offline,
            'notify_maintenance' => (bool) $settings->notify_maintenance,
            'notify_milestones' => (bool) $settings->notify_milestones,
            'auto_backup' => (bool) $settings->auto_backup,
        ];
    }

    private function copyIfProvided(
        array &$updates,
        string $target,
        array $flat,
        string $flatKey,
        array $nested,
        string $nestedKey
    ): void {
        if (array_key_exists($flatKey, $flat)) {
            $updates[$target] = $flat[$flatKey];
        } elseif (array_key_exists($nestedKey, $nested)) {
            $updates[$target] = $nested[$nestedKey];
        }
    }
}
