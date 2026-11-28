/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-absensi.js
GUNA FILE   : Tab Absensi pada halaman kelas siswa (frontend/siswa/kelas-detail.html).
              Siswa tidak perlu mengetik ID absensi: semua sesi kelas ini tampil
              dalam tabel. Pada sesi mode 'siswa' yang sedang dibuka, siswa memilih
              "Saya Hadir" (satu klik) atau "Izin" (wajib menulis alasan, lalu Kirim Izin).
              Pada sesi mode 'manual', kehadiran diisi pengajar, jadi tidak ada tombol.
              - Daftar sesi + status kehadiran saya : GET  ../../backend/api/siswa/absensi/list.php?id_kelas=
              - Kirim kehadiran                     : POST ../../backend/api/kehadiran/hadir.php
                                                      Body: { id_absensi, status, catatan }
                                                      status 'hadir' atau 'izin'; catatan
                                                      (alasan) wajib untuk 'izin'.
              Sesi yang waktu bukanya belum tiba ditandai "Terjadwal" dan belum bisa diisi.
              Pilihan siswa tidak bisa diubah sendiri setelah dikirim; koreksi dilakukan
              pengajar.
              Bergantung pada: siswa-common.js dan siswa-kelas-detail.js.
              Perubahan: tombol Izin dengan alasan, label status sesuai data (bukan selalu
                         "Hadir"), dan penanganan sesi mode manual.
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

    const STATUS_LABEL = {
        hadir: "Hadir",
        izin: "Izin",
        alpa: "Alpa"
    };

    const tbody = document.getElementById("absensiTableBody");

    function getSessionStatus(item) {
        if (item.status !== "terbuka") return "Ditutup";
        return item.belum_dimulai ? "Terjadwal" : "Dibuka";
    }

    function getAttendanceText(item) {
        if (!item.status_kehadiran) return "Belum diisi";

        const label = STATUS_LABEL[item.status_kehadiran] || item.status_kehadiran;

        // Pada sesi manual, waktu_absen hanya waktu pengajar mencatat, jadi tidak ditampilkan.
        if (item.status_kehadiran === "hadir" && item.mode !== "manual" && item.waktu_absen) {
            return label + " (" + item.waktu_absen + ")";
        }
        if (item.status_kehadiran === "izin" && item.catatan_siswa) {
            return label + " (" + item.catatan_siswa + ")";
        }
        return label;
    }

    function buildActionHtml(item) {
        const idAbsensi = parseInt(item.id_absensi, 10);

        // Sesi manual diisi sepenuhnya oleh pengajar.
        if (item.mode === "manual") {
            return item.status_kehadiran ? "-" : "Diisi oleh pengajar";
        }

        if (item.status !== "terbuka" || item.status_kehadiran) return "-";

        if (item.belum_dimulai) {
            return "Dimulai " + SiswaCommon.escapeHtml(item.waktu_buka);
        }

        return (
            "<div class=\"absen-aksi\" data-id=\"" + idAbsensi + "\">" +
            "<span class=\"absen-pilihan\">" +
            "<button type=\"button\" class=\"btn-hadir\" data-id=\"" + idAbsensi + "\">Saya Hadir</button> " +
            "<button type=\"button\" class=\"btn-izin-buka\">Izin</button>" +
            "</span>" +
            "<span class=\"absen-izin-form\" hidden>" +
            "<input type=\"text\" class=\"input-alasan\" maxlength=\"255\" placeholder=\"Alasan izin\"> " +
            "<button type=\"button\" class=\"btn-izin-kirim\" data-id=\"" + idAbsensi + "\">Kirim Izin</button> " +
            "<button type=\"button\" class=\"btn-izin-batal\">Batal</button>" +
            "</span>" +
            "</div>"
        );
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

    // Mengirim kehadiran ke server. status: 'hadir' atau 'izin'; catatan wajib untuk 'izin'.
    async function sendAttendance(idAbsensi, status, catatan, container) {
        SiswaCommon.hideMessage("absensiMessage");

        // Cegah klik ganda selama permintaan berjalan.
        container.querySelectorAll("button").forEach(function (button) {
            button.disabled = true;
        });

        const body = { id_absensi: idAbsensi, status: status };
        if (status === "izin") body.catatan = catatan;

        try {
            const result = await apiRequest(API.hadir, { method: "POST", body: body });
            SiswaCommon.showMessage("absensiMessage", result.message || "Kehadiran berhasil dicatat.", "success");
        } catch (error) {
            SiswaCommon.showMessage("absensiMessage", error.message || "Gagal mencatat kehadiran.", "error");
        }

        // Muat ulang agar status sesi dan kehadiran selalu sesuai kondisi terbaru di server.
        loadAbsensiList();
    }

    // Menampilkan atau menyembunyikan isian alasan izin pada baris yang sama.
    function toggleIzinForm(container, showForm) {
        container.querySelector(".absen-pilihan").hidden = showForm;
        container.querySelector(".absen-izin-form").hidden = !showForm;

        if (showForm) {
            container.querySelector(".input-alasan").focus();
        }
    }

    tbody.addEventListener("click", function (event) {
        const container = event.target.closest(".absen-aksi");
        if (!container) return;

        const idAbsensi = parseInt(container.getAttribute("data-id"), 10);
        if (!idAbsensi) return;

        if (event.target.closest(".btn-hadir")) {
            sendAttendance(idAbsensi, "hadir", "", container);
            return;
        }

        if (event.target.closest(".btn-izin-buka")) {
            toggleIzinForm(container, true);
            return;
        }

        if (event.target.closest(".btn-izin-batal")) {
            toggleIzinForm(container, false);
            return;
        }

        if (event.target.closest(".btn-izin-kirim")) {
            const alasan = container.querySelector(".input-alasan").value.trim();

            if (alasan === "") {
                SiswaCommon.showMessage("absensiMessage", "Alasan izin wajib diisi.", "error");
                return;
            }

            sendAttendance(idAbsensi, "izin", alasan, container);
        }
    });

    KelasPage.registerTab("absensi", loadAbsensiList);
})();