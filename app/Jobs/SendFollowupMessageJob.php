<?php

namespace App\Jobs;

use App\Models\WhatsappFollowup;
use App\Services\WhatsAppService;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFollowupMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 10, 30];

    private WhatsappFollowup $followup;

    public function __construct(WhatsappFollowup $followup)
    {
        $this->followup = $followup;
    }

    public function handle(WhatsAppService $whatsappService): void
    {
        if ($this->followup->is_sent) {
            return;
        }

        $phoneNumber = $this->followup->lead->whatsapp ?? $this->followup->lead->phone;

        if (!$phoneNumber) {
            Log::warning('Followup skipped - no phone number', [
                'followup_id' => $this->followup->id,
                'lead_id' => $this->followup->lead_id,
            ]);
            return;
        }

        $result = null;

        if ($this->followup->template_id) {
            $result = $whatsappService->sendTemplate(
                $phoneNumber,
                $this->followup->template->name,
                [],
                'followup',
                $this->followup->id
            );
        } elseif ($this->followup->message) {
            $result = $whatsappService->sendTextMessage(
                $phoneNumber,
                $this->followup->message,
                null,
                'followup',
                $this->followup->id
            );
        }

        if ($result['success'] ?? false) {
            $this->followup->markAsSent();

            $conversation = WhatsappConversation::firstOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'lead_id' => $this->followup->lead_id,
                    'status' => 'open',
                ]
            );

            if ($this->followup->message) {
                WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $this->followup->lead_id,
                    'direction' => 'outgoing',
                    'message' => $this->followup->message,
                    'message_type' => 'text',
                    'meta_message_id' => $result['message_id'] ?? null,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            Log::info('Followup message sent successfully', [
                'followup_id' => $this->followup->id,
                'lead_id' => $this->followup->lead_id,
            ]);
        } else {
            Log::error('Followup message failed', [
                'followup_id' => $this->followup->id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            $this->release(60);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Followup job failed', [
            'followup_id' => $this->followup->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
