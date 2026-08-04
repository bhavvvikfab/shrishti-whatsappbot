<?php

namespace App\Http\Controllers;

use App\Models\WhatsappConfig;
use App\Models\WhatsappLog;
use App\Models\WhatsappMessage;
use App\Services\WhatsAppAutomationService;
use App\Services\WhatsAppInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    private WhatsAppAutomationService $automationService;
    private WhatsAppInboxService $inboxService;

    public function __construct(WhatsAppAutomationService $automationService, WhatsAppInboxService $inboxService)
    {
        $this->automationService = $automationService;
        $this->inboxService = $inboxService;
    }

    public function verify(Request $request)
    {
        Log::info('WhatsApp webhook GET (verify)', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        Log::channel('whatsapp')->info('webhook_verify_hit', [
            'ip' => $request->ip(),
        ]);

        $mode = $request->query('hub.mode') ?? $request->query('hub_mode');
        $token = $request->query('hub.verify_token') ?? $request->query('hub_verify_token');
        $challenge = $request->query('hub.challenge') ?? $request->query('hub_challenge');

        $expectedToken = WhatsappConfig::resolveWebhookVerifyToken();

        if ($mode === 'subscribe' && hash_equals($expectedToken, (string) $token)) {
            Log::info('WhatsApp webhook verify succeeded', ['mode' => $mode]);
            Log::channel('whatsapp')->info('webhook_verify_ok', ['mode' => $mode]);

            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verify failed', [
            'mode' => $mode,
            'token_match' => $token !== null && hash_equals($expectedToken, (string) $token),
        ]);
        Log::channel('whatsapp')->warning('webhook_verify_failed', ['mode' => $mode]);

        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        $waLog = Log::channel('whatsapp');

        Log::info('WhatsApp webhook POST hit', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        $waLog->info('webhook_post_hit', [
            'ip' => $request->ip(),
        ]);

        $raw = $request->getContent();
        $payload = $request->all();
        if ($payload === [] && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            } else {
                Log::warning('WhatsApp webhook: could not decode JSON body', [
                    'prefix' => substr($raw, 0, 240),
                ]);
                $waLog->warning('json_decode_failed', ['prefix' => substr($raw, 0, 240)]);
            }
        }

        $entryCount = isset($payload['entry']) && is_array($payload['entry']) ? count($payload['entry']) : 0;
        Log::info('WhatsApp webhook POST', [
            'object' => $payload['object'] ?? null,
            'entry_count' => $entryCount,
        ]);
        $waLog->info('webhook_received', [
            'object' => $payload['object'] ?? null,
            'entry_count' => $entryCount,
            'body_bytes' => strlen($raw),
        ]);
        if (config('app.debug')) {
            Log::debug('WhatsApp webhook payload', $payload);
        }

        $entries = $payload['entry'] ?? [];
        $messagesProcessed = 0;
        $statusRows = 0;
        $changeFields = [];

        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];
                $changeFields[] = $field;

                $webhookPhoneId = $value['metadata']['phone_number_id'] ?? null;
                $config = WhatsappConfig::byPhoneNumberId($webhookPhoneId) ?? WhatsappConfig::adminConfig();

                if ($config) {
                    $this->inboxService->useConfig($config);
                } else {
                    Log::warning('WhatsApp webhook: no CRM config for phone_number_id', [
                        'webhook_phone_number_id' => $webhookPhoneId,
                        'change_field' => $field,
                    ]);
                    $waLog->warning('config_not_found', [
                        'webhook_phone_number_id' => $webhookPhoneId,
                        'change_field' => $field,
                    ]);
                }

                if ($webhookPhoneId && $config && (string) $webhookPhoneId !== (string) $config->phone_number_id) {
                    Log::warning('WhatsApp webhook phone_number_id differs from matched CRM config', [
                        'webhook_phone_number_id' => $webhookPhoneId,
                        'crm_phone_number_id' => $config->phone_number_id,
                        'change_field' => $field,
                    ]);
                    $waLog->warning('phone_number_id_mismatch', [
                        'webhook_phone_number_id' => $webhookPhoneId,
                        'crm_phone_number_id' => $config->phone_number_id,
                        'change_field' => $field,
                    ]);
                }

                if ($field === 'smb_message_echoes') {
                    foreach ($value['message_echoes'] ?? [] as $echo) {
                        try {
                            $stored = $this->inboxService->processOutgoingEcho($echo, $value);
                            if ($stored) {
                                $messagesProcessed++;
                            }
                        } catch (\Throwable $e) {
                            Log::error('WhatsApp echo processing error', [
                                'error' => $e->getMessage(),
                                'echo_id' => $echo['id'] ?? null,
                            ]);
                        }
                    }
                    continue;
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $statusRows++;
                    $wamid = $status['id'] ?? null;
                    $newStatus = strtolower($status['status'] ?? '');

                    if ($wamid && in_array($newStatus, ['delivered', 'read', 'failed'], true)) {
                        WhatsappLog::where('meta_message_id', $wamid)
                            ->update(['status' => $newStatus]);

                        WhatsappMessage::where('meta_message_id', $wamid)
                            ->update(['status' => $newStatus]);

                        if ($newStatus === 'delivered') {
                            WhatsappMessage::where('meta_message_id', $wamid)
                                ->update(['delivered_at' => now()]);
                        } elseif ($newStatus === 'read') {
                            WhatsappMessage::where('meta_message_id', $wamid)
                                ->update(['read_at' => now()]);
                        } elseif ($newStatus === 'failed') {
                            Log::warning('WhatsApp message status failed', [
                                'meta_message_id' => $wamid,
                                'errors' => $status['errors'] ?? null,
                                'recipient_id' => $status['recipient_id'] ?? null,
                            ]);
                        }
                    }
                }

                $incomingMessages = $value['messages'] ?? [];
                if ($field === 'messages' && $incomingMessages === []) {
                    $waLog->info('messages_field_empty', [
                        'phone_number_id' => $webhookPhoneId,
                    ]);
                }

                foreach ($incomingMessages as $message) {
                    Log::info('WhatsApp incoming message', [
                        'from' => $message['from'] ?? null,
                        'type' => $message['type'] ?? null,
                        'id' => $message['id'] ?? null,
                        'field' => $field,
                        'phone_number_id' => $webhookPhoneId,
                    ]);
                    $waLog->info('incoming_message_row', [
                        'from' => $message['from'] ?? null,
                        'type' => $message['type'] ?? null,
                        'id' => $message['id'] ?? null,
                        'field' => $field,
                        'phone_number_id' => $webhookPhoneId,
                    ]);

                    $contacts = $value['contacts'] ?? [];
                    $contactMap = [];
                    foreach ($contacts as $contact) {
                        $waId = $contact['wa_id'] ?? '';
                        $contactMap[$waId] = $contact;
                    }

                    $contactData = $contactMap[$message['from'] ?? ''] ?? [];

                    try {
                        $stored = $this->inboxService->processIncomingMessage($message, $contactData, $webhookPhoneId);
                        if ($stored) {
                            $messagesProcessed++;
                        } else {
                            $waLog->warning('incoming_message_not_stored', [
                                'message_id' => $message['id'] ?? null,
                                'from' => $message['from'] ?? null,
                                'type' => $message['type'] ?? null,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('WhatsApp message processing error', [
                            'error' => $e->getMessage(),
                            'message_id' => $message['id'] ?? null,
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $waLog->error('processIncomingMessage_failed', [
                            'error' => $e->getMessage(),
                            'message_id' => $message['id'] ?? null,
                        ]);
                    }
                }
            }
        }

        $waLog->info('webhook_complete', [
            'messages_saved' => $messagesProcessed,
            'status_rows' => $statusRows,
            'change_fields' => array_values(array_unique($changeFields)),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
