<?php

namespace App\Http\Controllers;

use App\Models\RecyclingTransaction;
use App\Models\SmartBinLog;
use App\Models\Redemptions;


use Illuminate\Http\Request;

class ProphetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function getForecastData()
    {
        $recycling = RecyclingTransaction::query()
            ->where('status', 'completed')
            ->selectRaw(
                'DATE(started_at) as ds,
             SUM(total_items) as y'
            )
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get();

        $participation = RecyclingTransaction::query()
            ->where('status', 'completed')
            ->selectRaw(
                'DATE(started_at) as ds,
             COUNT(DISTINCT student_id) as y'
            )
            ->groupByRaw('DATE(started_at)')
            ->orderBy('ds')
            ->get();

        $redemptions = Redemptions::query()
            ->selectRaw(
                'DATE(redeemed_at) as ds,
             COUNT(*) as y'
            )
            ->whereNotNull('redeemed_at')
            ->groupByRaw('DATE(redeemed_at)')
            ->orderBy('ds')
            ->get();

        $binFullness = SmartBinLog::query()
            ->selectRaw(
                'DATE(created_at) as ds,
             AVG(fill_percentage) as y'
            )
            ->groupByRaw('DATE(created_at)')
            ->orderBy('ds')
            ->get();

        return response()->json([
            'recycling' => $recycling,
            'participation' => $participation,
            'redemptions' => $redemptions,
            'bin_fullness' => $binFullness,
        ]);
    }

}
