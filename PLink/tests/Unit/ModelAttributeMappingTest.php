<?php

use App\Models\RecyclingTransaction;
use App\Models\SmartBin;
use App\Models\SmartBinLog;

it('uses distance-based smart bin attributes', function () {
    $fillable = (new SmartBin())->getFillable();

    expect($fillable)
        ->not->toContain('current_weight_kg')
        ->not->toContain('max_capacity_kg')
        ->toContain('current_fill_percentage')
        ->toContain('current_distance_cm')
        ->toContain('full_threshold_cm')
        ->toContain('empty_threshold_cm');
});

it('uses the migration fields for smart bin logs', function () {
    $fillable = (new SmartBinLog())->getFillable();

    expect($fillable)
        ->toContain('smart_bin_id')
        ->toContain('log_type')
        ->toContain('message')
        ->toContain('details');
});

it('removes total_weight_kg from recycling transactions', function () {
    $fillable = (new RecyclingTransaction())->getFillable();

    expect($fillable)
        ->not->toContain('total_weight_kg')
        ->toContain('total_items')
        ->toContain('total_points');
});
