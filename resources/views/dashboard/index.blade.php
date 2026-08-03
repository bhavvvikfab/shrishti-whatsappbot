@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
<div class="container-fluid p-0 dashboard-page">
    @php
    $dashboardUser = auth()->user();
    $canViewCustomers = $canViewCustomers ?? (bool) $dashboardUser;
    $canViewFollowUps = $dashboardUser?->hasMatrixPermission('view_followups') ?? false;
    $canViewLeads = $dashboardUser?->hasMatrixPermission('view_leads') ?? false;
    $canViewDeals = $dashboardUser?->hasMatrixPermission('view_deals') ?? false;
    $canViewWhatsappConversations = \App\Models\Setting::isEnabled('whatsapp_module_enabled', true)
        && ($dashboardUser?->hasMatrixPermission('view_whatsapp') ?? false);
    $canViewTasks = $dashboardUser?->hasMatrixPermission('view_tasks') ?? false;
    $canViewBookings = $dashboardUser?->hasMatrixPermission('view_bookings') ?? false;
    $canViewAnyTrend = $canViewLeads || $canViewFollowUps || $canViewCustomers || $canViewDeals;
    $dashboardHref = fn (bool $canView, string $url) => $canView ? $url : 'javascript:void(0)';
    $dashboardLinkClass = fn (bool $canView, string $base = 'text-decoration-none') => trim($base . ($canView ? '' : ' dashboard-link-disabled'));
    @endphp

    <div class="row row-cols-2 row-cols-md-3 {{ $canViewWhatsappConversations ? 'row-cols-lg-5' : 'row-cols-lg-4' }} g-3 mb-2" id="dashboardStats">


        <div class="col">
            <a href="{{ $dashboardHref($canViewLeads, route('leads.index')) }}"
                class="{{ $dashboardLinkClass($canViewLeads) }}"
                @unless($canViewLeads) aria-disabled="true" tabindex="-1" @endunless>
                <div class="metric-card card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="metric-label mb-1">Leads</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="metric-value mb-0" id="metricLeads">{{ $canViewLeads ? ($stats['leads'] ?? 0) : 0 }}</h3>
                            <span class="metric-icon icon-leads"><i class="bi bi-megaphone-fill"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        


        <div class="col">
            <a href="{{ $dashboardHref($canViewFollowUps, route('followups.index')) }}"
                class="{{ $dashboardLinkClass($canViewFollowUps) }}"
                @unless($canViewFollowUps) aria-disabled="true" tabindex="-1" @endunless>
                <div class="metric-card card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="metric-label mb-1">Follow Up</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="metric-value mb-0" id="metricFollowUps">{{ $canViewFollowUps ? ($stats['follow_ups'] ?? 0) : 0 }}</h3>
                            <span class="metric-icon icon-followups"><i class="bi bi-chat-dots-fill"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col">
            <a href="{{ $dashboardHref($canViewDeals, route('deals.index')) }}"
                class="{{ $dashboardLinkClass($canViewDeals) }}"
                @unless($canViewDeals) aria-disabled="true" tabindex="-1" @endunless>
                <div class="metric-card card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="metric-label mb-1">Deals</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="metric-value mb-0" id="metricDeals">{{ $canViewDeals ? ($stats['deals'] ?? 0) : 0 }}</h3>
                            <span class="metric-icon icon-deals"><i class="bi bi-award-fill"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ $dashboardHref($canViewCustomers, route('masters.customers.index')) }}"
                class="{{ $dashboardLinkClass($canViewCustomers) }}"
                @unless($canViewCustomers) aria-disabled="true" tabindex="-1" @endunless>
                <div class="metric-card card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="metric-label mb-1">Customers</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="metric-value mb-0" id="metricCustomers">{{ $canViewCustomers ? ($stats['customers'] ?? 0) : 0 }}</h3>
                            <span class="metric-icon icon-customers"><i class="bi bi-people-fill"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        @if($canViewWhatsappConversations)
            <div class="col-12 col-md col-lg">
                <a href="{{ $dashboardHref($canViewWhatsappConversations, route('whatsapp.inbox')) }}"
                    class="{{ $dashboardLinkClass($canViewWhatsappConversations) }}"
                    @unless($canViewWhatsappConversations) aria-disabled="true" tabindex="-1" @endunless>
                    <div class="metric-card card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="metric-label mb-1">WhatsApp Inbox</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="metric-value mb-0" id="metricWhatsappConversations">{{ $stats['whatsapp_conversations'] ?? 0 }}</h3>
                                <span class="metric-icon icon-followups"><i class="bi bi-whatsapp"></i></span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endif
    </div>

    <div class="lead-board-wrapper p-0" id="leadBoardWrapper">
        <button type="button" class="lead-board-arrow lead-board-arrow--left" id="leadBoardLeft" title="Scroll Left">
            <i class="fa-solid fa-angle-left fs-5"></i>
        </button>
        <div class="status-board mb-2 px-0" id="leadBoardContainer">
            <div class="card border-0 shadow-sm w-100">
                <div class="card-body text-muted small">{{ $canViewLeads ? 'Loading lead board...' : 'No lead data available.' }}</div>
            </div>
        </div>
        <button type="button" class="lead-board-arrow lead-board-arrow--right" id="leadBoardRight" title="Scroll Right">
            <i class="fa-solid fa-angle-right fs-5"></i>
        </button>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header dashboard-widget-head dashboard-widget-head--fixed px-3">
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <ul class="nav nav-tabs dashboard-inner-tabs mb-0" id="tasksTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-all-tasks" data-bs-toggle="tab"
                                    data-bs-target="#pane-all-tasks" type="button" role="tab"
                                    aria-controls="pane-all-tasks" aria-selected="true">All Tasks</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-due-tasks" data-bs-toggle="tab"
                                    data-bs-target="#pane-due-tasks" type="button" role="tab"
                                    aria-controls="pane-due-tasks" aria-selected="false">
                                    <i class="fa-solid fa-circle-exclamation me-1 text-danger"></i>Due
                                </button>
                            </li>
                        </ul>
                        <a href="{{ $dashboardHref($canViewTasks, route('tasks.index')) }}"
                            class="{{ $dashboardLinkClass($canViewTasks, 'text-dark badge bg-light px-3 py-2 fw-semibold small') }}"
                            style="color: #0c0c0c !important;"
                            @unless($canViewTasks) aria-disabled="true" tabindex="-1" @endunless>View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pane-all-tasks" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="dashboardTasksTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%;">Task Name</th>
                                            <th style="width: 25%;" class="text-center">Priority</th>
                                            <th style="width: 25%;" class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center text-muted py-4">{{ $canViewTasks ? 'Loading tasks...' : 'No task data available.' }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="pane-due-tasks" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="dashboardDueTasksTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%;">Task Name</th>
                                            <th style="width: 25%;" class="text-center">Priority</th>
                                            <th style="width: 25%;" class="text-center">Due Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center text-muted py-4">{{ $canViewTasks ? 'Loading...' : 'No task data available.' }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header dashboard-widget-head dashboard-widget-head--fixed px-3">
                    <h5 class="mb-0 fw-bold">Module Trends</h5>
                </div>
                <div class="card-body pt-1">
                    <div class="chart-wrap">
                        @if($canViewAnyTrend)
                        <canvas id="dashboardTrendChart" height="220"></canvas>
                        @else
                        <div class="d-flex align-items-center justify-content-center text-muted text-center" style="min-height: 220px;">
                            No trend data available.
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header dashboard-widget-head dashboard-widget-head--fixed d-flex align-items-center justify-content-between px-3">
                    <div>
                        <h5 class="mb-0 fw-bold lh-1">Inactive Leads</h5>
                        <span class="text-white-50 small">No activity in 3+ days</span>
                    </div>
                    <a href="{{ $dashboardHref($canViewLeads, route('leads.index')) }}"
                        class="{{ $dashboardLinkClass($canViewLeads, 'text-dark badge bg-light px-3 py-2 fw-semibold small') }}"
                        style="color: #0c0c0c !important;"
                        @unless($canViewLeads) aria-disabled="true" tabindex="-1" @endunless>View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="dashboardInactiveLeadsTable">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Lead Name</th>
                                    <th style="width: 25%;" class="d-none d-md-table-cell">Assigned To</th>
                                    <th style="width: 20%;" class="text-center">Status</th>
                                    <th style="width: 20%;" class="text-center d-none d-md-table-cell">Last Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4" class="text-center text-muted py-4">{{ $canViewLeads ? 'Loading...' : 'No lead data available.' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header dashboard-widget-head py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-white">Customer Report</h5>
                    <select id="customerReportYear" class="form-select form-select-sm dashboard-year-select" {{ $canViewCustomers ? '' : 'disabled' }}>
                        @php($thisYear = now()->year)
                        <option value="{{ $thisYear }}">{{ $thisYear }}</option>
                        <option value="{{ $thisYear - 1 }}">{{ $thisYear - 1 }}</option>
                        <option value="{{ $thisYear - 2 }}">{{ $thisYear - 2 }}</option>
                    </select>
                </div>
                <div class="card-body pt-2">
                    <div class="chart-wrap">
                        @if($canViewCustomers)
                        <canvas id="customerReportChart" height="250"></canvas>
                        @else
                        <div class="d-flex align-items-center justify-content-center text-muted text-center" style="min-height: 250px;">
                            No customer report data available.
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header dashboard-widget-head py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-white">All Deals</h5>
                    <a href="{{ $dashboardHref($canViewDeals, route('deals.index')) }}"
                        class="{{ $dashboardLinkClass($canViewDeals, 'text-dark badge bg-light px-3 py-2 fw-semibold small') }}"
                        style="color: #0c0c0c !important;"
                        @unless($canViewDeals) aria-disabled="true" tabindex="-1" @endunless>View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="dashboardDealsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Deals Name</th>
                                    <th style="width: 25%;" class="d-none d-md-table-cell">Deal Value</th>
                                    <th style="width: 20%;" class="text-center">Probability(%)</th>
                                    <th style="width: 15%;" class="text-center d-none d-md-table-cell">Status</th>
                                    <th style="width: 10%;" class="text-center d-md-none">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">{{ $canViewDeals ? 'Loading deals...' : 'No deal data available.' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="dashboard-footer text-center py-2 mt-4">
        © {{ date('Y') }} Copyright - Fablead Developers Technolab
    </footer>
