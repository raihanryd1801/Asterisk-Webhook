<?php

namespace App\Events;

use App\Models\Agent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // 🚀 WAJIB PAKAI INI
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentCallActivity implements ShouldBroadcastNow // 🚀 IMPLEMENTASIKAN DI SINI
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agent;
    public $destination;
    public $status;

    public function __construct(Agent $agent, $destination, $status)
    {
        $this->agent = $agent;
        $this->destination = $destination;
        $this->status = $status;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('supervisor.dashboard'),
        ];
    }

    // 🚀 PAKSA NAMA EVENT AGAR SINKRON DENGAN JAVASCRIPT
    public function broadcastAs(): string
    {
        return 'agent.call.activity';
    }
}