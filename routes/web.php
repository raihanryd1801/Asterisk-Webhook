<?php

use Illuminate\Support\Facades\Route;
use App\Models\Agent;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\SupervisorMonitoringController;
use App\Http\Controllers\SupervisorController;

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
// 2. AGENT OVERVIEW (DUMMY STATS AMAN TANPA ERROR)
// ==========================================
Route::get('/agent/overview', function () {
    // 1. Jika yang login adalah AGENT (via session extension)
    if (session()->has('agent_extension')) {
        $extension = session('agent_extension');
        
        $stats = [
            'today_calls'  => 19,
            'paid'         => 0,
            'promised'     => 0,
            'unsuccessful' => 0,
            'total_calls'  => 362,
            'success_rate' => 5,
            'all_time_paid'=> 1,
            'all_time_prom'=> 9,
        ];
        $roleTitle = "Agent Ext: " . $extension;
    } 
    // 2. Jika yang login adalah SUPERVISOR atau ADMIN
    elseif (auth()->check()) {
        $user = auth()->user();

        if ($user->role === 'supervisor') {
            $stats = [
                'today_calls'  => 45,
                'paid'         => 3,
                'promised'     => 12,
                'unsuccessful' => 5,
                'total_calls'  => 1250,
                'success_rate' => 8,
                'all_time_paid'=> 85,
                'all_time_prom'=> 210,
            ];
            $roleTitle = "Supervisor Group Overview";
        } else {
            $stats = [
                'today_calls'  => 120,
                'paid'         => 10,
                'promised'     => 35,
                'unsuccessful' => 15,
                'total_calls'  => 4500,
                'success_rate' => 12,
                'all_time_paid'=> 350,
                'all_time_prom'=> 890,
            ];
            $roleTitle = "Global Administrator Overview";
        }
    } else {
        return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
    }

    return view('agent.overview', compact('stats', 'roleTitle'));
});

// ==========================================
// 3. AGENT WORKSPACE (DIAMANKAN)
// ==========================================
Route::get('/agent/{extension}', function ($extension) {
    $loggedExt = session('agent_extension');

    if (!$loggedExt) {
        return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
    }

    if ($loggedExt !== $extension) {
        return redirect('/agent/' . $loggedExt)->with('error', 'Akses ditolak! Anda tidak boleh membuka ekstensi agen lain.');
    }

    $agent = Agent::where('extension', $extension)->firstOrFail();
    return view('agent.workspace', [
        'extension'   => $extension,
        'sipPassword' => $agent->secret,
    ]);
});

// ==========================================
// 4. ADMIN AREA (Hanya bisa diakses Admin)
// ==========================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');

    Route::get('/agents', [AgentController::class, 'index'])->name('admin.agents.index');
    Route::post('/agents/store', [AgentController::class, 'store'])->name('admin.agents.store');
    Route::put('/agents/{id}', [AgentController::class, 'update'])->name('admin.agents.update'); 
    Route::delete('/agents/{id}', [AgentController::class, 'destroy'])->name('admin.agents.destroy');
});

// ==========================================
// 5. SUPERVISOR AREA (Dashboard & Monitoring)
// ==========================================
Route::prefix('supervisor')->group(function () {
    Route::get('/dashboard', [SupervisorController::class, 'dashboard'])->name('supervisor.dashboard');

    Route::get('/call-history', function () {
        $isSupervisor = session()->has('supervisor_extension');
        $isAdmin = auth()->check();

        if (!$isSupervisor && !$isAdmin) {
            return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        return view('supervisor.call-history');
    })->name('supervisor.call-history');

    Route::post('/logout', [AuthController::class, 'supervisorLogout'])->name('supervisor.logout');

    Route::get('/agents', [SupervisorMonitoringController::class, 'agentsList']);
    Route::post('/agents', [SupervisorMonitoringController::class, 'createAgent']);
    Route::post('/spy', [SupervisorMonitoringController::class, 'spyAction']);
    Route::get('/call-logs', [SupervisorMonitoringController::class, 'callLogs']);
    Route::post('/agent/{extension}/status', [SupervisorMonitoringController::class, 'updateStatus']);
});