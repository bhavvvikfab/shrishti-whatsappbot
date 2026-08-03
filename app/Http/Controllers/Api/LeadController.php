<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiBaseController;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Task;
use App\Models\User;
use App\Services\UserLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LeadController extends ApiBaseController
{
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                Log::error('LeadController: No authenticated user');
                return $this->error('Unauthorized', 401);
            }

            Log::info('LeadController.index called', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'is_admin' => $user->isAdmin(),
                'bearer_token' => request()->bearerToken() ? substr(request()->bearerToken(), 0, 20) . '...' : 'NONE',
            ]);

            $search = $request->get('search');
            $relations = ['leadSource', 'stage', 'assignedUser'];

            if (Schema::hasTable('whatsapp_conversations')) {
                $relations[] = 'whatsappConversation';
            }

            $leads = $this->scopeOwnedRecords(
                Lead::query()->with($relations)
            )
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('whatsapp', 'like', "%{$search}%")
                            ->orWhere('source', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%")
                            ->orWhere('sic_code', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString();

            $leads->getCollection()->transform(function (Lead $lead) {
                $lead->setAttribute('whatsapp_chat_url', $lead->resolveWhatsappChatUrl());

                return $lead;
            });

            Log::info('LeadController.index result', [
                'total_leads' => $leads->total(),
                'per_page' => $leads->perPage(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leads retrieved successfully',
                'data' => $leads,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Lead index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load leads right now.',
                'data' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'prev_page_url' => null,
                    'next_page_url' => null,
                ],
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $this->authorize('create', Lead::class);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:leads,email'],
            'phone' => ['required', 'string', 'max:10', 'min:10', 'unique:leads,phone'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,gif,bmp,webp,avif,svg', 'max:2048'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'sic_code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:new,qualified,working,ready_to_close,won,lost'],
            'source' => ['required', 'string', 'max:255'],
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'lead_stage_id' => ['nullable', 'exists:stages,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status_comment' => ['nullable', 'string', 'max:2000'],
        ], $this->messages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $this->ensureAssignableUser($data['assigned_user_id'] ?? null, $user);
        if (Lead::supportsOwnedByUserColumn()) {
            $data['user_id'] = $this->resolveOwnedUserId($data['assigned_user_id'] ?? null, $user->id);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('leads', 'public');
        }

        $lead = Lead::create($data);

        $historyEntry = $this->recordStatusHistory($lead, $data['status'] ?? null, $data['status_comment'] ?? null);
        app(UserLogService::class)->created($lead);

        if ($request->has('custom_fields')) {
            $lead->saveCustomFields($request->get('custom_fields'));
        }

        $this->createAutoTaskForLead($lead);

        // WhatsApp notification — fire & forget, never breaks main flow
        try {
            $phone = $lead->whatsapp ?: $lead->phone;
            if ($phone) {
                app(\App\Services\WhatsAppService::class)->sendForModule(
                    'customer_created',
                    $phone,
                    [$lead->name],
                    $lead->id
                );
            }
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully.',
            'data' => $lead->fresh(['leadSource', 'stage', 'assignedUser']),
            'history_entry' => $this->serializeHistoryEntry($historyEntry),
            'redirect' => route('leads.index'),
        ], 201);
    }

    public function show(string $id)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $lead = Lead::with(['leadSource', 'stage', 'assignedUser'])->findOrFail($id);
        $this->authorize('view', $lead);

        return response()->json([
            'success' => true,
            'message' => 'Lead retrieved successfully',
            'data' => $lead,
        ]);
    }

    public function update(Request $request, string $id)
    { 
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $lead = Lead::findOrFail($id);
        $this->authorize('update', $lead);
        $originalStatus = $lead->status;
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:leads,email,' . $lead->id],
            'phone' => ['required', 'string', 'max:10', 'min:10', 'unique:leads,phone,' . $lead->id],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,gif,bmp,webp,avif,svg', 'max:2048'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'sic_code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:new,qualified,working,ready_to_close,won,lost'],
            'source' => ['required', 'string', 'max:255'],
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'lead_stage_id' => ['nullable', 'exists:stages,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status_comment' => ['nullable', 'string', 'max:2000'],
        ], $this->messages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $this->ensureAssignableUser($data['assigned_user_id'] ?? null, $user);
            if (Lead::supportsOwnedByUserColumn()) {
                $data['user_id'] = $this->resolveOwnedUserId(
                    $data['assigned_user_id'] ?? $lead->assigned_user_id,
                    $lead->created_by ?? $lead->user_id ?? $user->id
                );
            }

            if ($request->hasFile('image')) {
                if ($lead->image) {
                    Storage::disk('public')->delete($lead->image);
                }

                $data['image'] = $request->file('image')->store('leads', 'public');
            }

            $lead->update($data);

            if (
                (array_key_exists('status', $data) && $data['status'] !== $originalStatus)
                || filled($data['status_comment'] ?? null)
            ) {
                $historyEntry = $this->recordStatusHistory($lead, $data['status'] ?? $lead->status, $data['status_comment'] ?? null);
            }
            app(UserLogService::class)->updated($lead);

            if ($request->has('custom_fields')) {
                $lead->saveCustomFields($request->get('custom_fields'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Lead updated successfully.',
                'data' => $lead->fresh(['leadSource', 'stage', 'assignedUser']),
                'history_entry' => $this->serializeHistoryEntry($historyEntry ?? null),
                'redirect' => route('leads.index', $lead),
            ]);
        } catch (\Throwable $e) {
            Log::error('Lead update failed', [
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update lead right now.',
                'errors' => null,
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $lead = Lead::findOrFail($id);
        $this->authorize('delete', $lead);

        if ($lead->image) {
            Storage::disk('public')->delete($lead->image);
        }

        app(UserLogService::class)->deleted($lead);
        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully.',
        ]);
    }

    private function createAutoTaskForLead(Lead $lead): void
    {
        Task::create([
            'title' => 'Follow up lead',
            'description' => 'Auto task for new lead: ' . $lead->name,
            'related_type' => 'lead',
            'related_id' => $lead->id,
            'assigned_user_id' => $lead->assigned_user_id,
            'due_date' => Carbon::today()->addDays(1)->toDateString(),
            'status' => 'pending',
        ]);
    }

    private function ensureAssignableUser(?int $assignedUserId, $user): void
    {
        if (!$assignedUserId) {
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        abort_unless((int) $assignedUserId === (int) $user->id, 403, 'You can only assign records to yourself.');
    }

    private function recordStatusHistory(Lead $lead, ?string $status, ?string $comment): ?LeadStatusHistory
    {
        if (!$status && !filled($comment)) {
            return null;
        }

        try {
            return LeadStatusHistory::create([
                'lead_id' => $lead->id,
                'status' => $status ?? $lead->status,
                'comment' => filled($comment) ? $comment : null,
                'updated_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Lead status history save skipped.', [
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function serializeHistoryEntry(?LeadStatusHistory $history): ?array
    {
        if (!$history) {
            return null;
        }

        return [
            'status_label' => match (strtolower((string) $history->status)) {
                'ready_to_close' => 'Ready to Close',
                'won' => 'Closed Won',
                'lost' => 'Closed Lost',
                default => $history->status ? ucwords(str_replace('_', ' ', $history->status)) : '-',
            },
            'comment' => $history->comment ?: '-',
            'updated_by' => $history->updater?->name ?? auth()->user()?->name ?? 'System',
            'created_at' => $history->created_at?->timezone('Asia/Kolkata')->format('d M Y h:i A') ?? now()->timezone('Asia/Kolkata')->format('d M Y h:i A'),
        ];
    }

    private function messages(): array
    {
        return [
            'image.mimes' => 'Please select a valid image! Allowed types: AVIF, WEBP, JPG, JPEG, PNG, GIF, BMP, SVG.',
            'image.max' => 'Please select an image smaller than 2MB!',
        ];
    }
}
