(function () {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initTaskTable);
    } else {
        initTaskTable();
    }

    function initTaskTable() {
        const permissions = window.crmUserPermissions?.tasks || {};
        const tableBody = document.querySelector("#tasksTable tbody");
        const searchInput = document.getElementById("tasksSearch");
        const paginationContainer = document.getElementById("tasksPagination");

        if (!tableBody || !searchInput || !paginationContainer) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

        function authHeaders(extraHeaders = {}) {
            if (typeof window.crmApplyAuthHeaders === "function") {
                return window.crmApplyAuthHeaders(extraHeaders);
            }

            return extraHeaders;
        }

        function priorityClass(priority) {
            return {
                high: "text-danger",
                medium: "text-warning",
                low: "text-info",
            }[priority] || "text-muted";
        }

        function statusClass(status) {
            return {
                completed: "bg-success",
                in_progress: "bg-primary",
                pending: "bg-warning text-dark",
            }[status] || "bg-secondary";
        }

        function formatDate(value) {
            if (!value) {
                return "-";
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return "-";
            }

            return date.toLocaleDateString("en-GB", {
                day: "2-digit",
                month: "short",
                year: "numeric",
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
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function formatLabel(value) {
            if (!value) {
                return "-";
            }

            return String(value)
                .replace(/_/g, " ")
                .replace(/\b\w/g, function (char) {
                    return char.toUpperCase();
                });
        }

        function deleteTask(taskId, button) {
            window.showDeleteConfirm("Delete this task?").then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                button.disabled = true;

                $.ajax({
                    url: `/api/tasks/${taskId}`,
                    type: "DELETE",
                    headers: authHeaders({
                        "X-CSRF-TOKEN": csrfToken,
                        "X-Requested-With": "XMLHttpRequest",
                        Accept: "application/json",
                    }),
                    success: function (response) {
                        if (response.success) {
                            if (typeof window.showAlert === "function") {
                                window.showAlert("success", response.message || "Task deleted successfully.");
                            }
                            fetchTasks();
                        } else {
                            if (typeof window.showAlert === "function") {
                                window.showAlert("error", response.message || "Failed to delete task.");
                            }
                            button.innerHTML = originalHtml;
                            button.disabled = false;
                        }
                    },
                    error: function () {
                        if (typeof window.showAlert === "function") {
                            window.showAlert("error", "Something went wrong. Please try again.");
                        }
                        button.innerHTML = originalHtml;
                        button.disabled = false;
                    },
                });
            });
        }

        function renderRows(items, meta) {
            if (!items || items.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted mb-3">
                                <i class="bi bi-file-earmark-text display-1 opacity-25"></i>
                            </div>
                            <p class="text-muted">No tasks found.</p>
                            ${permissions.create ? '<a href="/tasks/create" class="btn btn-dark-blue btn-sm rounded-pill px-4">Create Your First Task</a>' : ""}
                        </td>
                    </tr>`;
                return;
            }

            tableBody.innerHTML = items.map(function (task, index) {
                const customerName = escapeHtml(task.customer?.name || task.project?.customer?.name || "-");
                const projectName = escapeHtml(task.project?.name || "-");
                const taskTitle = escapeHtml(task.title ?? "-");
                const priorityLabel = escapeHtml(formatLabel(task.priority));
                const statusLabel = escapeHtml(formatLabel(task.status));
                const dueDate = escapeHtml(formatDate(task.due_date));
                const rowNumber = meta && meta.from ? meta.from + index : index + 1;

                return `
                    <tr>
                        <td class="ps-4">
                            <span class="text-muted small fw-medium">${rowNumber}</span>
                        </td>
                        <td>
                            <div class="fw-bold small">${taskTitle}</div>
                            <div class="text-muted small mt-1">${customerName}</div>
                        </td>
                        <td class="d-none d-md-table-cell">${projectName}</td>
                        <td class="d-none d-md-table-cell">
                            <span class="fw-semibold text-uppercase ${priorityClass(task.priority)}">
                                ${priorityLabel}
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <span class="badge crm-status-pill rounded-pill ${statusClass(task.status)}">
                                ${statusLabel}
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell">${dueDate}</td>
                        <td class="text-end pe-4 d-none d-md-table-cell">
                            <div class="d-inline-flex align-items-center gap-2">
                                ${permissions.edit ? `<a href="/tasks/${task.id}/edit" class="btn crm-action-btn btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>` : ''}
                                ${permissions.view ? `<a href="/tasks/${task.id}" class="btn crm-action-btn btn-sm" title="View"><i class="bi bi-eye"></i></a>` : ''}
                                ${permissions.delete ? `<button type="button" class="btn crm-action-btn btn-sm text-danger delete-task-btn" data-task-id="${task.id}" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
                            </div>
                        </td>
                        <td class="text-center d-md-none">
                            <button type="button" class="btn-user-expand" data-task-id="${task.id}">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </td>
                    </tr>
                    <tr class="details-row d-md-none border-0" id="details-${task.id}" style="display: none;">
                        <td colspan="8" class="p-0 border">
                            <div class="details-content">
                                <div class="row g-3">
                                    <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                                        <div class="expand-label"><i class="fa-solid fa-user"></i> Customer :</div>
                                        <div class="expand-value text-end">${customerName}</div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                                        <div class="expand-label"><i class="fa-regular fa-folder-open"></i> Project :</div>
                                        <div class="expand-value text-end">${projectName}</div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                                        <div class="expand-label"><i class="fa-solid fa-flag"></i> Priority :</div>
                                        <div class="expand-value text-end">
                                            <span class="fw-semibold text-uppercase ${priorityClass(task.priority)}">${priorityLabel}</span>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                                        <div class="expand-label"><i class="fa-solid fa-signal"></i> Status :</div>
                                        <div class="expand-value text-end">
                                            <span class="badge crm-status-pill rounded-pill ${statusClass(task.status)}">${statusLabel}</span>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                                        <div class="expand-label"><i class="fa-solid fa-calendar-days"></i> Due Date :</div>
                                        <div class="expand-value text-end">${dueDate}</div>
                                    </div>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center pt-3 mt-3 border-top">
                                    <div class="expand-label"><i class="fa-solid fa-gear"></i> Actions :</div>
                                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                                        ${permissions.edit ? `<a href="/tasks/${task.id}/edit" class="btn crm-action-btn btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>` : ""}
                                        ${permissions.view ? `<a href="/tasks/${task.id}" class="btn crm-action-btn btn-sm" title="View"><i class="bi bi-eye"></i></a>` : ""}
                                        ${permissions.delete ? `<button type="button" class="btn crm-action-btn btn-sm text-danger delete-task-btn" data-task-id="${task.id}" title="Delete"><i class="bi bi-trash"></i></button>` : ""}
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>`;
            }).join("");

            document.querySelectorAll(".delete-task-btn").forEach(function (button) {
                button.addEventListener("click", function () {
                    deleteTask(this.dataset.taskId, this);
                });
            });

            tableBody.querySelectorAll(".btn-user-expand").forEach(function (button) {
                button.addEventListener("click", function () {
                    const id = this.dataset.taskId;
                    const detailsRow = document.getElementById(`details-${id}`);
                    const icon = this.querySelector("i");

                    if (!detailsRow) {
                        return;
                    }

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
            if (!data || data.total === 0) {
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
                html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
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
                html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
            }

            html += "</ul></div>";
            paginationContainer.innerHTML = html;

            paginationContainer.querySelectorAll(".page-link[data-page]").forEach(function (link) {
                link.addEventListener("click", function (event) {
                    event.preventDefault();
                    fetchTasks(this.dataset.page);
                });
            });
        }

        function fetchTasks(page = 1) {
            let url = `/api/tasks?page=${page}`;

            if (searchInput.value.trim()) {
                url += `&search=${encodeURIComponent(searchInput.value.trim())}`;
            }

            $.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                headers: authHeaders({
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                }),
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
                            <td colspan="8" class="text-center py-5">Error loading tasks</td>
                        </tr>`;
                },
            });
        }

        let timer;
        searchInput.addEventListener("input", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                fetchTasks(1);
            }, 300);
        });

        fetchTasks();
    }
})();

