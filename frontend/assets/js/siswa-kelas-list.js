/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-list.js
GUNA FILE   : Hanya menampilkan daftar kelas siswa.
              Endpoint: GET ../../backend/api/siswa/kelas/list.php
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API_KELAS = "../../backend/api/siswa/kelas/list.php";
    const tbody = document.getElementById("kelasTableBody");
    if (!tbody) return;

    async function loadKelasList() {
        tbody.innerHTML = "<tr><td colspan=\"3\">Memuat data...</td></tr>";
        SiswaCommon.hideMessage("kelasMessage");

        try {
            const result = await apiRequest(API_KELAS, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML =
                    "<tr><td colspan=\"3\">Anda belum terdaftar di kelas manapun.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                const id = encodeURIComponent(item.id_kelas);
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.nama_kelas) + "</td>" +
                    "<td>" +
                    "<a href=\"materi.html?id_kelas=" + id + "\">Materi</a> | " +
                    "<a href=\"tugas.html?id_kelas=" + id + "\">Tugas</a>" +
                    "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"3\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage(
                "kelasMessage",
                error.message || "Gagal mengambil daftar kelas.",
                "error"
            );
        }
    }

    loadKelasList();
})();