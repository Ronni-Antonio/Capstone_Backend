<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Students;
use App\Models\Section;
use App\Models\PlasticType;
use App\Models\Machine;
use App\Models\systemSettings;
use App\Models\Transactions;
use App\Models\ClassificationHistory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password123') // Default password: password123
            ]
        ); 

        // Create system settings
        if (systemSettings::count() === 0) {
            systemSettings::create([
                'school_name' => 'Pasig Elementary School',
                'point_conversion' => 1,
                'penalty_rejected' => -1,
                'penalty_non_pet' => -2,
                'penalty_invalid' => 0,
            ]);
        }

        // Create sections
        $sections = [
            'Bonifacio', 'Rizal', 'Mabini',
            'Bonifacio', 'Rizal', 'Mabini',
            'Bonifacio', 'Rizal', 'Mabini',
            'Bonifacio', 'Rizal', 'Mabini',
            'Bonifacio', 'Rizal', 'Mabini',
            'Bonifacio', 'Rizal', 'Mabini',
        ];

        foreach ($sections as $sectionName) {
            Section::firstOrCreate(['name' => $sectionName]);
        }

        // Create plastic types
        $plasticTypes = [
            ['name' => 'PET Bottle', 'multiplier' => 1.5, 'is_active' => true],
            ['name' => 'Contaminated PET Bottle', 'multiplier' => 1.0, 'is_active' => true],
            ['name' => 'Invalid', 'multiplier' => 0, 'is_active' => false],
        ];

        foreach ($plasticTypes as $type) {
            $type['points_per_item'] = $type['multiplier'] * 1; // Calculate initial points
            PlasticType::firstOrCreate(['name' => $type['name']], $type);
        }

        // Create machines
        $machines = [
            ['name' => 'Recycling Machine 1', 'location' => 'Main Building', 'status' => 'active', 'current_weight_kg' => 0, 'max_capacity_kg' => 20],
            
        ];

        foreach ($machines as $machine) {
            Machine::firstOrCreate(['name' => $machine['name']], $machine);
        }

        // Create 30 students if they don't exist yet
        $firstNames = ['Juan', 'Maria', 'Pedro', 'Ana', 'Jose', 'Sofia', 'Luis', 'Carla', 'Miguel', 'Elena', 'Carlos', 'Isabel', 'Pablo', 'Lucia', 'Diego', 'Valeria', 'Javier', 'Camila', 'Fernando', 'Gabriela', 'Ricardo', 'Daniela', 'Andres', 'Victoria', 'Alberto', 'Martina', 'Rodrigo', 'Paula', 'Marcos', 'Laura'];
        $lastNames = ['Santos', 'Garcia', 'Reyes', 'Cruz', 'Santos', 'Bautista', 'Dela Cruz', 'Fernandez', 'Lopez', 'Mendoza', 'Rivera', 'Romero', 'Aquino', 'Castro', 'Gonzales', 'Martinez', 'Santiago', 'Torres', 'Vargas', 'Villanueva', 'De Leon', 'Hernandez', 'Ignacio', 'Jimenez', 'Klein', 'Lazaro', 'Mercado', 'Natividad', 'Ocampo', 'Pascual'];

        for ($i = 1; $i <= 30; $i++) {
            Students::firstOrCreate(
                ['student_number' => '136721' . str_pad($i, 6, '0', STR_PAD_LEFT)],
                [
                    'first_name' => $firstNames[$i - 1],
                    'last_name' => $lastNames[$i - 1],
                    'grade_level' => rand(1, 6),
                    'section' => $sections[array_rand($sections)],
                    'points_balance' => rand(50, 200),
                ]
            );
        }

        // Create 50 transactions with classification histories
        $students = Students::all();
        $plasticTypes = PlasticType::all();
        $machines = Machine::where('status', 'active')->get();
        $statuses = ['valid', 'contaminated', 'non_pet', 'rejected', 'invalid'];

        for ($t = 1; $t <= 50; $t++) {
            $student = $students->random();
            $machine = $machines->random();
            $totalBottles = rand(1, 8);
            $validQty = 0;
            $contaminatedQty = 0;
            $nonPetQty = 0;
            $rejectedQty = 0;
            $totalPoints = 0;
            $breakdown = [];

            // Start DB transaction
            DB::beginTransaction();

            try {
                // Create transaction
                $transaction = Transactions::create([
                    'student_id' => $student->student_id,
                    'machine_id' => $machine->machine_id,
                    'bottle_qty' => $totalBottles,
                    'valid_qty' => 0,
                    'contaminated_qty' => 0,
                    'non_pet_qty' => 0,
                    'rejected_qty' => 0,
                    'points_earned' => 0,
                    'transaction_date' => now()->subDays(rand(1, 30))->subHours(rand(0, 23)),
                    'breakdown' => [],
                ]);

                // Create classification histories for each bottle
                for ($b = 0; $b < $totalBottles; $b++) {
                    $status = $statuses[array_rand($statuses)];
                    $plasticType = $plasticTypes->random();
                    $pointsChange = 0;

                    switch ($status) {
                        case 'valid':
                            $validQty++;
                            $pointsChange = $plasticType->points_per_item;
                            break;
                        case 'contaminated':
                            $contaminatedQty++;
                            $pointsChange = -1;
                            break;
                        case 'non_pet':
                            $nonPetQty++;
                            $pointsChange = -2;
                            break;
                        case 'rejected':
                        case 'invalid':
                            $rejectedQty++;
                            $pointsChange = 0;
                            break;
                    }

                    $totalPoints += $pointsChange;

                    $breakdown[] = [
                        'plastic_type_id' => $plasticType->plastic_type_id,
                        'plastic_type_name' => $plasticType->name,
                        'status' => $status,
                        'points_change' => $pointsChange,
                        'confidence_score' => rand(85, 100),
                    ];

                    // Create classification history
                    ClassificationHistory::create([
                        'plastic_type_id' => $plasticType->plastic_type_id,
                        'machine_id' => $machine->machine_id,
                        'transaction_id' => $transaction->transaction_id,
                        'confidence_score' => rand(85, 100),
                        'status' => $status,
                        'points_change' => $pointsChange,
                        'is_verified' => rand(0, 1),
                        'ai_model_version' => 'v1.0.' . rand(0, 5),
                    ]);
                }

                // Update transaction with final totals
                $transaction->update([
                    'valid_qty' => $validQty,
                    'contaminated_qty' => $contaminatedQty,
                    'non_pet_qty' => $nonPetQty,
                    'rejected_qty' => $rejectedQty,
                    'points_earned' => $totalPoints,
                    'breakdown' => $breakdown,
                ]);

                // Update student points
                $student->points_balance += $totalPoints;
                $student->save();

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }
    }
}
