<?php

use App\Http\Controllers\Api\AgentWorkspaceController;
use App\Http\Controllers\Api\SupervisorMonitoringController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CallLogController;

Route::prefix('agent/{extension}')->group(function () {
    Route::get('/', [AgentWorkspaceController::class, 'profile']);
    Route::post('/status', [AgentWorkspaceController::class, 'updateStatus']);
    Route::post('/call', [AgentWorkspaceController::class, 'call']);
    
});

Route::prefix('supervisor')->group(function () {
    Route::get('/agents', [SupervisorMonitoringController::class, 'agentsList']);
    Route::post('/spy', [SupervisorMonitoringController::class, 'spyAction']);
    Route::get('/call-logs', [SupervisorMonitoringController::class, 'callLogs']);
    Route::get('/play-recording', [SupervisorMonitoringController::class, 'playRecording']);
});

Route::post('/call-logs/store', [CallLogController::class, 'store']);

// PDS customer-first: dipakai AGI pds-bridge.php (otorisasi via X-Pds-Token)
Route::post('/pds/bridge', [\App\Http\Controllers\Api\PdsBridgeController::class, 'bridge']);
Route::post('/pds/result', [\App\Http\Controllers\Api\PdsBridgeController::class, 'result']);

// v1 mobile (collector lapangan): login nomor HP + PIN -> token Bearer.
// Login dibatasi 10x/menit anti brute-force; sisanya pakai token.
Route::post('/v1/collector/login', [\App\Http\Controllers\Api\CollectorAuthController::class, 'login'])->middleware('throttle:10,1');
Route::prefix('v1/collector')->middleware(['collector.token', 'throttle:120,1'])->group(function () {
    Route::get('/me', [\App\Http\Controllers\Api\CollectorTrackingController::class, 'me']);
    Route::post('/position', [\App\Http\Controllers\Api\CollectorTrackingController::class, 'store']);
    Route::post('/stop', [\App\Http\Controllers\Api\CollectorTrackingController::class, 'stop']);
    Route::post('/logout', [\App\Http\Controllers\Api\CollectorAuthController::class, 'logout']);
    Route::get('/customers', [\App\Http\Controllers\Api\CollectorCustomerController::class, 'index']);
    Route::get('/customers/{id}', [\App\Http\Controllers\Api\CollectorCustomerController::class, 'show']);
    Route::post('/visits', [\App\Http\Controllers\Api\CollectorVisitController::class, 'start']);
    Route::get('/visits/current', [\App\Http\Controllers\Api\CollectorVisitController::class, 'current']);
    Route::post('/visits/{id}/arrive', [\App\Http\Controllers\Api\CollectorVisitController::class, 'arrive']);
    Route::post('/visits/{id}/finish', [\App\Http\Controllers\Api\CollectorVisitController::class, 'finish']);
    Route::post('/visits/{id}/cancel', [\App\Http\Controllers\Api\CollectorVisitController::class, 'cancel']);
});

// Sinkron pesan keluar yang dikirim dari HP (fromMe via Baileys)
Route::post('/wa/sent', [\App\Http\Controllers\WaController::class, 'outboundSync']);
// Webhook pesan masuk dari sidecar wa-gateway (otorisasi via token, tanpa login)
Route::post('/wa/inbound', [\App\Http\Controllers\WaController::class, 'inbound']);
// Status keterkiriman (delivered/read) dari sidecar
Route::post('/wa/receipt', [\App\Http\Controllers\WaController::class, 'receipt']);