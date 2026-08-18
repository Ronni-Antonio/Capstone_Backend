<?php

namespace Database\Seeders;

use App\Models\AiClassification;
use App\Models\AiModel;
use App\Models\AnalyticsReport;
use App\Models\GradeLevel;
use App\Models\RecyclableType;
use App\Models\PointTransaction;
use App\Models\Prediction;
use App\Models\RecyclingItem;
use App\Models\RecyclingTransaction;
use App\Models\Rewards;
use App\Models\RfidCard;
use App\Models\Section;
use App\Models\SmartBin;
use App\Models\SmartBinCompartment;
use App\Models\SmartBinCompartmentLog;
use App\Models\SmartBinLog;
use App\Models\Students;
use App\Models\systemSettings;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. ADMIN USER
        |--------------------------------------------------------------------------
        */
        $admin = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test Admin',
                'password' => 'password123',
                'phone' => null,
                'school' => 'Pasig Elementary School',
                'role' => 'Eco Coordinator',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 2. SYSTEM SETTINGS
        |--------------------------------------------------------------------------
        */
        systemSettings::updateOrCreate(
            ['setting_id' => 1],
            [
                'school_name' => 'Pasig Elementary School',
                'school_address' => 'Pasig City, Philippines',
                'school_year' => '2026-2027',
                'school_email' => 'school@example.com',
                'notify_machine_full' => true,
                'notify_scanner_errors' => true,
                'notify_machine_offline' => true,
                'notify_maintenance' => true,
                'notify_weekly_summary' => true,
                'notify_milestones' => true,
                'auto_backup' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 3. GRADE LEVELS
        |
        | IMPORTANT: Students.grade_level_id is a foreign key to grade_levels.
        | Create these records BEFORE creating students.
        |--------------------------------------------------------------------------
        */
        $gradeLevels = [];

        for ($grade = 1; $grade <= 6; $grade++) {
            $gradeLevels[$grade] = GradeLevel::updateOrCreate(
                ['name' => 'Grade ' . $grade],
                []
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. SECTIONS
        |--------------------------------------------------------------------------
        */
        $sectionNames = ['Bonifacio', 'Rizal', 'Mabini'];
        $sections = [];

        foreach ($sectionNames as $sectionName) {
            $sections[$sectionName] = Section::updateOrCreate(
                ['name' => $sectionName],
                []
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. RECYCLABLE TYPES
        |--------------------------------------------------------------------------
        |
        | is_accepted is explicitly supplied because it controls whether a
        | classification can earn points.
        |--------------------------------------------------------------------------
        */
        $recyclableTypes = [];

        $recyclableTypes['PET'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 1'],
            [
                'name' => 'PET Bottle',
                'material_category' => 'plastic',
                'points_value' => 2,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['HDPE'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 2'],
            [
                'name' => 'HDPE Bottle',
                'material_category' => 'plastic',
                'points_value' => 3,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['PVC'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 3'],
            [
                'name' => 'PVC Bottle',
                'material_category' => 'plastic',
                'points_value' => 1,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );


        $recyclableTypes['LDPE'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 4'],
            [
                'name' => 'LDPE Bottle',
                'material_category' => 'plastic',
                'points_value' => 2,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['PP'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 5'],
            [
                'name' => 'PP Bottle',
                'material_category' => 'plastic',
                'points_value' => 2,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['PS'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 6'],
            [
                'name' => 'PS Bottle',
                'material_category' => 'plastic',
                'points_value' => 1,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['PC'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 7'],
            [
                'name' => 'PC Bottle',
                'material_category' => 'plastic',
                'points_value' => 1,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['PLA'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 8'],
            [
                'name' => 'PLA Plastic',
                'material_category' => 'plastic',
                'points_value' => 1,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['PAPER'] = RecyclableType::updateOrCreate(
            ['code' => 'Code 9'],
            [
                'name' => 'White Paper',
                'material_category' => 'paper',
                'points_value' => 1,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['CONTAMINATED'] = RecyclableType::updateOrCreate(
            ['code' => 'CONTAMINATED'],
            [
                'name' => 'Contaminated PET Bottle',
                'material_category' => 'plastic',
                'points_value' => 1,
                'is_accepted' => true,
                'is_active' => true,
            ]
        );

        $recyclableTypes['INVALID'] = RecyclableType::updateOrCreate(
            ['code' => 'INVALID'],
            [
                'name' => 'Invalid / Non-PET',
                'material_category' => 'other',
                'points_value' => 0,
                'is_accepted' => false,
                'is_active' => true,
            ]
        );
        /*
        |--------------------------------------------------------------------------
        | 6. SMART BIN
        |
        | HC-SR04 behavior:
        | - empty bin = larger distance
        | - full bin  = smaller distance
        |
        | Therefore empty_threshold_cm MUST be greater than full_threshold_cm.
        |--------------------------------------------------------------------------
        */
        $smartBin = SmartBin::updateOrCreate(
            ['name' => 'Smart Recycling Bin 1'],
            [
                'location' => 'Main Building',
                'status' => 'online',
                'current_fill_percentage' => 20,
                'current_distance_cm' => 68,
                'full_threshold_cm' => 20,
                'empty_threshold_cm' => 80,
                'last_maintenance_at' => null,
                'last_active_at' => now(),
            ]
        );


        // One physical Smart Bin, two independent HC-SR04-monitored compartments.
        $plasticCompartment = SmartBinCompartment::updateOrCreate(
            [
                'smart_bin_id' => $smartBin->smart_bin_id,
                'material_category' => 'plastic',
            ],
            [
                'name' => 'Plastic Compartment',
                'status' => 'online',
                'current_distance_cm' => 68,
                'current_fill_percentage' => 20,
                'full_threshold_cm' => 20,
                'empty_threshold_cm' => 80,
                'last_active_at' => now(),
            ]
        );

        $paperCompartment = SmartBinCompartment::updateOrCreate(
            [
                'smart_bin_id' => $smartBin->smart_bin_id,
                'material_category' => 'paper',
            ],
            [
                'name' => 'Paper Compartment',
                'status' => 'online',
                'current_distance_cm' => 50,
                'current_fill_percentage' => 50,
                'full_threshold_cm' => 20,
                'empty_threshold_cm' => 80,
                'last_active_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 7. CNN MODEL
        |--------------------------------------------------------------------------
        */
        $cnnModel = AiModel::updateOrCreate(
            [
                'name' => 'Plastic Classification CNN',
                'version' => '1.0.0',
            ],
            [
                'framework' => 'TensorFlow/Keras',
                'accuracy' => 95.000,
                'model_path' => 'models/plastic_cnn/v1.0.0',
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 8. PROPHET MODEL
        |--------------------------------------------------------------------------
        */
        $prophetModel = AiModel::updateOrCreate(
            [
                'name' => 'Recycling Volume Prophet',
                'version' => '1.0.0',
            ],
            [
                'framework' => 'Prophet',
                'accuracy' => null,
                'model_path' => 'models/prophet/recycling_volume/v1.0.0',
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 9. STUDENTS
        |
        | Use the actual GradeLevel and Section records instead of assuming
        | that their IDs are 1..6 / 1..3.
        |--------------------------------------------------------------------------
        */
        $firstNames = [
            'Juan',
            'Maria',
            'Pedro',
            'Ana',
            'Jose',
            'Sofia',
            'Luis',
            'Carla',
            'Miguel',
            'Elena',
            'Carlos',
            'Isabel',
            'Pablo',
            'Lucia',
            'Diego',
            'Valeria',
            'Javier',
            'Camila',
            'Fernando',
            'Gabriela',
            'Ricardo',
            'Daniela',
            'Andres',
            'Victoria',
            'Alberto',
            'Martina',
            'Rodrigo',
            'Paula',
            'Marcos',
            'Laura',
        ];

        $lastNames = [
            'Santos',
            'Garcia',
            'Reyes',
            'Cruz',
            'Santos',
            'Bautista',
            'Dela Cruz',
            'Fernandez',
            'Lopez',
            'Mendoza',
            'Rivera',
            'Romero',
            'Aquino',
            'Castro',
            'Gonzales',
            'Martinez',
            'Santiago',
            'Torres',
            'Vargas',
            'Villanueva',
            'De Leon',
            'Hernandez',
            'Ignacio',
            'Jimenez',
            'Klein',
            'Lazaro',
            'Mercado',
            'Natividad',
            'Ocampo',
            'Pascual',
        ];

        $students = collect();

        for ($i = 1; $i <= 30; $i++) {
            $gradeNumber = (($i - 1) % 6) + 1;
            $sectionName = $sectionNames[($i - 1) % count($sectionNames)];

            $student = Students::updateOrCreate(
                [
                    'student_number' => '136721' . str_pad($i, 6, '0', STR_PAD_LEFT),
                ],
                [
                    'first_name' => $firstNames[$i - 1],
                    'last_name' => $lastNames[$i - 1],
                    'grade_level_id' => $gradeLevels[$gradeNumber]->grade_level_id,
                    'section_id' => $sections[$sectionName]->section_id,
                    'status' => 'active',
                    'points_balance' => 0,
                ]
            );

            $students->push($student);
        }

        /*
        |--------------------------------------------------------------------------
        | 10. RFID CARDS
                foreach ($students as $index => $student) {
            RfidCard::updateOrCreate(
                ['student_id' => $student->student_id],
                [
                    'card_uid' => 'RFID-' . str_pad($index + 1, 8, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'assigned_at' => now(),
                ]
            );
        }
        |--------------------------------------------------------------------------
        */


        /*
        |--------------------------------------------------------------------------
        | 11. REWARDS
        |--------------------------------------------------------------------------
        */
        $rewards = [
            ['reward_name' => 'Pencil', 'points_cost' => 10, 'stock_quantity' => 100],
            ['reward_name' => 'Notebook', 'points_cost' => 25, 'stock_quantity' => 50],
            ['reward_name' => 'School Supplies Set', 'points_cost' => 50, 'stock_quantity' => 25],
        ];

        foreach ($rewards as $reward) {
            Rewards::updateOrCreate(
                ['reward_name' => $reward['reward_name']],
                [
                    'points_cost' => $reward['points_cost'],
                    'stock_quantity' => $reward['stock_quantity'],
                    'is_active' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 12. SMART BIN LOGS
        |
        | 30 days x 6 readings/day = 180 historical readings.
        | These are useful as sample time-series data for fullness prediction.
        |--------------------------------------------------------------------------
        */
        $emptyDistance = $smartBin->empty_threshold_cm;
        $fullDistance = $smartBin->full_threshold_cm;

        for ($day = 29; $day >= 0; $day--) {
            for ($reading = 0; $reading < 6; $reading++) {
                $timestamp = now()
                    ->subDays($day)
                    ->startOfDay()
                    ->addHours(7 + $reading * 2);

                // Produce a plausible daily fill pattern.
                $fillPercentage = min(95, max(0, 5 + ($reading * 12) + rand(0, 10)));

                // Occasionally simulate a bin reset/pickup.
                if ($reading === 0 && $day % 5 === 0) {
                    $fillPercentage = rand(0, 5);
                }

                $distance = $emptyDistance - (($emptyDistance - $fullDistance) * ($fillPercentage / 100));
                $distance = (int) round($distance);

                $status = $fillPercentage >= 100
                    ? 'full'
                    : ($fillPercentage >= 80 ? 'almost_full' : 'normal');

                $log = SmartBinLog::create([
                    'smart_bin_id' => $smartBin->smart_bin_id,
                    'distance_cm' => $distance,
                    'fill_percentage' => (int) round($fillPercentage),
                    'status' => $status,
                ]);

                // SmartBinLog only has created_at/updated_at for the timestamp.
                $log->created_at = $timestamp;
                $log->updated_at = $timestamp;
                $log->saveQuietly();


                // Keep independent historical readings for each physical compartment.
                $plasticFill = min(95, max(0, $fillPercentage));
                $paperFill = min(95, max(0, round($fillPercentage * 0.75 + rand(0, 8))));

                foreach ([
                    [$plasticCompartment, $plasticFill],
                    [$paperCompartment, $paperFill],
                ] as [$compartment, $compartmentFill]) {
                    $compartmentDistance = $compartment->empty_threshold_cm - (
                        ($compartment->empty_threshold_cm - $compartment->full_threshold_cm) *
                        ($compartmentFill / 100)
                    );

                    $compartmentLog = SmartBinCompartmentLog::create([
                        'compartment_id' => $compartment->compartment_id,
                        'distance_cm' => (int) round($compartmentDistance),
                        'fill_percentage' => (int) round($compartmentFill),
                        'status' => $compartmentFill >= 80 ? 'almost_full' : 'normal',
                    ]);
                    $compartmentLog->created_at = $timestamp;
                    $compartmentLog->updated_at = $timestamp;
                    $compartmentLog->saveQuietly();
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 13. RECYCLING TRANSACTIONS + ITEMS + CNN CLASSIFICATIONS
        |
        | No weight is used by the transaction workflow.
        | recycling_items.weight_kg remains NULL because the system no longer
        | measures bottle weight.
        |
        | Generate daily data so the dataset is suitable for later time-series
        | aggregation/Prophet testing.
        |--------------------------------------------------------------------------
        */
        $rfidCards = RfidCard::all()->keyBy('student_id');

        for ($day = 29; $day >= 0; $day--) {
            $transactionsToday = rand(1, 3);

            for ($t = 0; $t < $transactionsToday; $t++) {
                DB::transaction(function () use ($students, $rfidCards, $smartBin, $cnnModel, $recyclableTypes, $day) {
                    $student = $students->random();
                    $rfidCard = $rfidCards->get($student->student_id);

                    $transactionDate = now()
                        ->subDays($day)
                        ->startOfDay()
                        ->addHours(rand(7, 16))
                        ->addMinutes(rand(0, 59));

                    $transaction = RecyclingTransaction::create([
                        'transaction_code' => (string) Str::uuid(),
                        'student_id' => $student->student_id,
                        'rfid_card_id' => $rfidCard?->rfid_card_id,
                        'smart_bin_id' => $smartBin->smart_bin_id,
                        'status' => 'completed',
                        'total_items' => 0,
                        'total_points' => 0,
                        'started_at' => $transactionDate,
                        'completed_at' => $transactionDate->copy()->addSeconds(rand(10, 60)),
                    ]);

                    $totalItems = rand(1, 8);
                    $totalPoints = 0;

                    for ($itemNumber = 1; $itemNumber <= $totalItems; $itemNumber++) {
                        $roll = rand(1, 100);

                        if ($roll <= 10) {
                            $recyclableType = $recyclableTypes['PET'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 20) {
                            $recyclableType = $recyclableTypes['CONTAMINATED'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 35) {
                            $recyclableType = $recyclableTypes['HDPE'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 48) {
                            $recyclableType = $recyclableTypes['PVC'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } else if ($roll <= 59) {
                            $recyclableType = $recyclableTypes['LDPE'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 70) {
                            $recyclableType = $recyclableTypes['PP'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 80) {
                            $recyclableType = $recyclableTypes['PS'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 90) {
                            $recyclableType = $recyclableTypes['PC'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 95) {
                            $recyclableType = $recyclableTypes['PLA'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } elseif ($roll <= 98) {
                            $recyclableType = $recyclableTypes['PAPER'];
                            $classificationStatus = 'valid';
                            $itemStatus = 'accepted';
                        } else {
                            $recyclableType = $recyclableTypes['INVALID'];
                            $classificationStatus = 'rejected';
                            $itemStatus = 'rejected';
                        }

                        $item = RecyclingItem::create([
                            'transaction_id' => $transaction->transaction_id,
                            'item_number' => $itemNumber,
                            'image_path' => 'recycling-images/sample-' . $transaction->transaction_id . '-' . $itemNumber . '.jpg',
                            'weight_kg' => null,
                            'status' => $itemStatus,
                        ]);

                        $confidence = rand(8500, 9900) / 100;

                        AiClassification::create([
                            'recycling_item_id' => $item->recycling_item_id,
                            'recyclable_type_id' => $recyclableType->recyclable_type_id,
                            'model_id' => $cnnModel->model_id,
                            'confidence_score' => $confidence,
                            'status' => $classificationStatus,
                            'notes' => null,
                            'is_verified' => false,
                            'classified_at' => $transactionDate->copy()->addSeconds(rand(1, 10)),
                        ]);

                        if ($classificationStatus === 'valid' && $recyclableType->is_accepted) {
                            $totalPoints += $recyclableType->points_value;
                        }
                    }

                    $transaction->update([
                        'total_items' => $totalItems,
                        'total_points' => $totalPoints,
                    ]);

                    if ($totalPoints > 0) {
                        PointTransaction::create([
                            'student_id' => $student->student_id,
                            'recycling_transaction_id' => $transaction->transaction_id,
                            'redemption_id' => null,
                            'points' => $totalPoints,
                            'transaction_type' => 'earned',
                            'description' => 'Points earned from recycling transaction ' . $transaction->transaction_code,
                        ]);

                        $student->increment('points_balance', $totalPoints);
                    }
                });
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 14. SAMPLE PROPHET PREDICTIONS
        |--------------------------------------------------------------------------
        */
        $predictionDate = now()->toDateString();

        Prediction::updateOrCreate(
            [
                'model_id' => $prophetModel->model_id,
                'prediction_type' => 'recycling_volume',
                'prediction_date' => $predictionDate,
            ],
            [
                'target_date' => now()->addDays(7)->toDateString(),
                'predicted_value' => 25,
                'actual_value' => null,
                'confidence' => 0.85,
                'input_summary' => [
                    'source' => 'recycling_transactions',
                    'historical_days' => 30,
                ],
                'output' => [
                    'message' => 'Sample seeded prediction. Replace with actual Prophet API output.',
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 15. SAMPLE ANALYTICS REPORT
        |--------------------------------------------------------------------------
        */
        $totalItems = RecyclingTransaction::sum('total_items');
        $totalPoints = RecyclingTransaction::sum('total_points');
        $studentsParticipated = RecyclingTransaction::whereNotNull('student_id')
            ->distinct('student_id')
            ->count('student_id');

        AnalyticsReport::updateOrCreate(
            [
                'report_type' => 'system_summary',
                'title' => 'Initial System Analytics Report',
            ],
            [
                'generated_by_user_id' => $admin->id,
                'report_date_start' => now()->subDays(29)->toDateString(),
                'report_date_end' => now()->toDateString(),
                'total_items_collected' => $totalItems,
                'total_weight_kg' => 0,
                'total_points_awarded' => $totalPoints,
                'total_rewards_redeemed' => 0,
                'total_students_participated' => $studentsParticipated,
                'summary' => [
                    'source' => 'recycling_transactions',
                    'note' => 'Weight is intentionally zero because the current IoT design uses HC-SR04 distance sensing rather than weight measurement.',
                ],
                'predictive_insights' => [
                    'prophet_model_id' => $prophetModel->model_id,
                    'note' => 'Sample prediction record seeded for development/testing.',
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 16. NOTIFICATIONS
        |--------------------------------------------------------------------------
        */
        $this->call(NotificationSeeder::class);
    }
}
