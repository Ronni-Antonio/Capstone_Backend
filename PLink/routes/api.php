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
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MachineLogController;
use App\Http\Controllers\AnalyticsReportController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\AiModelController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProphetController;
use App\Http\Controllers\ReportsAnalyticsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\IotDeviceConfigController;
use App\Http\Controllers\IotControllerCommandController;

Route::get('/user', fn(Request $request) => $request->user())->middleware('auth:sanctum');

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Lightweight initial dashboard payload.
Route::get('dashboard', [DashboardController::class, 'index']);
Route::get('reports-analytics', [ReportsAnalyticsController::class, 'index']);

Route::get('logs', [ActivityLogController::class, 'index']);
Route::get('reports-analytics/pdf', [ReportsAnalyticsController::class, 'pdf']);
Route::get('prophet/status', [ProphetController::class, 'status']);
Route::get('prophet/models', [ProphetController::class, 'models']);
Route::post('prophet/train', [ProphetController::class, 'train']);
Route::post('prophet/run-forecast', [ProphetController::class, 'forecast']);
Route::post('auth/forgot-password/send-otp', [\App\Http\Controllers\ForgotPasswordController::class, 'sendOtp']);
Route::post('auth/forgot-password/verify-otp', [\App\Http\Controllers\ForgotPasswordController::class, 'verifyOtp']);
Route::post('auth/forgot-password/reset', [\App\Http\Controllers\ForgotPasswordController::class, 'resetPassword']);

Route::put('users/{id}/password', [userController::class, 'updatePassword']);
Route::post('user/{id}/request-email-change', [userController::class, 'requestEmailChange']);
Route::post('user/{id}/verify-email-change', [userController::class, 'verifyEmailChange']);
Route::resource('users', userController::class);

Route::get('settings', [SettingController::class, 'index']);
Route::post('settings', [SettingController::class, 'store']);
Route::put('settings', [SettingController::class, 'update']);
Route::patch('settings', [SettingController::class, 'update']);

Route::get('sections', [SectionController::class, 'index']);
Route::get('sections/list', [SectionController::class, 'list']);
Route::get('sections/ranking', [SectionController::class, 'sectionRankingOverall']);
Route::get('sections/ranking/overall', [SectionController::class, 'overallRanking']);
Route::get('sections/{id}/ranking', [SectionController::class, 'sectionRanking']);
Route::post('sections', [SectionController::class, 'store']);
Route::put('sections/{id}', [SectionController::class, 'update']);
Route::delete('sections/{id}', [SectionController::class, 'destroy']);

Route::post('students/import-csv', [StudentController::class, 'importCSV']);
Route::post('students/{id}/activate', [StudentController::class, 'activate']);
Route::post('students/assign-card', [StudentController::class, 'assignCard']);
Route::get('students/{id}/activate/status', [StudentController::class, 'activateStatus']);
Route::post('students/{id}/activate/cancel', [StudentController::class, 'cancelActivation']);
Route::post('students/identify-card', [StudentController::class, 'identifyCard']);
Route::get('students/active-scan-session', [StudentController::class, 'checkActiveScanSession']);
Route::post('students/clear-scan-session', [StudentController::class, 'clearScanSession']);
Route::resource('students', StudentController::class)->except(['create', 'edit']);

Route::get('transactions', [TransactionController::class, 'index']);
Route::get('transactions/{id}', [TransactionController::class, 'show']);
Route::post('transactions/process', [TransactionController::class, 'processTransaction']);
Route::post('iot/transactions/start', [TransactionController::class, 'start']);
Route::post('iot/transactions/{transactionCode}/classifications', [TransactionController::class, 'addClassification']);
Route::post('iot/transactions/{transactionCode}/rfid', [TransactionController::class, 'completeWithRfid']);
Route::delete('transactions/{id}', [TransactionController::class, 'destroy']);

// IoT controller Wi-Fi configuration.
// Admin routes require the logged-in Sanctum token.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('iot-device-configs', [IotDeviceConfigController::class, 'index']);
    Route::put('iot-device-configs/{controllerCode}', [IotDeviceConfigController::class, 'update']);
});

// Device-facing routes authenticate using X-Device-Key.
Route::get('iot/device-config/{controllerCode}', [IotDeviceConfigController::class, 'showForDevice']);
Route::post('iot/device-config/{controllerCode}/ack', [IotDeviceConfigController::class, 'acknowledge']);

// ESP32 outbound command queue. No inbound connection to the ESP32 is required.
Route::get('iot/controller-commands/{controllerCode}/next', [IotControllerCommandController::class, 'next']);
Route::post('iot/controller-commands/{controllerCode}/{commandId}/ack', [IotControllerCommandController::class, 'acknowledge']);

Route::resource('plastictypes', PlasticTypeController::class)->except(['create', 'edit']);
Route::get('rewards/inventory', [RewardController::class, 'inventory']);
Route::resource('rewards', RewardController::class)->except(['create', 'edit']);

Route::get('redemptions/initiate/{student_id}/{reward_id}/status', [RedemptionController::class, 'checkRedemptionStatus']);
Route::post('redemptions/initiate/{student_id}/{reward_id}', [RedemptionController::class, 'initiateRedemptionProcess']);
Route::resource('redemptions', RedemptionController::class)->except(['create', 'edit']);

Route::resource('collections', CollectionController::class)->except(['create', 'edit']);
Route::match(['put', 'patch'], 'machines/{machine}/compartments/{compartment}/sensor', [MachineController::class, 'updateCompartmentSensor']);
Route::resource('machines', MachineController::class)->except(['create', 'edit']);
Route::resource('machine-logs', MachineLogController::class)->except(['create', 'edit']);
Route::resource('notifications', NotificationController::class)->except(['create', 'edit']);
Route::resource('analytics-reports', AnalyticsReportController::class)->except(['create', 'edit']);
Route::resource('classifications', ClassificationController::class)->except(['create', 'edit']);
Route::resource('ai-models', AiModelController::class)->except(['create', 'edit']);
Route::resource('predictions', PredictionController::class)->except(['create', 'edit']);

/*
|--------------------------------------------------------------------------
| Legacy aliases
|--------------------------------------------------------------------------
| Keep these only so the existing React frontend has a transition path.
| "machines" now represent smart_bins; "classifications" now represent
| ai_classifications. "recycling-sessions" is intentionally removed.
*/
Route::get('collection', [CollectionController::class, 'index']);
Route::post('collection', [CollectionController::class, 'store']);


