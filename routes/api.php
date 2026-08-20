<?php

use App\Http\Controllers\Api\AgentWorkspaceController;
use App\Http\Controllers\Api\SupervisorMonitoringController;
use Illuminate\Support\Facades\Route;

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