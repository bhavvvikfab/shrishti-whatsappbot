@php
    use App\Models\CrmActivityNote;
    use App\Models\FollowUp;
    use App\Models\Meeting;
    use App\Models\Task;
    use App\Models\User;

    $activityType = $activityType ?? null;
    $activityRecord = $activityRecord ?? null;
    $activityNotesOnly = $activityNotesOnly ?? false;
    $recordClass = $activityRecord ? $activityRecord::class : null;
    $recordId = $activityRecord?->getKey();
    $tabSuffix = $activityType . '-' . $recordId;
    $currentUser = auth()->user();
    $activityUsers = $currentUser?->isAdmin()
        ? User::orderBy('name')->get()
        : User::whereKey(auth()->id())->get();
    $staffSelect = function ($fieldId) use ($activityUsers, $currentUser) {
        $html = '<select name="assigned_user_id" id="' . e($fieldId) . '" class="form-select form-select-sm">';
        foreach ($activityUsers as $user) {
            $selected = ((int) $user->id === (int) $currentUser?->id) ? ' selected' : '';
            $html .= '<option value="' . e($user->id) . '"' . $selected . '>' . e($user->name) . '</option>';
        }
        return $html . '</select>';
    };

    $notes = $activityRecord
        ? CrmActivityNote::with('createdBy')
            ->where('noteable_type', $recordClass)
            ->where('noteable_id', $recordId)
            ->latest()
            ->limit(8)
            ->get()
        : collect();

    $followUps = ($activityRecord && ! $activityNotesOnly)
        ? FollowUp::with('assignedUser')
            ->where(function ($query) use ($activityType, $recordId, $activityRecord) {
                $query->where(function ($related) use ($activityType, $recordId) {
                    $related->where('related_type', $activityType)->where('related_id', $recordId);
                });

                if ($activityType === 'lead') {
                    $query->orWhere('lead_id', $recordId);
                }

                if ($activityType === 'customer') {
                    $query->orWhere('customer_id', $recordId);
                }
            })
            ->latest('follow_up_at')
            ->limit(8)
            ->get()
        : collect();

    $tasks = ($activityRecord && ! $activityNotesOnly)
        ? Task::with('assignedUser')
            ->where('related_type', $activityType)
            ->where('related_id', $recordId)
            ->latest('due_date')
            ->limit(8)
            ->get()
        : collect();

    $meetings = ($activityRecord && ! $activityNotesOnly)
        ? Meeting::with('assignedUser')
            ->where(function ($query) use ($activityType, $recordId) {
                $query->where(function ($related) use ($activityType, $recordId) {
                    $related->where('related_type', $activityType)->where('related_id', $recordId);
                });

                if ($activityType === 'customer') {
                    $query->orWhere('customer_id', $recordId);
                }
            })
            ->latest('scheduled_at')
            ->limit(8)
            ->get()
        : collect();

    $canCreateFollowUpInline = ! $activityNotesOnly && $followUps->isEmpty();
    $canCreateMeetingInline = ! $activityNotesOnly && $meetings->isEmpty();
    $baseRouteParams = [$activityType, $recordId];
@endphp

