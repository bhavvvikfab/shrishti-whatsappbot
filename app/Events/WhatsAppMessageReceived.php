<?php

namespace App\Events;

use App\Models\WhatsappMessage;
use App\Models\WhatsappConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public WhatsappMessage $message;
    public WhatsappConversation $conversation;

    public function __construct(WhatsappMessage $message, WhatsappConversation $conversation)
    {
        $this->message = $message;
        $this->conversation = $conversation;
    }

    public function broadcastOn()
    {
        return [
            new Channel('whatsapp.conversations'),
            new PrivateChannel('whatsapp.conversation.' . $this->conversation->id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'direction' => $this->message->direction,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'media_url' => $this->message->media_url,
            'created_at' => $this->message->created_at->toIso8601String(),
            'phone_number' => $this->conversation->phone_number,
            'contact_name' => $this->conversation->contact_name,
        ];
    }
}