</div>
@endsection

@push('styles')
    <link rel="stylesheet"
        href="{{ ((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
    <style>
        .dashboard-link-disabled {
            pointer-events: none;
        }

        /* ── Fixed-height widget headers (keeps all 3 cards in a row even) ── */
        .dashboard-widget-head--fixed {
            height: 58px;
            display: flex;
            align-items: center;
        }
        .dashboard-inner-tabs {
            border-bottom: none;
            gap: 2px;
        }

        .dashboard-inner-tabs .nav-link {
            color: rgba(255,255,255,.55);
            font-size: .78rem;
            font-weight: 600;
            padding: 5px 12px;
            border: 1px solid transparent;
            border-radius: 6px;
            transition: all .18s ease;
            white-space: nowrap;
        }

        .dashboard-inner-tabs .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,.1);
        }

        .dashboard-inner-tabs .nav-link.active {
            color: #1E293B;
            background: #fff;
            border-color: transparent;
        }

        /* inactive leads status badge */
        .badge-lead-status {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .03em;
            display: inline-block;
            text-transform: uppercase;
        }

        .badge-lead-status.new          { background: #DBEAFE; color: #1E40AF; }
        .badge-lead-status.qualified    { background: #E0E7FF; color: #3730A3; }
        .badge-lead-status.working      { background: #FEF3C7; color: #92400E; }
        .badge-lead-status.ready_to_close { background: #D1FAE5; color: #065F46; }
        .badge-lead-status.contacted    { background: #F3E8FF; color: #6B21A8; }
    </style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script
    src="{{ ((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
@endpush
