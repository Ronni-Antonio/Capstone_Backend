<?php

namespace App\Http\Controllers;

use App\Models\systemSettings;
use Illuminate\Http\Request;

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
                'autoBackup' => true
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
            'autoBackup' => $settings->auto_backup
        ]);
    }

    /**
     * POST api/settings
     */
    public function store(Request $request)
    {
        $schoolInfo = $request->input('schoolInfo', []);
        $points = $request->input('points', []);
        $notifications = $request->input('notifications', []);
        $autoBackup = $request->input('autoBackup', true);

        // Unified updateOrCreate payload
        $settings = systemSettings::updateOrCreate(
            ['setting_id' => 1],
            [
                'school_name' => $schoolInfo['name'] ?? null,
                'school_address' => $schoolInfo['address'] ?? null,
                'school_year' => $schoolInfo['year'] ?? null,
                'school_email' => $schoolInfo['email'] ?? null,
                
                'point_conversion' => $points['conversion'] ?? 5,
                'penalty_rejected' => $points['rejected'] ?? -1,
                'penalty_invalid' => $points['invalid'] ?? -2,
                'penalty_non_pet' => $points['nonPet'] ?? -1,
                'penalty_custom' => $points['custom'] ?? -1, // FIXED: Matches your migration column perfectly
                
                'notify_machine_full' => $notifications['machineFull'] ?? true,
                'notify_scanner_errors' => $notifications['scannerErrors'] ?? true,
                'notify_machine_offline' => $notifications['machineOffline'] ?? true,
                'notify_maintenance' => $notifications['maintenance'] ?? true,
                'notify_weekly_summary' => $notifications['weeklySummary'] ?? false,
                'notify_milestones' => $notifications['milestones'] ?? true,
                
                'auto_backup' => $autoBackup,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Global configurations updated successfully!',
            'data' => $settings
        ]);
    }
}