<?php

namespace App\Events;

use App\Models\WhatsappCampaign;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignLaunched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public WhatsappCampaign $campaign;

    public function __construct(WhatsappCampaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function broadcastOn()
    {
        return [
            new Channel('campaigns'),
            new PrivateChannel('campaigns.' . $this->campaign->id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'campaign_id' => $this->campaign->id,
            'name' => $this->campaign->name,
            'type' => $this->campaign->type,
            'status' => $this->campaign->status,
            'total_recipients' => $this->campaign->total_recipients,
            'started_at' => $this->campaign->started_at?->toIso8601String(),
        ];
    }
}
