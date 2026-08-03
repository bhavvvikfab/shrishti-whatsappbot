@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-bell text-warning me-2"></i>WhatsApp Followups</h4>
            <p class="text-muted mb-0">Track and manage scheduled WhatsApp followups</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3 border-start border-warning border-3">
                <div class="fs-3 fw-bold text-warning">{{ $stats['pending'] }}</div>
                <div class="text-muted small">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3 border-start border-danger border-3">
                <div class="fs-3 fw-bold text-danger">{{ $stats['overdue'] }}</div>
                <div class="text-muted small">Overdue</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3 border-start border-primary border-3">
                <div class="fs-3 fw-bold text-primary">{{ $stats['today'] }}</div>
                <div class="text-muted small">Due Today</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3 border-start border-success border-3">
                <div class="fs-3 fw-bold text-success">{{ $stats['completed'] }}</div>
                <div class="text-muted small">Completed</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <select name="status" class="form-select form-select-sm" style="width:auto">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
                <select name="due_filter" class="form-select form-select-sm" style="width:auto">
                    <option value="">All Dates</option>
                    <option value="today" {{ request('due_filter') === 'today' ? 'selected' : '' }}>Due Today</option>
                    <option value="overdue" {{ request('due_filter') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                    <option value="week" {{ request('due_filter') === 'week' ? 'selected' : '' }}>This Week</option>
                </select>
                <select name="assigned_to" class="form-select form-select-sm" style="width:auto">
                    <option value="">All Agents</option>
                    @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('whatsapp.followups.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Lead</th>
                            <th>Assigned To</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($followups as $fu)
                        <tr class="{{ $fu->status === 'pending' && $fu->due_date?->isPast() ? 'table-danger' : '' }}">
                            <td>
                                <div class="fw-semibold">{{ $fu->title }}</div>
                                @if($fu->description)
                                    <div class="text-muted small">{{ Str::limit($fu->description, 60) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($fu->lead)
                                    <a href="{{ route('leads.show', $fu->lead) }}" class="text-decoration-none">{{ $fu->lead->name }}</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $fu->assignedUser?->name ?? 'Unassigned' }}</td>
                            <td>
                                <div class="{{ $fu->status === 'pending' && $fu->due_date?->isPast() ? 'text-danger fw-semibold' : '' }}">
                                    {{ $fu->due_date?->format('d M Y') }}
                                </div>
                                <div class="text-muted small">{{ $fu->due_date?->format('h:i A') }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ match($fu->status) {
                                    'pending' => 'warning',
                                    'in_progress' => 'primary',
                                    'completed' => 'success',
                                    'cancelled' => 'secondary',
                                    default => 'secondary'
                                } }}">{{ ucfirst(str_replace('_', ' ', $fu->status)) }}</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    @if($fu->status === 'pending')
                                    <button class="btn btn-sm btn-success" onclick="completeFollowup({{ $fu->id }})" title="Mark Complete">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    @endif
                                    @if($fu->conversation_id)
                                    <a href="{{ route('whatsapp.conversation', $fu->conversation_id) }}" class="btn btn-sm btn-outline-success" title="Open Chat">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    @endif
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteFollowup({{ $fu->id }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-bell fs-1 d-block mb-2 opacity-25"></i>
                                No followups found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $followups->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function completeFollowup(id) {
    if (!confirm('Mark this followup as completed?')) return;
    fetch(`/whatsapp/followups/${id}/complete`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

function deleteFollowup(id) {
    if (!confirm('Delete this followup?')) return;
    fetch(`/whatsapp/followups/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}
</script>
@endpush
