<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppAutomationService;
use App\Services\WhatsAppInboxService;
use App\Services\WhatsAppService;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;

class WhatsAppChatController extends Controller
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

    public function index()
    {
        $conversations = WhatsappConversation::with(['lead', 'assignedUser', 'latestMessage'])
            ->latest('last_message_at')
            ->paginate(20);

        return view('crm.whatsapp.inbox', compact('conversations'));
    }

    public function show(WhatsappConversation $conversation)
    {
        $conversation->load(['lead', 'assignedUser', 'messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);

        $conversation->markAsRead();

        return view('crm.whatsapp.chat', compact('conversation'));
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:whatsapp_conversations,id',
            'message' => 'required|string',
        ]);

        $conversation = WhatsappConversation::find($validated['conversation_id']);

        $result = $this->whatsappService->sendTextMessage(
            $conversation->phone_number,
            $validated['message'],
            $conversation->id
        );

        if ($result['success']) {
            app(WhatsAppInboxService::class)->clearPendingAiDeferral($conversation);

            WhatsappMessage::create([
                'conversation_id' => $conversation->id,
                'lead_id' => $conversation->lead_id,
                'direction' => 'outgoing',
                'message' => $validated['message'],
                'message_type' => 'text',
                'meta_message_id' => $result['message_id'] ?? null,
                'status' => 'sent',
                'sent_at' => now(),
                'metadata' => array_filter(['sent_by' => auth()->id()]),
            ]);

            $conversation->touch('last_message_at');

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send message',
        ], 400);
    }

    public function assign(Request $request, WhatsappConversation $conversation)
    {
        $validated = $request->validate([
            'assigned_user_id' => 'required|exists:users,id',
        ]);

        $conversation->update(['assigned_user_id' => $validated['assigned_user_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation assigned successfully',
        ]);
    }

    public function updateStatus(Request $request, WhatsappConversation $conversation)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,closed,archived',
        ]);

        $conversation->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
        ]);
    }
}
