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

// Sinkron pesan keluar yang dikirim dari HP (fromMe via Baileys)
Route::post('/wa/sent', [\App\Http\Controllers\WaController::class, 'outboundSync']);
// Webhook pesan masuk dari sidecar wa-gateway (otorisasi via token, tanpa login)
Route::post('/wa/inbound', [\App\Http\Controllers\WaController::class, 'inbound']);
// Status keterkiriman (delivered/read) dari sidecar
Route::post('/wa/receipt', [\App\Http\Controllers\WaController::class, 'receipt']);