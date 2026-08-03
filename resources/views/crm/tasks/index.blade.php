@extends('layouts.app')

@section('page_title', 'Tasks')

@push('styles')
    <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
@endpush

@section('content')
<div class="container-fluid p-0">
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-header border-bottom-0 py-3 px-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h4 class="fw-bold mb-0">Manage Tasks</h4>
                    <p class="text-muted small mb-0">Track team tasks, priorities, and deadlines.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('tasks.view')
                    <a href="{{ route('tasks.export') }}" class="btn btn-outline-dark-blue">
                        <i class="fa-solid fa-download me-1"></i>Export
                    </a>
                    @endcan
                    @can('tasks.create')
                    <a href="{{ route('tasks.create') }}" class="btn btn-dark-blue">
                        <i class="bi bi-plus-lg me-1"></i>Add Task
                    </a>
                    @endcan
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h6 class="fw-bold mb-0">Active Tasks</h6>
                <div class="input-group input-group-sm" style="max-width: 300px; width: 100%;">
                    <span class="input-group-text crm-search-icon border-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control crm-search-input border-0" placeholder="Search tasks..." id="tasksSearch">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 responsive-table" id="tasksTable">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 80px;">Sr.No</th>
                            <th>Task Info</th>
                            <th class="d-none d-md-table-cell">Project Name</th>
                            <th class="d-none d-md-table-cell">Priority</th>
                            <th class="d-none d-md-table-cell">Status</th>
                            <th class="d-none d-md-table-cell">Due Date</th>
                            <th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th>
                            <th class="text-center d-md-none" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="card-footer border-top-0 py-4 px-4">
                <div id="tasksPagination"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('crm.partials.module-permissions', [
    'module' => 'tasks',
    'permissions' => [
        'view' => auth()->user()?->hasMatrixPermission('view_tasks'),
        'create' => auth()->user()?->hasMatrixPermission('create_tasks'),
        'edit' => auth()->user()?->hasMatrixPermission('edit_tasks'),
        'delete' => auth()->user()?->hasMatrixPermission('delete_tasks'),
    ],
])
<script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/tasks.js') }}?v={{ filemtime(public_path('js/tasks.js')) }}"></script>
@endpush
