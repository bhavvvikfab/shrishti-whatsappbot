@extends('layouts.masters')

@section('page_title', 'Masters - Customers')

@section('masters_content')

    @push('styles')
        <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
        <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/users.css') }}?v={{ filemtime(public_path('css/users.css')) }}">
    @endpush


    <div class="card-header border-bottom-0 py-3 px-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold mb-0">Manage Customers</h4>
                <p class="text-muted small mb-0">View and manage your customer database and communication history.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('customers.create')
                    <button type="button" class="btn btn-outline-dark-blue" onclick="showImportDialog()">
                        <i class="fa-solid fa-upload me-1"></i>Import CSV
                    </button>
                @endcan
                @can('customers.view')
                    <a href="{{ route('masters.customers.export') }}" class="btn btn-outline-dark-blue">
                        <i class="fa-solid fa-download me-1"></i>Export
                    </a>
                @endcan
                @can('customers.create')
                    <a href="{{ route('masters.customers.create') }}" class="btn btn-dark-blue">
                        <i class="fa-solid fa-plus me-1"></i>Add Customer
                    </a>
                @endcan
            </div>
        </div>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <h6 class="fw-bold mb-0">Active Customers</h6>
            <div class="input-group input-group-sm" style="max-width: 300px; width: 100%;">
                <span class="input-group-text crm-search-icon border-0"><i class="fa-solid fa-search"></i></span>
                <input type="text" id="customerSearch" class="form-control crm-search-input border-0"
                    placeholder="Search customers..." name="search" value="{{ request('search') }}">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="customerTable" class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 80px;">Sr.No</th>
                        <th>Customer name</th>
                        <th class="d-none d-md-table-cell">Email</th>
                        <th class="d-none d-md-table-cell">Phone</th>
                        <th class="d-none d-md-table-cell">Created At</th>
                        <th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th>
                        <th class="text-center d-md-none" style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="customersTable"></tbody>
            </table>
        </div>

        <!-- Pagination Container -->
        <div id="customerPaginationContainer" class="card-footer border-top-0 py-4 px-4"></div>
    </div>

    <form id="customersImportForm" class="d-none" enctype="multipart/form-data">
        @csrf
        <input type="file" name="import_file" id="customersImportFile" accept=".csv,text/csv">
    </form>
@endsection

@push('styles')
    <style>
        .customer-action-disabled,
        .customer-action-disabled:hover,
        .customer-action-disabled:focus {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            opacity: 1;
            box-shadow: none;
        }

        [data-theme="dark"] .customer-action-disabled,
        [data-theme="dark"] .customer-action-disabled:hover,
        [data-theme="dark"] .customer-action-disabled:focus {
            background-color: #1e293b;
            border-color: rgba(255, 255, 255, .08);
            color: #64748b;
        }
    </style>
@endpush

@push('scripts')
    @include('crm.partials.module-permissions', [
        'module' => 'customers',
        'permissions' => [
            'view' => auth()->user()?->hasMatrixPermission('view_customers'),
            'create' => auth()->user()?->hasMatrixPermission('create_customers'),
            'edit' => auth()->user()?->hasMatrixPermission('edit_customers'),
            'delete' => auth()->user()?->hasMatrixPermission('delete_customers'),
        ],
    ])
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/customer.js') }}?v={{ filemtime(public_path('js/customer.js')) }}"></script>
@endpush