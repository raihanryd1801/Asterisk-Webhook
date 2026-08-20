<?php

namespace App\Events;

use App\Models\Agent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // 🚀 1. Ganti jadi ShouldBroadcastNow
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStatusUpdated implements ShouldBroadcastNow // 🚀 2. Implementasikan di sini
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agent;

    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * Tentukan channel tempat event ini disebar
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('supervisor.dashboard'), // 🚀 3. Satukan channelnya dengan event telepon
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.status.updated';
    }
}