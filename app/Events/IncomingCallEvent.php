<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingCallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $callData;
    public $targetExtension;

    // Terima data panggilan dan ekstensi agen tujuan
    public function __construct($callData, $targetExtension)
    {
        $this->callData = $callData;
        $this->targetExtension = $targetExtension;
    }

    // Siarkan ke channel privat agen tertentu
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.' . $this->targetExtension),
        ];
    }

    public function broadcastAs(): array
    {
        return 'incoming.call';
    }
}