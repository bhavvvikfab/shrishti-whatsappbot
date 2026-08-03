function buttonLoader(btn, text = "", show = true) {
    const button = $(btn);
    if (show) {
        if (!button.data("original-text")) {
            button.data("original-text", button.html());
        }
        button.prop("disabled", true).html(`
            <span class="spinner-border spinner-border-sm me-2"></span>
            ${text}...
        `);
    } else {
        button.prop("disabled", false).html(button.data("original-text"));
    }
}

function showAlert(type, message, title = "", redirectUrl = null) {
    Swal.fire({
        icon: type,
        title: title || (type === "success" ? "Success!" : "Error!"),
        text: message,
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true,
        customClass: { popup: "rounded-4 shadow" },
    }).then(() => {
        if (redirectUrl) window.location.href = redirectUrl;
    });
}

function showDeleteConfirm(message = "You won't be able to revert this!", options = {}) {
    return Swal.fire({
        title: options.title || "Are you sure?",
        text: message,
        icon: options.icon || "warning",
        showCancelButton: true,
        confirmButtonText: options.confirmButtonText || "Yes, delete it!",
        cancelButtonText: options.cancelButtonText || "Cancel",
        confirmButtonColor: options.confirmButtonColor || "#dc3545",
        cancelButtonColor: options.cancelButtonColor || "#0d6efd",
        customClass: {
            popup: "rounded-4 shadow",
        },
    });
}

window.showAlert = showAlert;
window.buttonLoader = buttonLoader;
window.showDeleteConfirm = showDeleteConfirm;

(function () {
    function extractConfirmMessage(value) {
        if (!value) {
            return "";
        }

        const match = value.match(/confirm\((['"])(.*?)\1\)/);
        return match ? match[2] : "";
    }

    document.addEventListener("click", function (event) {
        const target = event.target.closest("[data-confirm-message], [onclick*='confirm(']");
        if (!target) {
            return;
        }

        const isLink = target.tagName === "A" && target.href;
        const isButton = target.tagName === "BUTTON" || target.tagName === "INPUT";
        if (!isLink && !isButton) {
            return;
        }

        const message =
            target.getAttribute("data-confirm-message") ||
            extractConfirmMessage(target.getAttribute("onclick"));

        if (!message) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        showDeleteConfirm(message).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            if (isLink) {
                window.location.href = target.href;
                return;
            }

            const form = target.form;
            if (form) {
                form.removeAttribute("onsubmit");
                target.removeAttribute("onclick");
                form.submit();
            }
        });
    }, true);

    document.addEventListener("submit", function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const message =
            form.getAttribute("data-confirm-message") ||
            extractConfirmMessage(form.getAttribute("onsubmit"));

        if (!message || form.dataset.confirmed === "true") {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        showDeleteConfirm(message).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            form.dataset.confirmed = "true";
            form.removeAttribute("onsubmit");
            form.submit();
        });
    }, true);
})();
