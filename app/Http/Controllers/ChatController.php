<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Message;

class ChatController extends Controller
{
    public function getContacts(Request $request)
    {
        try {
            // 1. Tentukan siapa "SAYA" yang sedang membuka chat
            $myId = $request->query('my_id');
            
            if (!$myId) {
                if (session()->has('agent_extension')) {
                    $agent = Agent::where('extension', session('agent_extension'))->first();
                    $myId = $agent ? $agent->id : null;
                } else {
                    $spv = Agent::where('role', 'supervisor')
                                ->orWhere('name', auth()->user()->name ?? '')
                                ->first();
                    $myId = $spv ? $spv->id : 2; 
                }
            }

            $myRole = Agent::where('id', $myId)->value('role');

            // 2. Filter Kontak Berdasarkan Role & Relasi, serta KECUALIKAN diri sendiri (!= $myId)
            if ($myRole === 'supervisor') {
                $contacts = Agent::where('id', '!=', $myId) // 🛡️ Mencegah diri sendiri masuk list
                                 ->whereHas('supervisors', function($query) use ($myId) {
                                     $query->where('supervisor_id', $myId);
                                 })->select('id', 'name', 'extension', 'status', 'role')->get();

            } else {
                $contacts = Agent::where('id', '!=', $myId) // 🛡️ Mencegah diri sendiri masuk list
                                 ->whereHas('agents', function($query) use ($myId) {
                                     $query->where('agent_id', $myId);
                                 })->select('id', 'name', 'extension', 'status', 'role')->get();
            }

            return response()->json([
                'status' => 'success', 
                'contacts' => $contacts
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function fetchMessages(Request $request, $partnerId)
    {
        try {
            $myId = $request->query('my_id'); 

            // Validasi tambahan: jangan ambil pesan jika partnerId sama dengan myId
            if ($myId == $partnerId) {
                return response()->json([]);
            }

            // 1. Ambil pesan antara kedua belah pihak
            $messages = Message::where(function($q) use ($myId, $partnerId) {
                    $q->where('sender_id', $myId)
                      ->where('receiver_id', $partnerId);
                })->orWhere(function($q) use ($myId, $partnerId) {
                    $q->where('sender_id', $partnerId)
                      ->where('receiver_id', $myId);
                })->orderBy('created_at', 'asc')->get();

            // 2. Tandai pesan dari partner sebagai "sudah dibaca" (is_read = 1)
            Message::where('sender_id', $partnerId)
                  ->where('receiver_id', $myId)
                  ->where('is_read', 0)
                  ->update(['is_read' => 1]);

            return response()->json($messages);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'receiver_id' => 'required',
                'message'     => 'required|string|max:1000',
                'sender_id'   => 'required'
            ]);

            // 🛡️ Pencegahan Mutlak: Tolak jika mencoba kirim pesan ke diri sendiri
            if ($request->sender_id == $request->receiver_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak dapat mengirim pesan ke diri sendiri.'
                ], 422);
            }

            $message = Message::create([
                'sender_id'   => $request->sender_id,
                'receiver_id' => $request->receiver_id,
                'message'     => $request->message,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}