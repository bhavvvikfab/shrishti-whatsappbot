<?php

namespace App\Services;

use App\Models\WhatsappCampaign;
use App\Models\CampaignLog;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Models\WhatsappConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    private WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function createCampaign(array $data): WhatsappCampaign
    {
        return DB::transaction(function () use ($data) {
            $campaign = WhatsappCampaign::create($data);

            if ($campaign->type === 'broadcast' && $campaign->status === 'scheduled') {
                $this->scheduleCampaign($campaign);
            }

            return $campaign;
        });
    }

    public function updateCampaign(WhatsappCampaign $campaign, array $data): WhatsappCampaign
    {
        $campaign->update($data);
        return $campaign;
    }

    public function deleteCampaign(WhatsappCampaign $campaign): bool
    {
        return $campaign->delete();
    }

    public function launchCampaign(WhatsappCampaign $campaign): array
    {
        if ($campaign->status !== 'draft' && $campaign->status !== 'scheduled') {
            return ['success' => false, 'message' => 'Campaign cannot be launched'];
        }

        $recipients = $this->getRecipients($campaign);

        if ($recipients->isEmpty()) {
            return ['success' => false, 'message' => 'No recipients found'];
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
            'total_recipients' => $recipients->count(),
        ]);

        foreach ($recipients as $recipient) {
            $this->sendCampaignMessage($campaign, $recipient);
        }

        $campaign->markAsCompleted();

        return [
            'success' => true,
            'message' => 'Campaign completed',
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
        ];
    }

    private function getRecipients(WhatsappCampaign $campaign): \Illuminate\Database\Eloquent\Collection
    {
        $query = Lead::whereNotNull('whatsapp');

        $filters = $campaign->filters ?? [];

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

    private function sendCampaignMessage(WhatsappCampaign $campaign, Lead $recipient): void
    {
        $phoneNumber = $recipient->whatsapp ?? $recipient->phone;

        if (!$phoneNumber) {
            $campaign->incrementFailed();
            return;
        }

        $result = null;

        if ($campaign->template_id) {
            $result = $this->whatsappService->sendTemplate(
                $phoneNumber,
                $campaign->template->name,
                [],
                'campaign',
                $campaign->id
            );
        } elseif ($campaign->message) {
            $result = $this->whatsappService->sendTextMessage(
                $phoneNumber,
                $campaign->message,
                null,
                'campaign',
                $campaign->id
            );
        }

        CampaignLog::create([
            'campaign_id' => $campaign->id,
            'lead_id' => $recipient->id,
            'phone_number' => $phoneNumber,
            'status' => $result['success'] ?? false ? 'sent' : 'failed',
            'error_message' => $result['error'] ?? null,
        ]);

        if ($result['success'] ?? false) {
            $campaign->incrementSent();

            $conversation = WhatsappConversation::firstOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'lead_id' => $recipient->id,
                    'status' => 'open',
                ]
            );

            if ($campaign->message) {
                WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $recipient->id,
                    'direction' => 'outgoing',
                    'message' => $campaign->message,
                    'message_type' => 'text',
                    'meta_message_id' => $result['message_id'] ?? null,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } else {
            $campaign->incrementFailed();
        }
    }

    private function scheduleCampaign(WhatsappCampaign $campaign): void
    {
        if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
            // In production, use Laravel's scheduler or queue jobs
            Log::info('Campaign scheduled', [
                'campaign_id' => $campaign->id,
                'scheduled_at' => $campaign->scheduled_at,
            ]);
        }
    }

    public function getCampaignStats(WhatsappCampaign $campaign): array
    {
        return [
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'read_count' => $campaign->read_count,
            'failed_count' => $campaign->failed_count,
            'progress_percentage' => $campaign->progress_percentage,
            'success_rate' => $campaign->success_rate,
        ];
    }

    public function getAllCampaigns(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = WhatsappCampaign::with('template');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->latest()->get();
    }
}
