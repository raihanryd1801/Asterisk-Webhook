<?php

namespace App\Events;

use App\Models\Agent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentCallActivity implements ShouldBroadcastNow // <--- Implementasikan di sini

{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agent;
    public $destination;
    public $status; // 'calling', 'ringing', 'connected', 'ended'

    public function __construct(Agent $agent, $destination, $status = 'calling')
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

    public function broadcastAs(): string
    {
        return 'agent.call.activity';
    }
}