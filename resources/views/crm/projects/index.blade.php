@extends('layouts.app')

@section('page_title', 'Projects')

@section('content')
<div class="container-fluid p-0">
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-header border-bottom-0 py-3 px-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <h4 class="fw-bold mb-0">Manage Projects</h4>
                <div class="d-flex flex-wrap gap-2">
                    @can('projects.view')
                    <a href="{{ route('projects.export') }}" class="btn btn-outline-dark-blue">
                        <i class="fa-solid fa-download me-1"></i>Export
                    </a>
                    @endcan
                    @can('projects.create')
                    <a href="{{ route('projects.create') }}" class="btn btn-dark-blue">
                        <i class="bi bi-plus-lg me-1"></i>Add Project
                    </a>
                    @endcan
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h6 class="fw-bold mb-0">Active Projects</h6>
                <div class="input-group input-group-sm" style="max-width: 300px; width: 100%;">
                    <span class="input-group-text crm-search-icon border-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control crm-search-input border-0" placeholder="Search projects..." id="projectsSearch">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 responsive-table" id="projectsTable">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 80px;">Sr.No</th>
                            <th>Project Information</th>
                            <th class="d-none d-md-table-cell">Customer</th>
                            <th class="d-none d-md-table-cell">Status</th>
                            <th class="text-start d-none d-md-table-cell">Timeline</th>
                            <th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th>
                            <th class="text-center d-md-none" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            
            {{-- Pagination --}}
            <div id="paginationContainer" class="card-footer border-top-0 py-4 px-4"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
@endpush

@push('scripts')
@include('crm.partials.module-permissions', [
    'module' => 'projects',
    'permissions' => [
        'view' => auth()->user()?->hasMatrixPermission('view_projects'),
        'create' => auth()->user()?->hasMatrixPermission('create_projects'),
        'edit' => auth()->user()?->hasMatrixPermission('edit_projects'),
        'delete' => auth()->user()?->hasMatrixPermission('delete_projects'),
    ],
])
<script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/project.js') }}"></script>
@endpush
