<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userController as userController;
use App\Http\Controllers\AuthController as AuthController;
use App\Http\Controllers\CollectionController as CollectionController;
use App\Http\Controllers\StudentController as StudentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::resource("users", userController::class);
Route::resource("collection", CollectionController::class);
Route::resource("students", StudentController::class);
Route::post("auth/login", [AuthController::class, "login"]);
Route::post("auth/logout", [AuthController::class, "logout"])->middleware('auth:sanctum');


