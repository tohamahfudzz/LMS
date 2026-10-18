/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-common.js
GUNA FILE   : Helper bersama halaman pengajar.
              - Proteksi role, sambutan, logout
              - Util UI (pesan, escape, select, loading)
              - Cache daftar kelas
              Dipakai oleh: dashboard, materi, tugas, absensi.
================================================================================
*/

const PengajarCommon = (function () {
    "use strict";

    const API_KELAS_LIST = "../../backend/api/pengajar/kelas/list.php";

    let cachedKelas = [];

    function initPage() {
        const user = requireAuth("pengajar");
        if (!user) return null;

        const welcomeEl = document.getElementById("welcomeText");
        if (welcomeEl) {
            welcomeEl.textContent = "Pengajar: " + (user.nama || "-");
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

    function fillSelect(selectId, items, valueKey, labelFn, selectedValue) {
        const select = document.getElementById(selectId);
        if (!select) return;

        if (!items || items.length === 0) {
            select.innerHTML = '<option value="">-- Tidak ada data --</option>';
            return;
        }

        let html = '<option value="">-- Pilih --</option>';
        items.forEach(function (item) {
            const value = item[valueKey];
            const selected = String(value) === String(selectedValue) ? " selected" : "";
            html += '<option value="' + escapeHtml(value) + '"' + selected + ">" +
                escapeHtml(labelFn(item)) + "</option>";
        });
        select.innerHTML = html;
    }

    async function ensureKelasLoaded() {
        if (cachedKelas.length > 0) return cachedKelas;
        try {
            const result = await apiRequest(API_KELAS_LIST, { method: "GET" });
            cachedKelas = result.data || [];
        } catch (e) {
            cachedKelas = [];
        }
        return cachedKelas;
    }

    function getCachedKelas() {
        return cachedKelas;
    }

    function setCachedKelas(list) {
        cachedKelas = list || [];
    }

    /** "2026-10-15T14:30" → "2026-10-15 14:30:00" */
    function formatDateTimeLocal(value) {
        if (!value) return "";
        return value.replace("T", " ") + ":00";
    }

    return {
        initPage: initPage,
        showMessage: showMessage,
        hideMessage: hideMessage,
        setButtonLoading: setButtonLoading,
        escapeHtml: escapeHtml,
        fillSelect: fillSelect,
        ensureKelasLoaded: ensureKelasLoaded,
        getCachedKelas: getCachedKelas,
        setCachedKelas: setCachedKelas,
        formatDateTimeLocal: formatDateTimeLocal,
        API_KELAS_LIST: API_KELAS_LIST
    };
})();