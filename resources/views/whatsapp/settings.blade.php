@extends('layouts.app')

@section('page_title', 'WhatsApp Settings')

@push('styles')
<link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/settings.css') }}?v={{ filemtime(public_path('css/settings.css')) }}">
@endpush

@section('content')
<div class="container-fluid px-0 settings-shell">
    <div class="d-flex justify-content-between align-items-center mb-3 settings-page-head">
        <div>
            <h1 class="h4 mb-1">WhatsApp Bot Settings</h1>
            <p class="text-muted small mb-0">Configure your WhatsApp API credentials or use the admin bot when shared access is granted.</p>
        </div>
        <a href="{{ route('whatsapp.inbox') }}" class="btn btn-outline-dark-blue btn-sm">
            <i class="fab fa-whatsapp me-1"></i> Open Inbox
        </a>
    </div>

    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @if (! $whatsappModuleEnabled)
        <div class="alert alert-warning">WhatsApp module is disabled by admin.</div>
    @elseif ($usingShared)
        <div class="alert alert-info d-flex align-items-start">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>
                <strong>You are using the admin WhatsApp bot.</strong>
                <div class="small mt-1">Inbox and messages use the admin configuration. To use your own bot, ask admin to remove shared access, then save your credentials below.</div>
            </div>
        </div>
    @endif

    @if ($whatsappModuleEnabled && $canEdit)
        <div class="settings-panel">
            <div class="settings-panel-head">API Configuration</div>
            <div class="settings-panel-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">WhatsApp App ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wa_app_id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">WhatsApp App Secret <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wa_app_secret">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wa_phone_number_id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Business Account ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wa_business_account_id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Access Token <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wa_access_token">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Webhook URL</label>
                        <input type="text" class="form-control" id="wa_webhook_url"
                            placeholder="{{ \App\Models\WhatsappConfig::webhookCallbackUrl() }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Webhook Verify Token</label>
                        <input type="text" class="form-control" id="wa_verify_token">
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4">
                    <span class="small" id="wa_status_msg"></span>
                    <button type="button" class="btn btn-primary settings-submit-btn" id="wa_save_btn">
                        <i class="bi bi-floppy-fill me-1"></i> Save Configuration
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    window.settingsPageConfig = {
        whatsappModuleEnabled: @json($whatsappModuleEnabled),
    };
</script>
<script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'assets/js/setting.js') }}?v={{ filemtime(public_path('assets/js/setting.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.settingsPageConfig.whatsappModuleEnabled) {
            if (typeof window.loadWhatsappConfig === 'function') {
                window.loadWhatsappConfig();
            }
        }
    });
</script>
@endpush
