<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\Asterisk\OriginateService;
use Illuminate\Http\Request;
use Illuminate\Broadcasting\Channel;

class AgentWorkspaceController extends Controller
{
    protected $originateService;

    public function __construct(OriginateService $originateService)
    {
        $this->originateService = $originateService;
    }

    /**
     * Ambil informasi status agen yang sedang login
     */
    public function profile($extension)
    {
        $agent = Agent::where('extension', $extension)->firstOrFail();
        return response()->json(['status' => 'success', 'data' => $agent]);
    }

    /**
     * Update status agen (Online, Break, Offline)
     */
    public function updateStatus(Request $request, $extension)
{
    // Pastikan 'prayer' dan 'lunch' masuk ke dalam daftar validasi 'in:...'
    $request->validate([
        'status' => 'required|in:online,prayer,break,lunch,offline'
    ]);

    $agent = Agent::where('extension', $extension)->firstOrFail();
    $agent->status = $request->status;
    $agent->save();

    // Broadcast status terbaru ke Dashboard Supervisor
    broadcast(new \App\Events\AgentStatusUpdated($agent));

    return response()->json([
        'status' => 'success',
        'message' => "Status berhasil diubah menjadi {$agent->status}"
    ]);
}

    /**
     * Tombol Click-to-Dial dari Workspace Agent
     */
   public function call(Request $request, $extension)
    {
        $request->validate(['destination' => 'required|string']);

        try {
            $agent = Agent::where('extension', $extension)->firstOrFail();

            // Panggil service originate
            $response = $this->originateService->clickToDial($extension, $request->destination);

            // 🚀 TAMBAHKAN BROADCAST INI AGAR SUPERVISOR TAHU AGEN SEDANG CALLING
            broadcast(new \App\Events\AgentCallActivity($agent, $request->destination, 'calling'));

            return response()->json([
                'status' => 'success',
                'message' => "Panggilan ke {$request->destination} sedang diinisiasi...",
                'asterisk_response' => trim($response)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}