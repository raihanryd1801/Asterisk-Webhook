<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    // Menyimpan Agen Baru & Auto-Generate Secret SIP
    public function store(Request $request)
    {
        // Cek apakah yang login adalah admin
    if ($request->user()->role !== 'admin') {
        return response()->json(['status' => 'error', 'message' => 'Hanya Admin yang bisa membuat agen!'], 403);
    }
        $request->validate([
            'name'      => 'required|string|max:255',
            'extension' => 'required|string|unique:agents,extension',
        ]);

        // Generate password SIP (secret) secara acak yang aman
        $sipSecret = Str::random(12);

        // Simpan ke database Laravel tabel 'agents'
        $agent = Agent::create([
            'name'      => $request->name,
            'extension' => $request->extension,
            'secret'    => $sipSecret,
            'status'    => 'offline',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Agen {$request->name} (Ext: {$request->extension}) berhasil ditambahkan!",
            'data'    => $agent
        ]);
    }
    public function update(Request $request, $id)
{
    $agent = Agent::findOrFail($id);
    $agent->update($request->only(['name', 'extension', 'group_id']));
    // Contoh logic memindahkan agen ke Queue (Grup) di Asterisk
$queue = $agent->group->queue_number;
Process::run("asterisk -rx 'queue add member PJSIP/{$agent->extension} to {$queue}'");
    // Provisioning logic ke FreePBX (Contoh CLI)
    // Process::run("fwconsole pjsip update extension {$agent->extension} ...");
    
    return response()->json(['status' => 'success', 'message' => 'Agent updated!']);
}

public function destroy($id)
{
    $agent = Agent::findOrFail($id);
    
    // Hapus dari Asterisk dulu
    // Process::run("fwconsole pjsip delete extension {$agent->extension}");
    
    $agent->delete();
    return response()->json(['status' => 'success', 'message' => 'Agent deleted!']);
}
public function index()
{
    // Pastikan model Agent sudah di-import
    $agents = \App\Models\Agent::with('group')->get(); 
    return view('admin.agents.index', compact('agents'));
}
}