<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $callData;

    public function __construct(array $callData)
    {
        $this->callData = $callData;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('supervisor.dashboard')
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.status.updated';
    }
}