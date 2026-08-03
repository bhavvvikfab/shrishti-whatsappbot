<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Services\WhatsAppAutomationService;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WhatsAppApiController extends Controller
{
    private WhatsAppService $whatsappService;
    private WhatsAppAutomationService $automationService;

    public function __construct(
        WhatsAppService $whatsappService,
        WhatsAppAutomationService $automationService
    ) {
        $this->whatsappService = $whatsappService;
        $this->automationService = $automationService;
    }

    public function conversations(Request $request): JsonResponse
    {
        $query = WhatsappConversation::with(['lead', 'assignedUser', 'latestMessage']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('assigned_user_id')) {
            $query->where('assigned_user_id', $request->assigned_user_id);
        }

        if ($request->has('unread')) {
            $query->unread();
        }

        $conversations = $query->latest('last_message_at')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    public function conversation(WhatsappConversation $conversation): JsonResponse
    {
        $conversation->load(['lead', 'assignedUser', 'messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'message' => 'required|string',
            'conversation_id' => 'nullable|exists:whatsapp_conversations,id',
        ]);

        $result = $this->whatsappService->sendTextMessage(
            $validated['phone_number'],
            $validated['message'],
            $validated['conversation_id'] ?? null
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        $conversation = WhatsappConversation::firstOrCreate(
            ['phone_number' => $validated['phone_number']],
            ['status' => 'open', 'last_message_at' => now()]
        );

        WhatsappMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outgoing',
            'message' => $validated['message'],
            'message_type' => 'text',
            'meta_message_id' => $result['message_id'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Message sent successfully',
        ]);
    }

    public function sendMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'media_url' => 'required|url',
            'media_type' => 'required|in:image,document,video',
            'caption' => 'nullable|string',
        ]);

        $result = $this->whatsappService->sendMediaMessage(
            $validated['phone_number'],
            $validated['media_url'],
            $validated['media_type'],
            $validated['caption'] ?? null
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Media sent successfully',
        ]);
    }

    public function markAsRead(WhatsappConversation $conversation): JsonResponse
    {
        $conversation->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read',
        ]);
    }

    public function assign(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'assigned_user_id' => 'required|exists:users,id',
        ]);

        $conversation->update(['assigned_user_id' => $validated['assigned_user_id']]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
            'message' => 'Conversation assigned successfully',
        ]);
    }

    public function updateStatus(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,closed,archived',
        ]);

        $conversation->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
            'message' => 'Conversation status updated',
        ]);
    }

    public function messages(WhatsappConversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }
}
