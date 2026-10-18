/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-common.js
GUNA FILE   : Helper bersama halaman siswa saja.
              - Proteksi role siswa, sambutan, logout
              - Util pesan, escape, loading
              Tidak berisi logika fitur (absensi/tugas/materi).
================================================================================
*/

const SiswaCommon = (function () {
    "use strict";

    function initPage() {
        const user = requireAuth("siswa");
        if (!user) return null;

        const welcomeEl = document.getElementById("welcomeText");
        if (welcomeEl) {
            welcomeEl.textContent = "Siswa: " + (user.nama || "-");
        }

        const logoutBtn = document.getElementById("logoutButton");
        if (logoutBtn) {
            logoutBtn.addEventListener("click", function () {
                logout();
            });
        }

        return user;
    }

    function showMessage(elementId, text, type) {
        const el = document.getElementById(elementId);
        if (!el) return;
        el.hidden = false;
        el.textContent = text;
        if (type === "success") {
            el.style.background = "#e8f8e8";
            el.style.borderColor = "#2e7d32";
            el.style.color = "#1b5e20";
        } else {
            el.style.background = "#fdecea";
            el.style.borderColor = "#c62828";
            el.style.color = "#b71c1c";
        }
    }

    function hideMessage(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        el.hidden = true;
        el.textContent = "";
    }

    function setButtonLoading(button, isLoading, labelIdle) {
        if (!button) return;
        button.disabled = isLoading;
        button.textContent = isLoading ? "Memproses..." : labelIdle;
    }

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text == null ? "" : String(text);
        return div.innerHTML;
    }

    return {
        initPage: initPage,
        showMessage: showMessage,
        hideMessage: hideMessage,
        setButtonLoading: setButtonLoading,
        escapeHtml: escapeHtml
    };
})();