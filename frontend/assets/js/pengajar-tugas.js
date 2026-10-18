/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-tugas.js
GUNA FILE   : Hanya fitur tugas pengajar — list, buat, tutup pengumpulan.
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API = {
        tugasList:   "../../backend/api/pengajar/tugas/list.php",
        tugasCreate: "../../backend/api/tugas/create.php",
        tugasClose:  "../../backend/api/tugas/close.php"
    };

    const form = document.getElementById("createTugasForm");
    const filterSelect = document.getElementById("filterKelasTugas");
    const tbody = document.getElementById("tugasTableBody");

    if (!form && !filterSelect && !tbody) return;

    async function loadTugasList(idKelas) {
        if (!tbody) return;
        tbody.innerHTML = "<tr><td colspan=\"8\">Memuat data...</td></tr>";

        let url = API.tugasList;
        if (idKelas) url += "?id_kelas=" + encodeURIComponent(idKelas);

        try {
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"8\">Belum ada tugas.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                const status = item.status || "-";
                const canClose = status === "terbuka";
                const aksi = canClose
                    ? '<button type="button" class="btn-close-tugas" data-id="' +
                      PengajarCommon.escapeHtml(item.id_tugas) + '">Tutup</button>'
                    : "-";

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.nama_kelas) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_mulai) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.deadline) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(status) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.jumlah_pengumpulan) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");

            tbody.querySelectorAll(".btn-close-tugas").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    const idTugas = parseInt(btn.getAttribute("data-id"), 10);
                    if (idTugas) closeTugas(idTugas, btn);
                });
            });
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"8\">Gagal memuat: " +
                PengajarCommon.escapeHtml(error.message) + "</td></tr>";
        }
    }

    async function closeTugas(idTugas, buttonEl) {
        if (!window.confirm("Tutup pengumpulan tugas ini? Siswa tidak bisa mengumpulkan lagi.")) {
            return;
        }

        PengajarCommon.hideMessage("tugasListMessage");
        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.textContent = "...";
        }

        try {
            const result = await apiRequest(API.tugasClose, {
                method: "POST",
                body: { id_tugas: idTugas }
            });
            PengajarCommon.showMessage("tugasListMessage", result.message || "Tugas berhasil ditutup.", "success");
            loadTugasList(filterSelect ? filterSelect.value || null : null);
        } catch (error) {
            PengajarCommon.showMessage("tugasListMessage", error.message || "Gagal menutup tugas.", "error");
            if (buttonEl) {
                buttonEl.disabled = false;
                buttonEl.textContent = "Tutup";
            }
        }
    }

    async function init() {
        const kelasList = await PengajarCommon.ensureKelasLoaded();
        const params = new URLSearchParams(window.location.search);
        const idKelasUrl = params.get("id_kelas");

        PengajarCommon.fillSelect(
            "tugasKelas",
            kelasList,
            "id_kelas",
            function (i) { return i.nama_kelas; },
            idKelasUrl
        );

        if (filterSelect) {
            let html = '<option value="">Semua kelas</option>';
            kelasList.forEach(function (item) {
                const selected = String(item.id_kelas) === String(idKelasUrl) ? " selected" : "";
                html += '<option value="' + PengajarCommon.escapeHtml(item.id_kelas) + '"' + selected + ">" +
                    PengajarCommon.escapeHtml(item.nama_kelas) + "</option>";
            });
            filterSelect.innerHTML = html;
            filterSelect.addEventListener("change", function () {
                loadTugasList(filterSelect.value || null);
            });
        }

        loadTugasList(idKelasUrl || null);
    }

    if (form) {
        form.addEventListener("submit", async function (event) {
            event.preventDefault();
            PengajarCommon.hideMessage("tugasMessage");

            const idKelas = document.getElementById("tugasKelas").value;
            const judul = document.getElementById("tugasJudul").value.trim();
            const deskripsi = document.getElementById("tugasDeskripsi").value.trim();
            const waktuMulai = document.getElementById("tugasWaktuMulai").value;
            const deadline = document.getElementById("tugasDeadline").value;
            const fileInput = document.getElementById("tugasFile");
            const btn = document.getElementById("createTugasButton");

            if (!idKelas) {
                PengajarCommon.showMessage("tugasMessage", "Silakan pilih kelas.", "error");
                return;
            }
            if (!judul) {
                PengajarCommon.showMessage("tugasMessage", "Judul wajib diisi.", "error");
                return;
            }
            if (!waktuMulai || !deadline) {
                PengajarCommon.showMessage("tugasMessage", "Waktu mulai dan deadline wajib diisi.", "error");
                return;
            }
            if (new Date(waktuMulai) >= new Date(deadline)) {
                PengajarCommon.showMessage("tugasMessage", "Waktu mulai harus lebih awal dari deadline.", "error");
                return;
            }

            const formData = new FormData();
            formData.append("id_kelas", idKelas);
            formData.append("judul", judul);
            if (deskripsi) formData.append("deskripsi", deskripsi);
            formData.append("waktu_mulai", PengajarCommon.formatDateTimeLocal(waktuMulai));
            formData.append("deadline", PengajarCommon.formatDateTimeLocal(deadline));
            if (fileInput.files && fileInput.files[0]) {
                formData.append("file", fileInput.files[0]);
            }

            PengajarCommon.setButtonLoading(btn, true, "Simpan Tugas");
            try {
                const response = await fetch(API.tugasCreate, {
                    method: "POST",
                    credentials: "include",
                    body: formData
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error((data && data.message) || "Gagal membuat tugas.");
                }
                PengajarCommon.showMessage("tugasMessage", data.message || "Tugas berhasil dibuat.", "success");
                form.reset();
                PengajarCommon.fillSelect(
                    "tugasKelas",
                    PengajarCommon.getCachedKelas(),
                    "id_kelas",
                    function (i) { return i.nama_kelas; },
                    idKelas
                );
                loadTugasList(filterSelect ? filterSelect.value || null : null);
            } catch (error) {
                PengajarCommon.showMessage("tugasMessage", error.message || "Gagal membuat tugas.", "error");
            } finally {
                PengajarCommon.setButtonLoading(btn, false, "Simpan Tugas");
            }
        });
    }

    init();
})();