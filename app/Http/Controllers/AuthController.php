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
            return redirect('/supervisor/dashboard');
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
            session(['agent_extension' => $agent->extension]);
            
            // 🚀 Ubah status jadi online dan save secara model agar instance-nya aktif
            $agent->status = 'online';
            $agent->save();

            // 🚀 KIRIM BROADCAST KE REVERB SAAT LOGIN
            broadcast(new \App\Events\AgentStatusUpdated($agent));

            return redirect('/agent/' . $agent->extension);
        }

        return back()->with('error', 'Nomor Ekstensi atau Password SIP salah!')->withInput();
    }

    // ================= LOGOUT =================
    public function logout(Request $request)
    {
        if (session()->has('agent_extension')) {
            $agent = Agent::where('extension', session('agent_extension'))->first();
            if ($agent) {
                $agent->status = 'offline';
                $agent->save();

                // 🚀 KIRIM BROADCAST SAAT LOGOUT
                broadcast(new \App\Events\AgentStatusUpdated($agent));
            }
            session()->forget('agent_extension');
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
}