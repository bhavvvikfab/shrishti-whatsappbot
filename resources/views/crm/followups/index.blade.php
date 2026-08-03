@extends('layouts.app')

@section('page_title', 'Follow Ups')

@section('content')
<div class="container-fluid p-0">

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-header border-bottom-0 py-3 px-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h4 class="fw-bold mb-0">Manage Follow Ups</h4>
                    <p class="text-muted small mb-0">Track and manage your follow up tasks.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('followups.view')
                    <a href="{{ route('followups.export') }}" class="btn btn-outline-dark-blue">
                        <i class="fa-solid fa-download me-1"></i>Export
                    </a>
                    @endcan
                    @can('followups.create')
                    <a href="{{ route('followups.create') }}" class="btn btn-dark-blue">
                        <i class="bi bi-plus-lg me-1"></i>Add Follow Up
                    </a>
                    @endcan
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h6 class="fw-bold mb-0">Active Follow Ups</h6>
                <div class="input-group input-group-sm" style="max-width: 300px; width: 100%;">
                    <span class="input-group-text crm-search-icon border-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="followUpSearch" class="form-control crm-search-input border-0" placeholder="Search follow ups..." name="search" value="{{ request('search') }}">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 responsive-table">
                    <thead>
                        <tr>
                            <th class="ps-4">Purpose & Target</th>
                            <th class="text-start">Follow Up At</th>
                            <th class="d-none d-md-table-cell">Priority</th>
                            <th class="d-none d-md-table-cell">Staff</th>
                            <th class="d-none d-md-table-cell">Status</th>
                            <th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th>
                            <th class="text-center d-md-none" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="followUpsTable"></tbody>
                </table>
            </div>
            <div id="followupPaginationContainer" class="px-4 pb-3 pt-0"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" accesskey=""href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
@endpush

@push('scripts')
    @include('crm.partials.module-permissions', [
        'module' => 'followups',
        'permissions' => [
            'view' => auth()->user()?->hasMatrixPermission('view_followups'),
            'create' => auth()->user()?->hasMatrixPermission('create_followups'),
            'edit' => auth()->user()?->hasMatrixPermission('edit_followups'),
            'delete' => auth()->user()?->hasMatrixPermission('delete_followups'),
        ],
    ])
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/followup.js') }}?v={{ filemtime(public_path('js/followup.js')) }}"></script>
@endpush
