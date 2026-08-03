<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiBaseController;
use App\Models\FollowUp;
use App\Models\FollowUpStatusHistory;
use App\Models\WhatsappFollowup;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FollowUpController extends ApiBaseController
{
    private const FOLLOW_UP_TIMEZONE = 'Asia/Kolkata';

    private const FOLLOW_UP_RULES = [
        'lead_id' => ['required', 'integer', 'exists:leads,id'],
        'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        'purpose' => ['required', 'string', 'max:255'],
        'comment' => ['nullable', 'string', 'max:2000'],
        'status_comment' => ['nullable', 'string', 'max:2000'],
        'priority' => ['required', 'in:low,medium,high'],
        'status' => ['required', 'in:pending,resheduled,completed,cancelled'],
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $normalQuery = $this->scopeOwnedRecords(
            FollowUp::with(['lead', 'assignedUser', 'creator'])
        );
        $whatsappQuery = WhatsappFollowup::with(['lead', 'assignedUser', 'conversation']);

        if ($request->has('lead_id') && $request->lead_id) {
            $normalQuery->where('lead_id', $request->lead_id);
            $whatsappQuery->where('lead_id', $request->lead_id);
        }

        if ($request->has('status') && $request->status) {
            $normalQuery->where('status', $request->status);
            $whatsappQuery->where('status', $request->status);
        }

        // ✅ ADVANCED SEARCH
        if ($request->filled('search')) {
            $search = $request->search;

            $normalQuery->where(function ($q) use ($search) {
                $q->where('purpose', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhereHas('lead', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('assignedUser', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });

            $whatsappQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('lead', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('assignedUser', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->has('follow_up_at') && $request->follow_up_at) {
            $normalQuery->whereDate('follow_up_at', $request->follow_up_at);
            $whatsappQuery->whereDate('due_date', $request->follow_up_at);
        }

        $page = max((int) $request->get('page', 1), 1);
        $perPage = 10;

        $items = $normalQuery->get()
            ->map(fn (FollowUp $followUp) => $this->serializeFollowUpListItem($followUp))
            ->concat(
                $whatsappQuery->get()->map(fn (WhatsappFollowup $followUp) => $this->serializeWhatsappFollowUpListItem($followUp))
            )
            ->sortByDesc('follow_up_at_sort')
            ->values();

        $followUps = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Follow-ups retrieved successfully',
            'data' => $followUps,
        ], 200);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $this->authorize('create', FollowUp::class);

        $validator = $this->makeValidator($request, true);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['follow_up_at'] = $this->normalizeFollowUpAt($data['follow_up_at']);
        $this->ensureVisibleLead((int) $data['lead_id']);
        $data['assigned_user_id'] = $data['assigned_user_id'] ?? $user->id;
        $this->ensureAssignableUser((int) $data['assigned_user_id'], $user);
        if (FollowUp::supportsOwnedByUserColumn()) {
            $data['user_id'] = $this->resolveOwnedUserId($data['assigned_user_id'] ?? null, $user->id);
        }
        $data['created_by'] = $user->id;
        $data['updated_by'] = auth()->id();

        $followUp = FollowUp::create($data);
        $historyEntry = $this->recordStatusHistory($followUp, $data['status'] ?? null, $data['status_comment'] ?? null);
        app(\App\Services\UserLogService::class)->created($followUp, 'Created a Follow Up ' . ($followUp->purpose ?: ('ID ' . $followUp->id)));

        try {
            app(\App\Services\UpcomingReminderService::class)->sendDueFollowUpReminders(
                $followUp->fresh(['lead', 'assignedUser', 'customer']),
                true
            );
        } catch (\Throwable $e) {
            Log::error('Follow-up reminder dispatch after create failed', [
                'follow_up_id' => $followUp->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeFollowUp($followUp->fresh(['lead', 'assignedUser', 'creator'])),
            'message' => 'Follow-up created successfully',
            'history_entry' => $this->serializeHistoryEntry($historyEntry),
            'redirect' => route('followups.index'),
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $followUp = FollowUp::with(['lead', 'customer', 'assignedUser', 'creator'])->find($id);

        if (!$followUp) {
            return $this->error('Follow-up not found', 404);
        }

        $this->authorize('view', $followUp);

        return response()->json([
            'success' => true,
            'data' => $this->serializeFollowUp($followUp),
            'message' => 'Follow-up retrieved successfully'
        ]);
    }


    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $followUp = FollowUp::find($id);

        if (!$followUp) {
            return $this->error('Follow-up not found', 404);
        }

        $this->authorize('update', $followUp);

        $originalStatus = $followUp->status;
        $validator = $this->makeValidator($request, false);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['follow_up_at'] = $this->normalizeFollowUpAt($data['follow_up_at']);
        $this->ensureVisibleLead((int) $data['lead_id'], $followUp);
        $data['assigned_user_id'] = $data['assigned_user_id'] ?? $followUp->assigned_user_id ?? $user->id;
        $this->ensureAssignableUser((int) $data['assigned_user_id'], $user);
        if (FollowUp::supportsOwnedByUserColumn()) {
            $data['user_id'] = $this->resolveOwnedUserId(
                $data['assigned_user_id'] ?? $followUp->assigned_user_id,
                $followUp->created_by ?? $followUp->user_id ?? $user->id
            );
        }
        $data['updated_by'] = $user->id;
        $followUp->update($data);
        $historyEntry = null;

        if (
            (array_key_exists('status', $data) && $data['status'] !== $originalStatus)
            || filled($data['status_comment'] ?? null)
        ) {
            $historyEntry = $this->recordStatusHistory($followUp, $data['status'] ?? $followUp->status, $data['status_comment'] ?? null);
        }
        app(\App\Services\UserLogService::class)->updated($followUp, 'Updated a Follow Up ' . ($followUp->purpose ?: ('ID ' . $followUp->id)));

        try {
            app(\App\Services\UpcomingReminderService::class)->sendDueFollowUpReminders(
                $followUp->fresh(['lead', 'assignedUser', 'customer']),
                true
            );
        } catch (\Throwable $e) {
            Log::error('Follow-up reminder dispatch after update failed', [
                'follow_up_id' => $followUp->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeFollowUp($followUp->fresh(['lead', 'assignedUser', 'creator'])),
            'message' => 'Follow-up updated successfully',
            'history_entry' => $this->serializeHistoryEntry($historyEntry),
            'redirect' => route('followups.index')
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $followUp = FollowUp::find($id);

        if (!$followUp) {
            return $this->error('Follow-up not found', 404);
        }

        $this->authorize('delete', $followUp);

        $followUp->update(['deleted_by' => $user->id]);
        app(\App\Services\UserLogService::class)->deleted($followUp, 'Deleted a Follow Up ' . ($followUp->purpose ?: ('ID ' . $followUp->id)));
        $followUp->delete();

        return response()->json([
            'success' => true,
            'message' => 'Follow-up deleted successfully'
        ]);
    }

    public function apiByLead($id)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $followUps = $this->scopeOwnedRecords(
            FollowUp::with(['lead', 'assignedUser', 'creator'])
        )
            ->where('lead_id', $id)
            ->latest('follow_up_at')
            ->paginate(10);
        $followUps->getCollection()->transform(fn (FollowUp $followUp) => $this->serializeFollowUp($followUp));

        return response()->json([
            'success' => true,
            'data' => $followUps,
            'message' => 'Follow-ups retrieved successfully'
        ]);
    }

    private function recordStatusHistory(FollowUp $followUp, ?string $status, ?string $comment): ?FollowUpStatusHistory
    {
        if (!$status && !filled($comment)) {
            return null;
        }

        try {
            return FollowUpStatusHistory::create([
                'follow_up_id' => $followUp->id,
                'status' => $status ?? $followUp->status,
                'comment' => filled($comment) ? $comment : null,
                'updated_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Follow-up status history save skipped.', [
                'follow_up_id' => $followUp->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function serializeHistoryEntry(?FollowUpStatusHistory $history): ?array
    {
        if (!$history) {
            return null;
        }

        $history->loadMissing('updater');

        return [
            'status' => $history->status,
            'status_label' => \Illuminate\Support\Str::of($history->status)->replace('_', ' ')->title()->toString(),
            'comment' => $history->comment ?: '-',
            'updated_by' => $history->updater?->name ?? 'System',
            'created_at' => $history->created_at?->timezone('Asia/Kolkata')->format('d M Y h:i A') ?? '-',
        ];
    }

    private function makeValidator(Request $request, bool $enforceFutureDate): \Illuminate\Contracts\Validation\Validator
    {
        $rules = self::FOLLOW_UP_RULES + [
            'follow_up_at' => $enforceFutureDate
                ? ['required', 'date', 'after_or_equal:today']
                : ['required', 'date'],
        ];

        return Validator::make($request->all(), $rules, $this->validationMessages());
    }

    private function validationMessages(): array
    {
        return [
            'lead_id.required' => 'Lead is required!',
            'lead_id.exists' => 'Please select a valid lead.',
            'assigned_user_id.exists' => 'Please select a valid staff member.',
            'purpose.required' => 'Purpose is required!',
            'priority.required' => 'Priority is required!',
            'priority.in' => 'Please select a valid priority.',
            'status.required' => 'Status is required!',
            'status.in' => 'Please select a valid status.',
            'follow_up_at.required' => 'Date/Time is required!',
            'follow_up_at.date' => 'Please enter a valid date/time.',
            'follow_up_at.after_or_equal' => 'Date/Time must be today or later.',
        ];
    }

    private function ensureVisibleLead(int $leadId, ?FollowUp $followUp = null): void
    {
        if ($followUp && (int) $followUp->lead_id === $leadId) {
            return;
        }

        $lead = \App\Models\Lead::findOrFail($leadId);
        $this->authorize('view', $lead);
    }

    private function ensureAssignableUser(int $assignedUserId, $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        abort_unless($assignedUserId === (int) $user->id, 403, 'You can only assign records to yourself.');
    }

    private function normalizeFollowUpAt(string $value): string
    {
        return Carbon::parse($value, self::FOLLOW_UP_TIMEZONE)
            ->timezone(config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function serializeFollowUp(FollowUp $followUp): FollowUp
    {
        $localFollowUpAt = $followUp->follow_up_at?->copy()->timezone(self::FOLLOW_UP_TIMEZONE);

        $followUp->setAttribute(
            'follow_up_at_formatted',
            $localFollowUpAt?->format('Y-m-d\TH:i')
        );
        $followUp->setAttribute(
            'follow_up_at_display_date',
            $localFollowUpAt?->format('d M Y')
        );
        $followUp->setAttribute(
            'follow_up_at_display_time',
            $localFollowUpAt?->format('h:i A')
        );

        return $followUp;
    }

    private function serializeFollowUpListItem(FollowUp $followUp): array
    {
        $localFollowUpAt = $followUp->follow_up_at?->copy()->timezone(self::FOLLOW_UP_TIMEZONE);

        return [
            'id' => $followUp->id,
            'source_type' => 'crm',
            'source_label' => 'CRM',
            'purpose' => $followUp->purpose,
            'comment' => $followUp->comment,
            'priority' => $followUp->priority,
            'status' => $followUp->status,
            'follow_up_at_display_date' => $localFollowUpAt?->format('d M Y'),
            'follow_up_at_display_time' => $localFollowUpAt?->format('h:i A'),
            'follow_up_at_sort' => $followUp->follow_up_at?->timestamp ?? 0,
            'lead' => $followUp->lead ? [
                'id' => $followUp->lead->id,
                'name' => $followUp->lead->name,
            ] : null,
            'customer' => $followUp->customer ? [
                'id' => $followUp->customer->id,
                'name' => $followUp->customer->name,
            ] : null,
            'assigned_user' => $followUp->assignedUser ? [
                'id' => $followUp->assignedUser->id,
                'name' => $followUp->assignedUser->name,
            ] : null,
        ];
    }

    private function serializeWhatsappFollowUpListItem(WhatsappFollowup $followUp): array
    {
        $localFollowUpAt = $followUp->due_date?->copy()->timezone(self::FOLLOW_UP_TIMEZONE);

        return [
            'id' => $followUp->id,
            'source_type' => 'whatsapp',
            'source_label' => 'WhatsApp',
            'purpose' => $followUp->title,
            'comment' => $followUp->description,
            'priority' => null,
            'status' => $followUp->status,
            'follow_up_at_display_date' => $localFollowUpAt?->format('d M Y'),
            'follow_up_at_display_time' => $localFollowUpAt?->format('h:i A'),
            'follow_up_at_sort' => $followUp->due_date?->timestamp ?? 0,
            'lead' => $followUp->lead ? [
                'id' => $followUp->lead->id,
                'name' => $followUp->lead->name,
            ] : null,
            'customer' => null,
            'assigned_user' => $followUp->assignedUser ? [
                'id' => $followUp->assignedUser->id,
                'name' => $followUp->assignedUser->name,
            ] : null,
            'conversation_id' => $followUp->conversation_id,
            'conversation_url' => $followUp->conversation_id ? route('whatsapp.conversation', $followUp->conversation_id) : null,
            'whatsapp_followups_url' => route('whatsapp.followups.index'),
        ];
    }
}
