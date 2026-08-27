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

        // Statistik Utama
        $statsData = (clone $query)->selectRaw("
            SUM(CASE WHEN DATE(calldate) = CURDATE() THEN 1 ELSE 0 END) as today_calls,
            SUM(CASE WHEN DATE(calldate) = CURDATE() AND disposition = 'ANSWERED' THEN 1 ELSE 0 END) as today_answered,
            COUNT(*) as total_calls,
            SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as all_answered
        ")->first();

        $today_calls      = (int) ($statsData->today_calls ?? 0);
        $today_answered   = (int) ($statsData->today_answered ?? 0);
        $today_unanswered = $today_calls - $today_answered; 
        $today_rate       = $today_calls > 0 ? round(($today_answered / $today_calls) * 100, 1) : 0;

        $total_calls    = (int) ($statsData->total_calls ?? 0);
        $all_answered   = (int) ($statsData->all_answered ?? 0);
        $all_unanswered = $total_calls - $all_answered;
        $all_time_rate  = $total_calls > 0 ? round(($all_answered / $total_calls) * 100, 1) : 0;

        $stats = [
            'today_calls'      => $today_calls,
            'today_answered'   => $today_answered,
            'today_unanswered' => $today_unanswered,
            'today_rate'       => $today_rate,
            'total_calls'      => $total_calls,
            'all_answered'     => $all_answered,
            'all_unanswered'   => $all_unanswered,
            'all_time_rate'    => $all_time_rate,
        ];

        // ==========================================
        // 🚀 1. DATA CALL VOLUME CHART
        // ==========================================
        // ==========================================
        // 🚀 1. DATA CALL VOLUME CHART (Dinamis Berdasarkan Filter)
        // ==========================================
        $chartVolumeCategories = [];
        $chartVolumeData = [];
        $chartSubtitle = "Call volume overview.";

        if ($range === 'today') {
            $volumeDataRaw = (clone $query)->selectRaw("HOUR(calldate) as time_key, COUNT(*) as total")
                ->groupByRaw("HOUR(calldate)")->get();
            $volumeRaw = $volumeDataRaw->pluck('total', 'time_key')->toArray();

            for ($i = 0; $i < 24; $i++) {
                $chartVolumeCategories[] = sprintf("%02d:00", $i);
                $chartVolumeData[] = (int) ($volumeRaw[$i] ?? 0);
            }
            $chartSubtitle = "Calls per hour, WIB.";
        } 
        elseif ($range === '7_days') {
            $volumeDataRaw = (clone $query)->selectRaw("DATE(calldate) as time_key, COUNT(*) as total")
                ->groupByRaw("DATE(calldate)")->get();
            $volumeRaw = $volumeDataRaw->pluck('total', 'time_key')->toArray();

            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $chartVolumeCategories[] = $date->format('d M');
                $chartVolumeData[] = (int) ($volumeRaw[$date->toDateString()] ?? 0);
            }
            $chartSubtitle = "Calls per day, last 7 days.";
        } 
        elseif ($range === 'this_month') {
            $volumeDataRaw = (clone $query)->selectRaw("DATE(calldate) as time_key, COUNT(*) as total")
                ->groupByRaw("DATE(calldate)")->get();
            $volumeRaw = $volumeDataRaw->pluck('total', 'time_key')->toArray();

            $daysInMonth = now()->daysInMonth;
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $dateString = now()->setDay($i)->toDateString();
                $chartVolumeCategories[] = $i;
                $chartVolumeData[] = (int) ($volumeRaw[$dateString] ?? 0);
            }
            $chartSubtitle = "Calls per day, this month.";
        } 
        else {
            $volumeDataRaw = (clone $query)->selectRaw("DATE_FORMAT(calldate, '%Y-%m') as time_key, COUNT(*) as total")
                ->groupByRaw("DATE_FORMAT(calldate, '%Y-%m')")
                ->orderBy('time_key')
                ->get();
            $volumeRaw = $volumeDataRaw->pluck('total', 'time_key')->toArray();

            foreach ($volumeRaw as $key => $val) {
                $date = \Carbon\Carbon::createFromFormat('Y-m', $key);
                $chartVolumeCategories[] = $date->format('M Y');
                $chartVolumeData[] = (int) $val;
            }
            $chartSubtitle = "Calls per month, all time.";
        }
        // ==========================================
        // 🚀 2. DATA CALL OUTCOMES CHART
        // ==========================================
        $outcomesRaw = (clone $query)
            ->selectRaw("disposition, COUNT(*) as total")
            ->groupBy('disposition')
            ->pluck('total', 'disposition');

        $chartOutcomesCounts = [
            $outcomesRaw['CANCEL'] ?? 0,
            $outcomesRaw['NO ANSWER'] ?? 0,
            $outcomesRaw['ANSWERED'] ?? 0,
            $outcomesRaw['BUSY'] ?? 0,
            $outcomesRaw['FAILED'] ?? 0,
        ];

        // ==========================================
        // TABEL AGENT PERFORMANCE
        // ==========================================
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

        // 🚀 Pastikan semua variabel ini ikut di-compact ke view
        return view('agent.overview', compact(
            'stats', 
            'roleTitle', 
            'range', 
            'agentPerformance', 
            'chartVolumeCategories', 
            'chartVolumeData', 
            'chartOutcomesCounts'
        ));
    })->name('dashboard.overview');

    // 2. Agent Workspace
    Route::get('/workspace/{extension}', function ($extension) {
        $loggedExt = session('agent_extension');
        if (!$loggedExt) return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        if ($loggedExt !== $extension) return redirect('/dashboard/workspace/' . $loggedExt)->with('error', 'Akses ditolak!');

        $agent = Agent::where('extension', $extension)->firstOrFail();
        return view('agent.workspace', ['extension' => $extension, 'sipPassword' => $agent->secret]);
    })->name('dashboard.workspace');

    Route::get('/agent/call-history', function () {
        if (!session()->has('agent_extension')) {
            return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        }
        return view('agent.call-history', ['extension' => session('agent_extension')]);
    })->name('dashboard.agent.call-history');

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

    // B. ENDPOINT API / AJAX
    Route::get('/api/live-agents', [SupervisorMonitoringController::class, 'agentsList']);
    Route::post('/api/spy', [SupervisorMonitoringController::class, 'spyAction']);
    Route::get('/api/call-logs', [SupervisorMonitoringController::class, 'callLogs']);
    Route::get('/api/call-logs/export', [SupervisorMonitoringController::class, 'exportExcel']);
    Route::get('/api/play-recording', [SupervisorMonitoringController::class, 'playRecording']);
    Route::post('/api/agent/{extension}/status', [SupervisorMonitoringController::class, 'updateStatus']);
    Route::post('/api/agent/click-to-call', [SupervisorMonitoringController::class, 'agentClickToCall']);
    Route::post('/monitoring/takeover', [SupervisorMonitoringController::class, 'takeoverAction']);
    Route::post('/api/call-logs/{uniqueid}/note', [SupervisorMonitoringController::class, 'saveNote']);
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