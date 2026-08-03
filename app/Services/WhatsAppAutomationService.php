<?php

namespace App\Services;

use App\Models\WhatsappAutomationRule;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class WhatsAppAutomationService
{
    private WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function processIncomingMessage(array $webhookData): void
    {
        $entries = $webhookData['entry'] ?? [];

        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $this->handleMessage($change['value'] ?? []);
            }
        }
    }

    private function handleMessage(array $value): void
    {
        $messages = $value['messages'] ?? [];

        foreach ($messages as $message) {
            $this->processSingleMessage($message, $value);
        }
    }

    private function processSingleMessage(array $message, array $value): void
    {
        $phoneNumber = $message['from'] ?? null;
        $messageText = $message['text']['body'] ?? '';
        $messageType = $message['type'] ?? 'text';

        if (!$phoneNumber) {
            return;
        }

        $conversation = $this->getOrCreateConversation($phoneNumber, $value);
        $this->storeIncomingMessage($conversation, $message, $messageType);

        $lead = $this->getOrCreateLead($phoneNumber, $value);

        if ($conversation && !$conversation->assigned_user_id) {
            $this->autoAssignConversation($conversation);
        }

        if ($messageType === 'text' && $messageText) {
            $this->triggerAutomation($conversation, $messageText, $lead);
        }
    }

    private function getOrCreateConversation(string $phoneNumber, array $value): ?WhatsappConversation
    {
        $contact = $value['contacts'][0] ?? [];
        $contactName = $contact['profile']['name'] ?? null;
        $profilePicture = $contact['profile']['picture'] ?? null;

        $conversation = WhatsappConversation::where('phone_number', $phoneNumber)->first();

        if (!$conversation) {
            $lead = Lead::where('whatsapp', 'like', "%{$phoneNumber}%")->orWhere('phone', 'like', "%{$phoneNumber}%")->first();

            $conversation = WhatsappConversation::create([
                'phone_number' => $phoneNumber,
                'lead_id' => $lead?->id,
                'contact_name' => $contactName,
                'profile_picture' => $profilePicture,
                'status' => 'open',
                'unread_count' => 1,
                'last_message_at' => now(),
            ]);
        } else {
            $conversation->incrementUnread();
        }

        return $conversation;
    }

    private function storeIncomingMessage(WhatsappConversation $conversation, array $message, string $messageType): void
    {
        $messageData = [
            'conversation_id' => $conversation->id,
            'lead_id' => $conversation->lead_id,
            'direction' => 'incoming',
            'message_type' => $messageType,
            'meta_message_id' => $message['id'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
        ];

        if ($messageType === 'text') {
            $messageData['message'] = $message['text']['body'] ?? '';
        } elseif ($messageType === 'image') {
            $messageData['media_url'] = $message['image']['url'] ?? '';
            $messageData['media_type'] = 'image';
            $messageData['message'] = '[Image]';
        } elseif ($messageType === 'document') {
            $messageData['media_url'] = $message['document']['url'] ?? '';
            $messageData['media_type'] = 'document';
            $messageData['message'] = $message['document']['filename'] ?? '[Document]';
        } elseif ($messageType === 'video') {
            $messageData['media_url'] = $message['video']['url'] ?? '';
            $messageData['media_type'] = 'video';
            $messageData['message'] = '[Video]';
        }

        WhatsappMessage::create($messageData);
    }

    private function getOrCreateLead(string $phoneNumber, array $value): ?Lead
    {
        $contact = $value['contacts'][0] ?? [];
        $contactName = $contact['profile']['name'] ?? null;

        $lead = Lead::where('whatsapp', 'like', "%{$phoneNumber}%")
            ->orWhere('phone', 'like', "%{$phoneNumber}%")
            ->first();

        if (!$lead && $contactName) {
            $lead = Lead::create([
                'name' => $contactName,
                'phone' => $phoneNumber,
                'whatsapp' => $phoneNumber,
                'source' => 'WhatsApp',
                'status' => 'open',
                'created_by' => auth()->id() ?? 1,
            ]);
        }

        return $lead;
    }

    private function autoAssignConversation(WhatsappConversation $conversation): void
    {
        $availableUser = \App\Models\User::where('is_active', true)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'sales');
            })
            ->orderBy('id')
            ->first();

        if ($availableUser) {
            $conversation->update(['assigned_user_id' => $availableUser->id]);
        }
    }

    private function triggerAutomation(WhatsappConversation $conversation, string $messageText, ?Lead $lead): void
    {
        if (! Setting::isEnabled('whatsapp_auto_ai_enabled', true)) {
            $welcomeRule = WhatsappAutomationRule::active()
                ->byTriggerType('welcome')
                ->first();

            if ($welcomeRule && $conversation->messages()->count() === 1) {
                $this->executeAutomation($welcomeRule, $conversation, $lead, $messageText, true);
                $welcomeRule->incrementExecution();
            }

            return;
        }

        $rules = WhatsappAutomationRule::active()
            ->byTriggerType('keyword')
            ->byPriority()
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matchesKeyword($messageText)) {
                $this->executeAutomation($rule, $conversation, $lead, $messageText);
                $rule->incrementExecution();
                break;
            }
        }

        $welcomeRule = WhatsappAutomationRule::active()
            ->byTriggerType('welcome')
            ->first();

        if ($welcomeRule && $conversation->messages()->count() === 1) {
            $this->executeAutomation($welcomeRule, $conversation, $lead, $messageText);
            $welcomeRule->incrementExecution();
        }
    }

    private function executeAutomation(
        WhatsappAutomationRule $rule,
        WhatsappConversation $conversation,
        ?Lead $lead,
        string $originalMessage,
        bool $allowWhenAutoAiDisabled = false
    ): void {
        if (! $allowWhenAutoAiDisabled && ! Setting::isEnabled('whatsapp_auto_ai_enabled', true)) {
            return;
        }

        $responseMessage = $rule->response_message;

        if ($rule->template_id) {
            $this->whatsappService->sendTemplate(
                $conversation->phone_number,
                $rule->template->name,
                [],
                'automation',
                $rule->id
            );
        } elseif ($responseMessage) {
            $result = $this->whatsappService->sendTextMessage(
                $conversation->phone_number,
                $responseMessage,
                $conversation->id,
                'automation',
                $rule->id
            );

            if ($result['success']) {
                WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => $responseMessage,
                    'message_type' => 'text',
                    'meta_message_id' => $result['message_id'] ?? null,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        }

        Log::info('WhatsApp automation executed', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'conversation_id' => $conversation->id,
            'phone' => $conversation->phone_number,
        ]);
    }

    public function createAutomationRule(array $data): WhatsappAutomationRule
    {
        return WhatsappAutomationRule::create($data);
    }

    public function updateAutomationRule(WhatsappAutomationRule $rule, array $data): WhatsappAutomationRule
    {
        $rule->update($data);
        return $rule;
    }

    public function deleteAutomationRule(WhatsappAutomationRule $rule): bool
    {
        return $rule->delete();
    }

    public function getActiveRules(): \Illuminate\Database\Eloquent\Collection
    {
        return WhatsappAutomationRule::active()->orderBy('priority', 'desc')->get();
    }
}
