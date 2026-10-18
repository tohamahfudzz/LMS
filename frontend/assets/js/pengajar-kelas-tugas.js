/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-tugas.js
GUNA FILE   : Tab Tugas pada halaman kelas pengajar (frontend/pengajar/kelas-detail.html).
              - Daftar tugas kelas ini   : GET  ../../backend/api/pengajar/tugas/list.php?id_kelas=
              - Buat tugas               : POST ../../backend/api/tugas/create.php (multipart)
              - Tutup pengumpulan tugas  : POST ../../backend/api/tugas/close.php  Body: { id_tugas }
              id_kelas diambil dari URL halaman (KelasPage), bukan dipilih manual.
              Bergantung pada: pengajar-common.js dan pengajar-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        tugasList: "../../backend/api/pengajar/tugas/list.php",
        tugasCreate: "../../backend/api/tugas/create.php",
        tugasClose: "../../backend/api/tugas/close.php"
    };

    const form = document.getElementById("createTugasForm");
    const tbody = document.getElementById("tugasTableBody");

    async function loadTugasList() {
        tbody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";

        try {
            const url = API.tugasList + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"7\">Belum ada tugas di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                const status = item.status || "-";
                const idTugas = parseInt(item.id_tugas, 10);
                const aksi = status === "terbuka"
                    ? "<button type=\"button\" class=\"btn-close-tugas\" data-id=\"" + idTugas + "\">Tutup</button>"
                    : "-";

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_mulai) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.deadline) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(status) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.jumlah_pengumpulan) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat: " +
                PengajarCommon.escapeHtml(error.message) + "</td></tr>";
        }
    }

    async function closeTugas(idTugas, button) {
        if (!window.confirm("Tutup pengumpulan tugas ini? Siswa tidak bisa mengumpulkan lagi.")) {
            return;
        }

        PengajarCommon.hideMessage("tugasListMessage");
        button.disabled = true;
        button.textContent = "...";

        try {
            const result = await apiRequest(API.tugasClose, {
                method: "POST",
                body: { id_tugas: idTugas }
            });
            PengajarCommon.showMessage("tugasListMessage", result.message || "Tugas berhasil ditutup.", "success");
            loadTugasList();
        } catch (error) {
            PengajarCommon.showMessage("tugasListMessage", error.message || "Gagal menutup tugas.", "error");
            button.disabled = false;
            button.textContent = "Tutup";
        }
    }

    async function submitTugas(event) {
        event.preventDefault();
        PengajarCommon.hideMessage("tugasMessage");

        const judul = document.getElementById("tugasJudul").value.trim();
        const deskripsi = document.getElementById("tugasDeskripsi").value.trim();
        const waktuMulai = document.getElementById("tugasWaktuMulai").value;
        const deadline = document.getElementById("tugasDeadline").value;
        const fileInput = document.getElementById("tugasFile");
        const button = document.getElementById("createTugasButton");

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

        PengajarCommon.setButtonLoading(button, true, "Simpan Tugas");

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
            loadTugasList();
        } catch (error) {
            PengajarCommon.showMessage("tugasMessage", error.message || "Gagal membuat tugas.", "error");
        } finally {
            PengajarCommon.setButtonLoading(button, false, "Simpan Tugas");
        }
    }

    tbody.addEventListener("click", function (event) {
        const button = event.target.closest(".btn-close-tugas");
        if (!button) return;

        const idTugas = parseInt(button.getAttribute("data-id"), 10);
        if (idTugas) closeTugas(idTugas, button);
    });

    form.addEventListener("submit", submitTugas);
    KelasPage.registerTab("tugas", loadTugasList);
})();
