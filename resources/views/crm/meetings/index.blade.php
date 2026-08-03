@extends('layouts.app')

@section('page_title', 'Meetings')

@section('content')
<div class="container-fluid p-0">

    <div class="card border-0 shadow-sm overflow-hidden" id="googleCalendarSection">
        <div class="card-header bg-white border-bottom-0 py-3 px-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div class="d-flex align-items-center gap-3">
                    <h4 class="fw-bold mb-0">Manage Meetings</h4>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @can('meetings.edit')
                    <button type="button" id="connectGoogleBtn" class="btn btn-outline-primary" onclick="GoogleCalendar.connect()">
                        <i class="bi bi-google me-1"></i>Connect Google Calendar
                    </button>
                    <button type="button" id="disconnectGoogleBtn" class="btn btn-outline-danger" onclick="GoogleCalendar.disconnect()" style="display: none;">
                        <i class="bi bi-x-circle me-1"></i>Disconnect
                    </button>
                    @endcan
                    @can('meetings.view')
                    <a href="{{ route('meetings.export') }}" class="btn btn-outline-dark-blue">
                        <i class="fa-solid fa-download me-1"></i>Export
                    </a>
                    @endcan
                    @can('meetings.create')
                    <a href="{{ route('meetings.create') }}" class="btn btn-dark-blue">
                        <i class="bi bi-plus-lg me-1"></i>Add Meeting
                    </a>
                    @endcan
                </div>
            </div>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h6 class="fw-bold mb-0">All Meetings</h6>
                <div class="input-group input-group-sm" style="max-width: 300px; width: 100%;">
                    <span class="input-group-text crm-search-icon border-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control crm-search-input border-0" placeholder="Search meetings..." id="meetingsSearch" value="{{ request('search') }}">
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 responsive-table">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 d-none d-md-table-cell">Sr.No</th>
                            <th class="ps-4">Title & Customer</th>
                            <th class="text-start">Scheduled At</th>
                            <th class="d-none d-md-table-cell">Location</th>
                            <th class="d-none d-md-table-cell">Staff</th>
                            <th class="d-none d-md-table-cell">Calender</th>
                            <th class="d-none d-md-table-cell">Status</th>
                            <th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th>
                            <th class="text-center d-md-none" style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <div id="meetingPaginationContainer" class="px-4 pb-3 pt-0"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
    <link rel="stylesheet"
        href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
@endpush

@push('scripts')
    @include('crm.partials.module-permissions', [
        'module' => 'meetings',
        'permissions' => [
            'view' => auth()->user()?->hasMatrixPermission('view_meetings'),
            'create' => auth()->user()?->hasMatrixPermission('create_meetings'),
            'edit' => auth()->user()?->hasMatrixPermission('edit_meetings'),
            'delete' => auth()->user()?->hasMatrixPermission('delete_meetings'),
        ],
    ])
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/meeting.js') }}"></script>
@endpush
