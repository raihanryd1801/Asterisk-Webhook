<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;

class SupervisorController extends Controller
{
    public function dashboard()
    {
        // 🔒 PENGAMAN AKSES (Ganda: Untuk Supervisor & Admin)
        $isSupervisor = session()->has('supervisor_extension');
        $isAdmin = auth()->check();

        if (!$isSupervisor && !$isAdmin) {
            return redirect('/agent/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        // 1. Ambil semua data agent & supervisor untuk live monitoring
        $agents = Agent::all();

        // 2. Ambil data Call History (CDR) dengan pengaman try-catch 
        // (Jaga-jaga jika koneksi database cdr belum aktif)
        try {
            $callLogs = DB::connection('mysql_cdr')
                          ->table('cdr')
                          ->orderBy('calldate', 'desc')
                          ->limit(50)
                          ->get();
        } catch (\Exception $e) {
            $callLogs = collect(); // Kosongkan jika koneksi CDR belum siap
        }

        // 3. Kirim variabel $agents dan $callLogs ke view supervisor.dashboard
        return view('supervisor.live-monitoring', compact('agents', 'callLogs'));
    }
}