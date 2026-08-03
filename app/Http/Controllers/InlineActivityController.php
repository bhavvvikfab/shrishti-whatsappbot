<?php

namespace App\Http\Controllers;

use App\Models\CrmActivityNote;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InlineActivityController extends Controller
{
    private const TIMEZONE = 'Asia/Kolkata';

    public function storeNote(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->resolveRecord($type, $id);
        $this->authorize('view', $record);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
            'note_type' => ['nullable', 'in:general,call,email,whatsapp,meeting'],
            'is_private' => ['nullable', 'boolean'],
        ]);

        CrmActivityNote::create([
            'noteable_type' => $record::class,
            'noteable_id' => $record->getKey(),
            'created_by' => auth()->id(),
            'note' => $data['note'],
            'note_type' => $data['note_type'] ?? 'general',
            'is_private' => $request->boolean('is_private'),
        ]);

        return back()->with('success', 'Note added.');
    }

    public function storeFollowUp(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->resolveRecord($type, $id);
        $this->authorize('view', $record);
        $this->authorize('create', FollowUp::class);

        if ($this->hasInlineFollowUp($type, $record)) {
            return back()->with('error', 'A follow-up already exists for this record. Add a task or note instead.');
        }

        $data = $request->validate([
            'purpose' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:pending,resheduled,completed,cancelled'],
            'follow_up_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $user = auth()->user();
        $assignedUserId = $this->assignedUserId($data['assigned_user_id'] ?? null);
        $payload = [
            'assigned_user_id' => $assignedUserId,
            'purpose' => $data['purpose'],
            'comment' => $data['comment'] ?? null,
            'priority' => $data['priority'],
            'status' => $data['status'],
            'follow_up_at' => Carbon::parse($data['follow_up_at'], self::TIMEZONE)
                ->timezone(config('app.timezone', 'UTC')),
            'user_id' => $this->resolveOwnedUserId($assignedUserId, $user?->id),
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
            'related_type' => $type,
            'related_id' => $record->getKey(),
        ];

        if ($record instanceof Lead) {
            $payload['lead_id'] = $record->id;
        } else {
            $customer = $this->customerFor($record);
            $payload['customer_id'] = $customer?->id;
            if ($record instanceof Deal && $customer) {
                $payload['comment'] = trim(($payload['comment'] ? $payload['comment'] . "\n\n" : '') . 'Deal: ' . $record->title);
            }
        }

        FollowUp::create($payload);

        return back()->with('success', 'Follow-up scheduled.');
    }

    public function storeTask(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->resolveRecord($type, $id);
        $this->authorize('view', $record);
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['required', 'date'],
            'priority' => ['required', 'in:low,medium,high'],
            'status' => ['required', 'in:pending,in_progress,completed'],
        ]);

        $assignedUserId = $this->assignedUserId($data['assigned_user_id'] ?? null);

        Task::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'related_type' => $type,
            'related_id' => $record->getKey(),
            'assigned_user_id' => $assignedUserId,
            'user_id' => $this->resolveOwnedUserId($assignedUserId, auth()->id()),
            'due_date' => $data['due_date'],
            'priority' => $data['priority'],
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Task created.');
    }

    public function storeMeeting(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->resolveRecord($type, $id);
        $this->authorize('view', $record);
        $this->authorize('create', Meeting::class);

        if ($this->hasInlineMeeting($type, $record)) {
            return back()->with('error', 'A meeting already exists for this record. Add a task or note instead.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'meeting_type' => ['required', 'in:online,offline,phone,video'],
            'status' => ['required', 'in:scheduled,completed,cancelled'],
            'address' => ['nullable', 'string', 'max:255'],
            'agenda' => ['required', 'string'],
        ]);

        $assignedUserId = $this->assignedUserId($data['assigned_user_id'] ?? null);
        $customer = $this->customerFor($record);

        Meeting::create([
            'title' => $data['title'],
            'customer_id' => $customer?->id,
            'related_type' => $type,
            'related_id' => $record->getKey(),
            'assigned_user_id' => $assignedUserId,
            'scheduled_at' => Carbon::parse($data['scheduled_at'], self::TIMEZONE)
                ->timezone(config('app.timezone', 'UTC')),
            'meeting_type' => $data['meeting_type'],
            'status' => $data['status'],
            'address' => $data['address'] ?? null,
            'agenda' => $data['agenda'],
            'user_id' => $this->resolveOwnedUserId($assignedUserId, auth()->id()),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Meeting scheduled.');
    }

    private function resolveRecord(string $type, int $id): Model
    {
        return match ($type) {
            'lead' => Lead::findOrFail($id),
            'customer' => Customer::findOrFail($id),
            'deal' => Deal::with('customer')->findOrFail($id),
            default => abort(404),
        };
    }

    private function customerFor(Model $record): ?Customer
    {
        if ($record instanceof Customer) {
            return $record;
        }

        if ($record instanceof Deal) {
            return $record->customer;
        }

        if ($record instanceof Lead) {
            return $record->convertedCustomer;
        }

        return null;
    }

    private function assignedUserId(?int $requestedUserId): int
    {
        $user = auth()->user();

        if ($requestedUserId && $user?->isAdmin()) {
            return $requestedUserId;
        }

        if ($requestedUserId && $requestedUserId === (int) $user?->id) {
            return $requestedUserId;
        }

        return (int) auth()->id();
    }

    private function hasInlineFollowUp(string $type, Model $record): bool
    {
        return FollowUp::query()
            ->where(function ($query) use ($type, $record) {
                $query->where(function ($related) use ($type, $record) {
                    $related->where('related_type', $type)
                        ->where('related_id', $record->getKey());
                });

                if ($record instanceof Lead) {
                    $query->orWhere('lead_id', $record->id);
                }

                if ($record instanceof Customer) {
                    $query->orWhere('customer_id', $record->id);
                }
            })
            ->exists();
    }

    private function hasInlineMeeting(string $type, Model $record): bool
    {
        return Meeting::query()
            ->where(function ($query) use ($type, $record) {
                $query->where(function ($related) use ($type, $record) {
                    $related->where('related_type', $type)
                        ->where('related_id', $record->getKey());
                });

                if ($record instanceof Customer) {
                    $query->orWhere('customer_id', $record->id);
                }
            })
            ->exists();
    }
}
