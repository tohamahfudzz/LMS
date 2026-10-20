/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-tugas-list.js
GUNA FILE   : Hanya menampilkan daftar tugas siswa (+ filter kelas).
              Endpoint: GET siswa/tugas/list.php , GET siswa/kelas/list.php
              Tidak mengurus upload.
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API = {
        tugasList: "../../backend/api/siswa/tugas/list.php",
        kelasList: "../../backend/api/siswa/kelas/list.php"
    };

    const tbody = document.getElementById("tugasTableBody");
    const filterSelect = document.getElementById("filterKelasTugas");
    if (!tbody) return;

    function fillUploadId(idTugas) {
        const input = document.getElementById("uploadIdTugas");
        if (input) {
            input.value = idTugas;
            input.focus();
        }
    }

    async function loadTugasList(idKelas) {
        tbody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";
        SiswaCommon.hideMessage("tugasListMessage");

        let url = API.tugasList;
        if (idKelas) {
            url += "?id_kelas=" + encodeURIComponent(idKelas);
        }

        try {
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"7\">Belum ada tugas.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
    const statusKumpul = item.sudah_mengumpulkan ? "Sudah dikumpulkan" : "Belum";
    const bisaKumpul = item.status_tugas === "terbuka";

    let aksiParts = [];

    if (bisaKumpul) {
        aksiParts.push(
            '<button type="button" class="btn-pilih-tugas" data-id="' +
            SiswaCommon.escapeHtml(item.id_tugas) +
            '">Pilih untuk kumpul</button>'
        );
    }

    // Tombol hapus file hanya jika sudah kumpul + ada file + tugas masih terbuka
    const punyaFile =
        item.sudah_mengumpulkan &&
        item.pengumpulan &&
        item.pengumpulan.file;

    if (punyaFile && bisaKumpul) {
        aksiParts.push(
            '<button type="button" class="btn-hapus-file-tugas" data-id="' +
            SiswaCommon.escapeHtml(item.id_tugas) +
            '">Hapus file</button>'
        );
    }

    const aksi = aksiParts.length ? aksiParts.join(" ") : "-";

    return (
        "<tr>" +
        "<td>" + (index + 1) + "</td>" +
        "<td>" + SiswaCommon.escapeHtml(item.judul) + "</td>" +
        "<td>" + SiswaCommon.escapeHtml(item.nama_kelas) + "</td>" +
        "<td>" + SiswaCommon.escapeHtml(item.deadline) + "</td>" +
        "<td>" + SiswaCommon.escapeHtml(item.status_tugas) + "</td>" +
        "<td>" + SiswaCommon.escapeHtml(statusKumpul) + "</td>" +
        "<td>" + aksi + "</td>" +
        "</tr>"
    );
}).join("");

// Event pilih-tugas tetap seperti sebelumnya
tbody.querySelectorAll(".btn-pilih-tugas").forEach(function (btn) {
    btn.addEventListener("click", function () {
        const id = parseInt(btn.getAttribute("data-id"), 10);
        if (id) fillUploadId(id);
    });
});
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage(
                "tugasListMessage",
                error.message || "Gagal mengambil daftar tugas.",
                "error"
            );
        }
    }

    async function initFilter() {
        if (!filterSelect) {
            loadTugasList(null);
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const idKelasUrl = params.get("id_kelas");

        try {
            const result = await apiRequest(API.kelasList, { method: "GET" });
            const kelasList = result.data || [];

            let html = '<option value="">Semua kelas</option>';
            kelasList.forEach(function (item) {
                const selected = String(item.id_kelas) === String(idKelasUrl) ? " selected" : "";
                html +=
                    '<option value="' + SiswaCommon.escapeHtml(item.id_kelas) + '"' + selected + ">" +
                    SiswaCommon.escapeHtml(item.nama_kelas) +
                    "</option>";
            });
            filterSelect.innerHTML = html;

            filterSelect.addEventListener("change", function () {
                loadTugasList(filterSelect.value || null);
            });

            loadTugasList(idKelasUrl || filterSelect.value || null);
        } catch (error) {
            filterSelect.innerHTML = '<option value="">Gagal memuat kelas</option>';
            loadTugasList(null);
        }
    }

    // Agar upload.js bisa minta refresh list setelah sukses
    window.siswaRefreshTugasList = function () {
        const idKelas = filterSelect ? filterSelect.value || null : null;
        loadTugasList(idKelas);
    };

    initFilter();
})();