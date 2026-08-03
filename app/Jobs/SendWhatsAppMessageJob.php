<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 10, 30];

    private string $phoneNumber;
    private string $message;
    private ?int $conversationId;
    private ?int $messageId;

    public function __construct(string $phoneNumber, string $message, ?int $conversationId = null, ?int $messageId = null)
    {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
    }

    public function handle(WhatsAppService $whatsappService): void
    {
        $result = $whatsappService->sendTextMessage($this->phoneNumber, $this->message, $this->conversationId);

        if ($result['success']) {
            if ($this->messageId) {
                WhatsappMessage::where('id', $this->messageId)->update([
                    'meta_message_id' => $result['message_id'] ?? null,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            Log::info('WhatsApp message sent successfully via job', [
                'phone' => $this->phoneNumber,
                'message_id' => $result['message_id'] ?? null,
            ]);
        } else {
            if ($this->messageId) {
                WhatsappMessage::where('id', $this->messageId)->update([
                    'status' => 'failed',
                ]);
            }

            Log::error('WhatsApp message failed via job', [
                'phone' => $this->phoneNumber,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            $this->release(30);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp message job failed', [
            'phone' => $this->phoneNumber,
            'error' => $exception->getMessage(),
        ]);

        if ($this->messageId) {
            WhatsappMessage::where('id', $this->messageId)->update([
                'status' => 'failed',
            ]);
        }
    }
}
