<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Redemptions;
use App\Models\Students;
use App\Models\Rewards;

class RedemptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $redemptions = Redemptions::with(['student', 'reward'])->get();
        return response()->json($redemptions);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * STEP 1: Admin clicks "Redeem" on Web UI.
     * This signals the physical ESP32 to prepare for a physical confirmation card tap.
     */
public function initiateRedemptionProcess($student_id, $reward_id) 
{
    // 1. Fetch the string exactly as it sits in your .env file
    $envUrl = env('ESP32_URL', 'http://192.168.254.174/prepare-activation');
    
    // 2. Dynamically replace the activation path string with your redemption path string
    $esp32Url = str_replace('/prepare-activation', '/prepare-redemption', $envUrl);
    
    try {
        $client = new \GuzzleHttp\Client();
        
        // 3. Send the wake-up request payload to the ESP32
        $client->post($esp32Url, [
            'json' => [
                'student_id' => (string) $student_id,
                'reward_id'  => (string) $reward_id
            ],
            'timeout' => 4 
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Redemption queued. Please tap card to confirm transaction.'
        ], 200);

    } catch (\GuzzleHttp\Exception\ConnectException $e) {
        return response()->json(['error' => 'The physical ESP32 reader is offline or unreachable.'], 502);
    } catch (\Exception $e) {
        return response()->json(['error' => 'System error: ' . $e->getMessage()], 500);
    }

    
}

public function checkRedemptionStatus($student_id, $reward_id)
{
    try {
        // Enforce strong variable integer typing to match database indexing requirements
        $studentIdInt = (int) $student_id;
        $rewardIdInt  = (int) $reward_id;

        // Query using Laravel's base database query builder to bypass potential model naming mismatches entirely
        $recentRedemption = \Illuminate\Support\Facades\DB::table('redemptions')
            ->where('student_id', $studentIdInt)
            ->where('reward_id', $rewardIdInt)
            ->where('redemption_date', '>=', now()->subMinutes(2)) // Expand checking window slightly to 2 minutes
            ->first();

        if ($recentRedemption) {
            return response()->json([
                'status' => 'success',
                'completed' => true,
                'message' => 'Physical checkout confirmation verified!'
            ], 200);
        }

        // Return pending status while waiting for the physical ESP32 transaction to land
        return response()->json([
            'status' => 'pending',
            'completed' => false
        ], 200);

    } catch (\Exception $e) {
        // Safely print the exact system exception error message to prevent unhandled 500 crashes
        return response()->json([
            'status' => 'error',
            'completed' => false,
            'error' => 'Status crash report: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * STEP 2: Student taps card on ESP32 reader.
     * Store a newly created resource in storage and reduce point balances securely.
     */
    public function store(Request $request)
    {
        try {
            // Validate incoming data structures and confirm cross-system inputs exist
            $validated = $request->validate([
                'student_id'      => 'required|exists:students,student_id',
                'reward_id'       => 'required|exists:rewards,reward_id',
                'card_uid'        => 'required|string', // <-- Enforce presence of physical card token
                'redemption_date' => 'nullable|date'
            ]);

            if (!isset($validated['redemption_date'])) {
                $validated['redemption_date'] = now();
            }

            // Secure Database Query: Match exactly against your unique key fields
            $student = Students::where('student_id', $validated['student_id'])->firstOrFail();
            $reward  = Rewards::where('reward_id', $validated['reward_id'])->firstOrFail();
            
            // SECURITY SHIELD: Verify physical card matches the student data submitted
            if (strtoupper($student->card_uid) !== strtoupper($validated['card_uid'])) {
                return response()->json([
                    'error' => 'Security Failure: The physical card tapped does not match the requested student record.'
                ], 403);
            }

            // Business Rules Validation: Balance Check
            if ($student->points_balance < $reward->points_cost) {
                return response()->json([
                    'error'   => 'Insufficient points balance',
                    'required'=> $reward->points_cost,
                    'current' => $student->points_balance
                ], 422);
            }

            // Business Rules Validation: Inventory Check
            if ($reward->stock_quantity <= 0) {
                return response()->json(['error' => 'Reward out of stock'], 422);
            }

            // Append calculated point subtraction parameters into the create model payload
            $validated['points_spent'] = $reward->points_cost;

            // Database Writes wrapped cleanly in database properties updates
            $redemption = Redemptions::create($validated);

            // Securely adjust numeric fields
            $student->decrement('points_balance', $reward->points_cost);
            $reward->decrement('stock_quantity', 1);

            return response()->json([
                'success'    => true,
                'message'    => 'Redemption processed cleanly via hardware validation.',
                'redemption' => $redemption,
                'student'    => $student
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}