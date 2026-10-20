/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-absensi.js
GUNA FILE   : Tab Absensi pada halaman kelas siswa (frontend/siswa/kelas-detail.html).
              Siswa tidak perlu mengetik ID absensi: semua sesi kelas ini tampil
              dalam tabel, dan tombol "Saya Hadir" muncul pada sesi yang sedang dibuka.
              - Daftar sesi + status kehadiran saya : GET  ../../backend/api/siswa/absensi/list.php?id_kelas=
              - Tandai hadir                        : POST ../../backend/api/kehadiran/hadir.php
                                                      Body: { id_absensi }
              Sesi yang waktu bukanya belum tiba ditandai "Terjadwal" dan belum bisa diisi.
              Bergantung pada: siswa-common.js dan siswa-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        list: "../../backend/api/siswa/absensi/list.php",
        hadir: "../../backend/api/kehadiran/hadir.php"
    };

    const tbody = document.getElementById("absensiTableBody");

    function getSessionStatus(item) {
        if (item.status !== "terbuka") return "Ditutup";
        return item.belum_dimulai ? "Terjadwal" : "Dibuka";
    }

    function getAttendanceText(item) {
        if (item.status_kehadiran) {
            return "Hadir" + (item.waktu_absen ? " (" + item.waktu_absen + ")" : "");
        }
        return item.status === "terbuka" ? "Belum absen" : "Tidak hadir";
    }

    function buildActionHtml(item) {
        const idAbsensi = parseInt(item.id_absensi, 10);

        if (item.status !== "terbuka" || item.status_kehadiran) return "-";

        if (item.belum_dimulai) {
            return "Dimulai " + SiswaCommon.escapeHtml(item.waktu_buka);
        }

        return "<button type=\"button\" class=\"btn-hadir\" data-id=\"" + idAbsensi + "\">Saya Hadir</button>";
    }

    async function loadAbsensiList() {
        tbody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";

        try {
            const url = API.list + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"7\">Belum ada sesi absensi di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.tanggal) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.waktu_buka) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.waktu_tutup || "-") + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(getSessionStatus(item)) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(getAttendanceText(item)) + "</td>" +
                    "<td>" + buildActionHtml(item) + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage("absensiMessage", error.message || "Gagal mengambil daftar absensi.", "error");
        }
    }

    async function markPresent(idAbsensi, button) {
        SiswaCommon.hideMessage("absensiMessage");
        button.disabled = true;
        button.textContent = "Memproses...";

        try {
            const result = await apiRequest(API.hadir, {
                method: "POST",
                body: { id_absensi: idAbsensi }
            });
            SiswaCommon.showMessage("absensiMessage", result.message || "Kehadiran berhasil dicatat.", "success");
        } catch (error) {
            SiswaCommon.showMessage("absensiMessage", error.message || "Gagal mencatat kehadiran.", "error");
        }

        // Muat ulang agar status sesi dan kehadiran selalu sesuai kondisi terbaru di server.
        loadAbsensiList();
    }

    tbody.addEventListener("click", function (event) {
        const button = event.target.closest(".btn-hadir");
        if (!button) return;

        const idAbsensi = parseInt(button.getAttribute("data-id"), 10);
        if (idAbsensi) markPresent(idAbsensi, button);
    });

    KelasPage.registerTab("absensi", loadAbsensiList);
})();