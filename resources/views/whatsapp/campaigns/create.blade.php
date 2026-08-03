@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('whatsapp.campaigns.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="mb-0">Create WhatsApp Campaign</h4>
            <p class="text-muted mb-0 small">Send broadcast messages to your leads</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('whatsapp.campaigns.store') }}" method="POST">
                @csrf
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent fw-bold">Campaign Details</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="e.g. Summer Sale Announcement" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Optional description">{{ old('description') }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Campaign Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-select">
                                    <option value="broadcast" {{ old('type') === 'broadcast' ? 'selected' : '' }}>Broadcast (Send to all)</option>
                                    <option value="drip" {{ old('type') === 'drip' ? 'selected' : '' }}>Drip Campaign</option>
                                    <option value="scheduled" {{ old('type') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Schedule Date/Time</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}">
                                <div class="form-text">Leave empty to save as draft</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent fw-bold">Message Content</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">WhatsApp Template</label>
                            <select name="template_id" class="form-select" id="templateSelect" onchange="toggleCustomMessage()">
                                <option value="">-- Use custom message --</option>
                                @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}" {{ old('template_id') == $tpl->id ? 'selected' : '' }}>
                                    {{ $tpl->name }} ({{ $tpl->category }}, {{ $tpl->language }})
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text">Using an approved template is recommended for better deliverability.</div>
                        </div>
                        <div id="customMessageSection">
                            <label class="form-label fw-semibold">Custom Message</label>
                            <textarea name="message" class="form-control" rows="5" placeholder="Type your broadcast message here...">{{ old('message') }}</textarea>
                            <div class="form-text">Note: Custom messages may require an active conversation window (24h).</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('whatsapp.campaigns.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Create Campaign
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-bold">Audience Preview</div>
                <div class="card-body text-center py-4">
                    <div class="fs-1 fw-bold text-primary">{{ number_format($totalLeads) }}</div>
                    <div class="text-muted">Leads with WhatsApp numbers</div>
                    <hr>
                    <div class="text-start">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Total leads</span>
                            <span class="fw-semibold">{{ number_format($totalLeads) }}</span>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 text-start small">
                        <i class="bi bi-info-circle me-1"></i>
                        Campaign will be sent to all leads with a WhatsApp number.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleCustomMessage() {
    const templateId = document.getElementById('templateSelect').value;
    document.getElementById('customMessageSection').style.display = templateId ? 'none' : 'block';
}
toggleCustomMessage();
</script>
@endpush
