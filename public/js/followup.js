(function () {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    function init() {
        const permissions = window.crmUserPermissions?.followups || {};
        const tableBody = document.getElementById("followUpsTable");

        if (!tableBody) {
            console.error("FollowUps table not found");
            return;
        }

        const paginationContainer = document.getElementById("followupPaginationContainer");
        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
        const authHeaders = (extraHeaders = {}) =>
            typeof window.crmApplyAuthHeaders === "function"
                ? window.crmApplyAuthHeaders(extraHeaders)
                : extraHeaders;

        // ✅ DELETE FUNCTION
        function deleteFollowUp(id, button, sourceType = "crm") {
            window.showDeleteConfirm("This follow-up will be deleted!").then((result) => {
                if (result.isConfirmed) {
                    const originalHtml = button.innerHTML;
                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm"></span>';
                    button.disabled = true;

                    $.ajax({
                        url: sourceType === "whatsapp" ? `/whatsapp/followups/${id}` : `/api/follow-ups/${id}`,
                        type: "DELETE",
                        headers: authHeaders({
                            "X-CSRF-TOKEN": csrfToken,
                            "X-Requested-With": "XMLHttpRequest",
                        }),
                        success: function (res) {
                            if (res.success) {
                                if (typeof window.showAlert === "function") {
                                    window.showAlert("success", res.message || "Follow-up deleted successfully.");
                                }
                                fetchFollowUps();
                            } else {
                                if (typeof window.showAlert === "function") {
                                    window.showAlert("error", res.message || "Delete failed");
                                }
                                button.innerHTML = originalHtml;
                                button.disabled = false;
                            }
                        },
                        error: function (xhr) {
                            const message = xhr?.responseJSON?.message || "Something went wrong";
                            if (typeof window.showAlert === "function") {
                                window.showAlert("error", message);
                            }
                            button.innerHTML = originalHtml;
                            button.disabled = false;
                        },
                    });
                }
            });
        }

        // ✅ RENDER ROWS (KEEP YOUR DESIGN)
        function renderRows(items) {
            if (!items || items.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-muted mb-3">
                                <i class="bi bi-file-earmark-text display-1 opacity-25"></i>
                            </div>
                            <p class="text-muted">No follow ups found.</p>
                            ${permissions.create ? '<a href="/follow-ups/create" class="btn btn-dark-blue btn-sm rounded-pill px-4">Create Your First Follow Up</a>' : ''}
                        </td>
                    </tr>`;
                return;
            }

            tableBody.innerHTML = items.map(followUp => {
                const isWhatsApp = followUp.source_type === "whatsapp";

                const entity = followUp.lead || followUp.customer;
                const entityType = followUp.lead ? 'Lead' : 'Customer';
                const initial = entity?.name
                    ? entity.name.substring(0, 1).toUpperCase()
                    : "?";

                const date = followUp.follow_up_at_display_date || "-";
                const time = followUp.follow_up_at_display_time || "";

                const priorityClass = {
                    low: "bg-info",
                    medium: "bg-primary",
                    high: "bg-danger",
                }[followUp.priority] || "bg-secondary";

                const statusClass = {
                    pending: "bg-warning text-dark",
                    resheduled: "bg-info",
                    completed: "bg-success",
                    cancelled: "bg-danger",
                }[followUp.status] || "bg-secondary";

                const priorityHtml = `<span class="badge ${priorityClass} px-3 py-2 rounded-pill text-capitalize">${followUp.priority ?? '-'}</span>`;
                const statusHtml = `<span class="badge ${statusClass} px-3 py-2 rounded-pill text-capitalize">${followUp.status ?? '-'}</span>`;
                const sourceSuffix = isWhatsApp ? ' <span class="text-success small">(WhatsApp)</span>' : '';
                const desktopActions = isWhatsApp
                    ? `
                        ${followUp.conversation_url ? `<a href="${followUp.conversation_url}" class="btn crm-action-btn btn-sm text-success"><i class="fab fa-whatsapp"></i></a>` : ''}
                        <a href="${followUp.whatsapp_followups_url || '/whatsapp/followups'}" class="btn crm-action-btn btn-sm"><i class="bi bi-eye"></i></a>
                        ${permissions.delete ? `<button class="btn crm-action-btn btn-sm text-danger delete-btn" data-source-type="whatsapp" data-id="${followUp.id}"><i class="bi bi-trash"></i></button>` : ''}
                    `
                    : `
                        ${permissions.view ? `<a href="/follow-ups/${followUp.id}" class="btn crm-action-btn btn-sm"><i class="bi bi-eye"></i></a>` : ''}
                        ${permissions.edit ? `<a href="/follow-ups/${followUp.id}/edit" class="btn crm-action-btn btn-sm"><i class="bi bi-pencil"></i></a>` : ''}
                        ${permissions.delete ? `<button class="btn crm-action-btn btn-sm text-danger delete-btn" data-source-type="crm" data-id="${followUp.id}"><i class="bi bi-trash"></i></button>` : ''}
                    `;
                const mobileActions = isWhatsApp
                    ? `
                        ${followUp.conversation_url ? `<a href="${followUp.conversation_url}" class="btn crm-action-btn btn-sm text-success"><i class="fab fa-whatsapp"></i></a>` : ''}
                        <a href="${followUp.whatsapp_followups_url || '/whatsapp/followups'}" class="btn crm-action-btn btn-sm"><i class="bi bi-eye"></i></a>
                        ${permissions.delete ? `<button class="btn crm-action-btn btn-sm text-danger delete-btn" data-source-type="whatsapp" data-id="${followUp.id}"><i class="bi bi-trash"></i></button>` : ''}
                    `
                    : `
                        ${permissions.view ? `<a href="/follow-ups/${followUp.id}" class="btn crm-action-btn btn-sm"><i class="bi bi-eye"></i></a>` : ''}
                        ${permissions.edit ? `<a href="/follow-ups/${followUp.id}/edit" class="btn crm-action-btn btn-sm"><i class="bi bi-pencil"></i></a>` : ''}
                        ${permissions.delete ? `<button class="btn crm-action-btn btn-sm text-danger delete-btn" data-source-type="crm" data-id="${followUp.id}"><i class="bi bi-trash"></i></button>` : ''}
                    `;

                return `
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3 py-1">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white bg-primary d-none d-md-flex"
                                style="width: 36px; height: 36px; font-weight: 600; font-size: 0.9rem;">
                                ${initial}
                            </div>
                            <div>
                                <div class="fw-bold small">${followUp.purpose ?? '-'}${sourceSuffix}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">
                                    ${entity?.name ?? 'Unknown'} (${entityType})
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="small fw-semibold">${date}</div>
                        <div class="text-muted small" style="font-size: 0.7rem;">
                            ${time}
                        </div>
                    </td>

                    <td class="d-none d-md-table-cell">${priorityHtml}</td>

                    <td class="d-none d-md-table-cell">
                        <div class="small">
                            ${followUp.assigned_user?.name ?? 'Unassigned'}
                        </div>
                    </td>

                    <td class="d-none d-md-table-cell">
                        <span class="badge ${statusClass} px-3 py-2 rounded-pill text-capitalize">
                            ${followUp.status ?? '-'}
                        </span>
                    </td>

                    <td class="text-end pe-4 d-none d-md-table-cell">
                        <div class="d-inline-flex align-items-center gap-2">
                            ${desktopActions}
                        </div>
                    </td>
                    <td class="text-center d-md-none">
                        <button type="button" class="btn-user-expand" data-followup-id="${followUp.id}">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </td>
                </tr>
                <tr class="details-row d-md-none border-0" id="details-${followUp.id}" style="display: none;">
                    <td colspan="7" class="p-0 border">
                        <div class="details-content">
                            <div class="row g-3">
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="expand-label"><i class="fa-solid fa-circle-info"></i> Status :</div>
                                    <div class="expand-value">${statusHtml}</div>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="expand-label"><i class="fa-solid fa-bolt"></i> Priority :</div>
                                    <div class="expand-value">${priorityHtml}</div>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="expand-label"><i class="fa-solid fa-user"></i> Staff :</div>
                                    <div class="expand-value">${followUp.assigned_user?.name ?? 'Unassigned'}</div>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center pt-3 mt-3 border-top">
                                    <div class="expand-label"><i class="fa-solid fa-gear"></i> Actions :</div>
                                    <div class="d-flex justify-content-end flex-wrap gap-2">
                                        ${mobileActions}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>`;
            }).join("");

            // attach delete
            document.querySelectorAll(".delete-btn").forEach(btn => {
                btn.addEventListener("click", function () {
                    deleteFollowUp(this.dataset.id, this, this.dataset.sourceType || "crm");
                });
            });

            // attach expand
            document.querySelectorAll(".btn-user-expand").forEach(function (button) {
                button.addEventListener("click", function () {
                    const id = this.dataset.followupId;
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

        let timer;
        const searchInput = document.getElementById("followUpSearch");
        searchInput.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(() => fetchFollowUps(1), 400);
        });

        // ✅ FETCH API
        function fetchFollowUps(page = 1) {
            let url = `/api/follow-ups?page=${page}`;

            if (searchInput && searchInput.value.trim()) {
                url += `&search=${encodeURIComponent(searchInput.value.trim())}`;
            }

            $.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                headers: authHeaders({
                    "X-Requested-With": "XMLHttpRequest",
                }),
                beforeSend: function () {
                    tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                    </td>
                </tr>`;
                },
                success: function (res) {
                    if (res.success && res.data) {
                        renderRows(res.data.data || []);
                        renderPagination(res.data);
                    }
                },
                error: function () {
                    tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        Error loading follow-ups
                    </td>
                </tr>`;
                },
            });
        }

        function renderPagination(data) {
            if (!paginationContainer) return;

            if (data.total === 0) {
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
            <div class="text-muted small">
                Showing ${from} to ${to} of ${total} results
            </div>
            <ul class="pagination crm-pagination mb-0">
    `;

            // Previous
            if (data.prev_page_url) {
                html += `
            <li class="page-item">
                <a class="page-link" href="#" data-page="${currentPage - 1}">
                    Previous
                </a>
            </li>`;
            } else {
                html += `<li class="page-item disabled"><span class="page-link">Previous</span></li>`;
            }

            // Pages
            for (let i = 1; i <= lastPage; i++) {
                if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += i === currentPage
                        ? `<li class="page-item active"><span class="page-link">${i}</span></li>`
                        : `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            // Next
            if (data.next_page_url) {
                html += `
            <li class="page-item">
                <a class="page-link" href="#" data-page="${currentPage + 1}">
                    Next
                </a>
            </li>`;
            } else {
                html += `<li class="page-item disabled"><span class="page-link">Next</span></li>`;
            }

            html += `</ul></div>`;

            paginationContainer.innerHTML = html;

            // Click events
            document.querySelectorAll(".page-link[data-page]").forEach(link => {
                link.addEventListener("click", function (e) {
                    e.preventDefault();
                    fetchFollowUps(this.dataset.page);
                });
            });
        }

        // ✅ INITIAL LOAD
        fetchFollowUps();
    }
})();

$(document).ready(function () {
    const authHeaders = (extraHeaders = {}) =>
        typeof window.crmApplyAuthHeaders === "function"
            ? window.crmApplyAuthHeaders(extraHeaders)
            : extraHeaders;

    function getEnhancedElement($field) {
        if (!$field || !$field.length || !$field.is('select')) {
            return $();
        }

        if ($field.next('.ts-wrapper').length) {
            return $field.next('.ts-wrapper');
        }

        if ($field.next('.select2-container').length) {
            return $field.next('.select2-container');
        }

        return $();
    }

    function getErrorAnchor($field) {
        const $enhanced = getEnhancedElement($field);
        return $enhanced.length ? $enhanced : $field;
    }

    function handleValidationErrors($form, errors) {
        Object.keys(errors).forEach(function (field) {
            const fieldEl = $form.find('[name="' + field + '"]');
            const errorEl = $form.find('#' + field + '-error');
            if (fieldEl.length) {
                fieldEl.addClass('is-invalid');
                getEnhancedElement(fieldEl).addClass('is-invalid');
                if (errorEl.length) {
                    errorEl.html(errors[field][0]);
                } else {
                    const errorHtml = `<div class="invalid-feedback ajax-error">${errors[field][0]}</div>`;
                    const errorAnchor = getErrorAnchor(fieldEl);
                    errorAnchor.siblings('.invalid-feedback.ajax-error').remove();
                    errorAnchor.after(errorHtml);
                }
            }
        });
    }

    // Handle form submission
    $('body').on('submit', '.ajax-followup-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const url = $form.attr('action');
        const btn = $form.find('button[type=submit]');
        const originalText = btn.html();
        const method = 'POST';

        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.ts-wrapper.is-invalid').removeClass('is-invalid');
        $form.find('.select2-container.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback.ajax-error').remove();
        $form.find('.invalid-feedback').not('.ajax-error').html('');
        $form.find('.ajax-alert').remove();

        const formData = new FormData(this);

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

        $.ajax({
            url: url,
            method: method,
            data: formData,
            processData: false,
            contentType: false,
            headers: authHeaders({
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                Accept: 'application/json'
            }),
            success: function (response) {
                const message = response.message || 'Follow-up saved successfully.';

                const isStatusCommentFlow = $form.hasClass('js-status-comment-form') && $form.find('input[name="status_comment"]').val().trim() !== '';

                if (isStatusCommentFlow && response.history_entry && window.crmStatusHistory) {
                    if (typeof window.showAlert === "function") {
                        window.showAlert('success', message);
                    }
                    $form.find('input[name="status_comment"]').val('');
                    window.crmStatusHistory.prepend(response.history_entry);
                    return;
                }

                const redirect = response.redirect || '/follow-ups';
                if (typeof window.showAlert === "function") {
                    window.showAlert('success', message, 'Success!', redirect);
                } else if (redirect) {
                    window.location.href = redirect;
                }
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    handleValidationErrors($form, xhr.responseJSON.errors);
                } else {
                    const response = xhr.responseJSON || {};
                    const message = response.message || 'Something went wrong while submitting the form. Please try again.';
                    const alert = `<div class="alert alert-danger ajax-alert" role="alert">${message}</div>`;
                    $form.prepend(alert);
                }
            },
            complete: function () {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    $('body').on('input change', '.ajax-followup-form input, .ajax-followup-form select, .ajax-followup-form textarea', function () {
        const $field = $(this);
        $field.removeClass('is-invalid');
        getEnhancedElement($field).removeClass('is-invalid');
        const fieldName = $field.attr('name');
        if (fieldName) {
            $field.closest('form').find('#' + fieldName + '-error').html('');
        }
        getErrorAnchor($field).siblings('.invalid-feedback.ajax-error').remove();
    });

    if ($.fn.select2) {
        $('.select2').select2({
            width: '100%',
            theme: 'bootstrap-5'
        });
    }
});