$(document).ready(function () {
    function getRemoteItems(url, query) {
        const requestUrl = `${url}?q=${encodeURIComponent(query || "")}`;

        return fetch(requestUrl, {
            method: "GET",
            headers: (typeof window.crmApplyAuthHeaders === "function"
                ? window.crmApplyAuthHeaders({
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                })
                : {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            }),
            credentials: "same-origin",
        }).then((response) => response.json());
    }

    function initRemoteTomSelect(selector, config) {
        const element = document.querySelector(selector);
        if (!element || element.tomselect) {
            return;
        }

        const searchUrl = element.dataset.searchUrl;
        if (!searchUrl) {
            return;
        }

        new TomSelect(selector, {
            valueField: "id",
            labelField: "name",
            searchField: config.searchField,
            preload: true,
            load: function (query, callback) {
                getRemoteItems(searchUrl, query)
                    .then((json) => callback(Array.isArray(json) ? json : []))
                    .catch(() => callback());
            },
            render: config.render,
            placeholder: config.placeholder,
            allowEmptyOption: true,
            copyAttributesToOptions: true,
        });
    }

    function initTomSelect() {
        initRemoteTomSelect("#assigned_user_id", {
            searchField: ["name", "email"],
            placeholder: "-- Search User --",
            render: {
                option: function (item, escape) {
                    const name = item.name || item.text;
                    const email = item.email || item.data_email || "";
                    return `<div class="py-2 px-3"><div class="fw-bold">${escape(name)}</div>${email ? `<div class="text-muted small">${escape(email)}</div>` : ""}</div>`;
                },
                item: function (item, escape) {
                    return `<div>${escape(item.name || item.text)}</div>`;
                },
            },
        });

        initRemoteTomSelect("#related_id", {
            searchField: ["name", "email", "phone"],
            placeholder: "-- Search Customer --",
            render: {
                option: function (item, escape) {
                    const name = item.name || item.text;
                    const email = item.email || item.data_email || "";
                    const phone = item.phone || item.data_phone || "";
                    const details = [email, phone].filter(Boolean).join(" | ");
                    return `<div class="py-2 px-3"><div class="fw-bold">${escape(name)}</div>${details ? `<div class="text-muted small">${escape(details)}</div>` : ""}</div>`;
                },
                item: function (item, escape) {
                    return `<div>${escape(item.name || item.text)}</div>`;
                },
            },
        });
    }

    function clearErrors($form) {
        $form.find(".is-invalid").removeClass("is-invalid");
        $form.find(".ts-wrapper.is-invalid").removeClass("is-invalid");
        $form.find(".invalid-feedback").html("");
    }

    function setFieldInvalid($input) {
        if (!$input.length) {
            return;
        }

        $input.addClass("is-invalid");

        if ($input.is("select")) {
            $input.next(".ts-wrapper").addClass("is-invalid");
        }

        const flatpickr = $input[0]?._flatpickr;
        if (flatpickr?.altInput) {
            $(flatpickr.altInput).addClass("is-invalid");
        }
    }

    function clearFieldInvalid($input) {
        if (!$input.length) {
            return;
        }

        $input.removeClass("is-invalid");

        if ($input.is("select")) {
            $input.next(".ts-wrapper").removeClass("is-invalid");
        }

        const flatpickr = $input[0]?._flatpickr;
        if (flatpickr?.altInput) {
            $(flatpickr.altInput).removeClass("is-invalid");
        }
    }

    function showErrors($form, errors) {
        $.each(errors, function (field, messages) {
            const input = $form.find(`#${field}`);
            const errorDiv = $form.find(`#${field}-error`);

            setFieldInvalid(input);

            if (errorDiv.length) {
                errorDiv.html(messages[0]);
            }
        });
    }

    initTomSelect();

    $("body").on("submit", ".ajax-task-form", function (e) {
        e.preventDefault();

        const $form = $(this);
        const btn = $form.find("#submitBtn");
        const btnText = $form.find("#btnText");
        const spinner = $form.find("#btnSpinner");
        const originalText = btnText.text();

        clearErrors($form);
        spinner.removeClass("d-none");
        btnText.text($form.find('input[name="_method"]').length ? "Updating..." : "Saving...");
        btn.prop("disabled", true);

        $.ajax({
            url: $form.attr("action"),
            type: "POST",
            data: $form.serialize(),
            dataType: "json",
            headers: (typeof window.crmApplyAuthHeaders === "function"
                ? window.crmApplyAuthHeaders({
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                    Accept: "application/json",
                })
                : {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                Accept: "application/json",
            }),
            success: function (response) {
                spinner.addClass("d-none");
                btnText.text(originalText);
                btn.prop("disabled", false);

                if (typeof window.showAlert === "function") {
                    window.showAlert("success", response.message || "Task saved successfully.");
                }

                if (response.history_entry && window.crmStatusHistory) {
                    $form.find('input[name="status_comment"]').val("");
                    window.crmStatusHistory.prepend(response.history_entry);
                }

                setTimeout(function () {
                    window.location.href = response.redirect || "/tasks";
                }, 300);
            },
            error: function (xhr) {
                spinner.addClass("d-none");
                btnText.text(originalText);
                btn.prop("disabled", false);

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    showErrors($form, xhr.responseJSON.errors);
                } else if (typeof window.showAlert === "function") {
                    window.showAlert("error", xhr.responseJSON?.message || "An error occurred. Please try again.");
                }
            },
        });
    });

    $("body").on("input change", "input, select, textarea", function () {
        const $field = $(this);
        clearFieldInvalid($field);
        $(`#${$(this).attr("id")}-error`).html("");
    });
});
