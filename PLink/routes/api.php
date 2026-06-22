<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userController as userController;
use App\Http\Controllers\AuthController as AuthController;
use App\Http\Controllers\CollectionController as CollectionController;
use App\Http\Controllers\StudentController as StudentController;
use App\Http\Controllers\TransactionController as TransactionController;
use App\Http\Controllers\RedemptionController as RedemptionController;
use App\Http\Controllers\RewardController as RewardController;
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
Route::post("auth/login", [AuthController::class, "login"]);
Route::post("auth/logout", [AuthController::class, "logout"])->middleware('auth:sanctum');
// System Settings routes
Route::get('settings', [SettingController::class, 'index']);
Route::post('settings', [SettingController::class, 'store']);
// Section Management interacting directly with the Student table cluster
Route::get('/sections', [SectionController::class, 'index']);
Route::post('/sections', [SectionController::class, 'store']);
Route::put('/sections/{sectionName}', [SectionController::class, 'update']);
Route::delete('/sections/{sectionName}', [SectionController::class, 'destroy']);


// Import students from CSV
Route::post('students/import-csv', [StudentController::class, 'importCSV']);


