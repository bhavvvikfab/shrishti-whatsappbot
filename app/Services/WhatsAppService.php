<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsappConfig;
use App\Models\WhatsappLog;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private ?WhatsappConfig $config = null;

    private function httpClient(bool $verifySsl = true)
    {
        $client = Http::timeout(15)->withOptions([
            'proxy' => '',
            'curl' => [
                CURLOPT_PROXY => '',
            ],
        ]);

        if (!$verifySsl || app()->environment(['local', 'development'])) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function normalizePhoneNumber(string $toNumber): string
    {
        $toNumber = preg_replace('/[^0-9]/', '', $toNumber);

        if (strlen($toNumber) === 10 && preg_match('/^[6-9]/', $toNumber)) {
            return '91' . $toNumber;
        }

        return $toNumber;
    }

    private function config(): ?WhatsappConfig
    {
        if ($this->config === null) {
            $this->config = WhatsappConfig::current();
        }

        return $this->config;
    }

    public function isModuleEnabled(): bool
    {
        return Setting::isEnabled('whatsapp_module_enabled', true);
    }

    private function logModuleDisabled(string $action, array $context = []): void
    {
        Log::info('WhatsApp module disabled, skipping action.', array_merge([
            'action' => $action,
        ], $context));
    }

    private function inferRecipientGroup(?string $module): ?string
    {
        $normalized = strtolower(trim((string) $module));

        if ($normalized === '') {
            return null;
        }

        $customerModules = [
            'meeting_scheduled_customer',
            'meeting_updated',
            'task_created_customer',
            'task_updated_customer',
            'customer_welcome_message',
            'customer_profile_updated',
            'customer_created',
            'followup',
            'campaign',
            'automation',
        ];

        $staffModules = [
            'meeting_scheduled_staff',
            'task_assigned_staff',
            'task_updated_staff',
            'staff_account_created',
            'staff_account_updated',
        ];

        $adminModules = [
            'admin',
        ];

        if (in_array($normalized, $customerModules, true)) {
            return 'customer';
        }

        if (in_array($normalized, $staffModules, true)) {
            return 'staff';
        }

        if (in_array($normalized, $adminModules, true) || str_contains($normalized, 'admin')) {
            return 'admin';
        }

        if (str_contains($normalized, 'customer') || str_contains($normalized, 'lead') || str_contains($normalized, 'followup') || str_contains($normalized, 'campaign')) {
            return 'customer';
        }

        if (str_contains($normalized, 'staff')) {
            return 'staff';
        }

        return null;
    }

    private function isRecipientGroupEnabled(?string $group): bool
    {
        if ($group === null) {
            return true;
        }

        $settingKey = match ($group) {
            'admin' => 'whatsapp_notifications_admin',
            'staff' => 'whatsapp_notifications_staff',
            'customer' => 'whatsapp_notifications_customer',
            default => null,
        };

        if ($settingKey === null) {
            return true;
        }

        return Setting::isEnabled($settingKey, true);
    }

    private function shouldSendForModule(?string $module, string $toNumber, ?int $moduleId = null): bool
    {
        $group = $this->inferRecipientGroup($module);

        if ($this->isRecipientGroupEnabled($group)) {
            return true;
        }

        Log::info('WhatsApp recipient group disabled, skipping action.', [
            'module' => $module,
            'module_id' => $moduleId,
            'to' => $toNumber,
            'recipient_group' => $group,
        ]);

        return false;
    }

    public function isConfigured(): bool
    {
        if (!$this->isModuleEnabled()) {
            return false;
        }

        $cfg = $this->config();

        return $cfg
            && filled($cfg->access_token)
            && filled($cfg->phone_number_id);
    }

    public function sendTemplate(
        string $toNumber,
        string $templateName,
        array $variables = [],
        ?string $module = null,
        ?int $moduleId = null
    ): bool {
        if (!$this->isModuleEnabled()) {
            $this->logModuleDisabled('send_template', [
                'template' => $templateName,
                'module' => $module,
                'module_id' => $moduleId,
            ]);

            return false;
        }

        if (!$this->shouldSendForModule($module, $toNumber, $moduleId)) {
            return false;
        }

        if (!$this->isConfigured()) {
            Log::warning('WhatsApp: not configured, skipping send.', [
                'template' => $templateName,
                'module' => $module,
                'module_id' => $moduleId,
            ]);

            return false;
        }

        $cfg = $this->config();
        $toNumber = $this->normalizePhoneNumber($toNumber);

        if (empty($toNumber)) {
            Log::warning('WhatsApp: empty phone number, skipping.', [
                'template' => $templateName,
                'module' => $module,
                'module_id' => $moduleId,
            ]);

            return false;
        }

        $components = [];
        if (!empty($variables)) {
            $params = array_map(fn($value) => ['type' => 'text', 'text' => (string) $value], $variables);
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        $template = WhatsappMessageTemplate::where('name', $templateName)->first();
        $language = $template?->language ?: 'en';

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $log = WhatsappLog::create([
            'to_number' => $toNumber,
            'template_name' => $templateName,
            'module' => $module,
            'module_id' => $moduleId,
            'variables' => $variables,
            'status' => 'pending',
            'sent_by' => auth()->id(),
        ]);

        Log::info('WhatsApp dispatch started', [
            'template' => $templateName,
            'module' => $module,
            'module_id' => $moduleId,
            'to' => $toNumber,
            'variables_count' => count($variables),
            'variables' => $variables,
            'language' => $language,
            'log_id' => $log->id,
        ]);

        try {
            $response = $this->httpClient()
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                $log->update([
                    'status' => 'sent',
                    'meta_message_id' => $body['messages'][0]['id'],
                ]);

                Log::info('WhatsApp dispatch succeeded', [
                    'template' => $templateName,
                    'module' => $module,
                    'module_id' => $moduleId,
                    'to' => $toNumber,
                    'meta_message_id' => $body['messages'][0]['id'],
                    'log_id' => $log->id,
                ]);

                return true;
            }

            $log->update([
                'status' => 'failed',
                'error_message' => json_encode($body),
            ]);

            Log::error('WhatsApp send failed', [
                'response' => $body,
                'template' => $templateName,
                'module' => $module,
                'module_id' => $moduleId,
                'to' => $toNumber,
                'log_id' => $log->id,
            ]);

            return false;
        } catch (\Throwable $e) {
            $isSslError = str_contains(strtolower($e->getMessage()), 'ssl certificate');

            if ($isSslError) {
                try {
                    $response = $this->httpClient(false)
                        ->withToken($cfg->access_token)
                        ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

                    $body = $response->json();

                    if ($response->successful() && isset($body['messages'][0]['id'])) {
                        $log->update([
                            'status' => 'sent',
                            'meta_message_id' => $body['messages'][0]['id'],
                            'error_message' => null,
                        ]);

                        Log::info('WhatsApp dispatch succeeded after SSL retry', [
                            'template' => $templateName,
                            'module' => $module,
                            'module_id' => $moduleId,
                            'to' => $toNumber,
                            'meta_message_id' => $body['messages'][0]['id'],
                            'log_id' => $log->id,
                        ]);

                        return true;
                    }

                    $log->update([
                        'status' => 'failed',
                        'error_message' => json_encode($body),
                    ]);

                    Log::error('WhatsApp send failed after SSL retry', [
                        'response' => $body,
                        'template' => $templateName,
                        'module' => $module,
                        'module_id' => $moduleId,
                        'to' => $toNumber,
                        'log_id' => $log->id,
                    ]);

                    return false;
                } catch (\Throwable $retryException) {
                    $e = $retryException;
                }
            }

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('WhatsApp exception', [
                'error' => $e->getMessage(),
                'template' => $templateName,
                'module' => $module,
                'module_id' => $moduleId,
                'to' => $toNumber,
                'log_id' => $log->id,
            ]);

            return false;
        }
    }

    public function sendForModule(
        string $module,
        string $toNumber,
        array $variables = [],
        ?int $moduleId = null
    ): bool {
        if (!$this->isModuleEnabled()) {
            $this->logModuleDisabled('send_for_module', [
                'module' => $module,
                'module_id' => $moduleId,
                'to' => $toNumber,
            ]);

            return false;
        }

        if (!$this->shouldSendForModule($module, $toNumber, $moduleId)) {
            return false;
        }

        $module = trim($module);

        Log::info('WhatsApp module send requested', [
            'module' => $module,
            'module_id' => $moduleId,
            'to' => $toNumber,
            'variables_count' => count($variables),
        ]);

        $template = WhatsappMessageTemplate::query()
            ->whereRaw('LOWER(TRIM(use_for_module)) = ?', [strtolower($module)])
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(status)) = ?', ['APPROVED'])
            ->first();

        if (!$template) {
            Log::warning('WhatsApp: no active approved template mapped for module.', [
                'module' => $module,
                'module_id' => $moduleId,
                'to' => $toNumber,
            ]);

            return false;
        }

        Log::info('WhatsApp module mapped to template', [
            'module' => $module,
            'module_id' => $moduleId,
            'template' => $template->name,
            'template_status' => $template->status,
            'template_active' => $template->is_active,
            'to' => $toNumber,
        ]);

        return $this->sendTemplate($toNumber, $template->name, $variables, $module, $moduleId);
    }

    public function sendTextMessage(
        string $toNumber,
        string $message,
        ?int $conversationId = null,
        ?string $module = null,
        ?int $moduleId = null
    ): array
    {
        if (!$this->isModuleEnabled()) {
            $this->logModuleDisabled('send_text_message', [
                'conversation_id' => $conversationId,
                'to' => $toNumber,
            ]);

            return ['success' => false, 'error' => 'WhatsApp module is disabled'];
        }

        if (!$this->shouldSendForModule($module, $toNumber, $moduleId)) {
            return ['success' => false, 'error' => 'WhatsApp recipient group is disabled'];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp not configured'];
        }

        $cfg = $this->config();
        $toNumber = $this->normalizePhoneNumber($toNumber);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toNumber,
            'type' => 'text',
            'text' => ['body' => $message],
        ];

        try {
            $response = $this->httpClient()
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $body['messages'][0]['id'],
                    'to' => $toNumber,
                ];
            }

            return ['success' => false, 'error' => json_encode($body)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendMediaMessage(string $toNumber, string $mediaUrl, string $mediaType, ?string $caption = null): array
    {
        if (!$this->isModuleEnabled()) {
            $this->logModuleDisabled('send_media_message', [
                'to' => $toNumber,
                'media_type' => $mediaType,
            ]);

            return ['success' => false, 'error' => 'WhatsApp module is disabled'];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp not configured'];
        }

        $cfg = $this->config();
        $toNumber = $this->normalizePhoneNumber($toNumber);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toNumber,
            'type' => $mediaType,
            $mediaType => [
                'link' => $mediaUrl,
            ],
        ];

        if ($caption) {
            $payload[$mediaType]['caption'] = $caption;
        }

        try {
            $response = $this->httpClient()
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $body['messages'][0]['id'],
                    'to' => $toNumber,
                ];
            }

            return ['success' => false, 'error' => json_encode($body)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function markMessageAsRead(string $messageId): bool
    {
        if (!$this->isModuleEnabled()) {
            $this->logModuleDisabled('mark_message_as_read', [
                'message_id' => $messageId,
            ]);

            return false;
        }

        if (!$this->isConfigured()) {
            return false;
        }

        $cfg = $this->config();

        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];

        try {
            $response = $this->httpClient()
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('WhatsApp mark as read failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
