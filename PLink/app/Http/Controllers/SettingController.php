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
        $schoolInfo = $allData['schoolInfo'] ?? [];
        $notifications = $allData['notifications'] ?? [];

        // Support both formats: nested (schoolInfo/points) AND flat
        $school_name = $allData['school_name'] ?? ($schoolInfo['name'] ?? null);
        $school_address = $allData['school_address'] ?? ($schoolInfo['address'] ?? null);
        $school_year = $allData['school_year'] ?? ($schoolInfo['year'] ?? null);
        $school_email = $allData['school_email'] ?? ($schoolInfo['email'] ?? null);

        $notify_machine_full = $allData['notify_machine_full'] ?? ($notifications['machineFull'] ?? true);
        $notify_scanner_errors = $allData['notify_scanner_errors'] ?? ($notifications['scannerErrors'] ?? true);
        $notify_machine_offline = $allData['notify_machine_offline'] ?? ($notifications['machineOffline'] ?? true);
        $notify_maintenance = $allData['notify_maintenance'] ?? ($notifications['maintenance'] ?? true);
        $notify_weekly_summary = $allData['notify_weekly_summary'] ?? ($notifications['weeklySummary'] ?? false);
        $notify_milestones = $allData['notify_milestones'] ?? ($notifications['milestones'] ?? true);

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
            'notify_machine_full' => $notify_machine_full
        ]);

        // Unified updateOrCreate payload
        $settings = systemSettings::updateOrCreate(
            ['setting_id' => 1],
            compact(
                'school_name', 'school_address', 'school_year', 'school_email',
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
