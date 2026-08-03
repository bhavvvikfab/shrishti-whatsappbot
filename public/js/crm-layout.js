(function () {
    function showToast(message, type = "info", duration = 5000) {
        const toastId = "toast-" + Date.now();
        const bgColor =
            {
                success: "bg-success",
                error: "bg-danger",
                warning: "bg-warning",
                info: "bg-info",
            }[type] || "bg-info";

        const icon =
            {
                success: "bi-check-circle-fill",
                error: "bi-exclamation-triangle-fill",
                warning: "bi-exclamation-circle-fill",
                info: "bi-info-circle-fill",
            }[type] || "bi-info-circle-fill";

        const toast = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="${duration}">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <i class="bi ${icon} me-2"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        $("#toastContainer").append(toast);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();

        $(`#${toastId}`).on("hidden.bs.toast", function () {
            $(this).remove();
        });
    }

    function enhanceFormLabels() {
        if (!document.body.classList.contains("crm-form-page")) {
            return;
        }

        const iconMatchers = [
            { match: /(customer|client|assigned to|assigned staff|assigned for|lead name|lead|staff|user|contact)/, icon: "bi-person-fill" },
            { match: /(email|mail)/, icon: "bi-envelope-fill" },
            { match: /(phone|mobile|whatsapp|call)/, icon: "bi-telephone-fill" },
            { match: /(address|location|city|country)/, icon: "bi-geo-alt-fill" },
            { match: /(status)/, icon: "bi-info-circle-fill" },
            { match: /(priority)/, icon: "bi-exclamation-circle-fill" },
            { match: /(date|time|due|follow up|scheduled|start|end|deadline)/, icon: "bi-calendar-event-fill" },
            { match: /(comment|description|agenda|note|remarks|message|details)/, icon: "bi-text-paragraph" },
            { match: /(meeting type|type|category|stage|source)/, icon: "bi-diagram-3-fill" },
            { match: /(amount|value|price|cost|budget|revenue)/, icon: "bi-currency-dollar" },
            { match: /(probability|percent|percentage)/, icon: "bi-percent" },
            { match: /(image|photo|logo|avatar|icon)/, icon: "bi-image-fill" },
            { match: /(company|organization|business)/, icon: "bi-building" },
            { match: /(project)/, icon: "bi-folder-fill" },
            { match: /(task)/, icon: "bi-list-check" },
            { match: /(deal|opportunity)/, icon: "bi-briefcase-fill" },
            { match: /(ticket|support)/, icon: "bi-life-preserver" },
            { match: /(service)/, icon: "bi-tools" },
            { match: /(meeting)/, icon: "bi-camera-video-fill" },
        ];

        const getLabelText = function (label) {
            return Array.from(label.childNodes)
                .filter(function (node) {
                    return node.nodeType === Node.TEXT_NODE || (node.nodeType === Node.ELEMENT_NODE && !node.classList.contains("text-danger"));
                })
                .map(function (node) {
                    return node.textContent || "";
                })
                .join(" ")
                .replace(/\s+/g, " ")
                .trim()
                .toLowerCase();
        };

        const findAssociatedField = function (label) {
            if (label.htmlFor) {
                return document.getElementById(label.htmlFor);
            }

            const fieldContainer = label.closest(".col, .col-md-6, .col-md-4, .col-md-3, .col-lg-6, .col-lg-4, .col-lg-3, .col-xl-6, .col-xl-4, .col-xl-3") || label.parentElement;
            return fieldContainer ? fieldContainer.querySelector("input, select, textarea") : null;
        };

        const resolveIcon = function (label, field) {
            const parts = [getLabelText(label)];
            if (field) {
                parts.push((field.name || "").replaceAll("_", " "));
                parts.push((field.id || "").replaceAll("_", " "));
            }

            const haystack = parts.join(" ").toLowerCase();
            const match = iconMatchers.find(function (entry) {
                return entry.match.test(haystack);
            });

            return match ? match.icon : "bi-tag-fill";
        };

        document.querySelectorAll(".crm-form-page form label.form-label").forEach(function (label) {
            if (label.dataset.iconEnhanced === "true" || label.querySelector(".crm-label-icon, .fa, .fas, .far, .fab, .bi")) {
                return;
            }

            const field = findAssociatedField(label);
            const iconClass = resolveIcon(label, field);
            const icon = document.createElement("i");
            icon.className = "bi " + iconClass + " crm-label-icon";
            icon.setAttribute("aria-hidden", "true");

            label.classList.add("crm-label-with-icon");
            label.prepend(icon);
            label.dataset.iconEnhanced = "true";
        });
    }

    function initCrmRemoteSelect(selector, options) {
        if (!window.TomSelect) {
            return null;
        }

        const element = document.querySelector(selector);
        if (!element || element.tomselect) {
            return null;
        }

        const searchUrl = element.dataset.searchUrl;
        if (!searchUrl) {
            return null;
        }

        const inferredSearchType = options.searchType
            || element.dataset.searchType
            || (searchUrl.includes("/users/search") ? "user" : null)
            || (searchUrl.includes("/customers/search") ? "customer" : null)
            || ((element.name || element.id || "").includes("user") ? "user" : null)
            || ((element.name || element.id || "").includes("customer") ? "customer" : null)
            || "default";
        const placeholder = element.dataset.searchPlaceholder
            || options.placeholder
            || (inferredSearchType === "user" ? "-- Search User --" : null)
            || (inferredSearchType === "customer" ? "-- Search Customer --" : null)
            || "-- Search --";

        const configByType = {
            user: {
                searchField: ["name", "email"],
                render: {
                    option: function (item, escape) {
                        const name = item.name || item.text || "";
                        const email = item.email || item.data_email || "";
                        return '<div class="py-2 px-3"><div class="fw-bold">' + escape(name) + "</div>" +
                            (email ? '<div class="text-muted small">' + escape(email) + "</div>" : "") +
                            "</div>";
                    },
                    item: function (item, escape) {
                        return "<div>" + escape(item.name || item.text || "") + "</div>";
                    }
                }
            },
            customer: {
                searchField: ["name", "email", "phone"],
                render: {
                    option: function (item, escape) {
                        const name = item.name || item.text || "";
                        const email = item.email || item.data_email || "";
                        const phone = item.phone || item.data_phone || "";
                        const details = [email, phone].filter(Boolean).join(" | ");
                        return '<div class="py-2 px-3"><div class="fw-bold">' + escape(name) + "</div>" +
                            (details ? '<div class="text-muted small">' + escape(details) + "</div>" : "") +
                            "</div>";
                    },
                    item: function (item, escape) {
                        return "<div>" + escape(item.name || item.text || "") + "</div>";
                    }
                }
            },
            default: {
                searchField: ["name"],
                render: {
                    option: function (item, escape) {
                        return '<div class="py-2 px-3">' + escape(item.name || item.text || "") + "</div>";
                    },
                    item: function (item, escape) {
                        return "<div>" + escape(item.name || item.text || "") + "</div>";
                    }
                }
            }
        };

        const config = configByType[inferredSearchType] || configByType.default;
        const initialOptions = Array.from(element.options)
            .filter(function (option) {
                return option.value !== "";
            })
            .map(function (option) {
                return {
                    id: option.value,
                    name: option.textContent.trim(),
                    email: option.dataset.email || "",
                    phone: option.dataset.phone || "",
                };
            });
        const initialItems = Array.from(element.selectedOptions)
            .filter(function (option) {
                return option.value !== "";
            })
            .map(function (option) {
                return option.value;
            });

        return new TomSelect(selector, {
            valueField: "id",
            labelField: "name",
            searchField: config.searchField,
            options: initialOptions,
            items: initialItems,
            preload: true,
            hideSelected: true,
            load: function (query, callback) {
                const requestUrl = searchUrl + "?q=" + encodeURIComponent(query || "");
                fetch(requestUrl, {
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
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (json) {
                        const fetchedItems = Array.isArray(json) ? json : [];
                        const selectedItems = Array.from(element.options)
                            .filter(function (option) {
                                return option.selected && option.value !== "";
                            })
                            .map(function (option) {
                                return {
                                    id: option.value,
                                    name: option.textContent.trim(),
                                    email: option.dataset.email || "",
                                    phone: option.dataset.phone || "",
                                };
                            });

                        const mergedItems = [...selectedItems, ...initialOptions, ...fetchedItems].filter(function (item, index, items) {
                            return item && item.id && items.findIndex(function (candidate) {
                                return String(candidate.id) === String(item.id);
                            }) === index;
                        });

                        callback(mergedItems);
                    })
                    .catch(function () {
                        callback();
                    });
            },
            render: config.render,
            placeholder: placeholder,
            allowEmptyOption: true,
            copyAttributesToOptions: true,
        });
    }

    function initPageChrome() {
        const forms = document.querySelectorAll(".js-validate");
        forms.forEach(function (form) {
            form.addEventListener("submit", function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add("was-validated");
            }, false);
        });

        const sidebarToggle = document.getElementById("sidebarToggle");
        const mobileSidebarToggle = document.getElementById("mobileSidebarToggle");
        const sidebar = document.querySelector(".crm-sidebar");
        const sidebarBackdrop = document.getElementById("crmSidebarBackdrop");

        if (sidebar) {
            const syncToggleState = function () {
                const expanded = window.innerWidth < 992
                    ? sidebar.classList.contains("open")
                    : !sidebar.classList.contains("collapsed");
                [sidebarToggle, mobileSidebarToggle].forEach(function (btn) {
                    if (btn) btn.setAttribute("aria-expanded", String(expanded));
                });
            };

            const toggleSidebar = function () {
                if (window.innerWidth < 992) {
                    sidebar.classList.toggle("open");
                    if (sidebarBackdrop) {
                        sidebarBackdrop.classList.toggle("show", sidebar.classList.contains("open"));
                    }
                    document.body.classList.toggle("crm-sidebar-open", sidebar.classList.contains("open"));
                } else if (sidebarToggle) {
                    sidebar.classList.toggle("collapsed");
                }
                syncToggleState();
            };

            if (sidebarToggle) {
                sidebarToggle.addEventListener("click", toggleSidebar);
            }
            if (mobileSidebarToggle) {
                mobileSidebarToggle.addEventListener("click", toggleSidebar);
            }

            window.addEventListener("resize", function () {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove("open");
                    document.body.classList.remove("crm-sidebar-open");
                    if (sidebarBackdrop) {
                        sidebarBackdrop.classList.remove("show");
                    }
                }
                syncToggleState();
            });

            // Home Screen / standalone: keep sidebar closed on launch.
            if (window.innerWidth < 992 || window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone) {
                sidebar.classList.remove("open");
                document.body.classList.remove("crm-sidebar-open");
                if (sidebarBackdrop) sidebarBackdrop.classList.remove("show");
            }

            syncToggleState();
        }

        if (sidebarBackdrop && sidebar) {
            sidebarBackdrop.addEventListener("click", function () {
                sidebar.classList.remove("open");
                document.body.classList.remove("crm-sidebar-open");
                sidebarBackdrop.classList.remove("show");
            });
        }

        if (window.flatpickr) {
            document.querySelectorAll(".js-date").forEach(function (element) {
                flatpickr(element, {
                    allowInput: true,
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "d/m/Y",
                    minDate: element.dataset.minDate || null,
                });
            });

            flatpickr(".js-datetime", {
                allowInput: true,
                enableTime: true,
                time_24hr: false,
                dateFormat: "Y-m-d H:i",
                altInput: true,
                altFormat: "d/m/Y h:i K",
            });
        }

        enhanceFormLabels();
    }

    function initRemoteSelects() {
        if (!window.TomSelect) {
            return;
        }

        document.querySelectorAll("select[data-search-url]").forEach(function (element) {
            if (!element.id || element.tomselect) {
                return;
            }

            initCrmRemoteSelect("#" + element.id, {
                searchType: element.dataset.searchType,
                placeholder: element.dataset.searchPlaceholder,
            });
        });
    }

    window.showToast = showToast;
    window.initCrmRemoteSelect = initCrmRemoteSelect;

    function initHardRefreshButton() {
        const btn = document.getElementById("hardRefreshBtn");
        if (!btn || btn.dataset.bound === "1") {
            return;
        }
        btn.dataset.bound = "1";

        btn.addEventListener("click", function () {
            btn.disabled = true;
            btn.classList.add("is-spinning");

            const hardReload = function () {
                const url = new URL(window.location.href);
                url.searchParams.delete("_hard");
                url.searchParams.set("_hard", String(Date.now()));
                window.location.replace(url.toString());
            };

            if (window.caches && typeof window.caches.keys === "function") {
                window.caches.keys()
                    .then(function (names) {
                        return Promise.all(names.map(function (name) {
                            return window.caches.delete(name);
                        }));
                    })
                    .catch(function () {})
                    .finally(hardReload);
                return;
            }

            try {
                window.location.reload(true);
            } catch (error) {
                hardReload();
            }

            window.setTimeout(hardReload, 120);
        });
    }

    function cleanHardRefreshQueryParam() {
        const url = new URL(window.location.href);
        if (!url.searchParams.has("_hard")) {
            return;
        }
        url.searchParams.delete("_hard");
        const next = url.pathname + url.search + url.hash;
        window.history.replaceState({}, "", next);
    }

    window.hardRefreshCurrentTab = function () {
        document.getElementById("hardRefreshBtn")?.click();
    };

    document.addEventListener("DOMContentLoaded", function () {
        cleanHardRefreshQueryParam();
        initPageChrome();
        initRemoteSelects();
        initNavGroups();
        initHardRefreshButton();
    });

    function initNavGroups() {
        document.querySelectorAll(".nav-group__toggle").forEach(function (toggle) {
            toggle.addEventListener("click", function (e) {
                e.preventDefault();
                const menuId = toggle.getAttribute("aria-controls");
                const menu = menuId ? document.getElementById(menuId) : null;
                if (!menu) return;

                const isOpen = toggle.getAttribute("aria-expanded") === "true";

                if (isOpen) {
                    // collapse
                    menu.style.height = menu.scrollHeight + "px";
                    requestAnimationFrame(function () {
                        menu.style.transition = "height .22s ease";
                        menu.style.height = "0";
                        menu.style.overflow = "hidden";
                    });
                    menu.addEventListener("transitionend", function handler() {
                        menu.style.display = "none";
                        menu.style.height = "";
                        menu.style.transition = "";
                        menu.removeEventListener("transitionend", handler);
                    });
                    toggle.setAttribute("aria-expanded", "false");
                } else {
                    // expand
                    menu.style.display = "";
                    menu.style.height = "0";
                    menu.style.overflow = "hidden";
                    menu.style.transition = "height .22s ease";
                    requestAnimationFrame(function () {
                        menu.style.height = menu.scrollHeight + "px";
                    });
                    menu.addEventListener("transitionend", function handler() {
                        menu.style.height = "";
                        menu.style.overflow = "";
                        menu.style.transition = "";
                        menu.removeEventListener("transitionend", handler);
                    });
                    toggle.setAttribute("aria-expanded", "true");
                }
            });
        });
    }
})();
