/*
================================================================================
LOKASI FILE : frontend/assets/js/admin-kelas-tahun.js
GUNA FILE   : Pengaturan tahun ajaran dan semester di halaman frontend/admin/kelas.html.
              Hanya mengurus dua hal:
              1. Mengisi pilihan tahun ajaran di form "Buat Kelas Baru" (bawaan:
                 tahun ajaran aktif terbaru) dan di panel "Atur Tahun Ajaran Kelas".
              2. Panel untuk mengubah tahun ajaran + semester kelas yang sudah ada
                 (dibuka dari tombol "Atur Tahun Ajaran" di tabel kelas).
                GET  ../../backend/api/tahun_ajaran/list.php
                POST ../../backend/api/admin/kelas/set_tahun_ajaran.php
              Pembuatan kelas dan tabel kelas tetap diurus admin.js, yang juga
              menyediakan window.adminRefreshKelasList() untuk memuat ulang tabel.
              Bergantung pada: api.js (apiRequest) dan auth.js (requireAuth).
================================================================================
*/

(function () {
    "use strict";

    if (!requireAuth("admin")) return;

    const API = {
        tahunList: "../../backend/api/tahun_ajaran/list.php",
        setTahun: "../../backend/api/admin/kelas/set_tahun_ajaran.php"
    };

    const MESSAGE_ID = "aturTahunMessage";

    const createTahunSelect = document.getElementById("kelasTahunAjaran");
    const panel = document.getElementById("aturTahunPanel");
    const panelForm = document.getElementById("aturTahunForm");
    const panelTahunSelect = document.getElementById("aturTahunAjaran");
    const panelSemesterSelect = document.getElementById("aturSemester");
    const saveButton = document.getElementById("aturTahunSimpan");
    const cancelButton = document.getElementById("aturTahunBatal");
    const createForm = document.getElementById("createKelasForm");

    if (!createTahunSelect || !panel || !panelForm || !panelTahunSelect) return;

    let defaultTahunId = "";

    function escapeHtml(text) {
        return String(text == null ? "" : text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function showMessage(text, type) {
        const el = document.getElementById(MESSAGE_ID);
        if (!el) return;

        el.hidden = false;
        el.textContent = text;
        el.dataset.type = type || "error";
        el.style.color = type === "success" ? "#1b5e20" : "#b71c1c";
    }

    function hideMessage() {
        const el = document.getElementById(MESSAGE_ID);
        if (!el) return;

        el.hidden = true;
        el.textContent = "";
    }

    function buildOptions(items) {
        let html = '<option value="">-- Belum diatur --</option>';

        items.forEach(function (item) {
            html +=
                '<option value="' + Number(item.id_tahun_ajaran) + '">' +
                escapeHtml(item.nama_tahun_ajaran) +
                (item.status === "aktif" ? "" : " (nonaktif)") +
                "</option>";
        });

        return html;
    }

    function applyCreateDefault() {
        createTahunSelect.value = defaultTahunId;
    }

    async function loadTahunOptions() {
        try {
            const result = await apiRequest(API.tahunList, { method: "GET" });
            const items = result.data || [];

            createTahunSelect.innerHTML = buildOptions(items);
            panelTahunSelect.innerHTML = buildOptions(items);

            // Daftar sudah terurut dari yang terbaru; ambil yang aktif pertama.
            const newestActive = items.find(function (item) {
                return item.status === "aktif";
            });
            defaultTahunId = newestActive ? String(newestActive.id_tahun_ajaran) : "";
            applyCreateDefault();
        } catch (error) {
            createTahunSelect.innerHTML = '<option value="">Gagal memuat tahun ajaran</option>';
            panelTahunSelect.innerHTML = '<option value="">Gagal memuat tahun ajaran</option>';
        }
    }

    function openPanel(button) {
        hideMessage();

        document.getElementById("aturIdKelas").value = button.getAttribute("data-id") || "";
        // Nama kelas diambil dari sel tabel agar tidak perlu disimpan di atribut HTML.
        document.getElementById("aturKelasNama").textContent =
            button.closest("tr").children[1].textContent;
        panelTahunSelect.value = button.getAttribute("data-tahun") || "";
        panelSemesterSelect.value = button.getAttribute("data-semester") || "";

        panel.hidden = false;
        panel.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function closePanel() {
        panel.hidden = true;
        hideMessage();
    }

    async function savePanel(event) {
        event.preventDefault();
        hideMessage();

        const idKelas = parseInt(document.getElementById("aturIdKelas").value, 10);
        if (!idKelas) return;

        saveButton.disabled = true;
        saveButton.textContent = "Memproses...";

        try {
            await apiRequest(API.setTahun, {
                method: "POST",
                body: {
                    id_kelas: idKelas,
                    id_tahun_ajaran: panelTahunSelect.value ? parseInt(panelTahunSelect.value, 10) : null,
                    semester: panelSemesterSelect.value || null
                }
            });

            closePanel();

            if (typeof window.adminRefreshKelasList === "function") {
                window.adminRefreshKelasList();
            }
        } catch (error) {
            showMessage(error.message || "Gagal menyimpan.", "error");
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = "Simpan";
        }
    }

    document.addEventListener("click", function (event) {
        const button = event.target.closest(".btn-atur-tahun");
        if (button) openPanel(button);
    });

    panelForm.addEventListener("submit", savePanel);
    cancelButton.addEventListener("click", closePanel);

    // form.reset() mengembalikan pilihan ke baris pertama; kembalikan ke bawaan.
    if (createForm) {
        createForm.addEventListener("reset", function () {
            setTimeout(applyCreateDefault, 0);
        });
    }

    loadTahunOptions();
})();