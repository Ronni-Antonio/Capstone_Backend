<?php

use App\Models\SmartBinLog;
use App\Models\RecyclingTransaction;

test('it uses the migration fields for smart bin logs', function () {
    $fillable = (new SmartBinLog())->getFillable();

    expect($fillable)
        ->toContain('smart_bin_id')
        ->toContain('distance_cm')
        ->toContain('fill_percentage')
        ->toContain('status');
});

test('it removes total_weight_kg from recycling transactions', function () {
    $fillable = (new RecyclingTransaction())->getFillable();

    expect($fillable)
        ->not->toContain('total_weight_kg');
});