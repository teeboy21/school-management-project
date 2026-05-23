(function () {
    let container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement("div");
            container.className = "toast-container";
            document.body.appendChild(container);
        }
        return container;
    }

    window.showToast = function showToast(message, type = "info", title = "") {
        const root = getContainer();
        const toast = document.createElement("div");
        toast.className = `toast ${type}`;

        const safeTitle = title || (type === "success" ? "Success" : type === "error" ? "Error" : "Notice");
        toast.innerHTML = `
            <div class="toast-title">${safeTitle}</div>
            <div class="toast-message">${message}</div>
        `;

        root.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add("show"));

        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 220);
        }, 3200);
    };

    window.Toast = {
        show: function(message, type = "info") {
            showToast(message, type);
        },
        success: function(message) {
            showToast(message, "success");
        },
        error: function(message) {
            showToast(message, "error");
        },
        info: function(message) {
            showToast(message, "info");
        }
    };
})();
