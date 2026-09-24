<?php

namespace App\Events;

use App\Models\CollectorPosition;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Posisi baru collector lapangan — didengar peta live supervisor. */
class CollectorPositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public CollectorPosition $position) {}

    public function broadcastOn(): array
    {
        return [new Channel('tracking.live')];
    }

    public function broadcastAs(): string
    {
        return 'collector.position.updated';
    }

    public function broadcastWith(): array
    {
        $c = $this->position->collector;

        return [
            'collector_id' => $this->position->collector_id,
            'name' => $c?->name,
            'phone' => $c?->phone,
            'area' => $c?->area,
            'latitude' => (float) $this->position->latitude,
            'longitude' => (float) $this->position->longitude,
            'accuracy' => $this->position->accuracy,
            'at' => $this->position->recorded_at?->toDateTimeString(),
        ];
    }
}
