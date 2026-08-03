<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFollowup;
use App\Models\WhatsappMessageTemplate;
use App\Services\WhatsAppInboxService;
use Illuminate\Http\Request;

class WhatsappFollowupController extends Controller
{
    public function __construct(private WhatsAppInboxService $inboxService) {}

    public function index(Request $request)
    {
        $followups = WhatsappFollowup::with(['lead', 'assignedUser', 'conversation'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_user_id', $request->assigned_to))
            ->when($request->due_filter === 'today', fn($q) => $q->dueToday())
            ->when($request->due_filter === 'overdue', fn($q) => $q->overdue())
            ->when($request->due_filter === 'week', fn($q) => $q->dueThisWeek())
            ->orderBy('due_date')
            ->paginate(20);

        $users = User::orderBy('name')->get();
        $stats = [
            'pending' => WhatsappFollowup::pending()->count(),
            'overdue' => WhatsappFollowup::overdue()->count(),
            'today' => WhatsappFollowup::dueToday()->count(),
            'completed' => WhatsappFollowup::where('status', 'completed')->count(),
        ];

        return view('whatsapp.followups.index', compact('followups', 'users', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'conversation_id' => 'nullable|exists:whatsapp_conversations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'template_id' => 'nullable|exists:whatsapp_message_templates,id',
            'message' => 'nullable|string',
        ]);

        $data['status'] = 'pending';
        $data['created_by'] = auth()->id();

        $followup = WhatsappFollowup::create($data);
        $followup->load(['lead', 'assignedUser']);

        return response()->json([
            'success' => true,
            'followup' => $followup,
        ]);
    }

    public function update(Request $request, WhatsappFollowup $followup)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        $data['updated_by'] = auth()->id();
        $followup->update($data);

        return response()->json(['success' => true, 'followup' => $followup]);
    }

    public function complete(WhatsappFollowup $followup)
    {
        $followup->markAsCompleted();

        // Send WhatsApp message if configured
        if ($followup->conversation_id && ($followup->template_id || $followup->message)) {
            $conversation = $followup->conversation;
            if ($conversation) {
                if ($followup->template_id && $followup->template) {
                    $this->inboxService->sendTemplateMessage($conversation, $followup->template->name);
                } elseif ($followup->message) {
                    $this->inboxService->sendTextMessage($conversation, $followup->message);
                }
                $followup->markAsSent();
            }
        }

        return response()->json(['success' => true]);
    }

    public function destroy(WhatsappFollowup $followup)
    {
        $followup->delete();
        return response()->json(['success' => true]);
    }
}
