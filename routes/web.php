<?php

use Illuminate\Support\Facades\Route;
use App\Models\Agent;
use App\Http\Controllers\Api\SupervisorMonitoringController;
use App\Http\Controllers\AuthController;

// --- LOGIN ADMIN ---
Route::get('/login', [AuthController::class, 'showAdminLogin'])->name('login');
Route::post('/login', [AuthController::class, 'authenticateAdmin']);

// --- LOGIN AGENT ---
Route::get('/agent/login', [AuthController::class, 'showAgentLogin']);
Route::post('/agent/login', [AuthController::class, 'authenticateAgent']);

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Halaman utama (Landing redirect atau langsung login)
Route::get('/', function () {
    return redirect('/login');
});

// Route untuk Agent Workspace
Route::get('/agent/{extension}', function ($extension) {
    $agent = Agent::where('extension', $extension)->firstOrFail();
    return view('agent.workspace', [
        'extension'   => $extension,
        'sipPassword' => $agent->secret,
    ]);
});

// Grup Route Supervisor (Dilindungi Middleware Auth)
Route::middleware('auth')->prefix('supervisor')->group(function () {
    
    // 1. Halaman Utama Dashboard Supervisor
    Route::get('/dashboard', function () {
        return view('supervisor.dashboard');
    })->name('supervisor.dashboard');

    // 2. Endpoint API / Data untuk Dashboard & Aksi SPV
    Route::get('/agents', [SupervisorMonitoringController::class, 'agentsList']);
    Route::post('/agents', [SupervisorMonitoringController::class, 'createAgent']); // 🚀 Tambahan rute Create Agent
    Route::post('/spy', [SupervisorMonitoringController::class, 'spyAction']);
    Route::get('/call-logs', [SupervisorMonitoringController::class, 'callLogs']);
    
    // 3. Halaman Call History
    Route::get('/call-history', function () {
        return view('supervisor.call-history');
    });
});