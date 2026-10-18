/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kuis-list.js
GUNA FILE   : Hanya menampilkan daftar kuis siswa.
              Endpoint: GET ../../backend/api/kuis/tersedia.php
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API_TERSEDIA = "../../backend/api/kuis/tersedia.php";
    const tbody = document.getElementById("kuisTableBody");
    if (!tbody) return;

    function pick(obj, keys, fallback) {
        for (let i = 0; i < keys.length; i++) {
            if (obj[keys[i]] !== undefined && obj[keys[i]] !== null) {
                return obj[keys[i]];
            }
        }
        return fallback;
    }

    async function loadKuisList() {
        tbody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";
        SiswaCommon.hideMessage("kuisListMessage");

        try {
            const result = await apiRequest(API_TERSEDIA, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"7\">Belum ada kuis tersedia.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                const idKuis = pick(item, ["id_kuis"], "");
                const judul = pick(item, ["judul"], "-");
                const namaKelas = pick(item, ["nama_kelas"], "-");
                const durasi = pick(item, ["durasi_menit"], "-");
                const status = pick(item, ["status_pengajuan", "status_pengerjaan", "status", "ketersediaan"], "-");
                const nilai = pick(item, ["nilai_akhir", "nilai"], "-");

                // Tombol kerjakan jika belum selesai (sesuaikan field response bila beda)
                const sudahSelesai =
                    String(status).toLowerCase().indexOf("selesai") !== -1 ||
                    String(status).toLowerCase().indexOf("final") !== -1 ||
                    item.sudah_mengerjakan === true;

                const aksi = sudahSelesai
                    ? "-"
                    : '<a href="kuis-kerjakan.html?id_kuis=' + encodeURIComponent(idKuis) + '">Kerjakan</a>';

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(judul) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(namaKelas) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(durasi) + " menit</td>" +
                    "<td>" + SiswaCommon.escapeHtml(status) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(nilai) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage(
                "kuisListMessage",
                error.message || "Gagal mengambil daftar kuis.",
                "error"
            );
        }
    }

    loadKuisList();
})();