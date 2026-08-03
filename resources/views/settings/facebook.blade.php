@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Settings Panel -->
        <div class="col-md-4">
            <div class="card shadow border-0 rounded-4 overflow-hidden mb-4" style="background: rgba(255,255,255,0.85); backdrop-filter: blur(10px);">
                <div class="card-header border-0 bg-primary text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fab fa-facebook me-2"></i>Meta API Configuration</h5>
                </div>
                <div class="card-body p-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted">Facebook App ID</label>
                            <input type="text" name="facebook_app_id" class="form-control rounded-3" value="{{ App\Models\Setting::getValue('facebook_app_id') }}" placeholder="Enter Meta App ID">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted">Facebook App Secret</label>
                            <input type="password" name="facebook_app_secret" class="form-control rounded-3" value="{{ App\Models\Setting::getValue('facebook_app_secret') }}" placeholder="••••••••••••••••">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-muted">Webhook Verify Token</label>
                            <input type="text" name="facebook_verify_token" class="form-control rounded-3" value="{{ App\Models\Setting::getValue('facebook_verify_token', 'fablead_meta_leads_verify') }}" placeholder="Verify Token">
                            <small class="text-muted d-block mt-1">Configure this token inside your Meta app webhook setup.</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-bold"><i class="fas fa-save me-2"></i>Save Configurations</button>
                    </form>
                </div>
            </div>

            @if($hasCredentials)
            <div class="card shadow border-0 rounded-4 overflow-hidden" style="background: rgba(255,255,255,0.85); backdrop-filter: blur(10px);">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold mb-3">Connect to Meta</h5>
                    <p class="text-muted small">Authorize this CRM application to fetch your Pages, Lead Forms, Ads Campaigns, and sync real-time leads.</p>
                    <a href="{{ route('facebook.connect') }}" class="btn btn-primary btn-lg w-100 rounded-3 py-2 fw-bold text-white shadow-sm" style="background-color: #1877F2; border: none;">
                        <i class="fab fa-facebook-f me-2"></i>Connect Facebook Account
                    </a>
                </div>
            </div>
            @endif
        </div>

        <!-- Sync Actions & Dynamic Tabs -->
        <div class="col-md-8">
            <div class="card shadow border-0 rounded-4 overflow-hidden mb-4" style="background: rgba(255,255,255,0.85); backdrop-filter: blur(10px);">
                <div class="card-header border-0 bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-link me-2"></i>Connected Meta Accounts</h5>
                </div>
                <div class="card-body p-4">
                    @if($isConnected)
                        @foreach($accounts as $account)
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-4 shadow-sm border border-light">
                            <div class="d-flex align-items-center">
                                <div class="avatar bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center fw-bold" style="width: 50px; height: 50px; background-color: #1877F2; font-size: 20px;">
                                    {{ strtoupper(substr($account->name, 0, 1)) }}
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">{{ $account->name }}</h6>
                                    <span class="badge bg-success text-white rounded-pill">Active Integration</span>
                                </div>
                            </div>
                            <div>
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 me-2" onclick="syncAllData({{ $account->id }}, this)">
                                    <i class="fas fa-sync me-1"></i>Sync Everything
                                </button>
                                <form action="{{ route('facebook.disconnect', $account->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Disconnect Facebook?')">
                                        <i class="fas fa-unlink me-1"></i>Disconnect
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Pages list -->
                        <h6 class="fw-bold text-dark mt-4 mb-3"><i class="fas fa-file-alt me-2 text-primary"></i>Connected Pages</h6>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Page Name</th>
                                        <th>Page ID</th>
                                        <th>Realtime Webhook</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($account->pages as $page)
                                    <tr>
                                        <td class="fw-semibold text-dark">{{ $page->name }}</td>
                                        <td><code>{{ $page->page_id }}</code></td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="switch-{{ $page->id }}" {{ $page->is_subscribed ? 'checked' : '' }} onchange="toggleSubscription({{ $page->id }}, this)">
                                                <label class="form-check-label text-muted small" for="switch-{{ $page->id }}">Subscribed</label>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-secondary text-white rounded-pill px-3 py-1">Webhook Monitored</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endforeach
                    @else
                    <div class="text-center py-5">
                        <img src="https://img.icons8.com/color/96/facebook.png" alt="No Accounts" class="mb-3 img-fluid">
                        <h6 class="fw-bold text-muted">No Meta Accounts Connected Yet</h6>
                        <p class="text-muted small px-5">Add your Meta App credentials in the configuration panel on the left, then authorize your account to connect Facebook Pages and Leads Forms.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncAllData(accountId, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing...';

    fetch(`/settings/facebook/${accountId}/sync`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if(data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        console.error('Error syncing:', error);
        alert('Sync execution experienced a connectivity issue.');
    });
}

function toggleSubscription(pageId, toggle) {
    const isChecked = toggle.checked;
    
    fetch(`/settings/facebook/page/${pageId}/subscription`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ subscribe: isChecked })
    })
    .then(response => response.json())
    .then(data => {
        if(!data.success) {
            toggle.checked = !isChecked; // Revert
            alert(data.message);
        }
    })
    .catch(error => {
        toggle.checked = !isChecked; // Revert
        console.error('Error changing webhook subscription:', error);
    });
}
</script>
@endsection
