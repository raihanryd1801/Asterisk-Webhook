<?php

use Illuminate\Support\Facades\Route;
use App\Models\Agent;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\SupervisorMonitoringController;

// ==========================================
// 1. AUTHENTICATION ROUTES
// ==========================================
Route::get('/login', [AuthController::class, 'showAdminLogin'])->name('login');
Route::post('/login', [AuthController::class, 'authenticateAdmin']);

Route::get('/agent/login', [AuthController::class, 'showAgentLogin']);
Route::post('/agent/login', [AuthController::class, 'authenticateAgent']);

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/agent/logout', [AuthController::class, 'agentLogout']);

// Redirect root ke login
Route::get('/', function () {
    return redirect('/login');
});

// ==========================================
// 2. AGENT WORKSPACE
// ==========================================
Route::get('/agent/{extension}', function ($extension) {
    $agent = Agent::where('extension', $extension)->firstOrFail();
    return view('agent.workspace', [
        'extension'   => $extension,
        'sipPassword' => $agent->secret,
    ]);
});

// ==========================================
// 3. ADMIN AREA (Hanya bisa diakses Admin)
// ==========================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');

    // Agent Management (CRUD)
    Route::get('/agents', [AgentController::class, 'index'])->name('admin.agents.index');
    Route::post('/agents/store', [AgentController::class, 'store'])->name('admin.agents.store');
    Route::delete('/agents/{id}', [AgentController::class, 'destroy'])->name('admin.agents.destroy');
});

// ==========================================
// 4. SUPERVISOR & ADMIN AREA (Dashboard & Monitoring)
// ==========================================
Route::middleware(['auth', 'role:supervisor,admin'])->prefix('supervisor')->group(function () {
    
    // Halaman Utama Dashboard Supervisor
    Route::get('/dashboard', function () {
        return view('supervisor.dashboard');
    })->name('supervisor.dashboard');

    // Halaman Call History
    Route::get('/call-history', function () {
        return view('supervisor.call-history');
    })->name('supervisor.call-history');

    // API / Monitoring Endpoints untuk SPV
    Route::get('/agents', [SupervisorMonitoringController::class, 'agentsList']);
    Route::post('/agents', [SupervisorMonitoringController::class, 'createAgent']);
    Route::post('/spy', [SupervisorMonitoringController::class, 'spyAction']);
    Route::get('/call-logs', [SupervisorMonitoringController::class, 'callLogs']);
    Route::post('/agent/{extension}/status', [SupervisorMonitoringController::class, 'updateStatus']);
});