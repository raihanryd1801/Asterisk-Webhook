<?php

use Illuminate\Support\Facades\Route;
use App\Models\Agent;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SupervisorController;
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
// 2. DASHBOARD AREA (Semua URL Berawalan /dashboard)
// ==========================================
Route::prefix('dashboard')->group(function () {

    // ------------------------------------------
    // A. HALAMAN UTAMA (VIEWS)
    // ------------------------------------------

    // 1. Overview (Bisa diakses Agent/SPV/Admin)
    Route::get('/overview', function (Illuminate\Http\Request $request) {
        
        $range = $request->query('range', 'this_month');
        $query = App\Models\Cdr::query();

        switch ($range) {
            case 'today': $query->whereDate('calldate', today()); break;
            case '7_days': $query->where('calldate', '>=', now()->subDays(7)); break;
            case 'all_time': break;
            case 'this_month':
            default: $query->where('calldate', '>=', now()->startOfMonth()); break;
        }
        
        $managedExtensions = [];
        $roleTitle = "Overview";

        if (session()->has('agent_extension')) {
            $extension = session('agent_extension');
            $query->where(function($q) use ($extension) {
                $q->where('src', $extension)->orWhere('dst', $extension);
            });
            $roleTitle = "Agent Ext: " . $extension;
        } 
        elseif (session()->has('supervisor_extension')) {
            $spvExt = session('supervisor_extension');
            $spv = App\Models\Agent::where('extension', $spvExt)->first();

            if ($spv) {
                $managedExtensions = App\Models\Agent::where('supervisor_id', $spv->id)
                                          ->orWhere('id', $spv->id)->pluck('extension')->toArray();

                $query->where(function($q) use ($managedExtensions) {
                    $q->whereIn('src', $managedExtensions)->orWhereIn('dst', $managedExtensions);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
            $roleTitle = "Supervisor Group Overview";
        } 
        elseif (auth()->check()) {
            $user = auth()->user();
            $roleTitle = ($user->role === 'supervisor') ? "Supervisor Group Overview" : "Global Administrator Overview";
        } else {
            return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $today = today()->toDateString();
        $statsData = (clone $query)->selectRaw("
            COUNT(CASE WHEN DATE(calldate) = ? THEN 1 END) as today_calls,
            COUNT(CASE WHEN DATE(calldate) = ? AND disposition = 'ANSWERED' THEN 1 END) as today_answered,
            COUNT(CASE WHEN DATE(calldate) = ? AND disposition != 'ANSWERED' THEN 1 END) as today_unsuccessful,
            COUNT(*) as total_calls,
            COUNT(CASE WHEN disposition = 'ANSWERED' THEN 1 END) as all_answered
        ", [$today, $today, $today])->first();

        $total_calls  = $statsData->total_calls ?? 0;
        $allAnswered  = $statsData->all_answered ?? 0;
        $success_rate = $total_calls > 0 ? round(($allAnswered / $total_calls) * 100, 1) : 0;

        $stats = [
            'today_calls'  => $statsData->today_calls ?? 0,
            'paid'         => $statsData->today_answered ?? 0, 
            'promised'     => 0,                     
            'unsuccessful' => $statsData->today_unsuccessful ?? 0,
            'total_calls'  => $total_calls,
            'success_rate' => $success_rate,
            'all_time_paid'=> $allAnswered,    
            'all_time_prom'=> 0,
        ];

        $performanceQuery = clone $query;
        $agentPerformanceRaw = $performanceQuery->selectRaw("
            src as extension, COUNT(*) as total_calls, SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as connected_calls, SUM(billsec) as total_talk_time
        ")->where('src', '!=', '')->groupBy('extension')->orderBy('total_calls', 'desc')->limit(50)->get();

        $agentNames = \App\Models\Agent::whereIn('extension', $agentPerformanceRaw->pluck('extension'))->pluck('name', 'extension');

        $agentPerformance = $agentPerformanceRaw->map(function($item) use ($agentNames) {
            $name = $agentNames[$item->extension] ?? 'Unknown Agent';
            $percentage = $item->total_calls > 0 ? round(($item->connected_calls / $item->total_calls) * 100) : 0;
            $hours = floor($item->total_talk_time / 3600);
            $minutes = floor(($item->total_talk_time % 3600) / 60);
            $seconds = $item->total_talk_time % 60;
            $formattedTalkTime = sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);

            return [
                'name'            => $name,
                'extension'       => $item->extension,
                'total_calls'     => $item->total_calls,
                'connected_calls' => $item->connected_calls,
                'percentage'      => $percentage,
                'talk_time'       => $formattedTalkTime
            ];
        });

        return view('agent.overview', compact('stats', 'roleTitle', 'range', 'agentPerformance'));
    })->name('dashboard.overview');

    // 2. Agent Workspace
    Route::get('/workspace/{extension}', function ($extension) {
        $loggedExt = session('agent_extension');
        if (!$loggedExt) return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        if ($loggedExt !== $extension) return redirect('/dashboard/workspace/' . $loggedExt)->with('error', 'Akses ditolak!');

        $agent = Agent::where('extension', $extension)->firstOrFail();
        return view('agent.workspace', ['extension' => $extension, 'sipPassword' => $agent->secret]);
    })->name('dashboard.workspace');

    // 3. Supervisor & Admin Monitoring
    Route::get('/live-monitoring', [SupervisorController::class, 'dashboard'])->name('dashboard.live-monitoring');
    
    Route::get('/call-history', function () {
        if (!session()->has('supervisor_extension') && !auth()->check()) {
            return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        }
        return view('supervisor.call-history');
    })->name('dashboard.call-history');

    // 4. Admin Management (Hanya Admin)
    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('dashboard.users.index');
        
        Route::get('/agents', [AgentController::class, 'index'])->name('dashboard.agents.index');
        Route::post('/agents/store', [AgentController::class, 'store']);
        Route::put('/agents/{id}', [AgentController::class, 'update']);
        Route::delete('/agents/{id}', [AgentController::class, 'destroy']);
    });

    // ------------------------------------------
    // B. ENDPOINT API / AJAX (Untuk Fetch Alpine.js)
    // ------------------------------------------
    Route::get('/api/live-agents', [SupervisorMonitoringController::class, 'agentsList']);
    Route::post('/api/spy', [SupervisorMonitoringController::class, 'spyAction']);
    Route::get('/api/call-logs', [SupervisorMonitoringController::class, 'callLogs']);
    Route::get('/api/call-logs/export', [SupervisorMonitoringController::class, 'exportExcel']);
    Route::get('/api/play-recording', [SupervisorMonitoringController::class, 'playRecording']);
    Route::post('/api/agent/{extension}/status', [SupervisorMonitoringController::class, 'updateStatus']);
    Route::post('/api/agent/click-to-call', [SupervisorMonitoringController::class, 'agentClickToCall']);
});

// ==========================================
// 3. TEST ROUTES
// ==========================================
Route::get('/test-ami/{ext}', function($ext) {
    $amiService = app(\App\Services\Asterisk\OriginateService::class);
    return response()->json([
        'ekstensi_yang_dicek' => $ext,
        'hasil_dari_device_state' => $amiService->getExtensionState($ext),
        'hasil_dari_pjsip_show' => $amiService->isExtensionRegistered($ext) ? 'YES (Terdaftar)' : 'NO (Tidak Terdaftar)'
    ]);
});