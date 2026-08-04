<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadSource;
use App\Models\LeadTag;
use App\Models\Setting;
use App\Models\Stage;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFollowup;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageTemplate;
use App\Models\WhatsappConfig;
use App\Services\WhatsappConfigResolver;
use App\Services\WhatsAppInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsappInboxController extends Controller
{
    public function __construct(
        private WhatsAppInboxService $inboxService,
        private WhatsappConfigResolver $configResolver,
    ) {}

    private function activeWhatsappConfig(): ?WhatsappConfig
    {
        return WhatsappConfig::forUser(Auth::user());
    }

    private function scopeConversationsToActiveConfig($query): void
    {
        $config = $this->activeWhatsappConfig();
        if ($config && filled($config->phone_number_id)) {
            $query->where('whatsapp_phone_id', $config->phone_number_id);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * WhatsApp Chat Inbox
     */
    public function inbox(Request $request)
    {
        $user = Auth::user();
        $activeConfig = $this->activeWhatsappConfig();
        $needsSetup = $user
            && ! $user->isAdmin()
            && $user->canUseWhatsappInbox()
            && $activeConfig === null;

        if ($needsSetup) {
            return redirect()
                ->route('whatsapp.settings')
                ->with('warning', 'Configure your WhatsApp bot or ask admin for shared bot access.');
        }

        $this->inboxService->useConfig($activeConfig);

        $filter = $request->get('status', WhatsappConversation::FILTER_ALL);

        $conversations = WhatsappConversation::with(['lead', 'assignedUser', 'latestMessage'])
            ->inboxFilter($filter)
            ->tap(fn ($q) => $this->scopeConversationsToActiveConfig($q))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_user_id', $request->assigned_to))
            ->when($request->search, function ($q) use ($request) {
                $term = $this->likeTerm($request->search);
                $q->where(function ($sub) use ($term) {
                    $sub->where('contact_name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term)
                        ->orWhereHas('messages', function ($mq) use ($term) {
                            $mq->whereNotIn('message_type', ['reaction', 'revoked'])
                                ->where('message', 'like', $term);
                        });
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(30);

        $users = User::orderBy('name')->get();
        $totalUnreadQuery = WhatsappConversation::query()->where('unread_count', '>', 0);
        $this->scopeConversationsToActiveConfig($totalUnreadQuery);
        $totalUnread = $totalUnreadQuery->sum('unread_count');

        return view('whatsapp.inbox', compact('conversations', 'users', 'totalUnread', 'filter'));
    }

    /**
     * Show a single conversation
     */
    public function conversation(WhatsappConversation $conversation, Request $request)
    {
        $activeConfig = $this->activeWhatsappConfig();
        if ($activeConfig && filled($activeConfig->phone_number_id)
            && (string) $conversation->whatsapp_phone_id !== (string) $activeConfig->phone_number_id) {
            abort(404);
        }

        $this->inboxService->useConfig($activeConfig);

        $conversation->load(['lead', 'assignedUser', 'followups.assignedUser']);
        $conversation->markAsRead();

        $highlightMessageId = (int) $request->query('msg', 0);
        $chatMessages = $this->messagesForConversationView($conversation, $highlightMessageId ?: null);

        $lastChatMessageId = (int) ($conversation->messages()
            ->where('message_type', '!=', 'reaction')
            ->max('id') ?? 0);

        $templates = WhatsappMessageTemplate::where('is_active', true)
            ->whereRaw("UPPER(TRIM(status)) = 'APPROVED'")
            ->orderBy('name')
            ->get();

        $users = User::orderBy('name')->get();
        $stages = Stage::orderBy('name')->get();
        $sources = LeadSource::orderBy('name')->get();
        $whatsappAutoAiEnabled = Setting::isEnabled('whatsapp_auto_ai_enabled', true);

        return view('whatsapp.conversation', compact(
            'conversation',
            'chatMessages',
            'lastChatMessageId',
            'highlightMessageId',
            'templates',
            'users',
            'stages',
            'sources',
            'whatsappAutoAiEnabled'
        ));
    }

    private function prepareInboxForConversation(WhatsappConversation $conversation): void
    {
        $activeConfig = $this->activeWhatsappConfig();
        if ($activeConfig && filled($activeConfig->phone_number_id)
            && (string) $conversation->whatsapp_phone_id !== (string) $activeConfig->phone_number_id) {
            abort(404);
        }

        $this->inboxService->useConfig($activeConfig);
    }

    /**
     * Send a message in a conversation
     */
    public function sendMessage(Request $request, WhatsappConversation $conversation)
    {
        $this->prepareInboxForConversation($conversation);

        $request->validate([
            'message' => 'required_without:template_name|nullable|string|max:4096',
            'template_name' => 'required_without:message|nullable|string',
            'variables' => 'nullable|array',
            'reply_to_message_id' => 'nullable|integer|exists:whatsapp_messages,id',
        ]);

        $replyTo = null;
        if ($request->filled('reply_to_message_id')) {
            $replyTo = WhatsappMessage::query()
                ->where('id', $request->integer('reply_to_message_id'))
                ->where('conversation_id', $conversation->id)
                ->first();
        }

        if ($request->template_name) {
            $message = $this->inboxService->sendTemplateMessage(
                $conversation,
                $request->template_name,
                $request->variables ?? [],
                Auth::id()
            );
        } else {
            $sendError = null;
            $message = $this->inboxService->sendTextMessage(
                $conversation,
                $request->message,
                Auth::id(),
                $sendError,
                $replyTo
            );
        }

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => $sendError ?? 'Failed to send message. Check WhatsApp settings (Phone Number ID must match Meta).',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message),
        ]);
    }

    /**
     * Upload and send an image or document in a conversation.
     */
    public function sendMedia(Request $request, WhatsappConversation $conversation)
    {
        $this->prepareInboxForConversation($conversation);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:16384',
                function (string $attribute, $file, \Closure $fail): void {
                    $allowedExtensions = [
                        'jpg', 'jpeg', 'png', 'webp', 'gif',
                        'mp4', '3gp', 'mov', 'webm',
                        'mp3', 'm4a', 'aac', 'amr', 'ogg', 'opus',
                        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
                    ];
                    $mimeType = strtolower((string) ($file->getMimeType() ?: ''));
                    $extension = strtolower((string) $file->getClientOriginalExtension());

                    $isVideoMime = str_starts_with($mimeType, 'video/');
                    $isImageMime = str_starts_with($mimeType, 'image/');
                    $isAudioMime = str_starts_with($mimeType, 'audio/');
                    $isAllowedExt = in_array($extension, $allowedExtensions, true);

                    // WhatsApp downloads often arrive as application/octet-stream with .mp4 extension.
                    if (! $isVideoMime && ! $isImageMime && ! $isAudioMime && ! $isAllowedExt) {
                        $fail('The attachment must be an image, video (.mp4/.3gp), audio, or document file.');
                    }
                },
            ],
            'caption' => 'nullable|string|max:1024',
            'voice_note' => 'nullable|boolean',
        ]);

        $sendError = null;
        $message = $this->inboxService->sendMediaMessage(
            $conversation,
            $validated['file'],
            $validated['caption'] ?? null,
            Auth::id(),
            $sendError,
            (bool) ($validated['voice_note'] ?? false)
        );

        if (! $message) {
            return response()->json([
                'success' => false,
                'message' => $sendError ?? 'Failed to send attachment.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message),
        ]);
    }

    /**
     * Send a WhatsApp location pin in a conversation.
     */
    public function sendLocation(Request $request, WhatsappConversation $conversation)
    {
        $this->prepareInboxForConversation($conversation);

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1024',
        ]);

        $sendError = null;
        $message = $this->inboxService->sendLocationMessage(
            $conversation,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated['name'] ?? null,
            $validated['address'] ?? null,
            Auth::id(),
            $sendError
        );

        if (! $message) {
            return response()->json([
                'success' => false,
                'message' => $sendError ?? 'Failed to send location.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message),
        ]);
    }

    /**
     * Display locally retained outbound media without relying on the server's public web root.
     */
    public function media(string $filename)
    {
        abort_unless(preg_match('/^[a-f0-9-]+\.[a-z0-9]{1,12}$/i', $filename), 404);

        $path = public_path('uploads/whatsapp/' . $filename);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Resolve inbound Meta media (legacy media_id rows) and stream/cache locally.
     */
    public function metaMedia(WhatsappMessage $message)
    {
        $url = $this->inboxService->resolveIncomingMedia($message);
        abort_unless(filled($url), 404);

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (preg_match('#/whatsapp/inbox/media/([a-f0-9-]+\.[a-z0-9]+)$#i', $path, $matches)) {
            return $this->media($matches[1]);
        }

        return redirect($url);
    }

    /**
     * Get messages for a conversation (AJAX polling)
     */
    public function getMessages(WhatsappConversation $conversation, Request $request)
    {
        if ($request->filled('around_id')) {
            $anchorId = (int) $request->get('around_id');
            $messages = $this->messagesAroundId($conversation, $anchorId);

            return response()->json([
                'messages' => $messages->map(fn ($m) => $this->formatMessage($m)),
                'reaction_updates' => [],
                'revoked_updates' => [],
                'unread_count' => 0,
                'server_time' => now()->toIso8601String(),
            ]);
        }

        $query = $conversation->messages()
            ->where('message_type', '!=', 'reaction')
            ->with([]);

        if ($request->after_id) {
            $query->where('id', '>', $request->after_id);
        }

        $messages = $query->orderBy('created_at', 'asc')->limit(80)->get();

        // Only write unread state when needed — polling used to UPDATE every few seconds.
        $hasNewIncoming = $messages->contains(fn ($m) => $m->direction === 'incoming');
        if ($hasNewIncoming || (int) $conversation->unread_count > 0) {
            $conversation->markAsRead();
        }

        $reactionUpdates = collect();
        $revokedUpdates = collect();
        if ($request->filled('since')) {
            try {
                $since = \Carbon\Carbon::parse($request->get('since'));
                $reactionUpdates = $conversation->messages()
                    ->where('message_type', '!=', 'reaction')
                    ->where('updated_at', '>=', $since)
                    ->when($request->after_id, fn ($q) => $q->where('id', '<=', (int) $request->after_id))
                    ->orderBy('id')
                    ->get()
                    ->filter(fn ($m) => ! empty($m->metadata['reactions']))
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'reactions' => $m->reactionEmojis(),
                    ])
                    ->values();

                $revokedUpdates = $conversation->messages()
                    ->where('message_type', 'revoked')
                    ->where('updated_at', '>=', $since)
                    ->when($request->after_id, fn ($q) => $q->where('id', '<=', (int) $request->after_id))
                    ->orderBy('id')
                    ->get(['id', 'message', 'message_type'])
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'revoked' => true,
                        'message' => $m->message,
                    ])
                    ->values();
            } catch (\Throwable $e) {
                $reactionUpdates = collect();
                $revokedUpdates = collect();
            }
        }

        return response()->json([
            'messages' => $messages->map(fn($m) => $this->formatMessage($m)),
            'reaction_updates' => $reactionUpdates,
            'revoked_updates' => $revokedUpdates,
            'unread_count' => 0,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Delete a message from the CRM inbox (WhatsApp-style delete).
     */
    public function deleteMessage(Request $request, WhatsappConversation $conversation, WhatsappMessage $message)
    {
        $this->prepareInboxForConversation($conversation);

        if ($message->conversation_id !== $conversation->id) {
            abort(404);
        }

        if (! $this->inboxService->deleteMessage($message, Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Could not delete this message.',
            ], 422);
        }

        if ($conversation->pinnedMessageId() === $message->id) {
            $conversation->unpinMessage();
        }

        return response()->json([
            'success' => true,
            'message_id' => $message->id,
            'pinned_message' => null,
        ]);
    }

    /**
     * Pin a message in this conversation (CRM-only, not synced to WhatsApp).
     */
    public function pinMessage(Request $request, WhatsappConversation $conversation, WhatsappMessage $message)
    {
        if ($message->conversation_id !== $conversation->id || $message->message_type === 'reaction') {
            abort(404);
        }

        if ($message->isRevoked() || $message->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'This message cannot be pinned.',
            ], 422);
        }

        $conversation->pinMessage($message, Auth::id());
        $conversation->refresh();

        return response()->json([
            'success' => true,
            'pinned_message' => $conversation->pinnedMessagePreview(),
        ]);
    }

    /**
     * Remove the pinned message from this conversation.
     */
    public function unpinMessage(Request $request, WhatsappConversation $conversation)
    {
        $conversation->unpinMessage();

        return response()->json([
            'success' => true,
            'pinned_message' => null,
        ]);
    }

    /**
     * React to a message from the CRM (WhatsApp sync for incoming messages).
     */
    public function reactMessage(Request $request, WhatsappConversation $conversation, WhatsappMessage $message)
    {
        $this->prepareInboxForConversation($conversation);

        if ($message->conversation_id !== $conversation->id) {
            abort(404);
        }

        $request->validate([
            'emoji' => 'nullable|string|max:32',
        ]);

        $sendError = null;
        $reactions = $this->inboxService->sendReaction(
            $conversation,
            $message,
            $request->input('emoji'),
            Auth::id(),
            $sendError
        );

        if ($reactions === null) {
            return response()->json([
                'success' => false,
                'message' => $sendError ?? 'Could not react to this message.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message_id' => $message->id,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Assign conversation to a user
     */
    public function assign(Request $request, WhatsappConversation $conversation)
    {
        $request->validate(['user_id' => 'nullable|exists:users,id']);

        $conversation->update(['assigned_user_id' => $request->user_id]);

        return response()->json(['success' => true]);
    }

    /**
     * Update conversation status (open / closed / archived)
     */
    public function updateStatus(Request $request, WhatsappConversation $conversation)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', WhatsappConversation::STATUSES),
        ]);

        $conversation->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'status' => $conversation->status,
            'status_label' => $conversation->statusLabel(),
            'tags' => $conversation->activeTags(),
        ]);
    }

    /**
     * Toggle a conversation tag (important / pending_payment / paid).
     */
    public function toggleTag(Request $request, WhatsappConversation $conversation)
    {
        $request->validate([
            'tag' => 'required|in:' . implode(',', WhatsappConversation::TAGS),
        ]);

        $enabled = $conversation->toggleTag($request->string('tag')->toString());
        $conversation->refresh();

        return response()->json([
            'success' => true,
            'tag' => $request->string('tag')->toString(),
            'enabled' => $enabled,
            'tag_label' => $conversation->tagLabel($request->string('tag')->toString()),
            'tags' => $conversation->activeTags(),
            'status' => $conversation->status,
            'status_label' => $conversation->statusLabel(),
        ]);
    }

    /**
     * Turn per-chat automatic replies on or off.
     */
    public function setAiReplyPreference(Request $request, WhatsappConversation $conversation)
    {
        $this->prepareInboxForConversation($conversation);

        $request->validate(['ai_reply_enabled' => 'required|boolean']);

        $enabled = $request->boolean('ai_reply_enabled');
        if ($enabled && ! Setting::isEnabled('whatsapp_auto_ai_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Auto AI is disabled for the full WhatsApp inbox.',
            ], 422);
        }

        if (! $enabled) {
            $this->inboxService->clearPendingAiDeferral($conversation);
        }

        $meta = $conversation->metadata ?? [];
        if ($enabled) {
            unset($meta['ai_reply_disabled']);
        } else {
            $meta['ai_reply_disabled'] = true;
        }
        $conversation->update(['metadata' => $meta]);

        return response()->json([
            'success' => true,
            'ai_reply_enabled' => $enabled,
        ]);
    }

    /**
     * Create a lead from a conversation
     */
    public function createLead(Request $request, WhatsappConversation $conversation)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'lead_source_id' => 'nullable|exists:lead_sources,id',
            'lead_stage_id' => 'nullable|exists:stages,id',
        ]);

        if ($conversation->lead_id) {
            return response()->json(['success' => false, 'message' => 'Conversation already linked to a lead.'], 422);
        }

        $lead = Lead::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $conversation->phone_number,
            'whatsapp' => $conversation->phone_number,
            'lead_source_id' => $request->lead_source_id,
            'lead_stage_id' => $request->lead_stage_id,
            'status' => 'new',
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]);

        $conversation->update(['lead_id' => $lead->id, 'contact_name' => $request->name]);

        return response()->json([
            'success' => true,
            'lead_id' => $lead->id,
            'lead_url' => route('leads.show', $lead),
        ]);
    }

    /**
     * Get conversation list for sidebar (AJAX)
     */
    public function conversationList(Request $request)
    {
        $filter = $request->get('status', WhatsappConversation::FILTER_ALL);

        $conversations = WhatsappConversation::with(['lead', 'latestMessage'])
            ->inboxFilter($filter)
            ->when($request->search, function ($q) use ($request) {
                $term = $this->likeTerm($request->search);
                $q->where(function ($sub) use ($term) {
                    $sub->where('contact_name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term)
                        ->orWhereHas('messages', function ($mq) use ($term) {
                            $mq->whereNotIn('message_type', ['reaction', 'revoked'])
                                ->where('message', 'like', $term);
                        });
                });
            })
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        return response()->json([
            'conversations' => $conversations->map(fn($c) => $this->formatConversation($c)),
        ]);
    }

    /**
     * Search chats and message text across the inbox (WhatsApp-style).
     */
    public function searchInbox(Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['chats' => [], 'messages' => []]);
        }

        $filter = $request->get('status', WhatsappConversation::FILTER_ALL);
        $term = $this->likeTerm($query);

        $chats = WhatsappConversation::with(['lead', 'latestMessage'])
            ->inboxFilter($filter)
            ->where(function ($sub) use ($term) {
                $sub->where('contact_name', 'like', $term)
                    ->orWhere('phone_number', 'like', $term);
            })
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get();

        $messages = WhatsappMessage::query()
            ->with(['conversation'])
            ->whereNotIn('message_type', ['reaction', 'revoked'])
            ->where('message', 'like', $term)
            ->whereHas('conversation', fn ($q) => $q->inboxFilter($filter))
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return response()->json([
            'chats' => $chats->map(fn ($c) => $this->formatConversation($c)),
            'messages' => $messages->map(fn ($m) => $this->formatMessageSearchHit($m, $query)),
        ]);
    }

    /**
     * Search messages inside one conversation.
     */
    public function searchConversationMessages(WhatsappConversation $conversation, Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        if (mb_strlen($query) < 1) {
            return response()->json(['results' => []]);
        }

        $term = $this->likeTerm($query);
        $messages = $conversation->messages()
            ->whereNotIn('message_type', ['reaction', 'revoked'])
            ->where('message', 'like', $term)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'results' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'message_type' => $m->message_type,
                'snippet' => $this->messageSearchSnippet((string) $m->message, $query),
                'time' => $m->created_at?->copy()->timezone(
                    config('services.whatsapp.display_timezone', 'Asia/Kolkata')
                )?->format('d M Y, h:i A'),
            ])->values(),
        ]);
    }

    private function messagesForConversationView(WhatsappConversation $conversation, ?int $aroundMessageId = null)
    {
        // Always load the latest messages; jump-to-message from search is handled client-side.
        return $conversation->messages()
            ->where('message_type', '!=', 'reaction')
            ->orderByDesc('created_at')
            ->limit(150)
            ->get()
            ->sortBy('created_at')
            ->values();
    }

    private function messagesAroundId(WhatsappConversation $conversation, int $messageId)
    {
        $anchor = $conversation->messages()
            ->where('id', $messageId)
            ->first();

        if (! $anchor || in_array($anchor->message_type, ['reaction', 'revoked'], true)) {
            return collect();
        }

        $before = $conversation->messages()
            ->where('message_type', '!=', 'reaction')
            ->where('created_at', '<=', $anchor->created_at)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $after = $conversation->messages()
            ->where('message_type', '!=', 'reaction')
            ->where('created_at', '>', $anchor->created_at)
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return $before->merge($after)->sortBy('created_at')->unique('id')->values();
    }

    private function formatMessageSearchHit(WhatsappMessage $message, string $query): array
    {
        $conversation = $message->conversation;
        $displayTime = $message->created_at?->copy()->timezone(
            config('services.whatsapp.display_timezone', 'Asia/Kolkata')
        );

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'contact_name' => $conversation?->contact_name ?? $conversation?->phone_number,
            'phone_number' => $conversation?->phone_number,
            'message_type' => $message->message_type,
            'snippet' => $this->messageSearchSnippet((string) $message->message, $query),
            'time' => $displayTime?->format('d M Y, h:i A'),
            'url' => $conversation
                ? route('whatsapp.conversation', ['conversation' => $conversation, 'msg' => $message->id])
                : null,
        ];
    }

    private function messageSearchSnippet(string $text, string $query, int $radius = 42): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?: '');
        if ($text === '') {
            return 'Message';
        }

        $lowerText = mb_strtolower($text);
        $lowerQuery = mb_strtolower($query);
        $pos = mb_strpos($lowerText, $lowerQuery);
        if ($pos === false) {
            return \Illuminate\Support\Str::limit($text, $radius * 2);
        }

        $start = max(0, $pos - $radius);
        $length = mb_strlen($query) + ($radius * 2);
        $snippet = mb_substr($text, $start, $length);
        if ($start > 0) {
            $snippet = '…'.$snippet;
        }
        if ($start + $length < mb_strlen($text)) {
            $snippet .= '…';
        }

        return $snippet;
    }

    private function likeTerm(string $value): string
    {
        $escaped = addcslashes(trim($value), '%_\\');

        return '%'.$escaped.'%';
    }

    private function formatMessage(WhatsappMessage $message): array
    {
        $displayTime = $message->created_at?->copy()->timezone(
            config('services.whatsapp.display_timezone', 'Asia/Kolkata')
        );

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'message' => $message->message,
            'message_type' => $message->message_type,
            'media_url' => $message->displayMediaUrl(),
            'media_type' => $message->media_type,
            'status' => $message->status,
            'reactions' => $message->reactionEmojis(),
            'location' => $message->message_type === 'location' ? $message->locationData() : null,
            'contacts' => $message->message_type === 'contacts' ? $message->contactsData() : [],
            'revoked' => $message->isRevoked(),
            'reply_to' => $message->replyContext(),
            'meta_message_id' => $message->meta_message_id,
            'created_at' => $displayTime?->format('Y-m-d H:i:s'),
            'time' => $displayTime?->format('h:i A'),
            'date' => $displayTime?->format('d M Y'),
        ];
    }

    private function formatConversation(WhatsappConversation $conversation): array
    {
        $latest = $conversation->relationLoaded('latestMessage')
            ? $conversation->latestMessage
            : $conversation->latestMessage()->where('message_type', '!=', 'reaction')->first();

        $preview = null;
        if ($latest) {
            $preview = $latest->message;
            $reactions = $latest->reactionEmojis();
            if ($reactions) {
                $preview = trim(($preview ?: '') . ' ' . implode('', $reactions));
            }
        }

        return [
            'id' => $conversation->id,
            'phone_number' => $conversation->phone_number,
            'contact_name' => $conversation->contact_name ?? $conversation->phone_number,
            'status' => $conversation->status,
            'status_label' => $conversation->statusLabel(),
            'tags' => $conversation->activeTags(),
            'unread_count' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at?->diffForHumans(),
            'last_message' => $preview,
            'lead_id' => $conversation->lead_id,
            'lead_name' => $conversation->lead?->name,
            'url' => route('whatsapp.conversation', $conversation),
        ];
    }
}
