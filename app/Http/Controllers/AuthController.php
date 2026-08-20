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
            $agent->update(['status' => 'online']);
            return redirect('/agent/' . $agent->extension);
        }

        return back()->with('error', 'Nomor Ekstensi atau Password SIP salah!')->withInput();
    }

    // ================= LOGOUT =================
    public function logout(Request $request)
    {
        if (session()->has('agent_extension')) {
            Agent::where('extension', session('agent_extension'))->update(['status' => 'offline']);
            session()->forget('agent_extension');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login');
    }
}