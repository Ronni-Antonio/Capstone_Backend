<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\RedemptionController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\PlasticTypeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\RecyclingSessionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MachineLogController;
use App\Http\Controllers\AnalyticsReportController;
use App\Http\Controllers\ClassificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::put('users/{id}/password', [userController::class, 'updatePassword']);
Route::resource("users", userController::class);
Route::resource("collection", CollectionController::class);
Route::resource("transactions", TransactionController::class);
Route::resource("rewards", RewardController::class);
Route::resource("plasticTypes", PlasticTypeController::class);
Route::post("auth/login", [AuthController::class, "login"]);
Route::post("auth/logout", [AuthController::class, "logout"])->middleware('auth:sanctum');

// Forgot Password Routes
Route::post('auth/forgot-password/send-otp', [\App\Http\Controllers\ForgotPasswordController::class, 'sendOtp']);
Route::post('auth/forgot-password/verify-otp', [\App\Http\Controllers\ForgotPasswordController::class, 'verifyOtp']);
Route::post('auth/forgot-password/reset', [\App\Http\Controllers\ForgotPasswordController::class, 'resetPassword']);
// System Settings routes
Route::get("settings", [SettingController::class, 'index']);
Route::post("settings", [SettingController::class, 'store']);

// Section routes
Route::get('sections', [SectionController::class, 'index']);
Route::get('sections/list', [SectionController::class, 'list']);
Route::get('sections/ranking', [SectionController::class, 'sectionRankingOverall']);
Route::get('sections/ranking/overall', [SectionController::class, 'overallRanking']);
Route::get('sections/{sectionName}/ranking', [SectionController::class, 'sectionRanking']);
Route::post('sections', [SectionController::class, 'store']);
Route::put('sections/{sectionName}', [SectionController::class, 'update']);
Route::delete('sections/{sectionName}', [SectionController::class, 'destroy']);

// Student Routes
Route::post('students/import-csv', [StudentController::class, 'importCSV']);
Route::post('students/{id}/activate', [StudentController::class, 'activate']);
Route::post('students/assign-card', [StudentController::class, 'assignCard']);
Route::get('/students/{id}/activate/status', [StudentController::class, 'activateStatus']);
Route::post('/students/{id}/activate/cancel', [StudentController::class, 'cancelActivation']); 
Route::post('/students/identify-card', [StudentController::class, 'identifyCard']);
Route::get('/students/active-scan-session', [StudentController::class, 'checkActiveScanSession']);
Route::post('/students/clear-scan-session', [StudentController::class, 'clearScanSession']);
Route::resource("students", StudentController::class);

// Process detailed transaction with classification data
Route::post('transactions/process', [TransactionController::class, 'processTransaction']);

// Redemption Routes
Route::get('/redemptions/initiate/{student_id}/{reward_id}/status', [RedemptionController::class, 'checkRedemptionStatus']);
Route::post('/redemptions/initiate/{student_id}/{reward_id}', [RedemptionController::class, 'initiateRedemptionProcess']);
Route::post('/redemptions', [RedemptionController::class, 'store']);
Route::resource('redemptions', RedemptionController::class);


// New resource routes
Route::resource('machines', MachineController::class);
Route::resource('recycling-sessions', RecyclingSessionController::class);
Route::resource('notifications', NotificationController::class);
Route::resource('machine-logs', MachineLogController::class);
Route::resource('analytics-reports', AnalyticsReportController::class);
Route::resource('classifications', ClassificationController::class);


