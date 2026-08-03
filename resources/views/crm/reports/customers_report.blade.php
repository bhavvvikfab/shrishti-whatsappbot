@extends('layouts.app')

@section('page_title', 'Customer Report')

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

        .report-filter-label {
            color: #1f3b63;
            font-weight: 500;
            margin-bottom: 0;
        }

        .report-filter-control {
            width: 160px;
            max-width: 100%;
            border-radius: 8px;
            min-height: 40px;
            border: 1px solid #d9e0ea !important;
            box-shadow: none;
        }

        .report-reset-btn {
            min-width: 120px;
        }

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
                <div class="row align-items-center g-3">
                    <div class="col-12 col-lg-2">
                        <h4 class="fw-bold mb-0">Customer</h4>
                    </div>
                    <div class="col-12 col-lg-10 d-flex justify-content-end">
                        <form action="" method="GET" class="report-filter-panel justify-content-lg-end">
                            <div class="report-filter-row">
                                <label class="report-filter-label">Year:</label>
                                <select name="year" class="form-select report-filter-control">
                                    @foreach($years as $y)
                                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="report-filter-row">
                                <label class="report-filter-label">From Date:</label>
                                <input type="date" name="from_date" value="{{ $from_date }}" class="form-control report-filter-control"
                                    placeholder="DD- MM">
                            </div>
                            <div class="report-filter-row">
                                <label class="report-filter-label">To Date:</label>
                                <input type="date" name="to_date" value="{{ $to_date }}" class="form-control report-filter-control"
                                    placeholder="DD-MM">
                            </div>
                            
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('reports.customers') }}" class="btn btn-dark-blue report-reset-btn">
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card-body px-4 pb-4">
                <div class="chart-container" style="position: relative; height:350px; width:100%">
                    <canvas id="customerChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white border-bottom-0 p-4 pb-0">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <div>
                        <h4 class="fw-bold mb-0">Customer Report</h4>
                        <p class="text-muted small mb-0">View all customers.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('masters.customers.export') }}" class="btn btn-outline-dark-blue">
                            <i class="fa-solid fa-download me-1"></i>Export
                        </a>
                    </div>
                </div>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h6 class="fw-bold mb-0">Active Customers</h6>
                    <div class="input-group input-group-sm" style="max-width: 300px; width: 100%;">
                        <span class="input-group-text crm-search-icon border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="customerReportSearch" class="form-control crm-search-input border-0"
                            placeholder="Search customers...">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="customersReport" class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 80px;">Sr.No</th>
                                <th>Customer Name</th>
                                <th class="d-none d-md-table-cell">Email</th>
                                <th class="d-none d-md-table-cell">Phone</th>
                                <th class="d-none d-md-table-cell">Type</th>
                                <th class="d-none d-md-table-cell">Status</th>
                                <th class="text-end pe-4 d-none d-md-table-cell" style="width: 120px;">Actions</th>
                                <th class="text-center d-md-none" style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="customersReportBody"></tbody>
                    </table>
                </div>
                <div id="customerReportPagination" class="card-footer border-top-0 py-4 px-4"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function () {
            const tableBody = document.getElementById('customersReportBody');
            const paginationContainer = document.getElementById('customerReportPagination');
            const searchInput = document.getElementById('customerReportSearch');

            function escapeHtml(value) {
                if (value === null || value === undefined) {
                    return "";
                }

                return String(value)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            function renderRows(items, meta) {
                if (!items || items.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted mb-3"><i class="bi bi-people display-1 opacity-25"></i></div>
                                <p class="text-muted">No customers found.</p>
                            </td>
                        </tr>`;
                    return;
                }

                tableBody.innerHTML = items.map((customer, index) => {
                    const rowNumber = meta && meta.from ? meta.from + index : index + 1;
                    const date = customer.created_at ? new Date(customer.created_at).toLocaleDateString("en-GB", {
                        day: "2-digit",
                        month: "short",
                        year: "numeric",
                    }) : "-";
                    const statusBadge = customer.is_active
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';

                    return `
                        <tr>
                            <td class="ps-4">
                                <span class="text-muted small fw-medium">${rowNumber}</span>
                            </td>
                            <td>
                                <div class="fw-bold small">${escapeHtml(customer.name || "-")}</div>
                            </td>
                            <td class="d-none d-md-table-cell">${escapeHtml(customer.email || "-")}</td>
                            <td class="d-none d-md-table-cell">${escapeHtml(customer.phone || "-")}</td>
                            <td class="d-none d-md-table-cell">${escapeHtml(customer.type || "-")}</td>
                            <td class="d-none d-md-table-cell">${statusBadge}</td>
                            <td class="text-end pe-4 d-none d-md-table-cell">
                                <a href="/masters/customers/${customer.id}" class="btn crm-action-btn btn-sm" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                            <td class="text-center d-md-none">
                                <button type="button" class="btn-user-expand" data-customer-id="${customer.id}">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="details-row d-md-none border-0" id="details-${customer.id}" style="display: none;">
                            <td colspan="8" class="p-0 border-0">
                                <div class="details-content">
                                    <div class="row g-3">
                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                            <div class="expand-label"><i class="fa-solid fa-envelope"></i> Email :</div>
                                            <div class="expand-value">${escapeHtml(customer.email || "-")}</div>
                                        </div>
                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                            <div class="expand-label"><i class="fa-solid fa-phone"></i> Phone :</div>
                                            <div class="expand-value">${escapeHtml(customer.phone || "-")}</div>
                                        </div>
                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                            <div class="expand-label"><i class="fa-solid fa-layer-group"></i> Type :</div>
                                            <div class="expand-value">${escapeHtml(customer.type || "-")}</div>
                                        </div>
                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                            <div class="expand-label"><i class="fa-solid fa-signal"></i> Status :</div>
                                            <div class="expand-value">${statusBadge}</div>
                                        </div>
                                        <div class="col-12 d-flex justify-content-between align-items-center">
                                            <div class="expand-label"><i class="fa-solid fa-calendar-days"></i> Created :</div>
                                            <div class="expand-value">${date}</div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center pt-3 mt-3 border-top">
                                        <div class="expand-label"><i class="fa-solid fa-gear"></i> Actions :</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="/masters/customers/${customer.id}" class="btn crm-action-btn btn-sm" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>`;
                }).join('');

                tableBody.querySelectorAll(".btn-user-expand").forEach(button => {
                    button.addEventListener("click", function () {
                        const id = this.dataset.customerId;
                        const detailsRow = document.getElementById(`details-${id}`);
                        const icon = this.querySelector("i");

                        if (detailsRow.style.display === "none") {
                            detailsRow.style.display = "table-row";
                            icon.classList.replace("fa-plus", "fa-minus");
                            this.classList.add("active");
                        } else {
                            detailsRow.style.display = "none";
                            icon.classList.replace("fa-minus", "fa-plus");
                            this.classList.remove("active");
                        }
                    });
                });
            }

            function renderPagination(data) {
                if (!paginationContainer || !data || data.total === 0) {
                    paginationContainer.innerHTML = "";
                    return;
                }

                const from = data.from || 0;
                const to = data.to || 0;
                const total = data.total || 0;
                const currentPage = data.current_page || 1;
                const lastPage = data.last_page || 1;

                let html = `
                    <div class="crm-pagination-container">
                        <div class="text-muted small">Showing ${from} to ${to} of ${total} results</div>
                        <ul class="pagination crm-pagination mb-0">`;

                if (data.prev_page_url) {
                    html += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a></li>`;
                } else {
                    html += `<li class="page-item disabled"><span class="page-link">Previous</span></li>`;
                }

                for (let i = 1; i <= lastPage; i++) {
                    if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
                        html += i === currentPage
                            ? `<li class="page-item active"><span class="page-link">${i}</span></li>`
                            : `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
                    } else if (i === currentPage - 3 || i === currentPage + 3) {
                        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    }
                }

                if (data.next_page_url) {
                    html += `<li class="page-item"><a class="page-link" href="#" data-page="${currentPage + 1}">Next</a></li>`;
                } else {
                    html += `<li class="page-item disabled"><span class="page-link">Next</span></li>`;
                }

                html += `</ul></div>`;
                paginationContainer.innerHTML = html;

                paginationContainer.querySelectorAll(".page-link[data-page]").forEach(link => {
                    link.addEventListener("click", function (e) {
                        e.preventDefault();
                        fetchCustomersReport(this.dataset.page);
                    });
                });
            }

            function fetchCustomersReport(page = 1) {
                const url = new URL("{{ route('reports.customers') }}", window.location.origin);
                url.searchParams.set("page", page);
                url.searchParams.set("year", $('select[name="year"]').val() || "");
                url.searchParams.set("from_date", $('input[name="from_date"]').val() || "");
                url.searchParams.set("to_date", $('input[name="to_date"]').val() || "");

                if (searchInput.value.trim()) {
                    url.searchParams.set("search", searchInput.value.trim());
                }

                $.ajax({
                    url: url.toString(),
                    type: "GET",
                    dataType: "json",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        Accept: "application/json",
                    },
                    beforeSend: function () {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="spinner-border text-primary"></div>
                                </td>
                            </tr>`;
                    },
                    success: function (response) {
                        if (response.success && response.data) {
                            renderRows(response.data.data || [], response.data);
                            renderPagination(response.data);
                        }
                    },
                    error: function () {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="8" class="text-center py-5">Error loading customers</td>
                            </tr>`;
                        paginationContainer.innerHTML = "";
                    }
                });
            }

            let searchTimer;
            searchInput.addEventListener("input", function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    fetchCustomersReport(1);
                }, 400);
            });

            $('select[name="year"], input[name="from_date"], input[name="to_date"]').on('change', function() {
                $(this).closest('form').submit();
            });

            // Chart initialization
            const ctx = document.getElementById('customerChart').getContext('2d');
            
            // Create gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(59, 183, 187, 0.2)');
            gradient.addColorStop(1, 'rgba(59, 183, 187, 0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Customers Created',
                        data: @json($chartData),
                        borderColor: '#3bb7bb',
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3bb7bb',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'center',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: '#fff',
                            titleColor: '#333',
                            bodyColor: '#666',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return ' Customers: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Customers',
                                color: '#666',
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month',
                                color: '#666',
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            fetchCustomersReport(1);
        });
    </script>
@endpush

