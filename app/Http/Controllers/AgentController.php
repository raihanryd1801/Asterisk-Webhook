<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use Illuminate\Support\Str;
use App\Jobs\ProvisionAsteriskAgent;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    public function index()
    {
        $agents = Agent::all();
        $supervisors = Agent::where('role', 'supervisor')->get(); 
        
        return view('admin.agents.index', compact('agents', 'supervisors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'extension'     => 'required|string|unique:agents,extension',
            'supervisor_id' => 'nullable|exists:agents,id', 
            'secret'        => 'nullable|string|min:4',
            'role'          => 'required|in:agent,supervisor',
            'context'       => 'nullable|string|in:from-internal,blokir-total' // 🚀 Validasi context
        ]);

        $sipSecret = $request->filled('secret') ? $request->secret : Str::random(12);
        $context = $request->context ?? 'from-internal'; // 🚀 Default context

        $agent = Agent::create([
            'name'          => $request->name,
            'extension'     => $request->extension,
            'secret'        => $sipSecret,
            'role'          => $request->role,
            'supervisor_id' => $request->supervisor_id,
            'status'        => 'offline',
            'context'       => $context, // 🚀 Simpan ke database lokal
        ]);

        // Lempar ke background queue
        ProvisionAsteriskAgent::dispatch($agent, 'create');

        return response()->json([
            'status'  => 'success',
            'message' => "Agen {$agent->name} berhasil disimpan dan diproses di latar belakang!"
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'supervisor_id' => 'nullable|exists:agents,id', 
            'secret'        => 'nullable|string|min:4',
            'role'          => 'required|in:agent,supervisor',
            'context'       => 'nullable|string|in:from-internal,blokir-total' // 🚀 Validasi context
        ]);

        $agent = Agent::findOrFail($id);
        $oldSecret = $agent->secret;
        $oldContext = $agent->context; // 🚀 Cek context lama
        
        $agent->name          = $request->name;
        $agent->supervisor_id = $request->supervisor_id;
        $agent->role          = $request->role;
        $agent->context       = $request->context ?? 'from-internal'; // 🚀 Update context

        $secretChanged = false;
        if ($request->filled('secret')) {
            $agent->secret = $request->secret;
            $secretChanged = ($request->secret !== $oldSecret);
        }

        // Cek apakah context juga berubah (Opsional, buat penanda di Job)
        $contextChanged = ($agent->context !== $oldContext);

        $agent->save();

        // Lempar proses update ke background queue
        // (Abang bisa memodifikasi parameter dispatch ini jika Job butuh tahu context-nya berubah)
        ProvisionAsteriskAgent::dispatch($agent, 'update', $secretChanged);

        return response()->json([
            'status'  => 'success',
            'message' => "Data agen {$agent->name} sedang diperbarui di latar belakang!"
        ]);
    }

    public function destroy($id)
    {
        $agent = Agent::findOrFail($id);
        $extension = $agent->extension;

        // Hapus dari database lokal
        $agent->delete();

        // Lempar ekstensi string ke background queue
        ProvisionAsteriskAgent::dispatch($extension, 'delete');

        return response()->json([
            'status'  => 'success',
            'message' => 'Agen sedang dihapus dari sistem di latar belakang!'
        ]);
    }
}