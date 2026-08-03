(function () {
    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.crmStatusHistory = {
        showLoading: function () {
            const tbody = document.querySelector(".js-status-history-body");
            if (!tbody || tbody.querySelector(".js-status-history-loading")) {
                return;
            }

            const row = document.createElement("tr");
            row.className = "js-status-history-loading";
            row.innerHTML =
                '<td colspan="5" class="text-center text-muted py-3">' +
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
                "Saving status update..." +
                "</td>";
            tbody.prepend(row);
        },
        clearLoading: function () {
            document.querySelector(".js-status-history-loading")?.remove();
        },
        prepend: function (entry) {
            if (!entry) {
                return;
            }

            const tbody = document.querySelector(".js-status-history-body");
            if (!tbody) {
                return;
            }

            this.clearLoading();

            const emptyRow = tbody.querySelector(".js-status-history-empty");
            if (emptyRow) {
                emptyRow.remove();
            }

            Array.from(tbody.querySelectorAll("tr")).forEach(function (row, index) {
                const firstCell = row.querySelector("td");
                if (firstCell) {
                    firstCell.textContent = String(index + 2);
                }
            });

            const row = document.createElement("tr");
            row.innerHTML =
                "<td>1</td>" +
                "<td>" + escapeHtml(entry.status_label || "-") + "</td>" +
                "<td>" + escapeHtml(entry.comment || "-") + "</td>" +
                "<td>" + escapeHtml(entry.updated_by || "System") + "</td>" +
                "<td>" + escapeHtml(entry.created_at || "-") + "</td>";

            tbody.prepend(row);
        },
    };

    function initStatusCommentBox() {
        const modalElement = document.getElementById("statusCommentModal");
        const commentInput = document.getElementById("statusCommentInput");
        const commentError = document.getElementById("statusCommentError");
        const saveButton = document.getElementById("statusCommentSaveBtn");

        if (!modalElement || !window.bootstrap || !commentInput || !saveButton) {
            return;
        }

        const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        let activeContext = null;
        let isSaving = false;
        const defaultSaveButtonHtml = saveButton.innerHTML;

        function ensureHiddenInput(form) {
            let input = form.querySelector('input[name="status_comment"]');
            if (!input) {
                input = document.createElement("input");
                input.type = "hidden";
                input.name = "status_comment";
                form.appendChild(input);
            }

            return input;
        }

        function openModal(select) {
            const form = select.closest("form");
            if (!form) {
                return;
            }

            const currentValue = select.value;
            const previousValue = select.dataset.confirmedValue ?? "";

            activeContext = {
                form: form,
                select: select,
                currentValue: currentValue,
                previousValue: previousValue,
            };

            commentInput.value = "";
            commentError.classList.add("d-none");
            modal.show();
            setTimeout(function () {
                commentInput.focus();
            }, 150);
        }

        function setSavingState(saving) {
            isSaving = saving;
            saveButton.disabled = saving;
            saveButton.innerHTML = saving
                ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...'
                : defaultSaveButtonHtml;
        }

        function revertSelection() {
            if (!activeContext || !activeContext.select) {
                return;
            }

            activeContext.select.value = activeContext.previousValue;
            if (activeContext.select.tomselect) {
                activeContext.select.tomselect.setValue(activeContext.previousValue, true);
            }
        }

        function clearFormErrors(form) {
            if (!form) {
                return;
            }

            form.querySelectorAll(".is-invalid").forEach(function (element) {
                element.classList.remove("is-invalid");
            });

            form.querySelectorAll(".ts-wrapper.is-invalid").forEach(function (element) {
                element.classList.remove("is-invalid");
            });

            form.querySelectorAll(".invalid-feedback").forEach(function (element) {
                if (!element.matches("#statusCommentError")) {
                    element.textContent = "";
                }
            });

            form.querySelectorAll(".invalid-feedback.ajax-error").forEach(function (element) {
                element.remove();
            });
        }

        function showValidationErrors(form, errors) {
            Object.entries(errors || {}).forEach(function ([field, messages]) {
                const input = form.querySelector(`[name="${field}"]`);
                const message = Array.isArray(messages) ? messages[0] : messages;

                if (!input) {
                    return;
                }

                input.classList.add("is-invalid");

                if (input.tomselect) {
                    input.nextElementSibling?.classList.add("is-invalid");
                }

                const errorBlock = form.querySelector(`#${field}-error`);
                if (errorBlock) {
                    errorBlock.textContent = message;
                    return;
                }

                const ajaxError = document.createElement("div");
                ajaxError.className = "invalid-feedback ajax-error";
                ajaxError.textContent = message;
                input.insertAdjacentElement("afterend", ajaxError);
            });
        }

        function submitStatusChange(context, comment) {
            const action = context.form.getAttribute("action") || "";
            const actionMatch = action.match(/\/api\/([^/]+)\/(\d+)(?:\/?|$)/i);
            const resource = actionMatch?.[1] || "";
            const recordId = actionMatch?.[2] || "";
            const moduleMap = {
                tasks: "task",
                "follow-ups": "followup",
                leads: "lead",
                projects: "project",
                services: "service",
                pipelines: "pipeline",
                meetings: "meeting",
                tickets: "ticket",
                deals: "deal",
            };
            const module = moduleMap[resource];

            if (!module || !recordId) {
                return $.Deferred().reject({
                    responseJSON: {
                        message: "Unable to detect status update module.",
                    },
                }).promise();
            }

            const payload = {
                module: module,
                record_id: recordId,
                status: context.currentValue,
                comment: comment,
            };

            return $.ajax({
                url: "/api/status-history",
                type: "POST",
                data: payload,
                dataType: "json",
                headers: (typeof window.crmApplyAuthHeaders === "function"
                    ? window.crmApplyAuthHeaders({
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN":
                            document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
                            context.form.querySelector('input[name="_token"]')?.value ||
                            "",
                        Accept: "application/json",
                    })
                    : {
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN":
                        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
                        context.form.querySelector('input[name="_token"]')?.value ||
                        "",
                    Accept: "application/json",
                }),
            });
        }

        document.querySelectorAll(".js-status-comment-form").forEach(function (form) {
            ensureHiddenInput(form);

            form.querySelectorAll(".js-status-comment-trigger").forEach(function (select) {
                select.dataset.confirmedValue = select.value || "";

                select.addEventListener("change", function (event) {
                    if (!event.isTrusted) {
                        select.dataset.confirmedValue = select.value || "";
                        return;
                    }

                    const confirmedValue = select.dataset.confirmedValue ?? "";
                    const nextValue = select.value || "";

                    ensureHiddenInput(form).value = "";

                    if (nextValue === confirmedValue) {
                        return;
                    }

                    openModal(select);
                });
            });
        });

        saveButton.addEventListener("click", function () {
            const comment = commentInput.value.trim();
            if (!comment || !activeContext) {
                commentError.classList.remove("d-none");
                return;
            }

            const context = activeContext;
            commentError.classList.add("d-none");
            clearFormErrors(context.form);
            setSavingState(true);
            window.crmStatusHistory?.showLoading?.();

            submitStatusChange(context, comment)
                .done(function (response) {
                    if (context.select.tomselect) {
                        context.select.tomselect.setValue(context.currentValue, true);
                    } else {
                        context.select.value = context.currentValue;
                    }

                    context.select.dataset.confirmedValue = context.currentValue;

                    if (response?.history_entry && window.crmStatusHistory) {
                        window.crmStatusHistory.prepend(response.history_entry);
                    }

                    activeContext = null;
                    modal.hide();
                })
                .fail(function (xhr) {
                    const response = xhr.responseJSON || {};
                    window.crmStatusHistory?.clearLoading?.();

                    if (xhr.status === 422 && response.errors) {
                        showValidationErrors(context.form, response.errors);
                        return;
                    }

                    if (typeof window.showAlert === "function") {
                        window.showAlert("error", response.message || "Unable to save status comment.");
                    }
                })
                .always(function () {
                    window.crmStatusHistory?.clearLoading?.();
                    setSavingState(false);
                });
        });

        modalElement.addEventListener("hidden.bs.modal", function () {
            if (activeContext && !isSaving) {
                revertSelection();
            }

            commentInput.value = "";
            commentError.classList.add("d-none");
            if (!isSaving) {
                activeContext = null;
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initStatusCommentBox);
    } else {
        initStatusCommentBox();
    }
})();
