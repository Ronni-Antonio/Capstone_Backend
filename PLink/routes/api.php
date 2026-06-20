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

// Import students from CSV
Route::post('students/import-csv', [StudentController::class, 'importCSV']);


