@extends('layouts.app')

@section('page_title', 'Followups Report')

@push('styles')
    <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/users.css') }}?v={{ filemtime(public_path('css/users.css')) }}">
    <style>
        .report-filter-panel {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
        }
        .report-filter-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .report-filter-label { color: #1f3b63; font-weight: 500; margin-bottom: 0; }
        .report-filter-control {
            width: 160px;
            max-width: 100%;
            min-height: 40px;
            border-radius: 8px;
            border: 1px solid #d9e0ea !important;
            box-shadow: none;
        }
        .report-reset-btn { min-width: 120px; }
        @media (max-width: 767.98px) {
            .report-filter-panel {
                max-width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .report-filter-row {
                display: grid;
                grid-template-columns: 90px minmax(0, 1fr);
                align-items: center;
                gap: 12px;
                width: 100%;
            }

            .report-filter-control {
                width: 100%;
            }

            .report-reset-btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
<div class="container-fluid p-0">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <div class="row g-4">
                <div class="col-12 col-lg-2"><h4 class="fw-bold mb-0">Followups Overview</h4></div>
                <div class="col-12 col-lg-10 d-flex justify-content-end"><form action="" method="GET" class="report-filter-panel"><div class="report-filter-row"><label class="report-filter-label">Year:</label><select name="year" class="form-select report-filter-control">@foreach($years as $y)<option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>@endforeach</select></div><div class="report-filter-row"><label class="report-filter-label">From Date:</label><input type="date" name="from_date" value="{{ $from_date }}" class="form-control report-filter-control" placeholder="DD-MM"></div><div class="report-filter-row"><label class="report-filter-label">To Date:</label><input type="date" name="to_date" value="{{ $to_date }}" class="form-control report-filter-control" placeholder="DD-MM"></div><div class="d-flex justify-content-start"><a href="{{ route('reports.followups') }}" class="btn btn-dark-blue report-reset-btn">Reset</a></div></form></div>
            </div>
        </div>
        <div class="card-body px-4 pb-4"><div class="chart-container" style="position: relative; height:350px; width:100%"><canvas id="followupsChart"></canvas></div></div>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-header border-bottom-0 py-3 px-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3"><div><h4 class="fw-bold mb-0">Followups Report</h4><p class="text-muted small mb-0">View all followups.</p></div><div class="d-flex flex-wrap gap-2"><a href="{{ route('reports.followups.export', request()->all()) }}" class="btn btn-outline-dark-blue"><i class="fa-solid fa-download me-1"></i>Export</a></div></div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><h6 class="fw-bold mb-0">Active Followups</h6><div class="input-group input-group-sm" style="max-width: 300px; width: 100%;"><span class="input-group-text crm-search-icon border-0"><i class="bi bi-search"></i></span><input type="text" id="followupsReportSearch" class="form-control crm-search-input border-0" placeholder="Search follow ups..."></div></div>
        </div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0 responsive-table"><thead><tr><th class="ps-4">Purpose & Target</th><th class="text-start">Follow Up At</th><th class="d-none d-md-table-cell">Priority</th><th class="d-none d-md-table-cell">Staff</th><th class="d-none d-md-table-cell">Status</th><th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th><th class="text-center d-md-none" style="width: 80px;">Action</th></tr></thead><tbody id="followupsReportBody"></tbody></table></div><div id="followupsReportPagination" class="px-4 pb-3 pt-0"></div></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function () {
    const tableBody = document.getElementById('followupsReportBody');
    const paginationContainer = document.getElementById('followupsReportPagination');
    const searchInput = document.getElementById('followupsReportSearch');

    function fetchFollowupsReport(page = 1) {
        const url = new URL("{{ route('reports.followups') }}", window.location.origin);
        url.searchParams.set('page', page); url.searchParams.set('year', $('select[name="year"]').val() || ''); url.searchParams.set('from_date', $('input[name="from_date"]').val() || ''); url.searchParams.set('to_date', $('input[name="to_date"]').val() || ''); if (searchInput.value.trim()) url.searchParams.set('search', searchInput.value.trim());
        $.ajax({ url: url.toString(), type: 'GET', dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, beforeSend: function () { tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>'; }, success: function (res) { if (res.success && res.data) { renderRows(res.data.data || []); renderPagination(res.data); } }, error: function () { tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-5">Error loading follow-ups</td></tr>'; paginationContainer.innerHTML = ''; } });
    }

    function renderRows(items) {
        if (!items || !items.length) { tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="text-muted mb-3"><i class="bi bi-telephone-outbound display-1 opacity-25"></i></div><p class="text-muted">No follow ups found.</p></td></tr>'; return; }
        tableBody.innerHTML = items.map(function (followUp) {
            const entity = followUp.lead || followUp.customer; const entityType = followUp.lead ? 'Lead' : 'Customer'; const initial = entity?.name ? entity.name.substring(0, 1).toUpperCase() : '?'; const date = followUp.follow_up_at_display_date || '-'; const time = followUp.follow_up_at_display_time || ''; const priorityClass = { low: 'bg-info', medium: 'bg-primary', high: 'bg-danger' }[followUp.priority] || 'bg-secondary'; const statusClass = { pending: 'bg-warning text-dark', resheduled: 'bg-info', completed: 'bg-success', cancelled: 'bg-danger' }[followUp.status] || 'bg-secondary'; const priorityHtml = `<span class="badge ${priorityClass} px-3 py-2 rounded-pill text-capitalize">${followUp.priority ?? '-'}</span>`; const statusHtml = `<span class="badge ${statusClass} px-3 py-2 rounded-pill text-capitalize">${followUp.status ?? '-'}</span>`;
            return `
            <tr><td class="ps-4"><div class="d-flex align-items-center gap-3 py-1"><div class="rounded-circle d-flex align-items-center justify-content-center text-white bg-primary d-none d-md-flex" style="width: 36px; height: 36px; font-weight: 600; font-size: 0.9rem;">${initial}</div><div><div class="fw-bold small">${followUp.purpose ?? '-'}</div><div class="text-muted" style="font-size: 0.7rem;">${entity?.name ?? 'Unknown'} (${entityType})</div></div></div></td><td><div class="small fw-semibold">${date}</div><div class="text-muted small" style="font-size: 0.7rem;">${time}</div></td><td class="d-none d-md-table-cell">${priorityHtml}</td><td class="d-none d-md-table-cell"><div class="small">${followUp.assigned_user?.name ?? 'Unassigned'}</div></td><td class="d-none d-md-table-cell">${statusHtml}</td><td class="text-end pe-4 d-none d-md-table-cell"><div class="d-inline-flex align-items-center gap-2"><a href="/follow-ups/${followUp.id}" class="btn crm-action-btn btn-sm"><i class="bi bi-eye"></i></a></div></td><td class="text-center d-md-none"><button type="button" class="btn-user-expand" data-followup-id="${followUp.id}"><i class="fa-solid fa-plus"></i></button></td></tr>
            <tr class="details-row d-md-none border-0" id="details-${followUp.id}" style="display: none;"><td colspan="7" class="p-0 border-0"><div class="details-content"><div class="row g-3"><div class="col-12 d-flex justify-content-between align-items-center"><div class="expand-label"><i class="fa-solid fa-circle-info"></i> Status :</div><div class="expand-value">${statusHtml}</div></div><div class="col-12 d-flex justify-content-between align-items-center"><div class="expand-label"><i class="fa-solid fa-bolt"></i> Priority :</div><div class="expand-value">${priorityHtml}</div></div><div class="col-12 d-flex justify-content-between align-items-center"><div class="expand-label"><i class="fa-solid fa-user"></i> Staff :</div><div class="expand-value">${followUp.assigned_user?.name ?? 'Unassigned'}</div></div><div class="col-12 d-flex justify-content-between align-items-center pt-3 mt-3 border-top"><div class="expand-label"><i class="fa-solid fa-gear"></i> Actions :</div><div class="d-flex flex-wrap gap-2"><a href="/follow-ups/${followUp.id}" class="btn crm-action-btn btn-sm"><i class="bi bi-eye"></i></a></div></div></div></div></td></tr>`;
        }).join('');
        tableBody.querySelectorAll('.btn-user-expand').forEach(function (button) { button.addEventListener('click', function () { const id = this.dataset.followupId; const detailsRow = document.getElementById(`details-${id}`); const icon = this.querySelector('i'); if (detailsRow.style.display === 'none') { detailsRow.style.display = 'table-row'; icon.classList.replace('fa-plus', 'fa-minus'); this.classList.add('active'); } else { detailsRow.style.display = 'none'; icon.classList.replace('fa-minus', 'fa-plus'); this.classList.remove('active'); } }); });
    }

    function renderPagination(data) {
        if (!data || data.total === 0) { paginationContainer.innerHTML = ''; return; }
        const from = data.from || 0, to = data.to || 0, total = data.total || 0, currentPage = data.current_page || 1, lastPage = data.last_page || 1;
        let html = `<div class="crm-pagination-container"><div class="text-muted small">Showing ${from} to ${to} of ${total} results</div><ul class="pagination crm-pagination mb-0">`;
        html += data.prev_page_url ? `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a></li>` : '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
        for (let i = 1; i <= lastPage; i++) { if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) { html += i === currentPage ? `<li class="page-item active"><span class="page-link">${i}</span></li>` : `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`; } else if (i === currentPage - 3 || i === currentPage + 3) { html += '<li class="page-item disabled"><span class="page-link">...</span></li>'; } }
        html += data.next_page_url ? `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage + 1}">Next</a></li>` : '<li class="page-item disabled"><span class="page-link">Next</span></li>';
        html += '</ul></div>'; paginationContainer.innerHTML = html; paginationContainer.querySelectorAll('.page-link[data-page]').forEach(function (link) { link.addEventListener('click', function (e) { e.preventDefault(); fetchFollowupsReport(this.dataset.page); }); });
    }

    let timer; searchInput.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { fetchFollowupsReport(1); }, 400); });
    $('select[name="year"], input[name="from_date"], input[name="to_date"]').on('change', function () { $(this).closest('form').submit(); });
    const ctx = document.getElementById('followupsChart').getContext('2d');
    new Chart(ctx, { type: 'bar', data: { labels: @json($chartLabels), datasets: [{ label: 'Followups', data: @json($chartData), backgroundColor: 'rgba(54, 162, 235, 0.25)', borderColor: 'rgba(54, 162, 235, 0.8)', borderWidth: 1, borderRadius: 2, borderSkipped: false }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'top', align: 'center', labels: { usePointStyle: true, pointStyle: 'rect', padding: 20, font: { size: 12 } } }, tooltip: { mode: 'index', intersect: false, backgroundColor: '#fff', titleColor: '#333', bodyColor: '#666', borderColor: '#ddd', borderWidth: 1, padding: 10, displayColors: true, callbacks: { label: function (context) { return ' Followups: ' + context.parsed.y; } } } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Followups', color: '#666', font: { size: 12 } }, grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false }, ticks: { stepSize: 1, font: { size: 11 } } }, x: { grid: { color: 'rgba(0, 0, 0, 0.05)' }, ticks: { font: { size: 11 } } } } } });
    fetchFollowupsReport(1);
});
</script>
@endpush

