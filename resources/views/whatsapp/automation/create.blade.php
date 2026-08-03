@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('whatsapp.automation.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="mb-0">Create Automation Rule</h4>
            <p class="text-muted mb-0 small">Set up automatic responses for WhatsApp messages</p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form action="{{ route('whatsapp.automation.store') }}" method="POST">
                @csrf
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Rule Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="e.g. Pricing Inquiry Reply" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Trigger Type <span class="text-danger">*</span></label>
                                <select name="trigger_type" class="form-select" id="triggerType" onchange="updateTriggerUI()">
                                    <option value="keyword" {{ old('trigger_type') === 'keyword' ? 'selected' : '' }}>Keyword Match</option>
                                    <option value="welcome" {{ old('trigger_type') === 'welcome' ? 'selected' : '' }}>Welcome Message (First Contact)</option>
                                    <option value="faq" {{ old('trigger_type') === 'faq' ? 'selected' : '' }}>FAQ Automation</option>
                                    <option value="drip" {{ old('trigger_type') === 'drip' ? 'selected' : '' }}>Drip Campaign</option>
                                    <option value="followup" {{ old('trigger_type') === 'followup' ? 'selected' : '' }}>Followup Reminder</option>
                                    <option value="scheduled" {{ old('trigger_type') === 'scheduled' ? 'selected' : '' }}>Scheduled Message</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Priority</label>
                                <input type="number" name="priority" class="form-control" value="{{ old('priority', 0) }}" min="0" max="100">
                                <div class="form-text">Higher priority rules are matched first (0-100)</div>
                            </div>
                        </div>

                        <div class="mb-3" id="keywordsSection">
                            <label class="form-label fw-semibold">Trigger Keywords</label>
                            <input type="text" name="trigger_keywords" class="form-control @error('trigger_keywords') is-invalid @enderror"
                                value="{{ old('trigger_keywords') }}" placeholder="price, cost, how much, pricing (comma-separated)">
                            <div class="form-text">Separate multiple keywords with commas. The rule triggers if any keyword is found in the message.</div>
                            @error('trigger_keywords')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <hr>
                        <h6 class="fw-bold mb-3">Response Configuration</h6>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Use WhatsApp Template</label>
                            <select name="template_id" class="form-select" id="templateSelect" onchange="toggleResponseMessage()">
                                <option value="">-- No template, use custom message --</option>
                                @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}" {{ old('template_id') == $tpl->id ? 'selected' : '' }}>
                                    {{ $tpl->name }} ({{ $tpl->language }})
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text">Select an approved WhatsApp template, or write a custom text message below.</div>
                        </div>

                        <div class="mb-3" id="responseMessageSection">
                            <label class="form-label fw-semibold">Custom Response Message</label>
                            <textarea name="response_message" class="form-control @error('response_message') is-invalid @enderror"
                                rows="4" placeholder="Type your auto-reply message here...">{{ old('response_message') }}</textarea>
                            @error('response_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                                    {{ old('is_active', '1') ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Active (rule will trigger automatically)</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex gap-2 justify-content-end">
                        <a href="{{ route('whatsapp.automation.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i>Create Rule
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
    const keywordsSection = document.getElementById('keywordsSection');
    keywordsSection.style.display = ['keyword', 'faq'].includes(type) ? 'block' : 'none';
}

function toggleResponseMessage() {
    const templateId = document.getElementById('templateSelect').value;
    const section = document.getElementById('responseMessageSection');
    section.style.display = templateId ? 'none' : 'block';
}

updateTriggerUI();
toggleResponseMessage();
</script>
@endpush
