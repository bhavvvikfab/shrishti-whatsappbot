@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('whatsapp.campaigns.show', $campaign) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="mb-0">Edit Campaign</h4>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form action="{{ route('whatsapp.campaigns.update', $campaign) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-transparent fw-bold">Campaign Details</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $campaign->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $campaign->description) }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Campaign Type</label>
                                <select name="type" class="form-select">
                                    <option value="broadcast" {{ old('type', $campaign->type) === 'broadcast' ? 'selected' : '' }}>Broadcast</option>
                                    <option value="drip" {{ old('type', $campaign->type) === 'drip' ? 'selected' : '' }}>Drip Campaign</option>
                                    <option value="scheduled" {{ old('type', $campaign->type) === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Schedule Date/Time</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control"
                                    value="{{ old('scheduled_at', $campaign->scheduled_at?->format('Y-m-d\TH:i')) }}">
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
                                <option value="{{ $tpl->id }}" {{ old('template_id', $campaign->template_id) == $tpl->id ? 'selected' : '' }}>
                                    {{ $tpl->name }} ({{ $tpl->category }}, {{ $tpl->language }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div id="customMessageSection">
                            <label class="form-label fw-semibold">Custom Message</label>
                            <textarea name="message" class="form-control" rows="5">{{ old('message', $campaign->message) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('whatsapp.campaigns.show', $campaign) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Update Campaign
                    </button>
                </div>
            </form>
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
