@extends('layouts.app')

@section('page_title', 'Leads')

@section('content')
    <div class="container-fluid p-0">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white border-bottom-0 py-3 px-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <div>
                        <h4 class="fw-bold mb-0">Manage Leads</h4>
                        <p class="text-muted small mb-0">Track and manage your sales pipeline and enquiries.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @can('leads.view')
                            <a href="{{ route('leads.export') }}" class="btn btn-outline-dark-blue">
                                <i class="fa-solid fa-download me-1"></i>Export
                            </a>
                        @endcan
                        @can('leads.create')
                            <a href="{{ route('leads.create') }}" class="btn btn-dark-blue">
                                <i class="bi bi-plus-lg me-1"></i>Add Lead
                            </a>
                        @endcan
                    </div>
                </div>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h6 class="fw-bold mb-0">Active Enquiries</h6>
                    <div class="input-group input-group-sm" style="max-width: 300px; width: 100%;">
                        <span class="input-group-text crm-search-icon border-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control crm-search-input border-0" placeholder="Search leads..."
                            id="leadsSearch" value="{{ request('search') }}">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="leadsTable">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 80px;">Sr.No</th>
                                <th>Lead Name</th>
                                <th class="d-none d-md-table-cell">Lead Source</th>
                                <th class="d-none d-md-table-cell">Created By</th>
                                <th class="d-none d-md-table-cell">Created At</th>
                                <th class="d-none d-md-table-cell">Status</th>
                                <th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th>
                                <th class="text-center d-md-none" style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="card-footer border-top-0 py-4 px-4" id="leadsPagination"></div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet"
        href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
    <link rel="stylesheet"
        href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/leads.css') }}?v={{ filemtime(public_path('css/leads.css')) }}">
@endpush

@push('scripts')
    @include('crm.partials.module-permissions', [
        'module' => 'leads',
        'permissions' => [
            'view' => auth()->user()?->hasMatrixPermission('view_leads'),
            'create' => auth()->user()?->hasMatrixPermission('create_leads'),
            'edit' => auth()->user()?->hasMatrixPermission('edit_leads'),
            'delete' => auth()->user()?->hasMatrixPermission('delete_leads'),
        ],
    ])
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/leads.js') }}?v={{ filemtime(public_path('js/leads.js')) }}"></script>
@endpush
