<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Agent;

class AuthController extends Controller
{
    // ================= ADMIN LOGIN (UTAMA) =================
    public function showAdminLogin()
    {
        return view('auth.login');
    }

    public function authenticateAdmin(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Karena tabel users cuma buat Admin, langsung arahkan ke Dashboard Admin
            return redirect('/dashboard/agents');
        }

        return back()->with('error', 'Email atau password salah!')->onlyInput('email');
    }

    // ================= AGENT & SPV LOGIN (PORTAL) =================
    public function showAgentLogin()
    {
        return view('auth.login-agent');
    }

    public function authenticateAgent(Request $request)
    {
        $request->validate([
            'extension' => 'required',
            'password'  => 'required', 
        ]);

        $agent = Agent::where('extension', $request->extension)->first();

        // Pengecekan extension dan secret password
        if ($agent && $agent->secret === $request->password) {
            
            // 1. JIKA YANG LOGIN ADALAH SUPERVISOR
            if ($agent->role === 'supervisor') {
                session(['supervisor_extension' => $agent->extension]);
                $redirectUrl = route('dashboard.overview');
            } 
            // 2. JIKA YANG LOGIN ADALAH AGEN BIASA (CS)
            else {
                session(['agent_extension' => $agent->extension]);
                $redirectUrl = '/dashboard/workspace/' . $agent->extension;
            }

            // Update status & Broadcast Realtime (Berlaku untuk SPV & Agent)
            $agent->status = 'online';
            $agent->save();
            broadcast(new \App\Events\AgentStatusUpdated($agent));

            return redirect($redirectUrl);
        }

        return back()->with('error', 'Nomor Ekstensi atau Password SIP salah!')->withInput();
    }

    // ================= LOGOUT ADMIN (UTAMA) =================
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login');
    }

    // ================= LOGOUT AGENT & SPV (PORTAL) =================
    public function agentLogout(Request $request)
    {
        // 🚀 Cek sesi: Apakah dia Agent atau SPV? Ambil extension-nya
        $ext = session('agent_extension') ?? session('supervisor_extension');

        if ($ext) {
            $user = Agent::where('extension', $ext)->first();
            if ($user) {
                $user->status = 'offline';
                $user->save();

                // 🚀 Pancarkan status Offline ke Live Monitoring
                broadcast(new \App\Events\AgentStatusUpdated($user));
            }
        }

        // Hapus semua sesi portal, lalu tendang ke halaman login agent
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/agent/login');
    }
}