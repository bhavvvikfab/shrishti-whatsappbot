@php
    $selectedMode = old('whatsapp_bot_mode', $whatsappBotMode ?? 'none');
    $staffConfig = $staffWhatsappConfig ?? null;
@endphp

<div class="mt-5 whatsapp-bot-setup" data-whatsapp-bot-setup>
    <div class="permission-section-title">WhatsApp Bot</div>
    <p class="text-muted small mb-3">
        Each staff uses one bot only: their own WhatsApp API config, or the admin bot when you grant shared access.
    </p>

    <div class="d-flex flex-column gap-2 mb-3">
        <label class="form-check d-flex align-items-start gap-2">
            <input type="radio" class="form-check-input mt-1" name="whatsapp_bot_mode" value="none"
                {{ $selectedMode === 'none' ? 'checked' : '' }}>
            <span>
                <span class="fw-semibold">No bot yet</span>
                <span class="d-block small text-muted">Staff can add their own config later, or you can grant shared access.</span>
            </span>
        </label>

        <label class="form-check d-flex align-items-start gap-2">
            <input type="radio" class="form-check-input mt-1" name="whatsapp_bot_mode" value="shared"
                {{ $selectedMode === 'shared' ? 'checked' : '' }}>
            <span>
                <span class="fw-semibold">Use admin WhatsApp bot</span>
                <span class="d-block small text-muted">Staff inbox uses the admin WhatsApp configuration (shared inbox).</span>
            </span>
        </label>

        <label class="form-check d-flex align-items-start gap-2">
            <input type="radio" class="form-check-input mt-1" name="whatsapp_bot_mode" value="own"
                {{ $selectedMode === 'own' ? 'checked' : '' }}>
            <span>
                <span class="fw-semibold">Separate WhatsApp bot for this staff</span>
                <span class="d-block small text-muted">Create a dedicated Meta WhatsApp API config for this staff member.</span>
            </span>
        </label>
    </div>

    <div class="whatsapp-bot-fields border rounded-3 p-3 bg-light" data-whatsapp-bot-fields style="{{ $selectedMode === 'own' ? '' : 'display:none;' }}">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="wa_app_id">App ID</label>
                <input type="text" class="form-control" id="wa_app_id" name="wa_app_id"
                    value="{{ old('wa_app_id', $staffConfig?->app_id) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="wa_app_secret">App Secret</label>
                <input type="text" class="form-control" id="wa_app_secret" name="wa_app_secret"
                    value="{{ old('wa_app_secret', $staffConfig?->app_secret) }}"
                    placeholder="{{ $staffConfig?->app_secret ? 'Leave blank to keep current' : 'Required' }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="wa_phone_number_id">Phone Number ID</label>
                <input type="text" class="form-control" id="wa_phone_number_id" name="wa_phone_number_id"
                    value="{{ old('wa_phone_number_id', $staffConfig?->phone_number_id) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="wa_business_account_id">Business Account ID</label>
                <input type="text" class="form-control" id="wa_business_account_id" name="wa_business_account_id"
                    value="{{ old('wa_business_account_id', $staffConfig?->business_account_id) }}">
            </div>
            <div class="col-md-12">
                <label class="form-label" for="wa_access_token">Access Token</label>
                <input type="text" class="form-control" id="wa_access_token" name="wa_access_token"
                    value="{{ old('wa_access_token', $staffConfig?->access_token) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="wa_webhook_url">Webhook URL</label>
                <input type="text" class="form-control" id="wa_webhook_url" name="wa_webhook_url"
                    value="{{ old('wa_webhook_url', $staffConfig?->webhook_url ?? \App\Models\WhatsappConfig::webhookCallbackUrl()) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="wa_verify_token">Verify Token</label>
                <input type="text" class="form-control" id="wa_verify_token" name="wa_verify_token"
                    value="{{ old('wa_verify_token', $staffConfig?->verify_token) }}">
            </div>
        </div>
    </div>
</div>
