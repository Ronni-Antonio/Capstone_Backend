<?php

namespace App\Http\Controllers;

use App\Models\Students;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\RfidCard;
use App\Models\ActivityLog;
use App\Models\IotControllerCommand;
use App\Services\IotCommandService;
use App\Services\RecyclingClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | RFID Scan Session
    |--------------------------------------------------------------------------
    |
    | Use one cache key for:
    | - storing the identified student
    | - frontend polling
    | - clearing the session
    |
    */
    private const SCAN_SESSION_CACHE_KEY = 'rfid_active_scan_session';

    public function index()
    {
        $students = Students::query()
            ->select([
                'student_id',
                'student_number',
                'first_name',
                'last_name',
                'grade_level_id',
                'section_id',
                'status',
                'points_balance',
                'created_at'
            ])
            ->with([
                'gradeLevel:grade_level_id,name',
                'section:section_id,name',
                'rfidCards' => fn ($q) => $q
                    ->where('status', 'active')
                    ->select(
                        'rfid_card_id',
                        'student_id',
                        'card_uid',
                        'status',
                        'assigned_at'
                    ),
            ])
            ->withSum([
                'transactions as total_items_recycled' => fn ($q) =>
                    $q->where('status', 'completed')
            ], 'total_items')
            ->latest('student_id')
            ->get();

        return response()->json($students);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_number' => 'required|string|max:255|unique:students,student_number',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'grade_level_id' => [
                'required',
                Rule::exists('grade_levels', 'grade_level_id')->where(
                    fn ($query) => $query->whereIn('name', ['Grade 4', 'Grade 5', 'Grade 6'])
                ),
            ],
            'section_id' => [
                'required',
                Rule::exists('sections', 'section_id')->where(
                    fn ($query) => $query->where('grade_level_id', $request->input('grade_level_id'))
                ),
            ],
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['status'] = $validated['status'] ?? 'inactive';
        $validated['points_balance'] = 0;

        $student = Students::create($validated)
            ->load([
                'gradeLevel',
                'section',
                'rfidCards'
            ]);

        $studentName = trim(
            ($student->first_name ?? '') . ' ' .
            ($student->last_name ?? '')
        ) ?: "Student #{$student->student_id}";

        ActivityLog::record(
            'CREATE_STUDENT',
            "User/student account created for {$studentName} ({$student->student_number}).",
            'Users',
            $request->user()?->id,
            $student->student_id,
            [
                'student' => $student->toArray()
            ]
        );

        return response()->json($student, 201);
    }

    public function show(string $id)
    {
        $student = Students::with([
            'gradeLevel',
            'section',
            'rfidCards',
            'transactions.items.classification.plasticType',
            'redemptions.reward',
            'pointTransactions'
        ])->findOrFail($id);

        return response()->json($student);
    }

    public function update(Request $request, string $id)
    {
        $student = Students::findOrFail($id);

        $targetGradeId = (int) $request->input('grade_level_id', $student->grade_level_id);

        $validated = $request->validate([
            'student_number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique(
                    'students',
                    'student_number'
                )->ignore(
                    $student->student_id,
                    'student_id'
                )
            ],
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'grade_level_id' => [
                'sometimes',
                Rule::exists('grade_levels', 'grade_level_id')->where(
                    fn ($query) => $query->whereIn('name', ['Grade 4', 'Grade 5', 'Grade 6'])
                ),
            ],
            'section_id' => [
                'sometimes',
                Rule::exists('sections', 'section_id')->where(
                    fn ($query) => $query->where('grade_level_id', $targetGradeId)
                ),
            ],
            'status' => 'sometimes|in:active,inactive',
        ]);

        $targetSectionId = (int) ($validated['section_id'] ?? $student->section_id);
        $effectiveGradeId = (int) ($validated['grade_level_id'] ?? $student->grade_level_id);

        $sectionMatchesGrade = Section::query()
            ->where('section_id', $targetSectionId)
            ->where('grade_level_id', $effectiveGradeId)
            ->exists();

        if (!$sectionMatchesGrade) {
            return response()->json([
                'message' => 'The selected section does not belong to the selected grade level.',
            ], 422);
        }

        $changes = [];

        foreach (
            [
                'student_number',
                'first_name',
                'last_name',
                'grade_level_id',
                'section_id',
                'status'
            ] as $field
        ) {
            if (
                isset($validated[$field]) &&
                $validated[$field] != $student->$field
            ) {
                $changes[$field] = [
                    'old' => $student->$field,
                    'new' => $validated[$field]
                ];
            }
        }

        $student->update($validated);

        if (!empty($changes)) {
            $studentName = trim(
                ($student->first_name ?? '') . ' ' .
                ($student->last_name ?? '')
            ) ?: "Student #{$student->student_id}";

            ActivityLog::record(
                'UPDATE_STUDENT',
                "User/student information updated for {$studentName}. Changes: " .
                    json_encode($changes),
                'Users',
                $request->user()?->id,
                $student->student_id,
                [
                    'changes' => $changes
                ]
            );
        }

        return response()->json(
            $student->load([
                'gradeLevel',
                'section',
                'rfidCards'
            ])
        );
    }

    public function importCSV(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $handle = fopen(
            $request->file('csv_file')->getRealPath(),
            'r'
        );

        if (!$handle) {
            return response()->json([
                'error' => 'Failed to open CSV file'
            ], 500);
        }

        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);

            return response()->json([
                'error' => 'CSV file is empty'
            ], 400);
        }

        $header = array_map(
            fn ($v) => strtolower(trim($v)),
            $header
        );

        $required = [
            'student_number',
            'first_name',
            'last_name',
            'grade_level',
            'section'
        ];

        $missing = array_values(
            array_diff(
                $required,
                $header
            )
        );

        if ($missing) {
            fclose($handle);

            return response()->json([
                'error' => 'Missing required columns',
                'missing' => $missing
            ], 422);
        }

        $map = array_flip($header);
        $imported = 0;
        $errors = [];
        $rowNo = 1;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNo++;

                if (
                    !count(
                        array_filter(
                            $row,
                            fn ($v) =>
                                trim((string) $v) !== ''
                        )
                    )
                ) {
                    continue;
                }

                $data = [
                    'student_number' => trim(
                        $row[$map['student_number']] ?? ''
                    ),
                    'first_name' => trim(
                        $row[$map['first_name']] ?? ''
                    ),
                    'last_name' => trim(
                        $row[$map['last_name']] ?? ''
                    ),
                    'grade_level' => trim(
                        $row[$map['grade_level']] ?? ''
                    ),
                    'section' => trim(
                        $row[$map['section']] ?? ''
                    ),
                ];

                $v = Validator::make(
                    $data,
                    [
                        'student_number' =>
                            'required|string|max:255|unique:students,student_number',
                        'first_name' =>
                            'required|string|max:255',
                        'last_name' =>
                            'required|string|max:255',
                        'grade_level' =>
                            'required|string|max:255',
                        'section' =>
                            'required|string|max:255',
                    ]
                );

                if ($v->fails()) {
                    $errors[] = [
                        'row' => $rowNo,
                        'data' => $data,
                        'errors' => $v->errors()->all()
                    ];

                    continue;
                }

                $rawGrade = preg_replace('/^grade\s*/i', '', trim($data['grade_level']));
                $gradeName = 'Grade ' . $rawGrade;

                if (!in_array($gradeName, ['Grade 4', 'Grade 5', 'Grade 6'], true)) {
                    $errors[] = [
                        'row' => $rowNo,
                        'data' => $data,
                        'message' => 'Grade level must be Grade 4, Grade 5, or Grade 6.',
                    ];
                    continue;
                }

                $grade = GradeLevel::where('name', $gradeName)->first();

                if (!$grade) {
                    $errors[] = [
                        'row' => $rowNo,
                        'data' => $data,
                        'message' => "{$gradeName} is not configured in grade_levels.",
                    ];
                    continue;
                }

                $section = Section::firstOrCreate([
                    'grade_level_id' => $grade->grade_level_id,
                    'name' => $data['section']
                ]);

                $student = Students::create([
                    'student_number' =>
                        $data['student_number'],
                    'first_name' =>
                        $data['first_name'],
                    'last_name' =>
                        $data['last_name'],
                    'grade_level_id' =>
                        $grade->grade_level_id,
                    'section_id' =>
                        $section->section_id,
                    'status' =>
                        'inactive',
                    'points_balance' =>
                        0,
                ]);

                $studentName = trim(
                    ($student->first_name ?? '') . ' ' .
                    ($student->last_name ?? '')
                ) ?: "Student #{$student->student_id}";

                ActivityLog::record(
                    'CREATE_STUDENT',
                    "Student account created via CSV import for {$studentName} ({$student->student_number}).",
                    'Users',
                    $request->user()?->id,
                    $student->student_id,
                    [
                        'imported_via' => 'csv'
                    ]
                );

                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }

        fclose($handle);

        if ($imported > 0) {
            ActivityLog::record(
                'BULK_IMPORT_STUDENTS',
                "CSV import completed. {$imported} students imported successfully.",
                'Users',
                $request->user()?->id,
                null,
                [
                    'imported_count' => $imported,
                    'error_count' => count($errors)
                ]
            );
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        ]);
    }


    /**
     * Controller 2 finalizes both first-time RFID assignment and replacement.
     *
     * The same endpoint is intentionally used for both operations so existing
     * frontend code does not need a separate "replace card" API. The command
     * payload tells Controller 2 whether this is an initial assignment or a
     * reassignment, while this method enforces the actual database rules.
     *
     * Safe reassignment rules:
     * - Every student may have only one active RFID card after completion.
     * - The previous active card is kept for history but marked "unassigned".
     * - A previously used card belonging to the SAME student may be reactivated.
     * - A card that belongs to ANOTHER student is never silently stolen.
     * - command_id is optional for backward compatibility with older Controller 2
     *   firmware, but the revised firmware sends it so assignment + command
     *   completion are atomic/idempotent.
     */
    public function assignCard(Request $request)
    {
        $this->authorizeController2($request);

        $validated = $request->validate([
            'student_id' => 'required|exists:students,student_id',
            'card_uid' => 'required|string|max:100',
            'command_id' => 'nullable|integer|exists:iot_controller_commands,command_id',
        ]);

        $normalizedUid = strtoupper(trim($validated['card_uid']));
        $commandId = isset($validated['command_id'])
            ? (int) $validated['command_id']
            : null;

        $result = DB::transaction(function () use (
            $validated,
            $normalizedUid,
            $commandId,
            $request
        ) {
            $command = null;

            if ($commandId !== null) {
                $command = IotControllerCommand::query()
                    ->where('command_id', $commandId)
                    ->where('controller_code', 'controller-2')
                    ->where('command_type', 'assign_card')
                    ->lockForUpdate()
                    ->firstOrFail();

                $payload = $command->payload ?? [];

                if ((int) ($payload['student_id'] ?? 0) !== (int) $validated['student_id']) {
                    abort(409, 'The RFID assignment command does not match the selected student.');
                }

                if ($command->status === 'completed') {
                    $student = Students::with([
                        'rfidCards' => fn ($query) => $query->where('status', 'active')
                    ])->findOrFail($validated['student_id']);

                    return [
                        'already_processed' => true,
                        'assignment_mode' => $payload['assignment_mode'] ?? 'assign',
                        'student' => $student,
                        'card' => $student->rfidCards->first(),
                    ];
                }

                if (in_array($command->status, ['failed', 'cancelled', 'expired'], true)) {
                    abort(409, "This RFID assignment command is already {$command->status}.");
                }
            }

            $student = Students::lockForUpdate()
                ->findOrFail($validated['student_id']);

            // Lock the scanned UID if it already exists anywhere in the system.
            $scannedCard = RfidCard::query()
                ->where('card_uid', $normalizedUid)
                ->lockForUpdate()
                ->first();

            if (
                $scannedCard &&
                (int) $scannedCard->student_id !== (int) $student->student_id
            ) {
                abort(
                    409,
                    'This RFID card is already registered to another student. Use a different card.'
                );
            }

            // Lock all currently active cards for this student before changing any.
            $activeCards = RfidCard::query()
                ->where('student_id', $student->student_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            $previousActiveUids = $activeCards
                ->pluck('card_uid')
                ->map(fn ($uid) => strtoupper((string) $uid))
                ->values()
                ->all();

            $hadDifferentActiveCard = collect($previousActiveUids)
                ->contains(fn ($uid) => $uid !== $normalizedUid);

            // Keep the old card rows for historical/audit purposes, but ensure
            // only the scanned replacement remains active.
            RfidCard::query()
                ->where('student_id', $student->student_id)
                ->where('status', 'active')
                ->where('card_uid', '!=', $normalizedUid)
                ->update([
                    'status' => 'unassigned',
                ]);

            if ($scannedCard) {
                // This lets the same student reuse one of their previously
                // unassigned/lost cards without violating the unique UID index.
                $scannedCard->update([
                    'status' => 'active',
                    'assigned_at' => now(),
                ]);
                $card = $scannedCard->fresh();
            } else {
                $card = RfidCard::create([
                    'student_id' => $student->student_id,
                    'card_uid' => $normalizedUid,
                    'status' => 'active',
                    'assigned_at' => now(),
                ]);
            }

            $oldStudentStatus = $student->status;

            if ($student->status !== 'active') {
                $student->update([
                    'status' => 'active',
                ]);
            }

            $assignmentMode = $hadDifferentActiveCard
                ? 'reassign'
                : 'assign';

            $studentName = trim(
                ($student->first_name ?? '') . ' ' .
                ($student->last_name ?? '')
            ) ?: "Student #{$student->student_id}";

            if ($assignmentMode === 'reassign') {
                ActivityLog::record(
                    'REASSIGN_RFID_CARD',
                    "RFID card replaced for {$studentName}. The previous active card was deactivated and the newly scanned card is now active.",
                    'Machine',
                    $request->user()?->id,
                    $student->student_id,
                    [
                        'previous_card_uids' => $previousActiveUids,
                        'new_card_uid' => $normalizedUid,
                        'old_student_status' => $oldStudentStatus,
                        'new_student_status' => 'active',
                        'iot_command_id' => $commandId,
                    ]
                );
            } else {
                ActivityLog::record(
                    'ASSIGN_RFID_CARD',
                    "RFID card assigned to {$studentName}.",
                    'Machine',
                    $request->user()?->id,
                    $student->student_id,
                    [
                        'card_uid' => $normalizedUid,
                        'old_student_status' => $oldStudentStatus,
                        'new_student_status' => 'active',
                        'iot_command_id' => $commandId,
                    ]
                );
            }

            // Revised Controller 2 sends command_id. Completing the command in
            // this SAME transaction makes retries idempotent and prevents a card
            // replacement from being applied twice after a network retry.
            if ($command) {
                $command->update([
                    'status' => 'completed',
                    'result_message' => $assignmentMode === 'reassign'
                        ? 'RFID card reassigned successfully.'
                        : 'RFID card assigned successfully.',
                    'completed_at' => now(),
                ]);
            }

            return [
                'already_processed' => false,
                'assignment_mode' => $assignmentMode,
                'student' => $student->fresh([
                    'gradeLevel',
                    'section',
                    'rfidCards',
                ]),
                'card' => $card,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => ($result['assignment_mode'] ?? 'assign') === 'reassign'
                ? 'RFID card reassigned successfully.'
                : 'RFID card paired successfully.',
            'already_processed' => (bool) ($result['already_processed'] ?? false),
            'assignment_mode' => $result['assignment_mode'] ?? 'assign',
            'student' => $result['student'] ?? null,
            'card' => $result['card'] ?? null,
        ], ($result['already_processed'] ?? false) ? 200 : 201);
    }

    public function activate(
        string $id,
        IotCommandService $commands
    ) {
        $student = Students::with([
            'rfidCards' => fn ($query) => $query->where('status', 'active')
        ])->findOrFail($id);

        $hasActiveCard = $student->rfidCards->isNotEmpty();
        $assignmentMode = $hasActiveCard ? 'reassign' : 'assign';

        // Keep command_type = assign_card for backward compatibility with the
        // existing Controller 2 firmware. The revised firmware reads
        // assignment_mode and displays the correct replacement prompt.
        $command = $commands->queue(
            'controller-2',
            'assign_card',
            [
                'student_id' => (int) $student->student_id,
                'student_number' => $student->student_number,
                'student_name' => trim(
                    ($student->first_name ?? '') . ' ' .
                    ($student->last_name ?? '')
                ),
                'assignment_mode' => $assignmentMode,
                'has_active_card' => $hasActiveCard,
            ],
            180
        );

        return response()->json([
            'message' => $hasActiveCard
                ? 'RFID replacement queued. Please tap the NEW replacement card on Controller 2.'
                : 'RFID assignment queued. Please tap the student card on Controller 2.',
            'student_id' => (int) $student->student_id,
            'command_id' => (int) $command->command_id,
            'status' => $command->status,
            'assignment_mode' => $assignmentMode,
            'has_active_card' => $hasActiveCard,
        ]);
    }

    public function activateStatus(string $id)
    {
        $student = Students::findOrFail($id);

        $command =
            \App\Models\IotControllerCommand::query()
                ->where(
                    'controller_code',
                    'controller-2'
                )
                ->where(
                    'command_type',
                    'assign_card'
                )
                ->where(
                    'payload->student_id',
                    (int) $student->student_id
                )
                ->latest('command_id')
                ->first();

        if (!$command) {
            return response()->json([
                'status' => 'pending',
                'command_status' => null,
                'command_id' => null,
            ]);
        }

        $status = match ($command->status) {
            'completed' => 'success',
            'failed', 'cancelled', 'expired' => 'error',
            default => 'pending',
        };

        $payload = $command->payload ?? [];

        return response()->json([
            'status' => $status,
            'command_status' => $command->status,
            'command_id' => $command->command_id,
            'message' => $command->result_message,
            'assignment_mode' => $payload['assignment_mode'] ?? 'assign',
        ]);
    }

    public function cancelActivation(string $id)
    {
        $student = Students::findOrFail($id);

        $cancelled = IotControllerCommand::query()
            ->where('controller_code', 'controller-2')
            ->where('command_type', 'assign_card')
            ->where('payload->student_id', (int) $student->student_id)
            ->whereIn('status', ['pending', 'claimed'])
            ->update([
                'status' => 'cancelled',
                'result_message' => 'RFID assignment cancelled by the application.',
                'completed_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'cancelled_commands' => $cancelled,
            'message' => 'RFID assignment cancelled.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IDENTIFY STUDENT
    |--------------------------------------------------------------------------
    |
    | Controller 2 sends the RFID UID here.
    |
    | Once the student is found, we store the entire response in the cache.
    | The Rewards frontend polls checkActiveScanSession() and retrieves it.
    |
    */
    public function identifyCard(
        Request $request,
        RecyclingClaimService $recyclingClaims
    ) {
        $validated = $request->validate([
            'card_uid' =>
                'required|string|max:100',
        ]);

        $card = RfidCard::with([
            'student.gradeLevel',
            'student.section'
        ])
            ->where(
                'card_uid',
                strtoupper(
                    trim(
                        $validated['card_uid']
                    )
                )
            )
            ->where(
                'status',
                'active'
            )
            ->first();

        if (!$card) {
            return response()->json([
                'error' =>
                    'Unrecognized or inactive card.',
            ], 404);
        }

        /*
         * If Controller 1 has a recent recycling session
         * waiting for an RFID card, this will claim it.
         *
         * Otherwise this remains a normal student
         * identification tap for the Rewards UI.
         */
        $recyclingClaim =
            $recyclingClaims->claimLatestForCard(
                $validated['card_uid']
            );

        $student = $card->student?->fresh([
            'gradeLevel',
            'section',
            'rfidCards'
        ]);

        if (!$student) {
            return response()->json([
                'error' =>
                    'RFID card has no linked student.',
            ], 404);
        }

        $payload = [
            'success' => true,

            'student_id' =>
                (int) $student->student_id,

            'rfid_card_id' =>
                (int) $card->rfid_card_id,

            'points_balance' =>
                (int) $student->points_balance,

            'student' =>
                $student,

            'recycling_claim' =>
                $recyclingClaim,
        ];

        /*
         * ==========================================
         * SCAN SESSION FIX
         * ==========================================
         *
         * Previously:
         *
         * cache()->put(
         *     'plink.active_scan_session',
         *     ...
         * );
         *
         * But the frontend polling endpoint was
         * looking for:
         *
         * rfid_active_scan_session
         *
         * Now all methods use exactly the same key.
         */
        Cache::put(
            self::SCAN_SESSION_CACHE_KEY,
            $payload,
            now()->addMinutes(2)
        );

        return response()->json($payload);
    }

    /*
    |--------------------------------------------------------------------------
    | FRONTEND SCAN POLLING
    |--------------------------------------------------------------------------
    |
    | The Rewards frontend calls this while waiting
    | for Controller 2 to identify an RFID card.
    |
    */
    public function checkActiveScanSession()
    {
        $payload = Cache::get(
            self::SCAN_SESSION_CACHE_KEY
        );

        if (!$payload) {
            return response()->json([
                'student_found' => false,
                'student' => null,
            ]);
        }

        return response()->json([
            'student_found' => true,

            'student' =>
                $payload['student'] ?? null,

            'student_id' =>
                $payload['student_id'] ?? null,

            'points_balance' =>
                $payload['points_balance'] ?? 0,

            'success' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR SCAN SESSION
    |--------------------------------------------------------------------------
    */
    public function clearScanSession()
    {
        Cache::forget(
            self::SCAN_SESSION_CACHE_KEY
        );

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Device authentication for Controller 2 RFID operations.
     */
    private function authorizeController2(Request $request): void
    {
        $expected = (string) config('services.iot.controller_2_key');
        $provided = (string) $request->header('X-Device-Key', '');

        abort_if(
            $expected === '' ||
            $provided === '' ||
            !hash_equals($expected, $provided),
            401,
            'Invalid Controller 2 device key.'
        );
    }

    public function destroy(string $id)
    {
        $student = Students::findOrFail($id);

        if (
            $student->transactions()->exists() ||
            $student->pointTransactions()->exists() ||
            $student->redemptions()->exists()
        ) {
            return response()->json([
                'error' =>
                    'Student has historical records and cannot be deleted. Set status to inactive instead.'
            ], 409);
        }

        $studentName = trim(
            ($student->first_name ?? '') . ' ' .
            ($student->last_name ?? '')
        ) ?: "Student #{$student->student_id}";

        $studentNumber =
            $student->student_number;

        $studentId =
            $student->student_id;

        $student->delete();

        ActivityLog::record(
            'DELETE_STUDENT',
            "User/student account deleted for {$studentName} ({$studentNumber}).",
            'Users',
            request()->user()?->id,
            null,
            [
                'student_id' =>
                    $studentId,
                'student_number' =>
                    $studentNumber,
                'student_name' =>
                    $studentName
            ]
        );

        return response()->json(null, 204);
    }
}