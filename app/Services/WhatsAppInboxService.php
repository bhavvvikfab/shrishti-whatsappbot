<?php

namespace App\Services;

use App\Jobs\SendDeferredWhatsAppAiReply;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappAutomationRule;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappConfig;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageTemplate;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WhatsAppInboxService
{
    private ?WhatsappConfig $config = null;
    private OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    public function useConfig(?WhatsappConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    private function config(): ?WhatsappConfig
    {
        if ($this->config === null) {
            $this->config = WhatsappConfig::forUser(auth()->user());
        }

        return $this->config;
    }

    private function httpClient(bool $verifySsl = true, int $timeoutSeconds = 15)
    {
        $client = Http::timeout($timeoutSeconds)->withOptions([
            'proxy' => '',
            'curl' => [CURLOPT_PROXY => ''],
        ]);
        if (!$verifySsl || app()->environment(['local', 'development'])) {
            $client = $client->withoutVerifying();
        }
        return $client;
    }

    public function isConfigured(): bool
    {
        if (!Setting::isEnabled('whatsapp_module_enabled', true)) {
            return false;
        }

        $cfg = $this->config();
        return $cfg && filled($cfg->access_token) && filled($cfg->phone_number_id);
    }

    private function isAiAutoReplyEnabled(): bool
    {
        return config('services.whatsapp.ai_auto_reply', true)
            && Setting::isEnabled('whatsapp_auto_ai_enabled', true);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10 && preg_match('/^[6-9]/', $phone)) {
            return '91' . $phone;
        }
        return $phone;
    }

    /**
     * Create a CRM lead for a first-time WhatsApp contact (no matching lead phone/whatsapp).
     */
    private function createLeadFromIncomingWhatsapp(string $phone, ?string $contactName): ?Lead
    {
        try {
            $name = $contactName !== null && trim($contactName) !== '' ? trim($contactName) : 'WhatsApp ' . $phone;

            return Lead::create([
                'name' => $name,
                'phone' => $phone,
                'whatsapp' => $phone,
                'status' => 'new',
                'source' => 'WhatsApp',
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp auto-create lead failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process an incoming webhook message from Meta.
     */
    public function processIncomingMessage(array $messageData, array $contactData = []): ?WhatsappMessage
    {
        $phone = $messageData['from'] ?? null;
        if (!$phone) {
            return null;
        }

        $phone = $this->normalizePhone($phone);
        $contactName = $contactData['profile']['name'] ?? null;
        $metaMessageId = $messageData['id'] ?? null;
        $messageType = $messageData['type'] ?? 'text';

        if ($messageType === 'revoke') {
            $originalId = $messageData['revoke']['original_message_id'] ?? null;

            return $originalId ? $this->markMessageRevoked($originalId) : null;
        }

        // Extract message content
        $messageText = '';
        $mediaUrl = null;
        $mediaType = null;
        $metaMediaId = null;
        $mediaMetadata = [];

        switch ($messageType) {
            case 'text':
                $messageText = $messageData['text']['body'] ?? '';
                break;
            case 'image':
            case 'video':
            case 'document':
            case 'audio':
            case 'sticker':
                $metaMediaId = $messageData[$messageType]['id'] ?? null;
                $messageText = match ($messageType) {
                    'image' => $messageData['image']['caption'] ?? '[Image]',
                    'video' => $messageData['video']['caption'] ?? '[Video]',
                    'document' => $messageData['document']['filename']
                        ?? ($messageData['document']['caption'] ?? '[Document]'),
                    'audio' => '[Audio]',
                    'sticker' => '[Sticker]',
                    default => '[' . ucfirst($messageType) . ']',
                };

                $downloaded = $metaMediaId ? $this->downloadAndStoreMedia($metaMediaId) : null;
                if ($downloaded) {
                    $mediaUrl = $downloaded['url'];
                    $mediaType = $downloaded['mime'];
                    $mediaMetadata = [
                        'meta_media_id' => $metaMediaId,
                        'original_mime' => $downloaded['mime'],
                    ];
                } else {
                    // Keep Meta media id; displayMediaUrl() can proxy/download later.
                    $mediaUrl = $metaMediaId;
                    $mediaType = $messageType === 'sticker' ? 'image' : $messageType;
                    $mediaMetadata = ['meta_media_id' => $metaMediaId];
                }

                if ($messageType === 'sticker') {
                    $messageType = 'image';
                }
                break;
            case 'location':
                $lat = $messageData['location']['latitude'] ?? null;
                $lng = $messageData['location']['longitude'] ?? null;
                $locName = trim((string) ($messageData['location']['name'] ?? ''));
                $locAddress = trim((string) ($messageData['location']['address'] ?? ''));
                $mediaMetadata = [
                    'latitude' => $lat !== null ? (float) $lat : null,
                    'longitude' => $lng !== null ? (float) $lng : null,
                    'name' => $locName !== '' ? $locName : null,
                    'address' => $locAddress !== '' ? $locAddress : null,
                ];
                $messageText = $locName !== ''
                    ? $locName
                    : ($locAddress !== '' ? $locAddress : 'Location');
                if ($lat !== null && $lng !== null && $messageText === 'Location') {
                    $messageText = "Location: {$lat},{$lng}";
                }
                break;
            case 'reaction':
                $emoji = trim((string) ($messageData['reaction']['emoji'] ?? ''));
                $reactedMessageId = $messageData['reaction']['message_id'] ?? null;
                $mediaMetadata = [
                    'reacted_message_id' => $reactedMessageId,
                    'emoji' => $emoji !== '' ? $emoji : null,
                    'reaction_removed' => $emoji === '',
                ];
                $messageText = $emoji !== '' ? $emoji : '';
                break;
            case 'contacts':
                $contacts = collect($messageData['contacts'] ?? [])
                    ->map(function ($c) {
                        $phones = collect($c['phones'] ?? [])
                            ->map(fn ($p) => [
                                'phone' => $p['phone'] ?? null,
                                'wa_id' => $p['wa_id'] ?? null,
                                'type' => $p['type'] ?? null,
                            ])
                            ->filter(fn ($p) => filled($p['phone']) || filled($p['wa_id']))
                            ->values()
                            ->all();

                        $name = $c['name']['formatted_name']
                            ?? trim(($c['name']['first_name'] ?? '') . ' ' . ($c['name']['last_name'] ?? ''))
                            ?: 'Contact';

                        return [
                            'name' => $name,
                            'first_name' => $c['name']['first_name'] ?? null,
                            'last_name' => $c['name']['last_name'] ?? null,
                            'phones' => $phones,
                        ];
                    })
                    ->filter(fn ($c) => filled($c['name']))
                    ->values()
                    ->all();

                $mediaMetadata = ['contacts' => $contacts];
                $messageText = count($contacts)
                    ? (count($contacts) === 1
                        ? ($contacts[0]['name'] ?? 'Contact')
                        : count($contacts) . ' contacts')
                    : '[Contact]';
                break;
            case 'interactive':
                $interactive = $messageData['interactive'] ?? [];
                $messageText = $interactive['button_reply']['title']
                    ?? $interactive['list_reply']['title']
                    ?? '[Interactive reply]';
                break;
            case 'button':
                $messageText = $messageData['button']['text'] ?? '[Button reply]';
                break;
            default:
                $messageText = '[Unsupported message type: ' . $messageType . ']';
        }

        // Find or create conversation
        $conversation = WhatsappConversation::firstOrCreate(
            ['phone_number' => $phone],
            [
                'contact_name' => $contactName,
                'status' => 'open',
                'whatsapp_phone_id' => $this->config()?->phone_number_id,
            ]
        );

        if ($contactName && !$conversation->contact_name) {
            $conversation->update(['contact_name' => $contactName]);
        }

        // Link to existing lead, or auto-create for new numbers only
        if (! $conversation->lead_id) {
            $lead = Lead::query()
                ->where(function ($q) use ($phone) {
                    $q->where('whatsapp', $phone)->orWhere('phone', $phone);
                })
                ->first();

            if ($lead) {
                $conversation->update(['lead_id' => $lead->id]);
            } else {
                $lead = $this->createLeadFromIncomingWhatsapp($phone, $contactName);
                if ($lead) {
                    $conversation->update([
                        'lead_id' => $lead->id,
                        'contact_name' => $contactName ?: $conversation->contact_name ?: $lead->name,
                    ]);
                }
            }
        }

        // Avoid duplicate messages
        if ($metaMessageId && WhatsappMessage::where('meta_message_id', $metaMessageId)->exists()) {
            return null;
        }

        // WhatsApp reactions attach to the original message (no separate chat bubble).
        if ($messageType === 'reaction') {
            return $this->applyIncomingReaction(
                $conversation,
                $phone,
                $mediaMetadata['reacted_message_id'] ?? null,
                $mediaMetadata['emoji'] ?? null,
                (bool) ($mediaMetadata['reaction_removed'] ?? false),
                $metaMessageId
            );
        }

        if ($quotedMetaId = ($messageData['context']['id'] ?? null)) {
            $mediaMetadata = $this->attachReplyMetadata(
                $mediaMetadata,
                $quotedMetaId,
                $conversation,
                null,
                $messageData['context']['from'] ?? null,
                $phone
            );
        }

        // Store message
        $message = WhatsappMessage::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $conversation->lead_id,
            'direction' => 'incoming',
            'message' => $messageText,
            'message_type' => $messageType,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'meta_message_id' => $metaMessageId,
            'status' => 'delivered',
            'sent_at' => now(),
            'metadata' => $mediaMetadata ?: null,
        ]);

        // Update conversation
        $conversation->increment('unread_count');
        $conversation->update(['last_message_at' => now()]);
        $this->notifyIncomingWhatsappMessage($conversation, $messageText);

        try {
            event(new \App\Events\WhatsAppMessageReceived($message, $conversation));
        } catch (\Throwable $e) {
            Log::warning('WhatsApp broadcast event failed', ['error' => $e->getMessage()]);
        }

        if ($metaMessageId) {
            $this->refreshReplyLinksForMetaId($metaMessageId);
        }

        // Run automation after HTTP 200 is sent to Meta
        $inboxService = $this;
        dispatch(function () use ($inboxService, $conversation, $message, $messageText) {
            $conv = $conversation->fresh();
            $msg = $message->fresh();
            if ($conv && $msg) {
                $inboxService->ensureConfigForConversation($conv);
                $inboxService->triggerAutomation($conv, $msg, $messageText);
            }
        })->afterResponse();

        return $message;
    }

    /**
     * Attach/remove a WhatsApp reaction on the original message (WhatsApp-style badge).
     */
    private function applyIncomingReaction(
        WhatsappConversation $conversation,
        string $fromPhone,
        ?string $reactedMessageId,
        ?string $emoji,
        bool $removed,
        ?string $reactionEventId = null
    ): ?WhatsappMessage {
        // Remove any legacy standalone reaction bubbles for this chat.
        WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('message_type', 'reaction')
            ->delete();

        if (! filled($reactedMessageId)) {
            return null;
        }

        $target = WhatsappMessage::findByMetaMessageId($reactedMessageId, $conversation->id);

        if (! $target) {
            Log::info('WhatsApp reaction target message not found', [
                'conversation_id' => $conversation->id,
                'reacted_message_id' => $reactedMessageId,
                'emoji' => $emoji,
            ]);

            return null;
        }

        $meta = $target->metadata ?? [];
        $reactions = collect($meta['reactions'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values();

        // One reaction per sender on a message.
        $reactions = $reactions
            ->reject(fn ($row) => (string) ($row['from'] ?? '') === (string) $fromPhone)
            ->values();

        if (! $removed && filled($emoji)) {
            $reactions->push([
                'from' => $fromPhone,
                'emoji' => $emoji,
                'at' => now()->toIso8601String(),
                'event_id' => $reactionEventId,
            ]);
        }

        $meta['reactions'] = $reactions->values()->all();
        $target->update(['metadata' => $meta]);

        $conversation->update(['last_message_at' => now()]);

        if (! $removed && filled($emoji)) {
            $this->notifyIncomingWhatsappMessage($conversation, "Reacted {$emoji}");
        }

        return $target->fresh();
    }

    /**
     * Send or remove a reaction from the CRM (synced to WhatsApp for incoming messages).
     *
     * @return list<string>|null Reaction emoji list for the target message
     */
    public function sendReaction(
        WhatsappConversation $conversation,
        WhatsappMessage $target,
        ?string $emoji,
        ?int $sentBy = null,
        ?string &$errorMessage = null
    ): ?array {
        if ($target->conversation_id !== $conversation->id) {
            $errorMessage = 'Message not found in this conversation.';
            return null;
        }

        if ($target->isRevoked() || $target->trashed() || $target->message_type === 'reaction') {
            $errorMessage = 'This message cannot be reacted to.';
            return null;
        }

        if (! filled($target->meta_message_id)) {
            $errorMessage = 'This message is too old to react to on WhatsApp.';
            return null;
        }

        $emoji = $emoji !== null ? trim($emoji) : '';
        $businessFrom = 'biz:'.($this->config()?->phone_number_id ?? 'self');
        $existing = collect($target->metadata['reactions'] ?? [])
            ->first(fn ($row) => is_array($row) && (string) ($row['from'] ?? '') === $businessFrom);
        $existingEmoji = is_array($existing) ? (string) ($existing['emoji'] ?? '') : '';

        // Tap same emoji again = remove reaction.
        if ($emoji !== '' && $emoji === $existingEmoji) {
            $emoji = '';
        }

        $remove = $emoji === '';

        if ($target->direction === 'incoming') {
            if (! $this->isConfigured()) {
                $errorMessage = 'WhatsApp is not configured.';
                return null;
            }

            $cfg = $this->config();
            $phone = $this->normalizePhone($conversation->phone_number);
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'reaction',
                'reaction' => [
                    'message_id' => $target->meta_message_id,
                    'emoji' => $emoji,
                ],
            ];

            try {
                $response = $this->httpClient()
                    ->withToken($cfg->access_token)
                    ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

                $body = $response->json();
                if (! $response->successful()) {
                    Log::warning('WhatsApp reaction rejected', [
                        'target_id' => $target->id,
                        'response' => $body,
                    ]);
                    $errorMessage = $body['error']['message'] ?? 'WhatsApp rejected this reaction.';

                    return null;
                }
            } catch (\Throwable $e) {
                Log::error('WhatsApp reaction exception', ['error' => $e->getMessage()]);
                $errorMessage = $e->getMessage();

                return null;
            }
        }
        // Outgoing messages: CRM-only reaction badge (WhatsApp API does not support reacting to sent business messages).

        $meta = $target->metadata ?? [];
        $reactions = collect($meta['reactions'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values();

        $reactions = $reactions
            ->reject(fn ($row) => (string) ($row['from'] ?? '') === $businessFrom)
            ->values();

        if (! $remove && filled($emoji)) {
            $reactions->push([
                'from' => $businessFrom,
                'emoji' => $emoji,
                'at' => now()->toIso8601String(),
                'source' => 'crm',
                'sent_by' => $sentBy,
            ]);
        }

        $meta['reactions'] = $reactions->values()->all();
        $target->update(['metadata' => $meta]);

        return $target->fresh()->reactionEmojis();
    }

    private function notifyIncomingWhatsappMessage(WhatsappConversation $conversation, string $messageText): void
    {
        try {
            $contactName = trim((string) ($conversation->contact_name ?: $conversation->phone_number ?: 'Unknown contact'));
            $preview = trim($messageText);
            $preview = $preview !== '' ? mb_strimwidth($preview, 0, 80, '...') : 'Open the inbox to read it.';

            $notificationText = "New WhatsApp message from {$contactName}: {$preview}";
            $link = route('whatsapp.conversation', $conversation);

            $recipientIds = collect();

            if ($conversation->assigned_user_id) {
                $assignedUser = User::query()
                    ->where('id', $conversation->assigned_user_id)
                    ->where('is_active', true)
                    ->first();

                if ($assignedUser) {
                    $recipientIds->push((int) $assignedUser->id);
                }
            }

            // Always notify active admins, and if nobody is assigned notify all active users
            // so WhatsApp inbox staff get live browser notifications.
            $adminIds = User::query()
                ->where('is_active', true)
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['admin', 'super-admin']);
                })
                ->pluck('id');

            $recipientIds = $recipientIds->merge($adminIds);

            if ($recipientIds->isEmpty()) {
                $recipientIds = User::query()
                    ->where('is_active', true)
                    ->pluck('id');
            }

            $recipientIds
                ->unique()
                ->each(function ($userId) use ($notificationText, $link) {
                    Notification::create([
                        'user_id' => $userId,
                        'notification_text' => $notificationText,
                        'link' => $link,
                        'is_read' => 0,
                    ]);
                });
        } catch (\Throwable $e) {
            Log::error('WhatsApp incoming notification creation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a text message to a phone number.
     */
    public function sendTextMessage(
        WhatsappConversation $conversation,
        string $text,
        ?int $sentBy = null,
        ?string &$errorMessage = null,
        ?WhatsappMessage $replyTo = null
    ): ?WhatsappMessage {
        if (!$this->isConfigured()) {
            Log::warning('WhatsApp inbox: not configured');
            $errorMessage = 'WhatsApp is not configured. Save Phone Number ID and Access Token in Settings.';

            return null;
        }

        $cfg = $this->config();
        $phone = $this->normalizePhone($conversation->phone_number);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        if ($replyTo?->meta_message_id) {
            $payload['context'] = ['message_id' => $replyTo->meta_message_id];
        }

        try {
            $response = $this->httpClient()
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                $metadata = ['sent_by' => $sentBy];
                if ($replyTo) {
                    $metadata = $this->attachReplyMetadata(
                        $metadata,
                        $replyTo->meta_message_id,
                        $conversation,
                        $replyTo
                    );
                }

                $message = WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => $text,
                    'message_type' => 'text',
                    'meta_message_id' => $body['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => $metadata,
                ]);

                $conversation->update(['last_message_at' => now()]);
                if ($sentBy !== null) {
                    $this->clearPendingAiDeferral($conversation);
                }

                $this->refreshReplyLinksForMetaId($body['messages'][0]['id']);

                return $message;
            }

            $this->logGraphMessagesApiFailure('send text', is_array($body) ? $body : null, $cfg, $phone);
            $errorMessage = is_array($body) ? ($body['error']['message'] ?? 'Meta API rejected the message.') : 'Meta API rejected the message.';

            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send text exception', ['error' => $e->getMessage()]);
            $errorMessage = $e->getMessage();

            return null;
        }
    }

    /**
     * Send a WhatsApp location pin.
     */
    public function sendLocationMessage(
        WhatsappConversation $conversation,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null,
        ?int $sentBy = null,
        ?string &$errorMessage = null
    ): ?WhatsappMessage {
        if (! $this->isConfigured()) {
            $errorMessage = 'WhatsApp is not configured. Save Phone Number ID and Access Token in Settings.';

            return null;
        }

        $cfg = $this->config();
        $phone = $this->normalizePhone($conversation->phone_number);
        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
        if (filled($name)) {
            $location['name'] = $name;
        }
        if (filled($address)) {
            $location['address'] = $address;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'location',
            'location' => $location,
        ];

        try {
            $response = $this->httpClient(true, 30)
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            $body = $response->json();
            if (! $response->successful() || ! isset($body['messages'][0]['id'])) {
                $this->logGraphMessagesApiFailure('location send', is_array($body) ? $body : null, $cfg, $phone);
                $errorMessage = $body['error']['message'] ?? 'Meta rejected the location message.';

                return null;
            }

            $label = filled($name) ? $name : (filled($address) ? $address : 'Location');

            $message = WhatsappMessage::create([
                'conversation_id' => $conversation->id,
                'lead_id' => $conversation->lead_id,
                'direction' => 'outgoing',
                'message' => $label,
                'message_type' => 'location',
                'meta_message_id' => $body['messages'][0]['id'],
                'status' => 'sent',
                'sent_at' => now(),
                'metadata' => [
                    'sent_by' => $sentBy,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'name' => $name,
                    'address' => $address,
                ],
            ]);

            $conversation->update(['last_message_at' => now()]);
            if ($sentBy !== null) {
                $this->clearPendingAiDeferral($conversation);
            }

            return $message;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send location exception', ['error' => $e->getMessage()]);
            $errorMessage = $e->getMessage();

            return null;
        }
    }

    /**
     * Upload media to Meta and send it to the current conversation.
     */
    public function sendMediaMessage(
        WhatsappConversation $conversation,
        UploadedFile $file,
        ?string $caption = null,
        ?int $sentBy = null,
        ?string &$errorMessage = null,
        bool $asVoiceNote = false
    ): ?WhatsappMessage {
        if (! $this->isConfigured()) {
            $errorMessage = 'WhatsApp is not configured. Save Phone Number ID and Access Token in Settings.';
            return null;
        }

        $cfg = $this->config();
        $phone = $this->normalizePhone($conversation->phone_number);
        $resolved = $this->resolveUploadMediaType($file, $asVoiceNote);
        $mimeType = $resolved['mime'];
        $mediaType = $resolved['type'];
        $extension = $resolved['extension'];
        $originalName = $file->getClientOriginalName();
        $safeUploadName = $this->safeMediaFilename($originalName, $extension);

        $directory = public_path('uploads/whatsapp');
        File::ensureDirectoryExists($directory);
        $storedName = Str::uuid() . '.' . $extension;
        $storedPath = $file->move($directory, $storedName)->getPathname();

        // Normalize only when needed. Do NOT re-encode good JPEG/PNG under 5 MB —
        // that was destroying product photo quality for customers.
        if ($mediaType === 'image') {
            $normalized = $this->compressImageForWhatsApp($storedPath, $mimeType, $extension, false);
            if ($normalized) {
                $storedPath = $normalized['path'];
                $storedName = $normalized['filename'];
                $mimeType = $normalized['mime'];
                $extension = $normalized['extension'];
                $safeUploadName = $this->safeMediaFilename(pathinfo($originalName, PATHINFO_FILENAME) . '.' . $extension, $extension);
            } else {
                // Keep detected mime aligned with the real file on disk.
                $detected = $this->detectImageMime($storedPath);
                if ($detected) {
                    $mimeType = $detected;
                    if ($detected === 'image/jpeg') {
                        $extension = 'jpg';
                    } elseif ($detected === 'image/png') {
                        $extension = 'png';
                    }
                    $safeUploadName = $this->safeMediaFilename(pathinfo($originalName, PATHINFO_FILENAME) . '.' . $extension, $extension);
                }
            }
        }

        // Keep this relative so a stale APP_URL on production cannot break chat previews.
        $publicUrl = route('whatsapp.media', ['filename' => $storedName], false);

        try {
            $uploadSize = @filesize($storedPath) ?: 0;
            if ($mediaType === 'image' && $uploadSize > 5 * 1024 * 1024) {
                File::delete($storedPath);
                $errorMessage = 'Image is still larger than WhatsApp\'s 5 MB limit after compression. Please use a smaller photo.';
                return null;
            }

            $uploadResponse = $this->httpClient(true, 120)
                ->withToken($cfg->access_token)
                ->attach('file', fopen($storedPath, 'r'), $safeUploadName, ['Content-Type' => $mimeType])
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);

            $uploadBody = $uploadResponse->json();
            $mediaId = $uploadBody['id'] ?? null;
            if (! $uploadResponse->successful() || ! $mediaId) {
                File::delete($storedPath);
                Log::warning('WhatsApp media upload rejected', [
                    'mime' => $mimeType,
                    'extension' => $extension,
                    'original_name' => $originalName,
                    'size' => $uploadSize,
                    'response' => $uploadBody,
                ]);
                $errorMessage = $this->friendlyMediaUploadError($uploadBody, $mediaType);
                return null;
            }

            $mediaPayload = ['id' => $mediaId];
            if (filled($caption) && $mediaType !== 'audio') {
                $mediaPayload['caption'] = $caption;
            }
            if ($mediaType === 'document') {
                $mediaPayload['filename'] = $safeUploadName;
            }
            if ($asVoiceNote && $mediaType === 'audio' && $this->isWhatsAppVoiceNoteMime($mimeType, $extension)) {
                $mediaPayload['voice'] = true;
            }

            $response = $this->httpClient(true, 60)
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => $mediaType,
                    $mediaType => $mediaPayload,
                ]);

            $body = $response->json();
            if (! $response->successful() || ! isset($body['messages'][0]['id'])) {
                File::delete($storedPath);
                Log::warning('WhatsApp media message rejected', [
                    'mime' => $mimeType,
                    'to' => $phone,
                    'response' => $body,
                ]);
                $errorMessage = $body['error']['message'] ?? 'Meta rejected the attachment message.';
                return null;
            }

            $message = WhatsappMessage::create([
                'conversation_id' => $conversation->id,
                'lead_id' => $conversation->lead_id,
                'direction' => 'outgoing',
                'message' => filled($caption) && $mediaType !== 'audio'
                    ? $caption
                    : match ($mediaType) {
                        'image' => '[Image]',
                        'video' => '[Video]',
                        'audio' => '[Audio]',
                        default => $originalName,
                    },
                'message_type' => $mediaType,
                'media_url' => $publicUrl,
                'media_type' => $mimeType,
                'meta_message_id' => $body['messages'][0]['id'],
                'status' => 'sent',
                'sent_at' => now(),
                'metadata' => array_filter([
                    'sent_by' => $sentBy,
                    'original_name' => $originalName,
                    'meta_media_id' => $mediaId,
                    'voice_note' => ($asVoiceNote && $mediaType === 'audio') ? true : null,
                ]),
            ]);

            $conversation->update(['last_message_at' => now()]);
            if ($sentBy !== null) {
                $this->clearPendingAiDeferral($conversation);
            }

            return $message;
        } catch (\Throwable $e) {
            File::delete($storedPath);
            Log::error('WhatsApp send media exception', [
                'error' => $e->getMessage(),
                'mime' => $mimeType,
                'original_name' => $originalName,
            ]);
            $errorMessage = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Video upload timed out. Please try a smaller MP4 file (under 16 MB).'
                : $e->getMessage();
            return null;
        }
    }

    /**
     * Normalize browser/OS mime quirks (WhatsApp downloads often come as octet-stream).
     *
     * @return array{type: string, mime: string, extension: string}
     */
    private function resolveUploadMediaType(UploadedFile $file, bool $forceAudio = false): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
        $originalName = strtolower(basename((string) $file->getClientOriginalName()));

        $audioExtensions = ['m4a', 'mp3', 'ogg', 'opus', 'aac', 'amr', 'wav', 'caf'];
        $audioMimes = [
            'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/amr', 'audio/ogg',
            'audio/x-m4a', 'audio/m4a', 'audio/wav', 'audio/x-caf',
        ];

        $extensionMimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            '3gp' => 'video/3gpp',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'amr' => 'audio/amr',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/ogg',
            'wav' => 'audio/wav',
            'caf' => 'audio/x-caf',
            'pdf' => 'application/pdf',
        ];

        if (
            ($mimeType === 'application/octet-stream' || $mimeType === '' || $mimeType === 'binary/octet-stream')
            && isset($extensionMimeMap[$extension])
        ) {
            $mimeType = $extensionMimeMap[$extension];
        }

        $isVoiceUpload = $forceAudio
            || str_starts_with($originalName, 'voice-note-')
            || in_array($extension, $audioExtensions, true);

        if (
            $isVoiceUpload
            || str_starts_with($mimeType, 'audio/')
            || in_array($mimeType, $audioMimes, true)
        ) {
            $type = 'audio';
            if (! in_array($extension, $audioExtensions, true)) {
                $extension = 'm4a';
            }
            // iPhone recordings are often sniffed as video/mp4 even when they are audio-only .m4a.
            if (! str_starts_with($mimeType, 'audio/') || $mimeType === 'video/mp4') {
                $mimeType = $extensionMimeMap[$extension] ?? 'audio/mp4';
            }
        } elseif (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'video/') || in_array($extension, ['mp4', '3gp', 'mov', 'webm'], true)) {
            $type = 'video';
            if (! str_starts_with($mimeType, 'video/')) {
                $mimeType = $extensionMimeMap[$extension] ?? 'video/mp4';
            }
        } else {
            $type = 'document';
        }

        if ($type === 'video') {
            // Keep a safe playable extension for local preview + Meta upload.
            $extension = in_array($extension, ['mp4', '3gp', 'mov', 'webm'], true)
                ? $extension
                : 'mp4';
            if ($extension === 'mp4') {
                $mimeType = 'video/mp4';
            }
        } elseif ($extension === '' || $extension === 'bin') {
            $extension = match ($type) {
                'image' => 'jpg',
                'audio' => 'm4a',
                default => 'bin',
            };
            if ($type === 'audio') {
                $mimeType = $extensionMimeMap[$extension] ?? 'audio/mp4';
            }
        }

        return [
            'type' => $type,
            'mime' => $mimeType,
            'extension' => $extension,
        ];
    }

    private function isWhatsAppVoiceNoteMime(string $mimeType, string $extension): bool
    {
        $mimeType = strtolower($mimeType);
        $extension = strtolower(ltrim($extension, '.'));

        return $mimeType === 'audio/ogg'
            || str_contains($mimeType, 'opus')
            || $extension === 'ogg'
            || $extension === 'opus';
    }

    private function safeMediaFilename(string $originalName, string $extension): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $base) ?: 'media';
        $base = trim($base, '._-') ?: 'media';
        $extension = ltrim($extension, '.');

        return $base . '.' . $extension;
    }

    /**
     * Prepare images for WhatsApp Cloud API (JPEG/PNG, max 5 MB).
     * Prefer keeping the original file whenever Meta can accept it.
     *
     * @return array{path: string, filename: string, mime: string, extension: string}|null
     */
    private function compressImageForWhatsApp(string $path, string $mimeType, string $extension, bool $force = false): ?array
    {
        $size = @filesize($path) ?: 0;
        $info = @getimagesize($path);
        $detectedMime = is_array($info) ? strtolower((string) ($info['mime'] ?? '')) : '';
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);

        $isWhatsAppSafeMime = in_array($detectedMime, ['image/jpeg', 'image/png'], true);
        $isWebpOrOdd = ! $isWhatsAppSafeMime && (
            in_array($detectedMime, ['image/webp', 'image/bmp', 'image/tiff', 'image/gif'], true)
            || in_array(strtolower($extension), ['heic', 'heif', 'tiff', 'bmp', 'webp', 'gif'], true)
            || ($detectedMime !== '' && ! str_starts_with($detectedMime, 'image/'))
            || $detectedMime === ''
        );

        $needsShrink = $size > 5 * 1024 * 1024;
        // Only convert / re-encode when format is unsafe or file is over WhatsApp's 5 MB limit.
        $mustProcess = $force || $isWebpOrOdd || $needsShrink;

        if (! $mustProcess && $isWhatsAppSafeMime) {
            return null;
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $binary = @file_get_contents($path);
        if ($binary === false) {
            return null;
        }

        $source = @imagecreatefromstring($binary);
        if (! $source) {
            return null;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);

        // Prefer original pixels. Only downscale if still too large after high-quality JPEG encode.
        $targetW = $srcW;
        $targetH = $srcH;
        $maxEdge = 4096;
        if (max($srcW, $srcH) > $maxEdge) {
            $scale = $maxEdge / max($srcW, $srcH, 1);
            $targetW = max(1, (int) round($srcW * $scale));
            $targetH = max(1, (int) round($srcH * $scale));
        }

        $canvas = imagecreatetruecolor($targetW, $targetH);
        if (! $canvas) {
            imagedestroy($source);
            return null;
        }

        // Preserve PNG transparency when possible, otherwise white background for JPEG.
        $keepPng = $detectedMime === 'image/png' && ! $needsShrink && ! $isWebpOrOdd;
        if ($keepPng) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $white);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($source);

        $newName = Str::uuid() . ($keepPng ? '.png' : '.jpg');
        $newPath = dirname($path) . DIRECTORY_SEPARATOR . $newName;
        $written = false;
        $finalMime = $keepPng ? 'image/png' : 'image/jpeg';
        $finalExt = $keepPng ? 'png' : 'jpg';

        if ($keepPng) {
            $written = @imagepng($canvas, $newPath, 6);
            clearstatcache(true, $newPath);
            $newSize = @filesize($newPath) ?: 0;
            if ($newSize > 5 * 1024 * 1024) {
                // PNG still too big — flatten onto white and fall back to high-quality JPEG.
                @unlink($newPath);
                $flat = imagecreatetruecolor($targetW, $targetH);
                if ($flat) {
                    $white = imagecolorallocate($flat, 255, 255, 255);
                    imagefilledrectangle($flat, 0, 0, $targetW, $targetH, $white);
                    imagealphablending($flat, true);
                    imagecopy($flat, $canvas, 0, 0, 0, 0, $targetW, $targetH);
                    imagedestroy($canvas);
                    $canvas = $flat;
                }
                $keepPng = false;
                $written = false;
                $finalMime = 'image/jpeg';
                $finalExt = 'jpg';
                $newName = Str::uuid() . '.jpg';
                $newPath = dirname($path) . DIRECTORY_SEPARATOR . $newName;
            } else {
                $written = $newSize > 0;
            }
        }

        if (! $keepPng) {
            // High quality first; only lower quality / resize if over 5 MB.
            $qualitySteps = [95, 92, 88, 84, 78];
            $sizeAttempts = [
                [$targetW, $targetH],
            ];
            if (max($targetW, $targetH) > 2560) {
                $s = 2560 / max($targetW, $targetH, 1);
                $sizeAttempts[] = [max(1, (int) round($targetW * $s)), max(1, (int) round($targetH * $s))];
            }
            if (max($targetW, $targetH) > 1920) {
                $s = 1920 / max($targetW, $targetH, 1);
                $sizeAttempts[] = [max(1, (int) round($targetW * $s)), max(1, (int) round($targetH * $s))];
            }

            foreach ($sizeAttempts as [$attemptW, $attemptH]) {
                $export = $canvas;
                $tempCanvas = null;
                if ($attemptW !== $targetW || $attemptH !== $targetH) {
                    $tempCanvas = imagecreatetruecolor($attemptW, $attemptH);
                    if (! $tempCanvas) {
                        continue;
                    }
                    $white = imagecolorallocate($tempCanvas, 255, 255, 255);
                    imagefilledrectangle($tempCanvas, 0, 0, $attemptW, $attemptH, $white);
                    imagecopyresampled($tempCanvas, $canvas, 0, 0, 0, 0, $attemptW, $attemptH, $targetW, $targetH);
                    $export = $tempCanvas;
                }

                foreach ($qualitySteps as $quality) {
                    if (! @imagejpeg($export, $newPath, $quality)) {
                        continue;
                    }
                    clearstatcache(true, $newPath);
                    $newSize = @filesize($newPath) ?: 0;
                    if ($newSize > 0 && $newSize <= 5 * 1024 * 1024) {
                        $written = true;
                        if ($tempCanvas) {
                            imagedestroy($tempCanvas);
                        }
                        break 2;
                    }
                    $written = $newSize > 0;
                }

                if ($tempCanvas) {
                    imagedestroy($tempCanvas);
                }
            }
        }

        imagedestroy($canvas);

        if (! $written || ! is_file($newPath)) {
            @unlink($newPath);
            return null;
        }

        clearstatcache(true, $newPath);
        $finalSize = @filesize($newPath) ?: 0;
        if ($finalSize <= 0 || $finalSize > 5 * 1024 * 1024) {
            @unlink($newPath);
            return null;
        }

        @unlink($path);

        Log::info('WhatsApp image normalized before upload', [
            'from_bytes' => $size,
            'to_bytes' => $finalSize,
            'from_mime' => $detectedMime ?: $mimeType,
            'to_mime' => $finalMime,
            'from' => basename($path),
            'to' => $newName,
            'dimensions' => "{$width}x{$height}",
        ]);

        return [
            'path' => $newPath,
            'filename' => $newName,
            'mime' => $finalMime,
            'extension' => $finalExt,
        ];
    }

    private function detectImageMime(string $path): ?string
    {
        $info = @getimagesize($path);
        if (is_array($info) && ! empty($info['mime'])) {
            return strtolower((string) $info['mime']);
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (is_string($mime) && str_starts_with($mime, 'image/')) {
                return strtolower($mime);
            }
        }

        return null;
    }

    private function friendlyMediaUploadError(array $uploadBody, string $mediaType): string
    {
        $details = strtolower((string) data_get($uploadBody, 'error.error_data.details', ''));
        $message = (string) data_get($uploadBody, 'error.message', '');

        if (
            str_contains($details, 'મોટી')
            || str_contains($details, 'too large')
            || str_contains($details, 'file too big')
            || str_contains(strtolower($message), 'too large')
        ) {
            return $mediaType === 'image'
                ? 'Image is too large for WhatsApp (max 5 MB). Try a smaller photo.'
                : ($mediaType === 'video'
                    ? 'Video is too large for WhatsApp (max 16 MB). Please compress it and try again.'
                    : 'File is too large for WhatsApp. Please use a smaller file.');
        }

        if ($message !== '') {
            return $message;
        }

        return $mediaType === 'video'
            ? 'Meta rejected the media upload. WhatsApp videos must be MP4 (H.264) under 16 MB.'
            : 'Meta rejected the media upload. Please try another file.';
    }

    /**
     * Send a template message via conversation.
     */
    public function sendTemplateMessage(
        WhatsappConversation $conversation,
        string $templateName,
        array $variables = [],
        ?int $sentBy = null
    ): ?WhatsappMessage {
        if (!$this->isConfigured()) {
            return null;
        }

        $cfg = $this->config();
        $phone = $this->normalizePhone($conversation->phone_number);

        $template = WhatsappMessageTemplate::where('name', $templateName)->first();
        $language = $template?->language ?: 'en';

        $components = [];
        if (!empty($variables)) {
            $params = array_map(fn($v) => ['type' => 'text', 'text' => (string) $v], $variables);
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = $this->httpClient()
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                $message = WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id' => $conversation->lead_id,
                    'direction' => 'outgoing',
                    'message' => "[Template: {$templateName}]",
                    'message_type' => 'template',
                    'meta_message_id' => $body['messages'][0]['id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => ['template' => $templateName, 'variables' => $variables, 'sent_by' => $sentBy],
                ]);

                $conversation->update(['last_message_at' => now()]);
                if ($sentBy !== null) {
                    $this->clearPendingAiDeferral($conversation);
                }
                return $message;
            }

            $this->logGraphMessagesApiFailure('send template', is_array($body) ? $body : null, $cfg, $phone);
            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp template send exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update message status from webhook.
     */
    public function updateMessageStatus(string $metaMessageId, string $status, ?string $timestamp = null): void
    {
        $message = WhatsappMessage::where('meta_message_id', $metaMessageId)->first();
        if (!$message) {
            return;
        }

        $updates = ['status' => $status];
        if ($status === 'delivered') {
            $updates['delivered_at'] = $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : now();
        } elseif ($status === 'read') {
            $updates['read_at'] = $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : now();
        }

        $message->update($updates);

        // Update campaign log if applicable
        if ($message->metadata && isset($message->metadata['campaign_id'])) {
            $campaign = WhatsappCampaign::find($message->metadata['campaign_id']);
            if ($campaign) {
                if ($status === 'delivered') {
                    $campaign->incrementDelivered();
                } elseif ($status === 'read') {
                    $campaign->incrementRead();
                } elseif ($status === 'failed') {
                    $campaign->incrementFailed();
                }
            }
        }
    }

    public function ensureConfigForConversation(WhatsappConversation $conversation): void
    {
        if (filled($conversation->whatsapp_phone_id)) {
            $config = WhatsappConfig::byPhoneNumberId($conversation->whatsapp_phone_id);
            if ($config) {
                $this->useConfig($config);

                return;
            }
        }

        if ($this->config() === null) {
            $fallback = WhatsappConfig::adminConfig();
            if ($fallback) {
                $this->useConfig($fallback);
            }
        }
    }

    /**
     * Trigger automation rules for incoming messages.
     *
     * The first-contact welcome is always allowed. The global Auto AI switch
     * controls every subsequent keyword, FAQ, and generated AI reply.
     */
    public function triggerAutomation(WhatsappConversation $conversation, WhatsappMessage $message, string $text): void
    {
        $incomingCount = WhatsappMessage::where('conversation_id', $conversation->id)
            ->where('direction', 'incoming')
            ->count();

        // First customer message: welcome only — never AI, never keyword/FAQ.
        if ($incomingCount === 1) {
            $this->sendFirstContactWelcome($conversation);
            return;
        }

        if (! $this->isAiAutoReplyEnabled()) {
            $this->clearPendingAiDeferral($conversation);

            return;
        }

        // Keyword matching
        $keywordRules = WhatsappAutomationRule::active()
            ->byTriggerType('keyword')
            ->byPriority()
            ->get();

        foreach ($keywordRules as $rule) {
            if ($rule->matchesKeyword($text)) {
                $rule->incrementExecution();
                if ($this->executeAutomationRule($rule, $conversation)) {
                    return;
                }
            }
        }

        // FAQ automation
        $faqRules = WhatsappAutomationRule::active()
            ->byTriggerType('faq')
            ->byPriority()
            ->get();

        foreach ($faqRules as $rule) {
            if ($rule->matchesKeyword($text)) {
                $rule->incrementExecution();
                if ($this->executeAutomationRule($rule, $conversation)) {
                    return;
                }
            }
        }

        // Generated AI reply (global + per-chat). Never on first message.
        $this->scheduleDeferredAiReply($conversation, $message, $text);
    }

    /**
     * Send a configured welcome automation rule on first contact only.
     * No hardcoded default message — use WhatsApp Automation welcome rules if needed.
     */
    private function sendFirstContactWelcome(WhatsappConversation $conversation): void
    {
        $meta = $conversation->metadata ?? [];
        if (! empty($meta['welcome_sent_at'])) {
            return;
        }

        $welcomeRule = WhatsappAutomationRule::active()
            ->byTriggerType('welcome')
            ->byPriority()
            ->first();

        if (! $welcomeRule) {
            return;
        }

        $this->executeAutomationRule($welcomeRule, $conversation);
        $welcomeRule->incrementExecution();

        $meta['welcome_sent_at'] = now()->toIso8601String();
        $conversation->update(['metadata' => $meta]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function attachReplyMetadata(
        array $metadata,
        string $quotedMetaId,
        WhatsappConversation $conversation,
        ?WhatsappMessage $quotedMessage = null,
        ?string $contextFrom = null,
        ?string $currentSenderPhone = null
    ): array {
        $metadata['reply_to_meta_message_id'] = $quotedMetaId;
        if ($contextFrom) {
            $metadata['reply_context_from'] = $contextFrom;
        }

        $quoted = $quotedMessage
            ?? WhatsappMessage::findByMetaMessageId($quotedMetaId, $conversation->id);

        if ($quoted) {
            $metadata['reply_to_message_id'] = $quoted->id;
            $metadata['reply_preview'] = $quoted->toReplyPreview($conversation);

            return $metadata;
        }

        $quotedFromContact = $contextFrom && $currentSenderPhone
            && $this->normalizePhone($contextFrom) === $this->normalizePhone($currentSenderPhone);

        $metadata['reply_preview'] = [
            'message_id' => null,
            'author' => $quotedFromContact
                ? ($conversation->contact_name ?? $conversation->phone_number ?? 'Contact')
                : 'You',
            'text' => $quotedFromContact ? 'Original message' : 'Message sent from WhatsApp app',
            'message_type' => 'text',
            'direction' => $quotedFromContact ? 'incoming' : 'outgoing',
        ];

        return $metadata;
    }

    /**
     * Store messages sent from the WhatsApp Business app (smb_message_echoes webhook).
     */
    public function processOutgoingEcho(array $echoData, array $value = []): ?WhatsappMessage
    {
        $metaMessageId = $echoData['id'] ?? null;
        $recipientPhone = $echoData['to'] ?? null;
        if (! $metaMessageId || ! $recipientPhone) {
            return null;
        }

        if (WhatsappMessage::where('meta_message_id', $metaMessageId)->exists()) {
            return null;
        }

        $phone = $this->normalizePhone($recipientPhone);
        $messageType = $echoData['type'] ?? 'text';

        if ($messageType === 'revoke') {
            $originalId = $echoData['revoke']['original_message_id'] ?? null;

            return $originalId ? $this->markMessageRevoked($originalId) : null;
        }

        $messageText = '';
        $mediaUrl = null;
        $mediaType = null;
        $mediaMetadata = ['sent_from' => 'whatsapp_app'];

        switch ($messageType) {
            case 'text':
                $messageText = $echoData['text']['body'] ?? '';
                break;
            case 'image':
            case 'video':
            case 'document':
            case 'audio':
            case 'sticker':
                $metaMediaId = $echoData[$messageType]['id'] ?? null;
                $messageText = match ($messageType) {
                    'image' => $echoData['image']['caption'] ?? '[Image]',
                    'video' => $echoData['video']['caption'] ?? '[Video]',
                    'document' => $echoData['document']['filename']
                        ?? ($echoData['document']['caption'] ?? '[Document]'),
                    'audio' => '[Audio]',
                    'sticker' => '[Sticker]',
                    default => '[' . ucfirst($messageType) . ']',
                };

                $downloaded = $metaMediaId ? $this->downloadAndStoreMedia($metaMediaId) : null;
                if ($downloaded) {
                    $mediaUrl = $downloaded['url'];
                    $mediaType = $downloaded['mime'];
                    $mediaMetadata['meta_media_id'] = $metaMediaId;
                } else {
                    $mediaUrl = $metaMediaId;
                    $mediaType = $messageType === 'sticker' ? 'image' : $messageType;
                    $mediaMetadata['meta_media_id'] = $metaMediaId;
                }

                if ($messageType === 'sticker') {
                    $messageType = 'image';
                }
                break;
            default:
                $messageText = '[Unsupported message type: ' . $messageType . ']';
        }

        $conversation = WhatsappConversation::firstOrCreate(
            ['phone_number' => $phone],
            [
                'status' => 'open',
                'whatsapp_phone_id' => $this->config()?->phone_number_id,
            ]
        );

        if ($quotedMetaId = ($echoData['context']['id'] ?? null)) {
            $mediaMetadata = $this->attachReplyMetadata(
                $mediaMetadata,
                $quotedMetaId,
                $conversation,
                null,
                $echoData['context']['from'] ?? null,
                $phone
            );
        }

        $message = WhatsappMessage::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $conversation->lead_id,
            'direction' => 'outgoing',
            'message' => $messageText,
            'message_type' => $messageType,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'meta_message_id' => $metaMessageId,
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => $mediaMetadata ?: null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->refreshReplyLinksForMetaId($metaMessageId);

        return $message;
    }

    /**
     * When a quoted message arrives later (e.g. app echo), update existing replies.
     */
    public function refreshReplyLinksForMetaId(string $metaMessageId): void
    {
        $quoted = WhatsappMessage::withTrashed()->where('meta_message_id', $metaMessageId)->first();
        if (! $quoted) {
            return;
        }

        $quotedKey = WhatsappMessage::extractWamidMessageKey($metaMessageId);

        WhatsappMessage::query()
            ->where('conversation_id', $quoted->conversation_id)
            ->whereNotNull('metadata')
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->each(function (WhatsappMessage $message) use ($quoted, $metaMessageId, $quotedKey) {
                $meta = $message->metadata ?? [];
                $replyMetaId = $meta['reply_to_meta_message_id'] ?? null;
                if (! $replyMetaId) {
                    return;
                }

                $matchesExact = $replyMetaId === $metaMessageId;
                $matchesKey = $quotedKey
                    && WhatsappMessage::extractWamidMessageKey($replyMetaId) === $quotedKey;
                if (! $matchesExact && ! $matchesKey) {
                    return;
                }

                if (($meta['reply_to_message_id'] ?? null) === $quoted->id) {
                    $previewText = (string) ($meta['reply_preview']['text'] ?? '');
                    if ($previewText !== '' && $previewText !== 'Message' && $previewText !== 'Original message') {
                        return;
                    }
                }

                $conversation = $message->conversation ?? $quoted->conversation;
                if (! $conversation) {
                    return;
                }

                $meta['reply_to_message_id'] = $quoted->id;
                $meta['reply_preview'] = $quoted->toReplyPreview($conversation);
                $message->update(['metadata' => $meta]);
            });
    }

    /**
     * Remove a message from the CRM inbox (soft delete).
     */
    public function deleteMessage(WhatsappMessage $message, ?int $deletedBy = null): bool
    {
        if ($message->trashed() || $message->isRevoked()) {
            return true;
        }

        $metadata = $message->metadata ?? [];
        $metadata['deleted_by_user_id'] = $deletedBy;
        $metadata['deleted_at'] = now()->toIso8601String();
        $message->update(['metadata' => $metadata]);
        $message->delete();

        return true;
    }

    /**
     * Mark a message as deleted on WhatsApp (revoke webhook).
     */
    public function markMessageRevoked(string $originalMetaMessageId): ?WhatsappMessage
    {
        $message = WhatsappMessage::query()
            ->where('meta_message_id', $originalMetaMessageId)
            ->first();

        if (! $message || $message->isRevoked()) {
            return $message;
        }

        $metadata = $message->metadata ?? [];
        $metadata['revoked_at'] = now()->toIso8601String();
        $metadata['revoked_original'] = [
            'message' => $message->message,
            'message_type' => $message->message_type,
            'media_url' => $message->media_url,
            'media_type' => $message->media_type,
        ];

        $message->update([
            'message' => 'This message was deleted',
            'message_type' => 'revoked',
            'media_url' => null,
            'media_type' => null,
            'metadata' => $metadata,
        ]);

        return $message;
    }

    /**
     * Cancel pending delayed AI when an agent replies from the CRM inbox.
     */
    public function clearPendingAiDeferral(WhatsappConversation $conversation): void
    {
        $meta = $conversation->metadata ?? [];
        if (! isset($meta['defer_ai_for_message_id']) && ! isset($meta['defer_ai_until'])) {
            return;
        }
        unset($meta['defer_ai_for_message_id'], $meta['defer_ai_until']);
        $conversation->update(['metadata' => $meta]);
    }

    /**
     * After delay, send AI reply only if this inbound is still the latest deferred target and no agent replied.
     */
    public function processDeferredAiReply(int $conversationId, int $incomingMessageId): void
    {
        $conversation = WhatsappConversation::find($conversationId);

        if ($conversation) {
            $this->ensureConfigForConversation($conversation);
        }

        if (! $this->isAiAutoReplyEnabled()) {
            if ($conversation) {
                $this->clearPendingAiDeferral($conversation);
            }

            return;
        }

        $incoming = WhatsappMessage::find($incomingMessageId);
        if (! $conversation || ! $incoming || (int) $incoming->conversation_id !== $conversationId) {
            return;
        }

        $meta = $conversation->metadata ?? [];
        $deferredId = (int) ($meta['defer_ai_for_message_id'] ?? 0);
        if ($deferredId > 0 && $deferredId !== $incomingMessageId) {
            $latestIncoming = WhatsappMessage::find($deferredId);
            if ($latestIncoming && (int) $latestIncoming->conversation_id === $conversationId) {
                $incoming = $latestIncoming;
                $incomingMessageId = $deferredId;
            } elseif ($deferredId !== $incomingMessageId) {
                Log::info('WhatsApp deferred AI skipped: newer message deferred', [
                    'conversation_id' => $conversationId,
                    'job_message_id' => $incomingMessageId,
                    'deferred_message_id' => $deferredId,
                ]);

                return;
            }
        }

        if (! $conversation->aiReplyEnabled()) {
            $this->clearPendingAiDeferral($conversation);

            return;
        }

        $meta = $conversation->metadata ?? [];
        if ((int) ($meta['defer_ai_for_message_id'] ?? 0) !== $incomingMessageId) {
            return;
        }

        $humanReplied = WhatsappMessage::where('conversation_id', $conversationId)
            ->where('direction', 'outgoing')
            ->where('id', '>', $incomingMessageId)
            ->whereNotNull('metadata->sent_by')
            ->where(function ($query) {
                $query->whereNull('metadata->ai_generated')
                    ->orWhere('metadata->ai_generated', false);
            })
            ->exists();

        if ($humanReplied) {
            $this->clearPendingAiDeferral($conversation);

            return;
        }

        $this->sendAIReply($conversation, $incoming, (string) $incoming->message);
        $this->clearPendingAiDeferral($conversation->fresh() ?? $conversation);
    }

    private function scheduleDeferredAiReply(WhatsappConversation $conversation, WhatsappMessage $incomingMessage, string $text): void
    {
        if (! $this->isAiAutoReplyEnabled()) {
            $this->clearPendingAiDeferral($conversation);

            return;
        }

        if (! $conversation->fresh()->aiReplyEnabled()) {
            return;
        }

        $delaySeconds = max(15, (int) config('services.whatsapp.ai_reply_delay_seconds', 60));
        $minutes = (int) config('services.whatsapp.ai_reply_delay_minutes', 1);
        if ($minutes > 0) {
            $delaySeconds = max($delaySeconds, $minutes * 60);
        }

        $meta = array_merge($conversation->metadata ?? [], [
            'defer_ai_for_message_id' => $incomingMessage->id,
            'defer_ai_until' => now()->addSeconds($delaySeconds)->toIso8601String(),
        ]);
        $conversation->update(['metadata' => $meta]);

        $conversationId = $conversation->id;
        $incomingId = $incomingMessage->id;
        $useQueue = (bool) config('services.whatsapp.ai_use_queue', false)
            && config('queue.default') !== 'sync';

        if ($useQueue) {
            SendDeferredWhatsAppAiReply::dispatch($conversationId, $incomingId)
                ->delay(now()->addSeconds($delaySeconds));

            return;
        }

        if (config('queue.default') === 'sync' && $delaySeconds <= 0) {
            if ($this->isAiAutoReplyEnabled() && $conversation->fresh()->aiReplyEnabled()) {
                $this->sendAIReply($conversation->fresh(), $incomingMessage->fresh(), $text);
            }
            $this->clearPendingAiDeferral($conversation->fresh() ?? $conversation);

            return;
        }

        Log::info('WhatsApp AI reply scheduled (after response)', [
            'conversation_id' => $conversationId,
            'incoming_message_id' => $incomingId,
            'delay_seconds' => $delaySeconds,
        ]);

        dispatch(function () use ($conversationId, $incomingId, $delaySeconds) {
            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }

            $inbox = app(WhatsAppInboxService::class);
            $conv = WhatsappConversation::find($conversationId);
            if ($conv) {
                $inbox->ensureConfigForConversation($conv);
            }
            $inbox->processDeferredAiReply($conversationId, $incomingId);
        })->afterResponse();
    }

    /**
     * Execute an automation rule.
     */
    public function executeAutomationRule(WhatsappAutomationRule $rule, WhatsappConversation $conversation): bool
    {
        if ($rule->template_id && $rule->template) {
            $sent = $this->sendTemplateMessage($conversation, $rule->template->name);

            return $sent !== null;
        }

        if ($rule->response_message) {
            $sent = $this->sendTextMessage($conversation, $rule->response_message);

            return $sent !== null;
        }

        return false;
    }

    /**
     * Send AI-generated reply to incoming message.
     */
    private function sendAIReply(WhatsappConversation $conversation, WhatsappMessage $incomingMessage, string $text): void
    {
        if (! $this->isAiAutoReplyEnabled() || ! $conversation->fresh()->aiReplyEnabled()) {
            return;
        }

        $this->ensureConfigForConversation($conversation);

        if (! $this->isConfigured()) {
            Log::warning('AI reply skipped: WhatsApp API not configured for this conversation', [
                'conversation_id' => $conversation->id,
                'whatsapp_phone_id' => $conversation->whatsapp_phone_id,
            ]);

            return;
        }

        if (empty(config('services.openai.api_key'))) {
            Log::warning('AI reply skipped: OPENAI_API_KEY is not set in .env', [
                'conversation_id' => $conversation->id,
            ]);

            return;
        }

        try {
            // Get last 10 messages in chronological order for context (excluding the current one)
            $conversationHistory = WhatsappMessage::where('conversation_id', $conversation->id)
                ->where('id', '!=', $incomingMessage->id)
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get()
                ->map(fn($msg) => [
                    'direction' => $msg->direction,
                    'message'   => $msg->message,
                ])
                ->toArray();

            // Get lead information for context
            $leadInfo = [];
            if ($conversation->lead) {
                $leadInfo = [
                    'name'   => $conversation->lead->name,
                    'email'  => $conversation->lead->email,
                    'phone'  => $conversation->lead->phone,
                    'status' => $conversation->lead->status,
                ];
            }

            // Generate AI reply
            $aiReply = $this->openAIService->generateReply($text, [
                'conversation_history' => $conversationHistory,
                'lead_info'            => $leadInfo,
                'business_name'        => Setting::getValue('company_name')
                    ?: config('services.whatsapp.ai_business_name', 'Shrishti Trip'),
                'products'             => [
                    'Domestic tour packages',
                    'International holidays',
                    'Family and group tours',
                    'Hotel and transport bookings',
                    'Custom itineraries',
                    'Weekend getaways',
                ],
            ]);

            if (! $aiReply) {
                Log::warning('AI reply generation returned null', [
                    'conversation_id' => $conversation->id,
                ]);

                return;
            }

            // The setting may have been disabled while OpenAI was generating.
            // Re-check immediately before making the irreversible Meta API call.
            if (! $this->isAiAutoReplyEnabled() || ! $conversation->fresh()->aiReplyEnabled()) {
                return;
            }

            $cfg = $this->config();
            if (! $cfg) {
                Log::warning('AI reply skipped: no WhatsApp config resolved', [
                    'conversation_id' => $conversation->id,
                ]);

                return;
            }

            $phone = $this->normalizePhone($conversation->phone_number);

            $payload = [
                'messaging_product' => 'whatsapp',
                'to'   => $phone,
                'type' => 'text',
                'text' => ['body' => $aiReply],
            ];

            $response = $this->httpClient()
                ->withToken($cfg->access_token)
                ->post("https://graph.facebook.com/v19.0/{$cfg->phone_number_id}/messages", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['messages'][0]['id'])) {
                WhatsappMessage::create([
                    'conversation_id' => $conversation->id,
                    'lead_id'         => $conversation->lead_id,
                    'direction'       => 'outgoing',
                    'message'         => $aiReply,
                    'message_type'    => 'text',
                    'meta_message_id' => $body['messages'][0]['id'],
                    'status'          => 'sent',
                    'sent_at'         => now(),
                    'metadata'        => ['ai_generated' => true, 'model' => config('services.openai.model')],
                ]);

                $conversation->update(['last_message_at' => now()]);

                Log::info('AI reply sent', [
                    'conversation_id' => $conversation->id,
                    'lead_id'         => $conversation->lead_id,
                    'incoming'        => $text,
                    'reply'           => $aiReply,
                ]);
            } else {
                $this->logGraphMessagesApiFailure('AI reply send', is_array($body) ? $body : null, $cfg, $phone);
            }
        } catch (\Throwable $e) {
            Log::error('AI reply error', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get media URL from Meta.
     */
    public function getMediaUrl(string $mediaId): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->httpClient()
                ->withToken($this->config()->access_token)
                ->get("https://graph.facebook.com/v19.0/{$mediaId}");

            return $response->json('url');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Download inbound Meta media and store it under public/uploads/whatsapp.
     *
     * @return array{url: string, mime: string, filename: string}|null
     */
    public function downloadAndStoreMedia(string $mediaId): ?array
    {
        if (! $this->isConfigured() || ! filled($mediaId)) {
            return null;
        }

        try {
            $cfg = $this->config();
            $metaResponse = $this->httpClient()
                ->withToken($cfg->access_token)
                ->get("https://graph.facebook.com/v19.0/{$mediaId}");

            if (! $metaResponse->successful()) {
                Log::warning('WhatsApp media metadata fetch failed', [
                    'media_id' => $mediaId,
                    'status' => $metaResponse->status(),
                    'body' => $metaResponse->json(),
                ]);

                return null;
            }

            $downloadUrl = $metaResponse->json('url');
            $mimeType = (string) ($metaResponse->json('mime_type') ?: 'application/octet-stream');
            if (! filled($downloadUrl)) {
                return null;
            }

            $binaryResponse = $this->httpClient()
                ->withToken($cfg->access_token)
                ->withHeaders(['Accept' => '*/*'])
                ->get($downloadUrl);

            if (! $binaryResponse->successful()) {
                Log::warning('WhatsApp media binary download failed', [
                    'media_id' => $mediaId,
                    'status' => $binaryResponse->status(),
                ]);

                return null;
            }

            $extension = $this->extensionFromMime($mimeType);
            $directory = public_path('uploads/whatsapp');
            File::ensureDirectoryExists($directory);
            $storedName = Str::uuid() . '.' . $extension;
            $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
            File::put($storedPath, $binaryResponse->body());

            return [
                'url' => route('whatsapp.media', ['filename' => $storedName], false),
                'mime' => $mimeType,
                'filename' => $storedName,
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsApp media download exception', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * If a message still stores a Meta media id, download it now and update the row.
     */
    public function resolveIncomingMedia(WhatsappMessage $message): ?string
    {
        $mediaRef = (string) ($message->media_url ?? '');
        if ($mediaRef === '') {
            return null;
        }

        // Already a local media route / path.
        if (str_contains($mediaRef, '/whatsapp/inbox/media/') || str_contains($mediaRef, '/uploads/whatsapp/')) {
            return $message->displayMediaUrl();
        }

        $metaMediaId = $message->metadata['meta_media_id'] ?? null;
        if (! $metaMediaId && preg_match('/^\d{5,}$/', $mediaRef)) {
            $metaMediaId = $mediaRef;
        }

        if (! $metaMediaId) {
            return $message->displayMediaUrl();
        }

        $downloaded = $this->downloadAndStoreMedia((string) $metaMediaId);
        if (! $downloaded) {
            return null;
        }

        $meta = $message->metadata ?? [];
        $meta['meta_media_id'] = (string) $metaMediaId;

        $message->update([
            'media_url' => $downloaded['url'],
            'media_type' => $downloaded['mime'],
            'metadata' => $meta,
        ]);

        return $downloaded['url'];
    }

    private function extensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        if (isset($map[strtolower($mimeType)])) {
            return $map[strtolower($mimeType)];
        }

        $parts = explode('/', strtolower($mimeType));

        return preg_replace('/[^a-z0-9]/', '', $parts[1] ?? 'bin') ?: 'bin';
    }

    /**
     * Log Meta Graph /messages failures. 131030: wrong phone_number_id vs token, or Dev recipient list.
     */
    private function logGraphMessagesApiFailure(string $context, ?array $body, ?WhatsappConfig $cfg, ?string $toPhone): void
    {
        $code = is_array($body) ? ($body['error']['code'] ?? null) : null;
        if ((int) $code === 131030) {
            Log::warning("WhatsApp {$context}: Meta 131030 (recipient not allowed for this sender).", [
                'hint' => 'Use the same Phone number ID + access token in CRM as in Meta > WhatsApp > API setup. If webhook logs show a different phone_number_id than whatsapp_config in the DB, update the CRM config to match. In Development, add the recipient to Meta test list. "Already in list" means pick that number from the dropdown; do not add it again.',
                'crm_phone_number_id' => $cfg?->phone_number_id,
                'to' => $toPhone,
                'meta_error' => is_array($body) ? ($body['error'] ?? $body) : $body,
            ]);

            return;
        }

        Log::error("WhatsApp {$context} failed", ['response' => $body]);
    }

    /**
     * Send broadcast campaign.
     */
    public function sendCampaign(WhatsappCampaign $campaign): void
    {
        $campaign->markAsSending();

        $leads = Lead::whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->get();

        $campaign->update(['total_recipients' => $leads->count()]);

        foreach ($leads as $lead) {
            try {
                $conversation = WhatsappConversation::firstOrCreate(
                    ['phone_number' => $this->normalizePhone($lead->whatsapp)],
                    [
                        'lead_id' => $lead->id,
                        'contact_name' => $lead->name,
                        'status' => 'open',
                    ]
                );

                if ($campaign->template_id && $campaign->template) {
                    $message = $this->sendTemplateMessage($conversation, $campaign->template->name);
                } elseif ($campaign->message) {
                    $message = $this->sendTextMessage($conversation, $campaign->message);
                } else {
                    continue;
                }

                if ($message) {
                    $message->update(['metadata' => array_merge($message->metadata ?? [], ['campaign_id' => $campaign->id])]);
                    $campaign->incrementSent();
                } else {
                    $campaign->incrementFailed();
                }
            } catch (\Throwable $e) {
                $campaign->incrementFailed();
                Log::error('Campaign send error', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
            }
        }

        $campaign->markAsCompleted();
    }
}
