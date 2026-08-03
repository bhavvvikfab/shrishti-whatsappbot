@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('whatsapp.campaigns.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-0">{{ $campaign->name }}</h4>
            <p class="text-muted mb-0 small">{{ $campaign->description }}</p>
        </div>
        <div class="d-flex gap-2">
            @if(in_array($campaign->status, ['draft', 'scheduled']))
            <button class="btn btn-success" onclick="launchCampaign()">
                <i class="bi bi-play-fill me-1"></i>Launch Now
            </button>
            <a href="{{ route('whatsapp.campaigns.edit', $campaign) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            @endif
            @if(in_array($campaign->status, ['draft', 'scheduled', 'sending']))
            <button class="btn btn-outline-danger" onclick="cancelCampaign()">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
            @endif
        </div>
    </div>

    <!-- Status Banner -->
    <div class="alert alert-{{ match($campaign->status) {
        'draft' => 'secondary',
        'scheduled' => 'warning',
        'sending' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    } }} d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-{{ match($campaign->status) {
            'draft' => 'pencil',
            'scheduled' => 'clock',
            'sending' => 'arrow-repeat',
            'completed' => 'check-circle',
            'cancelled' => 'x-circle',
            default => 'info-circle'
        } }}"></i>
        <strong>{{ ucfirst($campaign->status) }}</strong>
        @if($campaign->scheduled_at && $campaign->status === 'scheduled')
            &mdash; Scheduled for {{ $campaign->scheduled_at->format('d M Y, h:i A') }}
        @elseif($campaign->completed_at)
            &mdash; Completed {{ $campaign->completed_at->diffForHumans() }}
        @endif
    </div>

    <div class="row g-4">
        <!-- Stats -->
        <div class="col-12">
            <div class="row g-3">
                <div class="col-6 col-md-2">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold">{{ number_format($campaign->total_recipients) }}</div>
                        <div class="text-muted small">Recipients</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-primary">{{ number_format($campaign->sent_count) }}</div>
                        <div class="text-muted small">Sent</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-success">{{ number_format($campaign->delivered_count) }}</div>
                        <div class="text-muted small">Delivered</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-info">{{ number_format($campaign->read_count) }}</div>
                        <div class="text-muted small">Read</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-danger">{{ number_format($campaign->failed_count) }}</div>
                        <div class="text-muted small">Failed</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-warning">{{ $campaign->success_rate }}%</div>
                        <div class="text-muted small">Success Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress -->
        @if($campaign->total_recipients > 0)
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-semibold">Delivery Progress</span>
                        <span class="small text-muted">{{ $campaign->progress_percentage }}%</span>
                    </div>
                    <div class="progress" style="height:10px">
                        <div class="progress-bar bg-success" style="width:{{ $campaign->progress_percentage }}%"></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Campaign Details -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-bold">Campaign Details</div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr><td class="text-muted">Type</td><td class="fw-semibold">{{ ucfirst($campaign->type) }}</td></tr>
                        <tr><td class="text-muted">Template</td><td class="fw-semibold">{{ $campaign->template?->name ?? 'Custom Message' }}</td></tr>
                        <tr><td class="text-muted">Created</td><td>{{ $campaign->created_at?->format('d M Y, h:i A') }}</td></tr>
                        <tr><td class="text-muted">Scheduled</td><td>{{ $campaign->scheduled_at?->format('d M Y, h:i A') ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Started</td><td>{{ $campaign->started_at?->format('d M Y, h:i A') ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Completed</td><td>{{ $campaign->completed_at?->format('d M Y, h:i A') ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Message Preview -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-bold">Message Preview</div>
                <div class="card-body">
                    @if($campaign->template)
                    <div class="alert alert-success">
                        <i class="bi bi-file-text me-2"></i>
                        <strong>Template:</strong> {{ $campaign->template->name }}
                        <div class="small text-muted mt-1">{{ $campaign->template->category }} &bull; {{ $campaign->template->language }}</div>
                    </div>
                    @elseif($campaign->message)
                    <div class="p-3 rounded" style="background:#dcf8c6; font-size:14px">
                        {{ $campaign->message }}
                    </div>
                    @else
                    <p class="text-muted">No message configured.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function launchCampaign() {
    if (!confirm('Launch this campaign now? Messages will be sent to all eligible leads.')) return;
    fetch('{{ route('whatsapp.campaigns.launch', $campaign) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) { alert(d.message); location.reload(); }
        else alert(d.message || 'Error');
    });
}

function cancelCampaign() {
    if (!confirm('Cancel this campaign?')) return;
    fetch('{{ route('whatsapp.campaigns.cancel', $campaign) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}
</script>
@endpush
