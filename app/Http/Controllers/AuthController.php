<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Agent;

class AuthController extends Controller
{
    // ================= ADMIN LOGIN (SUPERVISOR) =================
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
            
            // Ambil data user yang sedang login
            $user = Auth::user();

            // PENGALIHAN OTOMATIS BERDASARKAN ROLE
            if (isset($user->role) && $user->role === 'supervisor') {
                return redirect()->route('supervisor.dashboard');
            }

            // Jika dia Admin biasa / Manager, arahkan ke manajemen agent
            return redirect('/dashboard/agents');
        }

        return back()->with('error', 'Email atau password salah!')->onlyInput('email');
    }
    // ================= AGENT LOGIN (PORTAL AGENT) =================
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

        if ($agent && $agent->secret === $request->password) {
            
            // 1. JIKA YANG LOGIN ADALAH SUPERVISOR
            if (isset($agent->role) && $agent->role === 'supervisor') {
                session(['supervisor_extension' => $agent->extension]);
                
                $agent->status = 'online';
                $agent->save();

                // 🚀 PANCARKAN EVENT SPV ONLINE KE DASHBOARD
                broadcast(new \App\Events\AgentStatusUpdated($agent));

                return redirect()->route('dashboard.overview');
            }

            // 2. JIKA YANG LOGIN ADALAH AGEN BIASA (CS)
            session(['agent_extension' => $agent->extension]);
            
            $agent->status = 'online';
            $agent->save();

            // Kirim broadcast status online
            broadcast(new \App\Events\AgentStatusUpdated($agent));

            return redirect('/dashboard/workspace/' . $agent->extension);
        }

        return back()->with('error', 'Nomor Ekstensi atau Password SIP salah!')->withInput();
    }

    // ================= LOGOUT UTAMA =================
   public function logout(Request $request)
    {
        // 1. Jika yang logout adalah Agent
        if (session()->has('agent_extension')) {
            $agent = Agent::where('extension', session('agent_extension'))->first();
            if ($agent) {
                $agent->status = 'offline';
                $agent->save();
                broadcast(new \App\Events\AgentStatusUpdated($agent));
            }
            session()->forget('agent_extension');
        }

        // 2. 🚀 TAMBAHKAN INI: Jika yang logout adalah Supervisor
        if (session()->has('supervisor_extension')) {
            $supervisor = Agent::where('extension', session('supervisor_extension'))->first();
            if ($supervisor) {
                $supervisor->status = 'offline';
                $supervisor->save();
                broadcast(new \App\Events\AgentStatusUpdated($supervisor));
            }
            session()->forget('supervisor_extension');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login');
    }

    public function agentLogout(Request $request)
    {
        if (session()->has('agent_extension')) {
            $agent = Agent::where('extension', session('agent_extension'))->first();
            if ($agent) {
                $agent->status = 'offline';
                $agent->save();

                // 🚀 KIRIM BROADCAST SAAT AGENT LOGOUT
                broadcast(new \App\Events\AgentStatusUpdated($agent));
            }
            session()->forget('agent_extension');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/agent/login');
    }

    public function supervisorLogout(Request $request)
    {
        if (session()->has('supervisor_extension')) {
            $supervisor = Agent::where('extension', session('supervisor_extension'))->first();
            if ($supervisor) {
                $supervisor->status = 'offline';
                $supervisor->save();
                
                // 🚀 Tambahkan ini supaya Supervisor juga real-time
                broadcast(new \App\Events\AgentStatusUpdated($supervisor));
            }
            session()->forget('supervisor_extension');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/agent/login');
    }
}