(function () {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initDashboard);
    } else {
        initDashboard();
    }

    const endpoints = {
        stats: "/api/dashboard/stats",
        leadBoard: "/api/dashboard/lead-board",
        tasks: "/api/dashboard/tasks-widget",
        trend: "/api/dashboard/trend",
        customerReport: "/api/dashboard/customer-report",
        dealsWidget: "/api/dashboard/deals-widget",
        upcomingReminders: "/api/dashboard/upcoming-reminders",
        inactiveLeads: "/api/dashboard/inactive-leads",
    };

    let trendChart = null;
    let customerReportChart = null;
    let leadBoardSliderBound = false;
    let dashboardReminderModal = null;
    let reminderRefreshTimer = null;

    function initDashboard() {
        if (!document.getElementById("dashboardStats")) {
            return;
        }

        loadStats();
        loadLeadBoard();
        loadTasks();
        loadDueTasks();
        loadInactiveLeads();
        loadTrendChart();
        loadCustomerReport();
        loadDealsWidget();
        bindReminderTrigger();
        loadUpcomingReminders({ openModal: true });
        bindTaskTabs();

        if (reminderRefreshTimer) {
            clearInterval(reminderRefreshTimer);
        }
        reminderRefreshTimer = setInterval(function () {
            loadUpcomingReminders({ openModal: false });
        }, 60000);

        const yearSelect = document.getElementById("customerReportYear");
        if (yearSelect) {
            yearSelect.addEventListener("change", function () {
                loadCustomerReport(this.value);
            });
        }
    }

    function bindReminderTrigger() {
        const trigger = document.getElementById("dashboardReminderTrigger");
        if (!trigger || trigger.dataset.bound === "true") {
            return;
        }

        trigger.dataset.bound = "true";
        trigger.addEventListener("click", function () {
            loadUpcomingReminders({ openModal: true, forceOpen: true });
        });
    }

    function notifyError(message) {
        if (typeof window.showAlert === "function") {
            window.showAlert("error", message || "Something went wrong.");
            return;
        }

        console.error(message);
    }

    function getJson(url) {
        const headers = typeof window.crmApplyAuthHeaders === "function"
            ? window.crmApplyAuthHeaders({
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json",
            })
            : {
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json",
        };
        
        // if (token) {
        //     headers["Authorization"] = "Bearer " + token;
        // }
        
        // console.log('Fetching:', url, 'with token:', token ? 'yes' : 'no');
        
        return fetch(url, {
            method: "GET",
            headers: headers,
        }).then(async function (response) {
            console.log('Response status:', response.status, 'for', url);
            const payload = await response.json().catch(function () { return null; });
            console.log('Response payload:', payload);
            if (!response.ok) {
                throw new Error(payload && payload.message ? payload.message : "Request failed with status " + response.status);
            }
            return payload;
        });
    }

    function loadStats() {
        getJson(endpoints.stats)
            .then(function (response) {
                console.log('Stats response:', response);
                const data = response && response.data ? response.data : {};
                setText("metricCustomers", formatNumber(data.customers));
                setText("metricFollowUps", formatNumber(data.follow_ups || data.pending_followups));
                setText("metricLeads", formatNumber(data.leads || data.active_leads));
                setText("metricDeals", formatNumber(data.deals));
                setText("metricWhatsappConversations", formatNumber(data.whatsapp_conversations));
            })
            .catch(function (error) {
                console.error('Stats error:', error);
                notifyError("Failed to load dashboard stats.");
            });
    }

    function loadLeadBoard() {
        const container = document.getElementById("leadBoardContainer");
        if (!container) {
            return;
        }

        getJson(endpoints.leadBoard)
            .then(function (response) {
                const stages = response && response.data ? response.data : [];
                const themes = ["indigo", "slate", "green", "orange"];

                if (!Array.isArray(stages) || stages.length === 0) {
                    container.innerHTML = '<div class="card border-0 shadow-sm w-100"><div class="card-body text-muted small">No lead stages available.</div></div>';
                    return;
                }

                container.innerHTML = stages.map(function (stage, index) {
                    const theme = themes[index % themes.length];
                    const lead = Array.isArray(stage.leads) && stage.leads.length ? stage.leads[0] : null;

                    if (!lead) {
                        return '<div class="status-column status-column--' + theme + '">'
                            + '<div class="status-column__head status-column__head--' + theme + '">'
                            + '<span>' + escapeHtml(stage.name || "Stage") + '</span>'
                            + '<span class="status-column__count">' + formatNumber(stage.count || 0) + '</span>'
                            + '</div>'
                            + '<div class="status-column__body status-column__body--empty">'
                            + '<div class="status-column__empty">No leads available.</div>'
                            + '</div>'
                            + '</div>';
                    }

                    return '<div class="status-column status-column--' + theme + '">'
                        + '<div class="status-column__head status-column__head--' + theme + '">'
                        + '<span>' + escapeHtml(stage.name || "Stage") + '</span>'
                        + '<span class="status-column__count">' + formatNumber(stage.count || 0) + '</span>'
                        + '</div>'
                        + '<div class="status-column__body status-column__body--filled">'
                        + '<div class="status-lead-card">'
                        + '<div class="status-lead-card__body">'
                        + '<div class="status-lead-name">' + escapeHtml(lead.name || "-") + '</div>'
                        + '<div class="status-lead-row"><i class="bi bi-envelope-fill"></i><span>' + escapeHtml(lead.email || "-") + '</span></div>'
                        + '<div class="status-lead-row"><i class="bi bi-telephone-fill"></i><span>' + escapeHtml(lead.phone || "-") + '</span></div>'
                        + '<div class="status-lead-row"><i class="bi bi-person-plus-fill"></i><span>' + escapeHtml(lead.assigned_to || "Unassigned") + '</span></div>'
                        + '<div class="status-lead-row"><i class="bi bi-calendar-event-fill"></i><span>' + escapeHtml(formatDate(lead.created_at)) + '</span></div>'
                        + '</div>'
                        + '<div class="status-lead-card__footer">'
                        + '<div class="status-lead-actions">'
                        + '<a href="/leads/' + encodeURIComponent(lead.id) + '" class="status-lead-btn" title="View"><i class="bi bi-eye"></i></a>'
                        + '<a href="/leads/' + encodeURIComponent(lead.id) + '/edit" class="status-lead-btn" title="Edit"><i class="bi bi-pencil-square"></i></a>'
                        + '<a href="https://wa.me/' + (lead.phone || '').replace(/\D/g, '') + '" class="status-lead-btn" title="WhatsApp" target="_blank"><i class="bi bi-whatsapp"></i></a>'
                        + '</div>'
                        + '<button type="button" class="status-lead-more" title="More"><i class="bi bi-three-dots-vertical"></i></button>'
                        + '</div>'
                        + '</div>'
                        + '</div>'
                        + '</div>';
                }).join("");
            })
            .catch(function (error) {
                console.error('Failed to load lead board:', error);
                container.innerHTML = '<div class="card border-0 shadow-sm w-100"><div class="card-body text-danger small">Failed to load lead board.</div></div>';
            })
            .finally(function () {
                initLeadBoardSlider();
            });
    }

    function initLeadBoardSlider() {
        var container = document.getElementById("leadBoardContainer");
        var btnLeft = document.getElementById("leadBoardLeft");
        var btnRight = document.getElementById("leadBoardRight");

        if (!container || !btnLeft || !btnRight) {
            return;
        }

        function updateArrows() {
            var scrollLeft = Math.round(container.scrollLeft);
            var maxScroll = container.scrollWidth - container.clientWidth;
            btnLeft.disabled = scrollLeft <= 0;
            btnRight.disabled = scrollLeft >= maxScroll - 1;
        }

        function getScrollAmount() {
            var firstCol = container.querySelector(".status-column");
            if (firstCol) {
                return firstCol.offsetWidth + 14;
            }
            return container.clientWidth * 0.85;
        }

        if (!leadBoardSliderBound) {
            btnLeft.addEventListener("click", function () {
                container.scrollBy({ left: -getScrollAmount(), behavior: "smooth" });
            });

            btnRight.addEventListener("click", function () {
                container.scrollBy({ left: getScrollAmount(), behavior: "smooth" });
            });

            container.addEventListener("scroll", updateArrows);
            window.addEventListener("resize", updateArrows);
            leadBoardSliderBound = true;
        }

        updateArrows();
    }

    function loadTasks() {
        const tbody = document.querySelector("#dashboardTasksTable tbody");
        if (!tbody) {
            return;
        }

        getJson(endpoints.tasks)
            .then(function (response) {
                const tasks = response && response.data ? response.data : [];

                if (!Array.isArray(tasks) || tasks.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No tasks found.</td></tr>';
                    return;
                }

                tbody.innerHTML = tasks.map(function (task) {
                    const priority = (task.priority || "-").toString().toLowerCase();
                    const status = (task.status || "-").toString().toLowerCase();
                    const taskAssignedTo = task.assigned_to || task.assigned_user || "-";
                    const taskCustomerName = task.customer || task.customer_name || "-";
                    const taskDueDate = task.due_date || "-";

                    return '<tr class="task-main-row" data-task-id="' + task.id + '">'
                        + '<td>' + escapeHtml(task.title || "-") + '</td>'
                        + '<td class="text-center"><span class="badge-priority ' + escapeHtml(priority) + '">' + escapeHtml((task.priority || "-").toString().toUpperCase()) + '</span></td>'
                        + '<td class="text-center"><span class="badge-status ' + escapeHtml(status) + '">' + escapeHtml((task.status || "-").toString().replace(/_/g, "-").toUpperCase()) + '</span></td>'
                        + '</tr>';
                }).join("");

                // Handle main row click (navigation)
                tbody.querySelectorAll(".task-main-row").forEach(function (row) {
                    row.style.cursor = "pointer";
                    row.addEventListener("click", function () {
                        const id = this.getAttribute("data-task-id");
                        if (id) {
                            window.location.href = "/tasks/" + id;
                        }
                    });
                });
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Failed to load tasks.</td></tr>';
            });
    }

    // ── Tab binding for tasks widget ──────────────────────────────────────────
    function bindTaskTabs() {
        const tabDue = document.getElementById("tab-due-tasks");
        const tabAll = document.getElementById("tab-all-tasks");
        if (tabDue) {
            tabDue.addEventListener("shown.bs.tab", function () {
                loadDueTasks();
            });
        }
        if (tabAll) {
            tabAll.addEventListener("shown.bs.tab", function () {
                loadTasks();
            });
        }
    }

    // ── Due tasks (overdue, not completed/cancelled) ──────────────────────────
    function loadDueTasks() {
        const tbody = document.querySelector("#dashboardDueTasksTable tbody");
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';

        getJson(endpoints.tasks + "?filter=due")
            .then(function (response) {
                const tasks = response && response.data ? response.data : [];

                if (!Array.isArray(tasks) || tasks.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4"><i class="fa-solid fa-circle-check me-2 text-success"></i>No overdue tasks.</td></tr>';
                    return;
                }

                tbody.innerHTML = tasks.map(function (task) {
                    const priority = (task.priority || "-").toLowerCase();
                    return '<tr style="cursor:pointer" onclick="window.location.href=\'/tasks/' + task.id + '\'">'
                        + '<td>' + escapeHtml(task.title || "-") + '</td>'
                        + '<td class="text-center"><span class="badge-priority ' + escapeHtml(priority) + '">' + escapeHtml(priority.toUpperCase()) + '</span></td>'
                        + '<td class="text-center"><span class="text-danger fw-semibold small">' + escapeHtml(task.due_date || "-") + '</span></td>'
                        + '</tr>';
                }).join("");
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">Failed to load due tasks.</td></tr>';
            });
    }

    // ── Inactive leads (no activity 7+ days) ─────────────────────────────────
    function loadInactiveLeads() {
        const tbody = document.querySelector("#dashboardInactiveLeadsTable tbody");
        if (!tbody) return;

        getJson(endpoints.inactiveLeads)
            .then(function (response) {
                const leads = response && response.data ? response.data : [];

                if (!Array.isArray(leads) || leads.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><i class="fa-solid fa-circle-check me-2 text-success"></i>All leads are active.</td></tr>';
                    return;
                }

                tbody.innerHTML = leads.map(function (lead) {
                    const status = (lead.status || "").toLowerCase().replace(/\s+/g, "_");
                    return '<tr style="cursor:pointer" onclick="window.location.href=\'/leads/' + lead.id + '\'">'
                        + '<td><div class="fw-semibold">' + escapeHtml(lead.name || "-") + '</div>'
                        + '<div class="text-muted small d-none d-md-block">' + escapeHtml(lead.email || "") + '</div></td>'
                        + '<td class="d-none d-md-table-cell">' + escapeHtml(lead.assigned_to || "-") + '</td>'
                        + '<td class="text-center"><span class="badge-lead-status ' + escapeHtml(status) + '">' + escapeHtml((lead.status || "-").replace(/_/g, " ")) + '</span></td>'
                        + '<td class="text-center d-none d-md-table-cell"><span class="text-warning small fw-semibold">' + escapeHtml(lead.last_active || "-") + '</span></td>'
                        + '</tr>';
                }).join("");
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Failed to load inactive leads.</td></tr>';
            });
    }

    function loadTrendChart() {
        const canvas = document.getElementById("dashboardTrendChart");
        if (!canvas) {
            return;
        }

        // Wait for Chart to be available
        if (typeof window.Chart === "undefined") {
            setTimeout(loadTrendChart, 100);
            return;
        }

        getJson(endpoints.trend)
            .then(function (response) {
                const data = response && response.data ? response.data : {};
                const labels = Array.isArray(data.labels) ? data.labels : [];
                const datasets = data.datasets || {};

                if (trendChart) {
                    trendChart.destroy();
                }

                trendChart = new Chart(canvas, {
                    type: "line",
                    data: {
                        labels: labels,
                        datasets: [
                            buildDataset("Leads", datasets.leads, "#3B5BDB"),
                            buildDataset("Follow Up", datasets.followups, "#F43F5E"),
                            buildDataset("Customer", datasets.customers, "#475569"),
                            buildDataset("Deal", datasets.deals, "#0D9488"),
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { position: "top" },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: { color: "rgba(148, 163, 184, 0.2)" },
                            },
                            x: {
                                grid: { color: "rgba(148, 163, 184, 0.15)" },
                            },
                        },
                    },
                });
            })
            .catch(function (error) {
                console.error("Failed to load dashboard chart:", error);
                notifyError("Failed to load dashboard chart.");
            });
    }

    function loadCustomerReport(year) {
        const canvas = document.getElementById("customerReportChart");
        if (!canvas) {
            return;
        }

        // Wait for Chart to be available
        if (typeof window.Chart === "undefined") {
            setTimeout(() => loadCustomerReport(year), 100);
            return;
        }

        const selectedYear = year || (document.getElementById("customerReportYear") ? document.getElementById("customerReportYear").value : "");
        const url = selectedYear ? (endpoints.customerReport + "?year=" + encodeURIComponent(selectedYear)) : endpoints.customerReport;

        getJson(url)
            .then(function (response) {
                const data = response && response.data ? response.data : {};
                const labels = Array.isArray(data.labels) ? data.labels : [];
                const series = Array.isArray(data.series) ? data.series : [];

                if (customerReportChart) {
                    customerReportChart.destroy();
                }

                customerReportChart = new Chart(canvas, {
                    type: "line",
                    data: {
                        labels: labels,
                        datasets: [{
                            label: "Customers",
                            data: series,
                            borderColor: "#3B5BDB",
                            backgroundColor: "rgba(59,91,219,.10)",
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: { color: "rgba(148, 163, 184, .2)" },
                            },
                            x: { grid: { display: false } },
                        },
                    },
                });
            })
            .catch(function (error) {
                console.error("Failed to load customer report:", error);
                notifyError("Failed to load customer report.");
            });
    }

    function loadDealsWidget() {
        const tbody = document.querySelector("#dashboardDealsTable tbody");
        if (!tbody) {
            return;
        }

        getJson(endpoints.dealsWidget)
            .then(function (response) {
                const deals = response && response.data ? response.data : [];
                if (!Array.isArray(deals) || deals.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No deals found.</td></tr>';
                    return;
                }

                tbody.innerHTML = deals.map(function (deal) {
                    const statusName = deal && deal.status && typeof deal.status === "object"
                        ? (deal.status.name || "-")
                        : (deal.status || "-");
                    const normalized = statusName.toLowerCase().replace(/\s+/g, "_").replace(/-/g, "_");
                    const probability = (deal.probability || 0).toString() + "%";
                    const dealCustomerName = deal.customer || deal.customer_name || "-";
                    const dealAssignedTo = deal.assigned_to || deal.assigned_user || "-";
                    const dealExpectedCloseDate = deal.expected_close_date || "-";

                    return '<tr class="deal-main-row" data-deal-id="' + deal.id + '">'
                        + '<td>' + escapeHtml(deal.name || deal.title || "-") + '</td>'
                        + '<td class="d-none d-md-table-cell">₹' + formatCurrency(deal.amount) + '</td>'
                        + '<td class="text-center">' + escapeHtml(probability) + '</td>'
                        + '<td class="text-center d-none d-md-table-cell"><span class="badge-status ' + escapeHtml(normalized) + '">' + escapeHtml(statusName.replace(/_/g, "-").toUpperCase()) + '</span></td>'
                        + '<td class="text-center d-md-none">'
                        + '<button type="button" class="btn-deal-expand" data-target="' + deal.id + '">'
                        + '<i class="fa-solid fa-plus"></i>'
                        + '</button>'
                        + '</td>'
                        + '</tr>'
                        + '<tr class="deal-expand-row d-md-none" id="deal-expand-' + deal.id + '" style="display: none;">'
                        + '<td colspan="5">'
                        + '<div class="task-expand-content">'
                        + '<div class="row g-3">'
                        + '<div class="col-12 d-flex justify-content-between align-items-center">'
                        + '<div class="expand-label"><i class="fa-solid fa-building"></i> Customer Name :</div>'
                        + '<div class="expand-value">' + escapeHtml(dealCustomerName) + '</div>'
                        + '</div>'
                        + '<div class="col-12 d-flex justify-content-between align-items-center">'
                        + '<div class="expand-label"><i class="fa-solid fa-indian-rupee-sign"></i> Amount :</div>'
                        + '<div class="expand-value">₹' + formatCurrency(deal.amount) + '</div>'
                        + '</div>'
                        + '<div class="col-12 d-flex justify-content-between align-items-center">'
                        + '<div class="expand-label"><i class="fa-solid fa-user-tie"></i> Assigned User :</div>'
                        + '<div class="expand-value">' + escapeHtml(dealAssignedTo) + '</div>'
                        + '</div>'
                        + '<div class="col-12 d-flex justify-content-between align-items-center">'
                        + '<div class="expand-label"><i class="fa-solid fa-calendar-day"></i> Expected Close Date :</div>'
                        + '<div class="expand-value">' + escapeHtml(dealExpectedCloseDate) + '</div>'
                        + '</div>'
                        + '<div class="col-12 d-flex justify-content-between align-items-center">'
                        + '<div class="expand-label"><i class="fa-solid fa-circle-info"></i> Status :</div>'
                        + '<div><span class="badge-status ' + escapeHtml(normalized) + '">' + escapeHtml(statusName.replace(/_/g, "-").toUpperCase()) + '</span></div>'
                        + '</div>'
                        + '</div>'
                        + '</div>'
                        + '</td>'
                        + '</tr>';
                }).join("");

                tbody.querySelectorAll(".deal-main-row").forEach(function (row) {
                    row.style.cursor = "pointer";
                    row.addEventListener("click", function (e) {
                        if (e.target.closest(".btn-deal-expand")) {
                            return;
                        }
                        const id = this.getAttribute("data-deal-id");
                        if (id) {
                            window.location.href = "/deals/" + id;
                        }
                    });
                });

                // Handle expansion
                tbody.querySelectorAll(".btn-deal-expand").forEach(function (btn) {
                    btn.addEventListener("click", function (e) {
                        e.stopPropagation();
                        const dealId = this.getAttribute("data-target");
                        const expandRow = document.getElementById("deal-expand-" + dealId);
                        const icon = this.querySelector("i");

                        if (expandRow) {
                            const isVisible = expandRow.style.display !== "none";
                            expandRow.style.display = isVisible ? "none" : "table-row";

                            if (icon) {
                                icon.classList.remove(isVisible ? "fa-minus" : "fa-plus");
                                icon.classList.add(isVisible ? "fa-plus" : "fa-minus");
                            }

                            this.classList.toggle("active", !isVisible);
                        }
                    });
                });
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Failed to load deals.</td></tr>';
            });
    }

    function loadUpcomingReminders(options) {
        const modalElement = document.getElementById("dashboardReminderModal");
        if (!modalElement) {
            return;
        }

        const config = Object.assign({ openModal: false, forceOpen: false }, options || {});

        getJson(endpoints.upcomingReminders)
            .then(function (response) {
                const data = response && response.data ? response.data : {};
                const meetings = Array.isArray(data.meetings) ? data.meetings : [];
                const followUps = Array.isArray(data.follow_ups) ? data.follow_ups : [];
                const hasItems = Boolean(data.has_items);
                const totalItems = meetings.length + followUps.length;

                renderReminderList(
                    "dashboardMeetingReminderList",
                    meetings,
                    "No meetings scheduled for today."
                );
                renderReminderList(
                    "dashboardFollowUpReminderList",
                    followUps,
                    "No follow ups scheduled for today."
                );

                setText("dashboardMeetingReminderCount", formatNumber(meetings.length));
                setText("dashboardFollowUpReminderCount", formatNumber(followUps.length));
                updateReminderTriggerBadge(totalItems);
                setText(
                    "dashboardReminderTitle",
                    "Today's Schedule - " + (data.date_label || "")
                );

                if (typeof window.bootstrap !== "undefined") {
                    dashboardReminderModal = dashboardReminderModal || window.bootstrap.Modal.getOrCreateInstance(modalElement);

                    if (config.forceOpen) {
                        dashboardReminderModal.show();
                    } else if (hasItems && config.openModal) {
                        dashboardReminderModal.show();
                    } else if (!hasItems && dashboardReminderModal) {
                        dashboardReminderModal.hide();
                    }
                }
            })
            .catch(function (error) {
                console.error("Failed to load upcoming reminders:", error);
            });
    }

    function updateReminderTriggerBadge(totalItems) {
        const badge = document.getElementById("dashboardReminderTriggerBadge");
        if (!badge) {
            return;
        }

        badge.textContent = totalItems > 99 ? "99+" : String(Math.max(0, totalItems));
    }

    function renderReminderList(targetId, items, emptyMessage) {
        const container = document.getElementById(targetId);
        if (!container) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            container.innerHTML = '<div class="dashboard-reminder-empty">' + escapeHtml(emptyMessage) + '</div>';
            return;
        }

        container.innerHTML = items.map(function (item) {
            const statusClass = item.type === "meeting"
                ? "dashboard-reminder-status--meeting"
                : "dashboard-reminder-status--followup";
            const metaOne = item.type === "meeting"
                ? (item.meeting_type || "Meeting Type")
                : ((item.priority || "normal").toString().replace(/^./, function (c) { return c.toUpperCase(); }) + " Priority");
            const metaTwo = item.location || item.assigned_to || "Assigned";
            const details = item.details || "";

            return '<article class="dashboard-reminder-card dashboard-reminder-card--' + escapeHtml(item.type || "item") + '">'
                + '<div class="dashboard-reminder-card__top">'
                + '<div>'
                + '<h6 class="dashboard-reminder-card__title mb-1">' + escapeHtml(item.title || "-") + '</h6>'
                + '<p class="dashboard-reminder-card__subtitle mb-0">' + escapeHtml(item.related_name || "-") + '</p>'
                + '</div>'
                + '<span class="dashboard-reminder-status ' + statusClass + '">' + escapeHtml(formatReminderCountdown(item.starts_in_minutes)) + '</span>'
                + '</div>'
                + '<div class="dashboard-reminder-card__time">' + escapeHtml(item.scheduled_for_text || "-") + '</div>'
                + '<div class="dashboard-reminder-card__meta">'
                + '<span>' + escapeHtml(metaOne) + '</span>'
                + '<span>' + escapeHtml(metaTwo) + '</span>'
                + '<span>' + escapeHtml(item.assigned_to || "Unassigned") + '</span>'
                + '</div>'
                + (details ? '<p class="dashboard-reminder-card__details mb-0">' + escapeHtml(details) + '</p>' : "")
                + '<div class="dashboard-reminder-card__actions">'
                + '<a href="' + escapeHtml(item.view_url || "#") + '" class="btn btn-sm btn-outline-dark-blue">View</a>'
                + '</div>'
                + '</article>';
        }).join("");
    }

    function formatReminderCountdown(minutes) {
        const totalMinutes = Number(minutes || 0);
        if (!Number.isFinite(totalMinutes)) {
            return "-";
        }

        if (totalMinutes < 0) {
            return "Started";
        }

        if (totalMinutes === 0) {
            return "Now";
        }

        if (totalMinutes < 60) {
            return totalMinutes === 1 ? "1 min left" : totalMinutes + " mins left";
        }

        const hours = Math.floor(totalMinutes / 60);
        const remainingMinutes = totalMinutes % 60;

        if (remainingMinutes === 0) {
            return hours === 1 ? "1 hr left" : hours + " hrs left";
        }

        return (hours === 1 ? "1 hr" : hours + " hrs") + " " + remainingMinutes + " mins left";
    }

    function buildDataset(label, values, color) {
        return {
            label: label,
            data: Array.isArray(values) ? values : [],
            borderColor: color,
            backgroundColor: color + "22",
            fill: false,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2,
        };
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    function formatNumber(value) {
        const n = Number(value || 0);
        return Number.isFinite(n) ? n.toLocaleString("en-IN") : "0";
    }

    function formatCurrency(value) {
        const n = Number(value || 0);
        return Number.isFinite(n)
            ? n.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : "0.00";
    }

    function formatDate(value) {
        if (!value) {
            return "-";
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return "-";
        }

        return date.toLocaleString("en-GB", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return "";
        }

        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
})();
