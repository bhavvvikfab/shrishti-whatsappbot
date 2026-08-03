@extends('layouts.app')

@section('page_title', 'Settings')

@push('styles')
<link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/users.css') }}?v={{ filemtime(public_path('css/users.css')) }}">
<link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/settings.css') }}?v={{ filemtime(public_path('css/settings.css')) }}">
@endpush

@section('content')
@php
$logoPath = $settings['company_logo_path']->value ?? null;
$logoUrl = $logoPath && Storage::disk('public')->exists($logoPath)
? url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . $logoPath)
: 'https://crm.fableadtech.com/public/assets/img/logos/fabcrmlogo.png';
@endphp

<div class="container-fluid px-0 settings-shell">
    <div class="d-flex justify-content-between align-items-center mb-3 settings-page-head">
        <div>
            <h1 class="h4 mb-1">Settings</h1>
            <p class="text-muted small mb-0">Manage SMTP, keys, WhatsApp and integration preferences.</p>
        </div>
    </div>

    <div class="settings-tabs-wrap">
        <ul class="nav settings-main-tabs flex-wrap" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab"
                    data-bs-target="#whatsapp-configure" type="button" role="tab">WhatsApp Configure Settings</button>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="whatsapp-configure" role="tabpanel">
            <div class="settings-panel">
                <div class="settings-panel-head">WhatsApp Configure Settings</div>
                <div class="settings-panel-body">
                    <div class="row g-3 g-md-4">
                        <div class="col-12 col-md-6">
                            <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data"
                                id="whatsappModuleSettingsForm" data-reload-on-success="true" data-autosave-on-change="true">
                                @csrf
                                <div class="settings-section settings-toggle-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="settings-section-title mb-2">Module Switch</div>
                                            <div class="fw-semibold text-dark">Enable or disable the full WhatsApp module</div>
                                            <div class="settings-inline-help mt-2">When disabled, sidebar WhatsApp menu, integrations, message sending, and WhatsApp routes stay blocked.</div>
                                        </div>
                                        <div class="form-check form-switch settings-role-switch">
                                            <input type="hidden" name="whatsapp_module_enabled" value="0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="whatsapp_module_enabled" name="whatsapp_module_enabled" value="1"
                                                {{ ($settings['whatsapp_module_enabled']->value ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-start align-items-center flex-wrap gap-3 mt-4">
                                    <span id="whatsappModuleSettingsStatus" class="settings-form-status"></span>
                                </div>
                            </form>
                        </div>
                        <div class="col-12 col-md-6">
                            @if ($whatsappModuleEnabled)
                            <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data"
                                id="whatsappAutoAiSettingsForm" data-autosave-on-change="true" class="mb-4">
                                @csrf
                                <div class="settings-section settings-toggle-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="settings-section-title mb-2">Auto AI Switch</div>
                                            <div class="fw-semibold text-dark">Enable or disable Auto AI for all WhatsApp inbox chats</div>
                                            <div class="settings-inline-help mt-2">When disabled, the chat-level Auto AI switch stays hidden and delayed AI replies will not run for any WhatsApp conversation.</div>
                                        </div>
                                        <div class="form-check form-switch settings-role-switch">
                                            <input type="hidden" name="whatsapp_auto_ai_enabled" value="0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="whatsapp_auto_ai_enabled" name="whatsapp_auto_ai_enabled" value="1"
                                                {{ ($settings['whatsapp_auto_ai_enabled']->value ?? '1') === '1' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-start align-items-center flex-wrap gap-3 mt-4">
                                    <span id="whatsappAutoAiSettingsStatus" class="settings-form-status"></span>
                                </div>
                            </form>
                            @endif
                        </div>
                    </div>





                    @if (! $whatsappModuleEnabled)
                    <div class="alert alert-warning d-flex align-items-start mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                        <div>
                            <strong>WhatsApp module is disabled.</strong>
                            <div>Enable the module above to load configuration fields and templates.</div>
                        </div>
                    </div>
                    @else
                    <div class="mb-4">
                        <div>
                            <h4 class="fw-bold mb-1" style="color:#33496d;">Configure your WhatsApp API settings <span
                                    class="settings-status-badge settings-inline-status"><span
                                        class="settings-status-dot"></span>Connected</span></h4>
                            <div class="text-muted">Enter your WhatsApp App details and WhatsApp Business credentials.
                            </div>
                        </div>
                    </div>

                    <ul class="nav settings-subtabs mb-4" id="waSettingsTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="wa-config-tab"
                                data-bs-toggle="pill" data-bs-target="#wa-config-pane" type="button"
                                role="tab">Configuration</button></li>
                        <li class="nav-item d-none" role="presentation"><button class="nav-link" id="wa-templates-tab"
                                data-bs-toggle="pill" data-bs-target="#wa-templates-pane" type="button"
                                role="tab">Message Templates</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="wa-config-pane" role="tabpanel">
                            <div class="alert alert-primary d-flex align-items-start mb-4">
                                <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                                <div><strong>Credentials are saved in the database.</strong>
                                    <div>They load automatically when you open this tab. Update them whenever you rotate tokens in Meta.</div>
                                </div>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="text-dark bi bi-app me-2"></i>WhatsApp App ID
                                        <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="wa_app_id">
                                    <div class="invalid-feedback" id="wa_app_id_error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i
                                            class="text-dark bi bi-shield-lock-fill me-2"></i>WhatsApp App Secret <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="wa_app_secret">
                                    <div class="invalid-feedback" id="wa_app_secret_error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="text-dark bi bi-telephone-fill me-2"></i>Phone
                                        Number ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="wa_phone_number_id">
                                    <div class="invalid-feedback" id="wa_phone_number_id_error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="text-dark bi bi-building-fill me-2"></i>WhatsApp
                                        Business Account ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="wa_business_account_id">
                                    <div class="invalid-feedback" id="wa_business_account_id_error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="text-dark bi bi-key-fill me-2"></i>Access Token
                                        <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="wa_access_token">
                                    <div class="invalid-feedback" id="wa_access_token_error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="text-dark bi bi-link-45deg me-2"></i>Webhook
                                        URL</label>
                                    <input type="text" class="form-control" id="wa_webhook_url"
                                        placeholder="https://your-domain.com/whatsapp-configration/webhook">
                                    <div class="form-text">Paste this same URL in Meta → WhatsApp → Configuration → Webhook.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label"><i class="text-dark bi bi-shield-check me-2"></i>Webhook
                                        Verify Token</label>
                                    <input type="text" class="form-control" id="wa_verify_token"
                                        placeholder="Same token you enter in Meta webhook setup">
                                    <div class="form-text">Must match Meta exactly. Saved in database (overrides .env when set).</div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4">
                                <span class="small" id="wa_status_msg"></span>
                                <button type="button" class="btn btn-primary settings-submit-btn" id="wa_save_btn"><i
                                        class="bi bi-floppy-fill me-1"></i> Save Configuration</button>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="wa-templates-pane" role="tabpanel">
                            <div class="wa-templates-title">WhatsApp Message Templates</div>
                            <div class="wa-templates-subtitle">View and manage your WhatsApp message templates from
                                WhatsApp. Stored in database; refresh to sync from API.</div>

                            <div class="wa-templates-toolbar">
                                <div class="wa-templates-toolbar-left">
                                    <button type="button" class="btn btn-primary wa-templates-refresh-btn"
                                        id="wa_templates_refresh"><i class="bi bi-arrow-repeat me-1"></i> Refresh
                                        Templates</button>
                                    <div class="wa-templates-search-wrap"><input type="text" class="form-control"
                                            id="wa_templates_search" placeholder="Search templates..."></div>
                                </div>
                                <div class="wa-templates-show"><span>Show</span><select id="wa_templates_show"
                                        class="form-select">
                                        <option value="10" selected>10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select><span>entries</span></div>
                            </div>

                            <div class="table-responsive wa-templates-table-wrap">
                                <table class="table align-middle mb-0" id="wa_templates_table"
                                    data-module-options='@json($whatsappModuleOptions)'>
                                    <thead class="table-light">
                                        <tr>
                                            <th>Templates</th>
                                            <th class="d-none d-md-table-cell" style="width: 30%;">Use For Module</th>
                                            <th class="d-none d-md-table-cell" style="width: 20%;">Status</th>
                                            <th class="d-none d-md-table-cell" style="width: 20%;">Active/Inactive</th>
                                            <th class="text-center d-md-none" style="width: 80px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($whatsappTemplates as $template)
                                        <tr data-template-row="main" data-template-id="{{ $template->id }}">
                                            <td class="wa-templates-name">{{ $template->name }}</td>
                                            <td class="d-none d-md-table-cell">
                                                <select class="form-select form-select-sm wa-template-module-select"
                                                    data-template-id="{{ $template->id }}">
                                                    <option value="">-- Select --</option>
                                                    @foreach($whatsappModuleOptions as $key => $label)
                                                    <option value="{{ $key }}" {{ $template->use_for_module === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="d-none d-md-table-cell"><span class="wa-templates-status-badge">{{ $template->status }}</span>
                                            </td>
                                            <td class="d-none d-md-table-cell"><select class="form-select form-select-sm wa-template-status-select"
                                                    data-template-id="{{ $template->id }}">
                                                    <option value="1" {{ $template->is_active ? 'selected' : '' }}>
                                                        Active</option>
                                                    <option value="0" {{ !$template->is_active ? 'selected' : '' }}>
                                                        Inactive</option>
                                                </select></td>
                                            <td class="text-center d-md-none">
                                                <button type="button" class="btn-user-expand"
                                                    data-template-id="{{ $template->id }}">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="details-row d-md-none border-0" data-template-row="details"
                                            id="wa-template-details-{{ $template->id }}" style="display: none;">
                                            <td colspan="5" class="p-0">
                                                <div class="details-content">
                                                    <div class="row g-3">
                                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                                            <div class="expand-label"><i class="fa-solid fa-puzzle-piece"></i> Use For Module :</div>
                                                            <div class="expand-value">
                                                                <select class="form-select form-select-sm wa-template-module-select"
                                                                    data-template-id="{{ $template->id }}">
                                                                    <option value="">-- Select --</option>
                                                                    @foreach($whatsappModuleOptions as $key => $label)
                                                                    <option value="{{ $key }}" {{ $template->use_for_module === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                                            <div class="expand-label"><i class="fa-solid fa-signal"></i> Status :</div>
                                                            <div class="expand-value"><span class="wa-templates-status-badge">{{ $template->status }}</span></div>
                                                        </div>
                                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                                            <div class="expand-label"><i class="fa-solid fa-toggle-on"></i> Active / Inactive :</div>
                                                            <div class="expand-value">
                                                                <select class="form-select form-select-sm wa-template-status-select"
                                                                    data-template-id="{{ $template->id }}">
                                                                    <option value="1" {{ $template->is_active ? 'selected' : '' }}>Active</option>
                                                                    <option value="0" {{ !$template->is_active ? 'selected' : '' }}>Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No WhatsApp message
                                                templates found in database.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3"
                                id="wa_templates_pagination"></div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
    window.settingsPageConfig = {
        apiSettingsIndex: @json(route('api.settings.index')),
        apiSettingsUpdate: @json(route('api.settings.update')),
        whatsappModuleEnabled: @json($whatsappModuleEnabled),
    };
</script>
<script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'assets/js/setting.js') }}?v={{ filemtime(public_path('assets/js/setting.js')) }}"></script>
<script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/settings-page.js') }}?v={{ filemtime(public_path('js/settings-page.js')) }}"></script>
@endpush
@endsection
