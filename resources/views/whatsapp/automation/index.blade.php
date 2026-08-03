@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-robot text-success me-2"></i>WhatsApp Automation</h4>
            <p class="text-muted mb-0">Manage keyword replies, welcome messages, and FAQ automation</p>
        </div>
        <a href="{{ route('whatsapp.automation.create') }}" class="btn btn-success">
            <i class="bi bi-plus-lg me-1"></i>New Rule
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        @php
            $triggerTypes = ['keyword' => ['label'=>'Keyword Replies','icon'=>'bi-chat-dots','color'=>'primary'],
                'welcome' => ['label'=>'Welcome Messages','icon'=>'bi-hand-wave','color'=>'success'],
                'faq' => ['label'=>'FAQ Automation','icon'=>'bi-question-circle','color'=>'info'],
                'drip' => ['label'=>'Drip Campaigns','icon'=>'bi-droplet','color'=>'warning']];
        @endphp
        @foreach($triggerTypes as $type => $info)
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <i class="bi {{ $info['icon'] }} text-{{ $info['color'] }} fs-4 mb-1 d-block"></i>
                    <div class="fw-bold">{{ $rules->where('trigger_type', $type)->count() }}</div>
                    <div class="text-muted small">{{ $info['label'] }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Rule Name</th>
                            <th>Trigger Type</th>
                            <th>Keywords</th>
                            <th>Response</th>
                            <th>Priority</th>
                            <th>Executions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                        <tr>
                            <td class="fw-semibold">{{ $rule->name }}</td>
                            <td>
                                <span class="badge bg-{{ match($rule->trigger_type) {
                                    'keyword' => 'primary',
                                    'welcome' => 'success',
                                    'faq' => 'info',
                                    'drip' => 'warning',
                                    'followup' => 'secondary',
                                    'scheduled' => 'dark',
                                    default => 'secondary'
                                } }}">{{ ucfirst($rule->trigger_type) }}</span>
                            </td>
                            <td>
                                @if($rule->trigger_keywords)
                                    @foreach(explode(',', $rule->trigger_keywords) as $kw)
                                        <span class="badge bg-light text-dark border me-1">{{ trim($kw) }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-truncate" style="max-width:200px">
                                @if($rule->template)
                                    <i class="bi bi-file-text text-success me-1"></i>{{ $rule->template->name }}
                                @else
                                    {{ Str::limit($rule->response_message, 50) }}
                                @endif
                            </td>
                            <td>{{ $rule->priority }}</td>
                            <td>{{ number_format($rule->execution_count) }}</td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" {{ $rule->is_active ? 'checked' : '' }}
                                        onchange="toggleRule({{ $rule->id }}, this)">
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('whatsapp.automation.edit', $rule) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteRule({{ $rule->id }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-robot fs-1 d-block mb-2 opacity-25"></i>
                                No automation rules yet.
                                <a href="{{ route('whatsapp.automation.create') }}">Create your first rule</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $rules->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function toggleRule(id, checkbox) {
    fetch(`/whatsapp/automation/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (!d.success) checkbox.checked = !checkbox.checked;
    });
}

function deleteRule(id) {
    if (!confirm('Delete this automation rule?')) return;
    fetch(`/whatsapp/automation/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}
</script>
@endpush
