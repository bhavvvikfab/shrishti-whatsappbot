@extends('layouts.app')

@section('page_title', 'Lead Profile')

@section('content')
@php
    $waChatUrl = $lead->resolveWhatsappChatUrl();
@endphp
<div class="container-fluid p-0">
    {{-- Header Bar --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3 w-100">
        <div class="flex-grow-1 w-100">
            <h1 class="h4 mb-1">Lead Profile</h1>
            <p class="text-muted small mb-0">{{ $lead->name }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2 w-100 w-md-auto justify-content-lg-end justify-content-md-end">
            @if($lead->is_converted && $lead->converted_customer_id)
                <a href="{{ route('masters.customers.edit', $lead->converted_customer_id) }}" class="btn btn-success flex-grow-1 flex-md-grow-0">
                    <i class="bi bi-person-check me-1"></i>View Customer
                </a>
            @else
                <form method="POST" action="{{ route('leads.convert', $lead) }}" class="flex-grow-1 flex-md-grow-0">
                    @csrf
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-person-plus me-1"></i>Convert</button>
                </form>
            @endif
            @can('leads.edit')
            <a href="{{ route('leads.edit', $lead) }}" class="btn btn-dark-blue flex-grow-1 flex-md-grow-0">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            @endcan
            @if($waChatUrl)
                <a href="{{ $waChatUrl }}" class="btn btn-outline-success flex-grow-1 flex-md-grow-0">
                    <i class="fab fa-whatsapp me-1"></i>WhatsApp chat
                </a>
            @endif
            <a href="{{ route('leads.index') }}" class="btn btn-dark-blue flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-angle-left me-1"></i>Back
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            {{-- Basic Info Card --}}
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden detail-view-card">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-primary small text-uppercase" style="letter-spacing: 0.05em;">Lead Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="detail-view-block">
                        <div class="detail-view-grid">
                            <div class="detail-view-row mb-3">
                                <span class="detail-view-label text-muted small d-block mb-1">Email</span> 
                                <span class="detail-view-value fw-bold text-dark">{{ $lead->email ?? '-' }}</span>
                            </div>
                            <div class="detail-view-row mb-3">
                                <span class="detail-view-label text-muted small d-block mb-1">Phone</span> 
                                <span class="detail-view-value fw-bold text-dark">{{ $lead->phone ?? '-' }}</span>
                            </div>
                            <div class="detail-view-row mb-3">
                                <span class="detail-view-label text-muted small d-block mb-1">Status</span> 
                                <span class="detail-view-value">
                                    <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span>
                                </span>
                            </div>
                            <div class="detail-view-row mb-3">
                                <span class="detail-view-label text-muted small d-block mb-1">Source</span> 
                                <span class="detail-view-value text-muted">{{ $lead->leadSource?->name ?? $lead->source ?? '-' }}</span>
                            </div>
                            <div class="detail-view-row">
                                <span class="detail-view-label text-muted small d-block mb-1">Stage</span> 
                                <span class="detail-view-value text-muted">{{ $lead->leadStage?->name ?? '-' }}</span>
                            </div>
                        </div>
                    </div>

                    @php
                        $customFields = \App\Models\CustomField::where('module', 'Lead')->where('is_active', true)->get();
                    @endphp
                    @if($customFields->count() > 0)
                        <hr class="my-4">
                        <h6 class="fw-bold small text-primary text-uppercase mb-3" style="letter-spacing: 0.05em;">Additional Data</h6>
                        @foreach($customFields as $field)
                            @php $val = $lead->getCustomFieldValue($field->name); @endphp
                            @if($val)
                                <div class="bg-light p-3 rounded-3 border mb-2">
                                    <strong class="text-muted small d-block mb-1" style="font-size: 0.65rem;">{{ $field->label }}</strong> 
                                    <span class="text-dark small fw-semibold">{{ $val }}</span>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 mb-4 timeline-card">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
                        <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                             <i class="bi bi-clock-history text-primary"></i> Activity Timeline
                        </h6>
                    </div>

                    <div class="timeline ps-2">
                        {{-- Creation Entry --}}
                        <div class="timeline-item d-flex gap-3 mb-4 position-relative">
                            <div class="timeline-line position-absolute h-100 border-start" style="left: 16px; top: 32px; z-index: 0;"></div>
                            <div class="timeline-icon bg-light text-primary rounded-circle d-flex align-items-center justify-content-center border" style="width: 34px; height: 34px; flex-shrink: 0; min-width: 34px; z-index: 1;">
                                <i class="bi bi-stars"></i>
                            </div>
                            <div class="timeline-content pb-3 border-bottom w-100">
                                <div class="d-flex flex-column flex-sm-row justify-content-between mb-1">
                                    <h6 class="fw-bold mb-0">Lead Created</h6>
                                    <span class="small text-muted">{{ $lead->created_at?->format('d M, Y h:i A') }}</span>
                                </div>
                                <p class="text-muted small mb-0 opacity-75">System initialized the lead profile automatically.</p>
                            </div>
                        </div>

                        {{-- Follow-ups --}}
                        @forelse ($lead->followUps->sortByDesc('scheduled_at') as $fu)
                            <div class="timeline-item d-flex gap-3 mb-4 position-relative">
                                @if(!$loop->last)
                                <div class="timeline-line position-absolute h-100 border-start" style="left: 16px; top: 32px; z-index: 0;"></div>
                                @endif
                                <div class="timeline-icon bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center border" style="width: 34px; height: 34px; flex-shrink: 0; min-width: 34px; z-index: 1;">
                                    @switch($fu->channel)
                                        @case('Call') <i class="bi bi-telephone"></i> @break
                                        @case('WhatsApp') <i class="bi bi-whatsapp"></i> @break
                                        @case('Email') <i class="bi bi-envelope"></i> @break
                                        @case('Meeting') <i class="bi bi-people"></i> @break
                                        @default <i class="bi bi-chat-dots"></i>
                                    @endswitch
                                </div>
                                <div class="timeline-content pb-3 border-bottom w-100">
                                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold mb-1 d-flex flex-wrap align-items-center gap-2">
                                                {{ $fu->channel }} Follow-Up
                                                @if($fu->assignedUser)
                                                    <span class="badge bg-light text-muted border fw-normal small">by {{ $fu->assignedUser->name }}</span>
                                                @endif
                                            </h6>
                                            <div class="text-muted small mb-2 opacity-75">
                                                <i class="bi bi-calendar-event me-1"></i>{{ \Illuminate\Support\Carbon::parse($fu->scheduled_at)->format('d M, Y h:i A') }}
                                            </div>
                                            @if($fu->notes)
                                                <div class="bg-light p-3 rounded-3 small text-dark mb-2 border-start border-primary border-3 shadow-sm">
                                                    {{ $fu->notes }}
                                                </div>
                                            @endif
                                            <div class="small fw-semibold text-primary opacity-75">
                                                <i class="bi bi-clock-history me-1"></i>Next: {{ $fu->next_follow_up_at ? \Illuminate\Support\Carbon::parse($fu->next_follow_up_at)->format('d M, Y h:i A') : 'None scheduled' }}
                                            </div>
                                        </div>
                                        <div class="d-flex flex-row flex-md-column align-items-center align-items-md-end gap-2 mt-2 mt-md-0">
                                            <div class="form-check form-switch p-0 d-flex align-items-center gap-2">
                                                <input class="form-check-input ms-0 status-toggle" type="checkbox" role="switch" data-id="{{ $fu->id }}" {{ $fu->completed ? 'checked' : '' }}>
                                                <span class="badge {{ $fu->completed ? 'bg-success' : 'bg-warning text-dark' }} status-label px-2">
                                                    {{ $fu->completed ? 'Done' : 'Pending' }}
                                                </span>
                                            </div>
                                            <div class="btn-group btn-group-sm ms-auto ms-md-0 shadow-sm">
                                                @can('followups.edit')
                                                <a href="{{ route('followups.edit', $fu) }}" class="btn btn-white border" title="Edit"><i class="bi bi-pencil small"></i></a>
                                                @endcan
                                                <form action="{{ route('followups.destroy', $fu) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete activity?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-white border text-danger"><i class="bi bi-trash small"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted small px-3">
                                <i class="bi bi-chat-left-dots display-6 d-block mb-3 opacity-25"></i>
                                <p class="mb-0 fw-semibold">No follow-up activities recorded yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('crm.partials.inline-activity-panel', [
        'activityType' => 'lead',
        'activityRecord' => $lead,
    ])
</div>
@push('styles')
    <link rel="stylesheet"
        href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
@endpush

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const timeline = document.querySelector('.timeline');
        if (!timeline) return;

        timeline.addEventListener('change', function(e) {
            if (e.target.classList.contains('status-toggle')) {
                const id = e.target.dataset.id;
                const label = e.target.closest('.timeline-item').querySelector('.status-label');
                const checkbox = e.target;

                fetch(`/follow-ups/${id}/toggle`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        label.textContent = data.completed ? 'Done' : 'Pending';
                        label.className = `badge ${data.completed ? 'bg-success' : 'bg-warning text-dark'} status-label px-2`;
                        checkbox.checked = data.completed;
                    }
                })
                .catch(err => {
                    checkbox.checked = !checkbox.checked;
                    console.error('Toggle failed:', err);
                });
            }
        });
    });
</script>
@endpush
