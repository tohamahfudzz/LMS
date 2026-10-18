/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-dashboard.js
GUNA FILE   : Hanya untuk dashboard pengajar (frontend/pengajar/dashboard.html).
              Menampilkan daftar kelas yang diajar:
              GET ../../backend/api/pengajar/kelas/list.php
              Setiap kelas punya tombol "Buka Kelas" ke kelas-detail.html?id_kelas=..
              (materi, tugas, kuis, dan absensi dikelola di dalam halaman kelas itu).
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const tbody = document.getElementById("kelasTableBody");
    if (!tbody) return;

    async function loadKelasDashboard() {
        tbody.innerHTML = "<tr><td colspan=\"4\">Memuat data...</td></tr>";

        try {
            const result = await apiRequest(PengajarCommon.API_KELAS_LIST, { method: "GET" });
            const rows = result.data || [];
            PengajarCommon.setCachedKelas(rows);

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"4\">Anda belum ditugaskan ke kelas manapun.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.nama_kelas) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.jumlah_siswa) + "</td>" +
                    "<td><a href=\"kelas-detail.html?id_kelas=" + encodeURIComponent(item.id_kelas) +
                    "\">Buka Kelas</a></td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"4\">Gagal memuat: " +
                PengajarCommon.escapeHtml(error.message) + "</td></tr>";
        }
    }

    loadKelasDashboard();
})();
