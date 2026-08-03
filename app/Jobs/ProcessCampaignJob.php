<?php

namespace App\Jobs;

use App\Models\WhatsappCampaign;
use App\Models\Lead;
use App\Models\CampaignLog;
use App\Services\WhatsAppService;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 10, 30];

    private WhatsappCampaign $campaign;

    public function __construct(WhatsappCampaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function handle(WhatsAppService $whatsappService): void
    {
        $this->campaign->markAsSending();

        $recipients = $this->getRecipients();

        foreach ($recipients as $recipient) {
            $this->sendCampaignMessage($whatsappService, $recipient);
        }

        $this->campaign->markAsCompleted();

        Log::info('Campaign processed successfully', [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name,
            'total_recipients' => $recipients->count(),
        ]);
    }

    private function getRecipients()
    {
        $query = Lead::whereNotNull('whatsapp');

        $filters = $this->campaign->filters ?? [];

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['source_id'])) {
            $query->where('lead_source_id', $filters['source_id']);
        }

        if (!empty($filters['stage_id'])) {
            $query->where('lead_stage_id', $filters['stage_id']);
        }

        if (!empty($filters['tag_ids'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('lead_tags.id', $filters['tag_ids']);
            });
        }

        if (!empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }

        return $query->get();
    }

    private function sendCampaignMessage(WhatsAppService $whatsappService, Lead $recipient): void
    {
        $phoneNumber = $recipient->whatsapp ?? $recipient->phone;

        if (!$phoneNumber) {
            $this->campaign->incrementFailed();
            return;
        }

        $result = null;

        if ($this->campaign->template_id) {
            $result = $whatsappService->sendTemplate(
                $phoneNumber,
                $this->campaign->template->name,
                [],
                'campaign',
                $this->campaign->id
            );
        } elseif ($this->campaign->message) {
            $result = $whatsappService->sendTextMessage(
                $phoneNumber,
                $this->campaign->message,
                null,
                'campaign',
                $this->campaign->id
            );
        }

        CampaignLog::create([
            'campaign_id' => $this->campaign->id,
            'lead_id' => $recipient->id,
            'phone_number' => $phoneNumber,
            'status' => $result['success'] ?? false ? 'sent' : 'failed',
            'error_message' => $result['error'] ?? null,
        ]);

        if ($result['success'] ?? false) {
            $this->campaign->incrementSent();

            $conversation = WhatsappConversation::firstOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'lead_id' => $recipient->id,
                    'status' => 'open',
                ]
            );

            if ($this->campaign->message) {
                WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $recipient->id,
                    'direction' => 'outgoing',
                    'message' => $this->campaign->message,
                    'message_type' => 'text',
                    'meta_message_id' => $result['message_id'] ?? null,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } else {
            $this->campaign->incrementFailed();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign job failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);

        $this->campaign->update(['status' => 'failed']);
    }
}