@if($activityRecord)
<div class="card shadow-sm border-0 rounded-4 mt-4 inline-activity-panel">
    <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
        <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-lightning-charge text-primary"></i> Activity
        </h6>
    </div>
    <div class="card-body p-0">
        @unless($activityNotesOnly)
        <div class="overflow-x-auto overflow-y-hidden">
            <ul class="nav nav-tabs px-3 px-md-4 pt-3 border-bottom-0 flex-nowrap" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold text-nowrap" data-bs-toggle="tab" data-bs-target="#activity-notes-{{ $tabSuffix }}" type="button" role="tab">Notes</button>
                </li>
                @if($canCreateFollowUpInline)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-nowrap" data-bs-toggle="tab" data-bs-target="#activity-followups-{{ $tabSuffix }}" type="button" role="tab">Follow-ups</button>
                    </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold text-nowrap" data-bs-toggle="tab" data-bs-target="#activity-tasks-{{ $tabSuffix }}" type="button" role="tab">Tasks</button>
                </li>
                @if($canCreateMeetingInline)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-nowrap" data-bs-toggle="tab" data-bs-target="#activity-meetings-{{ $tabSuffix }}" type="button" role="tab">Meetings</button>
                    </li>
                @endif
            </ul>
        </div>
        @endunless

        <div class="{{ $activityNotesOnly ? '' : 'tab-content border-top' }} p-3 p-md-4">
            <div class="{{ $activityNotesOnly ? '' : 'tab-pane fade show active' }}" id="activity-notes-{{ $tabSuffix }}" role="tabpanel">
                <form method="POST" action="{{ route('inline-activities.notes.store', $baseRouteParams) }}" class="row g-3 align-items-end mb-4">
                    @csrf
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Note</label>
                        <textarea name="note" rows="2" class="form-control" required></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Type</label>
                        <select name="note_type" class="form-select">
                            <option value="general">General</option>
                            <option value="call">Call</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="meeting">Meeting</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark-blue w-100"><i class="bi bi-plus-lg me-1"></i>Add</button>
                    </div>
                </form>

                @forelse($notes as $note)
                    <div class="border-bottom py-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <span class="badge bg-light text-dark border">{{ ucfirst($note->note_type) }}</span>
                            <span class="text-muted small">{{ $note->created_at?->format('d M Y h:i A') }} by {{ $note->createdBy?->name ?? 'System' }}</span>
                        </div>
                        <div class="small mt-2">{{ $note->note }}</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No notes yet.</p>
                @endforelse
            </div>

            @if(! $activityNotesOnly && $canCreateFollowUpInline)
            <div class="tab-pane fade" id="activity-followups-{{ $tabSuffix }}" role="tabpanel">
                @can('create', FollowUp::class)
                <form method="POST" action="{{ route('inline-activities.followups.store', $baseRouteParams) }}" class="row g-3 align-items-end mb-4">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Purpose</label>
                        <input name="purpose" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date & Time</label>
                        <input type="datetime-local" name="follow_up_at" class="form-control" min="{{ now()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Assigned To</label>
                        {!! $staffSelect('followup-assigned-' . $tabSuffix) !!}
                    </div>
                    <div class="col-md-9">
                        <textarea name="comment" rows="2" class="form-control" placeholder="Comment"></textarea>
                    </div>
                    <div class="col-md-2">
                        <input type="hidden" name="status" value="pending">
                        <button type="submit" class="btn btn-dark-blue w-100"><i class="bi bi-plus-lg me-1"></i>Add</button>
                    </div>
                </form>
                @endcan

                @forelse($followUps as $followUp)
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 border-bottom py-2">
                        <div>
                            <div class="fw-semibold small">{{ $followUp->purpose ?: 'Follow-up' }}</div>
                            <div class="text-muted small">{{ $followUp->comment ?: 'No comment' }}</div>
                        </div>
                        <div class="text-md-end small">
                            <div>{{ $followUp->follow_up_at?->format('d M Y h:i A') ?? 'N/A' }}</div>
                            <span class="badge bg-light text-dark border">{{ ucfirst($followUp->status ?? 'pending') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No follow-ups yet.</p>
                @endforelse
            </div>
            @endif

            @unless($activityNotesOnly)
            <div class="tab-pane fade" id="activity-tasks-{{ $tabSuffix }}" role="tabpanel">
                @can('create', Task::class)
                <form method="POST" action="{{ route('inline-activities.tasks.store', $baseRouteParams) }}" class="row g-3 align-items-end mb-4">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Title</label>
                        <input name="title" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Assigned To</label>
                        {!! $staffSelect('task-assigned-' . $tabSuffix) !!}
                    </div>
                    <div class="col-md-9">
                        <textarea name="description" rows="2" class="form-control" placeholder="Description" required></textarea>
                    </div>
                    <div class="col-md-2">
                        <input type="hidden" name="status" value="pending">
                        <button type="submit" class="btn btn-dark-blue w-100"><i class="bi bi-plus-lg me-1"></i>Add</button>
                    </div>
                </form>
                @endcan

                @forelse($tasks as $task)
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 border-bottom py-2">
                        <div>
                            <div class="fw-semibold small">{{ $task->title }}</div>
                            <div class="text-muted small">{{ $task->description }}</div>
                        </div>
                        <div class="text-md-end small">
                            <div>{{ $task->due_date?->format('d M Y') ?? 'N/A' }}</div>
                            <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $task->status ?? 'pending')) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No tasks yet.</p>
                @endforelse
            </div>
            @endunless

            @if(! $activityNotesOnly && $canCreateMeetingInline)
            <div class="tab-pane fade" id="activity-meetings-{{ $tabSuffix }}" role="tabpanel">
                @can('create', Meeting::class)
                <form method="POST" action="{{ route('inline-activities.meetings.store', $baseRouteParams) }}" class="row g-3 align-items-end mb-4">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Title</label>
                        <input name="title" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Scheduled On</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control" min="{{ now()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Type</label>
                        <select name="meeting_type" class="form-select">
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="phone">Phone</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Assigned To</label>
                        {!! $staffSelect('meeting-assigned-' . $tabSuffix) !!}
                    </div>
                    <div class="col-md-5">
                        <textarea name="agenda" rows="2" class="form-control" placeholder="Agenda" required></textarea>
                    </div>
                    <div class="col-md-4">
                        <input name="address" class="form-control" placeholder="Address or link">
                    </div>
                    <div class="col-md-2">
                        <input type="hidden" name="status" value="scheduled">
                        <button type="submit" class="btn btn-dark-blue w-100"><i class="bi bi-plus-lg me-1"></i>Add</button>
                    </div>
                </form>
                @endcan

                @forelse($meetings as $meeting)
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 border-bottom py-2">
                        <div>
                            <div class="fw-semibold small">{{ $meeting->title }}</div>
                            <div class="text-muted small">{{ $meeting->agenda }}</div>
                        </div>
                        <div class="text-md-end small">
                            <div>{{ $meeting->scheduled_at?->format('d M Y h:i A') ?? 'N/A' }}</div>
                            <span class="badge bg-light text-dark border">{{ ucfirst($meeting->status ?? 'scheduled') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No meetings yet.</p>
                @endforelse
            </div>
            @endif
        </div>
    </div>
</div>
@endif
