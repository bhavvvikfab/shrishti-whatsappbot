(function () {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    function init() {
        const listContainer = document.getElementById("notificationList");
        if (!listContainer) return;

        const paginationContainer  = document.getElementById("notificationsPagination");
        const actionBtns           = document.getElementById("notifActionBtns");
        const deleteAllBtn         = document.getElementById("deleteAllNotificationsBtn");
        const markAllReadBtn       = document.getElementById("markAllReadBtn");

        const csrfToken            = window.crmCsrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
        const notificationsListUrl = window.crmNotificationsListUrl  || "/notifications/list";
        const deleteAllUrl         = window.crmDeleteAllNotificationsUrl || "/notifications/delete-all";
        const markAllReadUrl       = window.crmMarkAllReadUrl || "/notifications/mark-all-read";

        // ── Helpers ────────────────────────────────────────────────────────────

        function formatDate(dateValue) {
            if (!dateValue) return "Not Set";
            const date = new Date(dateValue);
            if (Number.isNaN(date.getTime())) return "Not Set";

            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60)     return "just now";
            if (diffInSeconds < 3600)   return Math.floor(diffInSeconds / 60) + "m ago";
            if (diffInSeconds < 86400)  return Math.floor(diffInSeconds / 3600) + "h ago";
            if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + "d ago";

            return date.toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
        }

        function setActionBtnsVisible(visible) {
            if (!actionBtns) return;
            if (visible) {
                actionBtns.classList.remove('d-none');
            } else {
                actionBtns.classList.add('d-none');
            }
        }

        // ── Render ─────────────────────────────────────────────────────────────

        function renderNotifications(items) {
            if (!items || items.length === 0) {
                listContainer.innerHTML = `
                    <div class="notif-empty-state">
                        <div class="notif-empty-state__scene">
                            <!-- blob -->
                            <div class="notif-empty-state__blob"></div>

                            <!-- floating decorations -->
                            <span class="notif-empty-state__plus" style="top:8px;left:22px">+</span>
                            <span class="notif-empty-state__plus" style="top:14px;right:18px">+</span>
                            <span class="notif-empty-state__plus" style="bottom:22px;right:10px">+</span>
                            <span class="notif-empty-state__dot" style="width:5px;height:5px;top:38px;left:14px"></span>
                            <span class="notif-empty-state__dot" style="width:4px;height:4px;bottom:30px;left:28px"></span>
                            <span class="notif-empty-state__dot" style="width:4px;height:4px;top:50px;right:12px"></span>

                            <!-- sad bell SVG -->
                            <div class="notif-empty-state__bell">
                                <svg viewBox="0 0 100 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <!-- bell body -->
                                    <path d="M50 10 C32 10 22 26 22 42 L18 72 H82 L78 42 C78 26 68 10 50 10Z"
                                          stroke="#b0bec5" stroke-width="3.5" stroke-linejoin="round" fill="#f8fafc"/>
                                    <!-- clapper -->
                                    <ellipse cx="50" cy="76" rx="10" ry="5" fill="#cfd8dc"/>
                                    <!-- base knob -->
                                    <circle cx="50" cy="10" r="4" stroke="#b0bec5" stroke-width="3" fill="#f8fafc"/>
                                    <!-- sad eyes -->
                                    <circle cx="43" cy="48" r="2.2" fill="#90a4ae"/>
                                    <circle cx="57" cy="48" r="2.2" fill="#90a4ae"/>
                                    <!-- sad mouth -->
                                    <path d="M43 58 Q50 54 57 58" stroke="#90a4ae" stroke-width="2.2" stroke-linecap="round" fill="none"/>
                                    <!-- speech bubble -->
                                    <rect x="60" y="18" width="28" height="20" rx="8" fill="#eceff1" stroke="#b0bec5" stroke-width="2"/>
                                    <path d="M66 38 L62 44 L70 38Z" fill="#eceff1" stroke="#b0bec5" stroke-width="1.5" stroke-linejoin="round"/>
                                    <!-- dots in bubble -->
                                    <circle cx="68" cy="28" r="2" fill="#90a4ae"/>
                                    <circle cx="74" cy="28" r="2" fill="#90a4ae"/>
                                    <circle cx="80" cy="28" r="2" fill="#90a4ae"/>
                                    <!-- shadow line -->
                                    <ellipse cx="50" cy="82" rx="28" ry="4" fill="#e2e8f0"/>
                                </svg>
                            </div>
                        </div>

                        <p class="notif-empty-state__title">No Notifications</p>
                        <p class="notif-empty-state__sub">Notification Inbox Empty</p>
                    </div>`;
                setActionBtnsVisible(false);
                return;
            }

            setActionBtnsVisible(true);

            listContainer.innerHTML = items.map(notification => `
                <div class="notification-row align-items-center">
                    <span class="notification-avatar">
                        <i class="bi bi-bell"></i>
                    </span>
                    <div class="d-flex flex-grow-1 justify-content-between align-items-center">
                        <div class="notification-message">
                            ${notification.notification_text}
                        </div>
                        <div class="d-flex flex-wrap align-items-center">
                            <div class="notification-time">
                                ${formatDate(notification.created_at)}
                            </div>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <button class="dropdown-item mark-as-read-ajax fw-semibold" type="button"
                                data-id="${notification.id}">
                                <i class="fa-solid fa-check-double me-2"></i>Mark as Read
                            </button>
                        </div>
                    </div>
                </div>
            `).join("");

            attachRowEvents();
        }

        function renderPagination(data) {
            if (!paginationContainer) return;
            if (data.total === 0) {
                paginationContainer.innerHTML = "";
                return;
            }

            const from        = data.from || 0;
            const to          = data.to || 0;
            const total       = data.total || 0;
            const currentPage = data.current_page || 1;
            const lastPage    = data.last_page || 1;

            let html = `
                <div class="crm-pagination-container">
                    <div class="text-muted small fw-medium">
                        Showing ${from} to ${to} of ${total} results
                    </div>
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

            html += '</ul></div>';
            paginationContainer.innerHTML = html;

            document.querySelectorAll('.page-link[data-page]').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    fetchNotifications(this.dataset.page);
                });
            });
        }

        // ── Fetch ──────────────────────────────────────────────────────────────

        function fetchNotifications(page = 1) {
            const separator = notificationsListUrl.includes("?") ? "&" : "?";
            $.ajax({
                url: `${notificationsListUrl}${separator}page=${page}`,
                type: 'GET',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                beforeSend: function () {
                    listContainer.innerHTML = `
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>`;
                },
                success: function (res) {
                    if (res.success && res.data) {
                        renderNotifications(res.data.data);
                        renderPagination(res.data);
                    }
                },
                error: function () {
                    listContainer.innerHTML = `
                        <div class="text-center text-danger py-5">
                            Error loading notifications. Please try again.
                        </div>`;
                }
            });
        }

        // ── Per-row events ─────────────────────────────────────────────────────

        function attachRowEvents() {
            document.querySelectorAll('.mark-as-read-ajax').forEach(button => {
                button.addEventListener('click', function () {
                    const id  = this.getAttribute('data-id');
                    const row = this.closest('.notification-row');

                    fetch(`/notifications/${id}/read`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            row.style.opacity = '0.5';
                            row.style.pointerEvents = 'none';
                            setTimeout(() => fetchNotifications(), 500);
                        } else {
                            Swal.fire('Error', data.message || 'Error marking notification as read', 'error');
                        }
                    })
                    .catch(err => console.error('Error:', err));
                });
            });
        }

        // ── Mark All Read ──────────────────────────────────────────────────────

        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function () {
                Swal.fire({
                    title: 'Mark All as Read?',
                    text: 'All your unread notifications will be marked as read.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3B5BDB',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fa-solid fa-check-double me-1"></i> Yes, Mark All',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    focusCancel: true,
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    markAllReadBtn.disabled = true;
                    markAllReadBtn.innerHTML = '<span class="btn-notif-action__icon"><i class="fa-solid fa-spinner fa-spin"></i></span> <span class="btn-notif-action__label">Updating...</span>';

                    fetch(markAllReadUrl, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            fetchNotifications();
                            Swal.fire({
                                title: 'Done!',
                                text: 'All notifications marked as read.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false,
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to update notifications.', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'An error occurred. Please try again.', 'error'))
                    .finally(() => {
                        markAllReadBtn.disabled = false;
                        markAllReadBtn.innerHTML = '<span class="btn-notif-action__icon"><i class="fa-solid fa-check-double"></i></span> <span class="btn-notif-action__label">Mark All Read</span>';
                    });
                });
            });
        }

        // ── Delete All ─────────────────────────────────────────────────────────

        if (deleteAllBtn) {
            deleteAllBtn.addEventListener('click', function () {
                Swal.fire({
                    title: 'Delete All Notifications?',
                    text: 'This will permanently remove all your notifications. This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#c0392b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> Yes, Delete All',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    focusCancel: true,
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    deleteAllBtn.disabled = true;
                    deleteAllBtn.innerHTML = '<span class="btn-notif-action__icon"><i class="fa-solid fa-spinner fa-spin"></i></span> <span class="btn-notif-action__label">Deleting...</span>';

                    fetch(deleteAllUrl, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            fetchNotifications();
                            Swal.fire({
                                title: 'Cleared!',
                                text: 'All notifications have been deleted.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false,
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete notifications.', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'An error occurred. Please try again.', 'error'))
                    .finally(() => {
                        deleteAllBtn.disabled = false;
                        deleteAllBtn.innerHTML = '<span class="btn-notif-action__icon"><i class="fa-solid fa-trash-can"></i></span> <span class="btn-notif-action__label">Delete All</span>';
                    });
                });
            });
        }

        // ── Init ───────────────────────────────────────────────────────────────

        fetchNotifications();
    }
})();
