@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-megaphone text-primary me-2"></i>WhatsApp Campaigns</h4>
            <p class="text-muted mb-0">Broadcast messages and drip campaigns</p>
        </div>
        <a href="{{ route('whatsapp.campaigns.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Campaign
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-primary">{{ $stats['total'] }}</div>
                <div class="text-muted small">Total Campaigns</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-secondary">{{ $stats['draft'] }}</div>
                <div class="text-muted small">Draft</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-warning">{{ $stats['scheduled'] }}</div>
                <div class="text-muted small">Scheduled</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-3 fw-bold text-success">{{ $stats['completed'] }}</div>
                <div class="text-muted small">Completed</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Campaign Name</th>
                            <th>Type</th>
                            <th>Template</th>
                            <th>Recipients</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Scheduled</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campaigns as $campaign)
                        <tr>
                            <td>
                                <a href="{{ route('whatsapp.campaigns.show', $campaign) }}" class="fw-semibold text-decoration-none">
                                    {{ $campaign->name }}
                                </a>
                                @if($campaign->description)
                                    <div class="text-muted small">{{ Str::limit($campaign->description, 50) }}</div>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ ucfirst($campaign->type) }}</span></td>
                            <td>{{ $campaign->template?->name ?? '-' }}</td>
                            <td>{{ number_format($campaign->total_recipients) }}</td>
                            <td style="min-width:120px">
                                @if($campaign->total_recipients > 0)
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-success" style="width:{{ $campaign->progress_percentage }}%"></div>
                                </div>
                                <small class="text-muted">{{ $campaign->sent_count }}/{{ $campaign->total_recipients }}</small>
                                @else
                                <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ match($campaign->status) {
                                    'draft' => 'secondary',
                                    'scheduled' => 'warning',
                                    'sending' => 'primary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'secondary'
                                } }}">{{ ucfirst($campaign->status) }}</span>
                            </td>
                            <td>{{ $campaign->scheduled_at?->format('d M Y, h:i A') ?? '-' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('whatsapp.campaigns.show', $campaign) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if(in_array($campaign->status, ['draft', 'scheduled']))
                                    <button class="btn btn-sm btn-success" onclick="launchCampaign({{ $campaign->id }})" title="Launch Now">
                                        <i class="bi bi-play-fill"></i>
                                    </button>
                                    <a href="{{ route('whatsapp.campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endif
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCampaign({{ $campaign->id }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-megaphone fs-1 d-block mb-2 opacity-25"></i>
                                No campaigns yet. <a href="{{ route('whatsapp.campaigns.create') }}">Create your first campaign</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $campaigns->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function launchCampaign(id) {
    if (!confirm('Launch this campaign now? Messages will be sent to all eligible leads.')) return;
    fetch(`/whatsapp/campaigns/${id}/launch`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) { alert(d.message); location.reload(); }
        else alert(d.message || 'Error launching campaign');
    });
}

function deleteCampaign(id) {
    if (!confirm('Delete this campaign?')) return;
    fetch(`/whatsapp/campaigns/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}
</script>
@endpush
