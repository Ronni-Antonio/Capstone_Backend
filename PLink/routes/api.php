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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::put('users/{id}/password', [userController::class, 'updatePassword']);
Route::resource("users", userController::class);
Route::resource("collection", CollectionController::class);
Route::resource("students", StudentController::class);
Route::resource("transactions", TransactionController::class);
Route::resource("redemptions", RedemptionController::class);
Route::resource("rewards", RewardController::class);
Route::resource("plasticTypes", PlasticTypeController::class);
Route::post("auth/login", [AuthController::class, "login"]);
Route::post("auth/logout", [AuthController::class, "logout"])->middleware('auth:sanctum');
// System Settings routes
Route::get("settings", [SettingController::class, 'index']);
Route::post("settings", [SettingController::class, 'store']);

// Section routes
Route::get('sections', [SectionController::class, 'index']);
Route::get('sections/list', [SectionController::class, 'list']);
Route::post('sections', [SectionController::class, 'store']);
Route::put('sections/{sectionName}', [SectionController::class, 'update']);
Route::delete('sections/{sectionName}', [SectionController::class, 'destroy']);


// Import students from CSV
Route::post('students/import-csv', [StudentController::class, 'importCSV']);
// Process detailed transaction with classification data
Route::post('transactions/process', [TransactionController::class, 'processTransaction']);


