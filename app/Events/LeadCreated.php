<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    public function broadcastOn()
    {
        return [
            new Channel('leads'),
            new PrivateChannel('leads.' . $this->lead->id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'lead_id' => $this->lead->id,
            'name' => $this->lead->name,
            'email' => $this->lead->email,
            'phone' => $this->lead->phone,
            'whatsapp' => $this->lead->whatsapp,
            'status' => $this->lead->status,
            'source' => $this->lead->source,
            'created_at' => $this->lead->created_at->toIso8601String(),
        ];
    }
}
