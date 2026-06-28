<?php

namespace App\Http\Controllers;

use App\Models\systemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    /**
     * GET api/settings
     */
    public function index()
    {
        $settings = systemSettings::first();

        if (!$settings) {
            return response()->json([
                'schoolInfo' => [
                    'name' => '', 'address' => '', 'year' => '', 'email' => ''
                ],
                'points' => [
                    'conversion' => 5, 'rejected' => -1, 'invalid' => -2, 'nonPet' => -1, 'custom' => -1
                ],
                'notifications' => [
                    'machineFull' => true, 'scannerErrors' => true, 'machineOffline' => true,
                    'maintenance' => true, 'weeklySummary' => false, 'milestones' => true
                ],
                'autoBackup' => true,
                // Also include flat format for frontend convenience
                'school_name' => '',
                'school_address' => '',
                'school_year' => '',
                'school_email' => '',
                'point_conversion' => 5,
                'penalty_rejected' => -1,
                'penalty_invalid' => -2,
                'penalty_non_pet' => -1,
                'penalty_custom' => -1,
                'notify_machine_full' => true,
                'notify_scanner_errors' => true,
                'notify_machine_offline' => true,
                'notify_maintenance' => true,
                'notify_weekly_summary' => false,
                'notify_milestones' => true,
                'auto_backup' => true
            ]);
        }

        return response()->json([
            'schoolInfo' => [
                'name' => $settings->school_name,
                'address' => $settings->school_address,
                'year' => $settings->school_year,
                'email' => $settings->school_email,
            ],
            'points' => [
                'conversion' => $settings->point_conversion,
                'rejected' => $settings->penalty_rejected,
                'invalid' => $settings->penalty_invalid,
                'nonPet' => $settings->penalty_non_pet,
                'custom' => $settings->penalty_custom,
            ],
            'notifications' => [
                'machineFull' => $settings->notify_machine_full,
                'scannerErrors' => $settings->notify_scanner_errors,
                'machineOffline' => $settings->notify_machine_offline,
                'maintenance' => $settings->notify_maintenance,
                'weeklySummary' => $settings->notify_weekly_summary,
                'milestones' => $settings->notify_milestones,
            ],
            'autoBackup' => $settings->auto_backup,
            // Also include flat format for frontend convenience
            'school_name' => $settings->school_name,
            'school_address' => $settings->school_address,
            'school_year' => $settings->school_year,
            'school_email' => $settings->school_email,
            'point_conversion' => $settings->point_conversion,
            'penalty_rejected' => $settings->penalty_rejected,
            'penalty_invalid' => $settings->penalty_invalid,
            'penalty_non_pet' => $settings->penalty_non_pet,
            'penalty_custom' => $settings->penalty_custom,
            'notify_machine_full' => $settings->notify_machine_full,
            'notify_scanner_errors' => $settings->notify_scanner_errors,
            'notify_machine_offline' => $settings->notify_machine_offline,
            'notify_maintenance' => $settings->notify_maintenance,
            'notify_weekly_summary' => $settings->notify_weekly_summary,
            'notify_milestones' => $settings->notify_milestones,
            'auto_backup' => $settings->auto_backup
        ]);
    }

    /**
     * POST api/settings
     */
    public function store(Request $request)
    {
        return $this->updateSettings($request);
    }

    /**
     * PUT/PATCH api/settings
     */
    public function update(Request $request)
    {
        return $this->updateSettings($request);
    }

    /**
     * Helper to update settings
     */
    private function updateSettings(Request $request)
    {
        // Log ALL possible request data for debugging
        Log::info('=== SYSTEM SETTINGS UPDATE DEBUG ===', [
            'method' => $request->method(),
            'all()' => $request->all(),
            'json()' => $request->json()->all(),
            'getContent()' => $request->getContent(),
            'headers' => $request->headers->all(),
        ]);

        // Try to get data from both input() and json()
        $allData = array_merge($request->all(), $request->json()->all());

        // Support both formats: nested (schoolInfo/points) AND flat
        $school_name = $allData['school_name'] ?? ($allData['schoolInfo']['name'] ?? null);
        $school_address = $allData['school_address'] ?? ($allData['schoolInfo']['address'] ?? null);
        $school_year = $allData['school_year'] ?? ($allData['schoolInfo']['year'] ?? null);
        $school_email = $allData['school_email'] ?? ($allData['schoolInfo']['email'] ?? null);

        $point_conversion = $allData['point_conversion'] ?? ($allData['points']['conversion'] ?? 5);
        $penalty_rejected = $allData['penalty_rejected'] ?? ($allData['points']['rejected'] ?? -1);
        $penalty_invalid = $allData['penalty_invalid'] ?? ($allData['points']['invalid'] ?? -2);
        $penalty_non_pet = $allData['penalty_non_pet'] ?? ($allData['points']['nonPet'] ?? -1);
        $penalty_custom = $allData['penalty_custom'] ?? ($allData['points']['custom'] ?? -1);

        $notify_machine_full = $allData['notify_machine_full'] ?? ($allData['notifications']['machineFull'] ?? true);
        $notify_scanner_errors = $allData['notify_scanner_errors'] ?? ($allData['notifications']['scannerErrors'] ?? true);
        $notify_machine_offline = $allData['notify_machine_offline'] ?? ($allData['notifications']['machineOffline'] ?? true);
        $notify_maintenance = $allData['notify_maintenance'] ?? ($allData['notifications']['maintenance'] ?? true);
        $notify_weekly_summary = $allData['notify_weekly_summary'] ?? ($allData['notifications']['weeklySummary'] ?? false);
        $notify_milestones = $allData['notify_milestones'] ?? ($allData['notifications']['milestones'] ?? true);

        $auto_backup = $allData['auto_backup'] ?? ($allData['autoBackup'] ?? true);

        // Convert 0/1 to actual boolean values
        $notify_machine_full = (bool)$notify_machine_full;
        $notify_scanner_errors = (bool)$notify_scanner_errors;
        $notify_machine_offline = (bool)$notify_machine_offline;
        $notify_maintenance = (bool)$notify_maintenance;
        $notify_weekly_summary = (bool)$notify_weekly_summary;
        $notify_milestones = (bool)$notify_milestones;
        $auto_backup = (bool)$auto_backup;

        Log::info('Final data to save', [
            'school_name' => $school_name,
            'point_conversion' => $point_conversion,
            'notify_machine_full' => $notify_machine_full
        ]);

        // Unified updateOrCreate payload
        $settings = systemSettings::updateOrCreate(
            ['setting_id' => 1],
            compact(
                'school_name', 'school_address', 'school_year', 'school_email',
                'point_conversion', 'penalty_rejected', 'penalty_invalid', 'penalty_non_pet', 'penalty_custom',
                'notify_machine_full', 'notify_scanner_errors', 'notify_machine_offline',
                'notify_maintenance', 'notify_weekly_summary', 'notify_milestones',
                'auto_backup'
            )
        );

        Log::info('Updated system settings', ['settings' => $settings->toArray()]);

        return response()->json([
            'success' => true,
            'message' => 'Global configurations updated successfully!',
            'data' => $settings
        ]);
    }
}

