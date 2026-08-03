@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('whatsapp.automation.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="mb-0">Edit Automation Rule</h4>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form action="{{ route('whatsapp.automation.update', $rule) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Rule Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $rule->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Trigger Type <span class="text-danger">*</span></label>
                                <select name="trigger_type" class="form-select" id="triggerType" onchange="updateTriggerUI()">
                                    <option value="keyword" {{ old('trigger_type', $rule->trigger_type) === 'keyword' ? 'selected' : '' }}>Keyword Match</option>
                                    <option value="welcome" {{ old('trigger_type', $rule->trigger_type) === 'welcome' ? 'selected' : '' }}>Welcome Message</option>
                                    <option value="faq" {{ old('trigger_type', $rule->trigger_type) === 'faq' ? 'selected' : '' }}>FAQ Automation</option>
                                    <option value="drip" {{ old('trigger_type', $rule->trigger_type) === 'drip' ? 'selected' : '' }}>Drip Campaign</option>
                                    <option value="followup" {{ old('trigger_type', $rule->trigger_type) === 'followup' ? 'selected' : '' }}>Followup Reminder</option>
                                    <option value="scheduled" {{ old('trigger_type', $rule->trigger_type) === 'scheduled' ? 'selected' : '' }}>Scheduled Message</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Priority</label>
                                <input type="number" name="priority" class="form-control" value="{{ old('priority', $rule->priority) }}" min="0" max="100">
                            </div>
                        </div>

                        <div class="mb-3" id="keywordsSection">
                            <label class="form-label fw-semibold">Trigger Keywords</label>
                            <input type="text" name="trigger_keywords" class="form-control"
                                value="{{ old('trigger_keywords', $rule->trigger_keywords) }}"
                                placeholder="price, cost, how much (comma-separated)">
                        </div>

                        <hr>
                        <h6 class="fw-bold mb-3">Response Configuration</h6>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Use WhatsApp Template</label>
                            <select name="template_id" class="form-select" id="templateSelect" onchange="toggleResponseMessage()">
                                <option value="">-- No template, use custom message --</option>
                                @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}" {{ old('template_id', $rule->template_id) == $tpl->id ? 'selected' : '' }}>
                                    {{ $tpl->name }} ({{ $tpl->language }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" id="responseMessageSection">
                            <label class="form-label fw-semibold">Custom Response Message</label>
                            <textarea name="response_message" class="form-control" rows="4">{{ old('response_message', $rule->response_message) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                                    {{ old('is_active', $rule->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>

                        <div class="alert alert-light border">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                This rule has been executed <strong>{{ number_format($rule->execution_count) }}</strong> times.
                                Last executed: {{ $rule->last_executed_at?->diffForHumans() ?? 'Never' }}
                            </small>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex gap-2 justify-content-end">
                        <a href="{{ route('whatsapp.automation.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Update Rule
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updateTriggerUI() {
    const type = document.getElementById('triggerType').value;
    document.getElementById('keywordsSection').style.display = ['keyword', 'faq'].includes(type) ? 'block' : 'none';
}
function toggleResponseMessage() {
    const templateId = document.getElementById('templateSelect').value;
    document.getElementById('responseMessageSection').style.display = templateId ? 'none' : 'block';
}
updateTriggerUI();
toggleResponseMessage();
</script>
@endpush
