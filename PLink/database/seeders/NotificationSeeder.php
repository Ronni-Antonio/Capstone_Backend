<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\SmartBin;
use App\Models\Students;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $smartBin = SmartBin::first();
        $student = Students::first();

        /*
        |--------------------------------------------------------------------------
        | Stop if required records do not exist
        |--------------------------------------------------------------------------
        */

        if (!$smartBin) {
            $this->command->warn(
                'No SmartBin found. Notification seeder skipped.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Machine / Smart Bin Notifications
        |--------------------------------------------------------------------------
        */

        $notifications = [
            [
                'notification_type' => 'machine_full',

                'title' => 'Smart Bin Full',

                'message' =>
                    $smartBin->name .
                    ' at ' .
                    $smartBin->location .
                    ' has reached full capacity. Please schedule a pickup.',

                'smart_bin_id' =>
                    $smartBin->smart_bin_id,

                'student_id' => null,

                'is_read' => false,

                'read_at' => null,

                'data' => [
                    'smart_bin_name' =>
                        $smartBin->name,

                    'smart_bin_location' =>
                        $smartBin->location,

                    'current_distance_cm' =>
                        $smartBin->current_distance_cm,

                    'full_threshold_cm' =>
                        $smartBin->full_threshold_cm,

                    'capacity_percentage' => 100,
                ],
            ],

            [
                'notification_type' => 'machine_almost_full',

                'title' => 'Smart Bin Almost Full',

                'message' =>
                    $smartBin->name .
                    ' at ' .
                    $smartBin->location .
                    ' is almost full (85% capacity). Please monitor the bin level.',

                'smart_bin_id' =>
                    $smartBin->smart_bin_id,

                'student_id' => null,

                'is_read' => true,

                'read_at' => now()->subHours(2),

                'data' => [
                    'smart_bin_name' =>
                        $smartBin->name,

                    'smart_bin_location' =>
                        $smartBin->location,

                    'capacity_percentage' => 85,

                    'full_threshold_cm' =>
                        $smartBin->full_threshold_cm,
                ],
            ],

            [
                'notification_type' => 'machine_pickup_completed',

                'title' => 'Smart Bin Pickup Completed',

                'message' =>
                    $smartBin->name .
                    ' at ' .
                    $smartBin->location .
                    ' has been emptied successfully.',

                'smart_bin_id' =>
                    $smartBin->smart_bin_id,

                'student_id' => null,

                'is_read' => true,

                'read_at' => now()->subDay(),

                'data' => [
                    'smart_bin_name' =>
                        $smartBin->name,

                    'smart_bin_location' =>
                        $smartBin->location,

                    'pickup_completed' => true,
                ],
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Student Notification
        |--------------------------------------------------------------------------
        */

        if ($student) {
            $notifications[] = [
                'notification_type' => 'points_earned',

                'title' => 'Points Earned',

                'message' =>
                    'You earned 15 points for recycling plastic bottles!',

                'student_id' =>
                    $student->student_id,

                'smart_bin_id' =>
                    $smartBin->smart_bin_id,

                'is_read' => false,

                'read_at' => null,

                'data' => [
                    'points_earned' => 15,

                    'reason' =>
                        'Plastic bottle recycling',

                    'smart_bin_name' =>
                        $smartBin->name,
                ],
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Notifications
        |--------------------------------------------------------------------------
        */

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }
}

