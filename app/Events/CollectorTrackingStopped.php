<?php

namespace App\Events;

use App\Models\DebtCollector;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Collector menekan "hentikan tracking" — peta langsung abu tanpa tunggu timeout. */
class CollectorTrackingStopped implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public DebtCollector $collector) {}

    public function broadcastOn(): array
    {
        return [new Channel('tracking.live')];
    }

    public function broadcastAs(): string
    {
        return 'collector.tracking.stopped';
    }

    public function broadcastWith(): array
    {
        return ['collector_id' => $this->collector->id];
    }
}
