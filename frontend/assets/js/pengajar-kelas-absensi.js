/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-absensi.js
GUNA FILE   : Tab Absensi pada halaman kelas pengajar (frontend/pengajar/kelas-detail.html).
              Pengajar tidak perlu mengetik ID absensi: semua sesi kelas ini tampil
              dalam tabel dengan tombol aksi.
              - Daftar sesi kelas ini : GET  ../../backend/api/pengajar/absensi/list.php?id_kelas=
              - Buka sesi             : POST ../../backend/api/absensi/open.php
                                        Body: { id_kelas, tanggal, jam_buka, jam_tutup }
                                        (diisi manual oleh pengajar; siswa hanya bisa absen
                                        di antara waktu buka dan waktu tutup, dan sesi
                                        otomatis tertutup setelah waktu tutup)
              - Tutup sesi            : POST ../../backend/api/absensi/close.php
                                        Body: { id_absensi }
              - Lihat kehadiran sesi  : GET  ../../backend/api/pengajar/absensi/attendance.php?id_absensi=
              - Report absensi kelas ini (halaman sendiri): absensi-report.html?id_kelas=
                (tautannya diisi otomatis di sini)
              Bergantung pada: pengajar-common.js dan pengajar-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        list: "../../backend/api/pengajar/absensi/list.php",
        open: "../../backend/api/absensi/open.php",
        close: "../../backend/api/absensi/close.php",
        attendance: "../../backend/api/pengajar/absensi/attendance.php"
    };

    const openForm = document.getElementById("openAbsensiForm");
    const tanggalInput = document.getElementById("absensiTanggal");
    const jamBukaInput = document.getElementById("absensiJamBuka");
    const jamTutupInput = document.getElementById("absensiJamTutup");
    const sesiBody = document.getElementById("absensiTableBody");
    const detailSection = document.getElementById("attendanceSection");
    const detailBody = document.getElementById("attendanceTableBody");

    // ID sesi yang sedang ditampilkan di bagian "Kehadiran Siswa" (null jika belum ada).
    let shownIdAbsensi = null;

    // Tanggal hari ini menurut jam perangkat, format YYYY-MM-DD.
    function getTodayDate() {
        const now = new Date();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        const day = String(now.getDate()).padStart(2, "0");
        return now.getFullYear() + "-" + month + "-" + day;
    }

    // Mengubah menit sejak tengah malam menjadi HH:MM (24 jam).
    function formatTime(totalMinutes) {
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        return String(hours).padStart(2, "0") + ":" + String(minutes).padStart(2, "0");
    }

    // Isi awal formulir: buka = jam sekarang, tutup = 1 jam kemudian (maksimal 23:59).
    // Keduanya hanya saran dan bisa diubah pengajar.
    function setDefaultTimes() {
        const now = new Date();
        const nowMinutes = now.getHours() * 60 + now.getMinutes();
        const closeMinutes = Math.min(nowMinutes + 60, 23 * 60 + 59);

        jamBukaInput.value = formatTime(nowMinutes);
        jamTutupInput.value = formatTime(closeMinutes);
    }

    // Sesi yang waktu bukanya masih di masa depan ditandai "terjadwal".
    function getStatusLabel(item) {
        if (item.status === "terbuka" && item.belum_dimulai) {
            return "terjadwal (belum dimulai)";
        }
        return item.status;
    }

    async function loadSesiList() {
        sesiBody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";

        try {
            const url = API.list + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const payload = result.data || {};
            const rows = payload.sesi || [];
            const jumlahSiswa = payload.jumlah_siswa || 0;

            if (rows.length === 0) {
                sesiBody.innerHTML = "<tr><td colspan=\"7\">Belum ada sesi absensi. Buka sesi baru di atas.</td></tr>";
                return;
            }

            sesiBody.innerHTML = rows.map(function (item, index) {
                const idAbsensi = parseInt(item.id_absensi, 10);
                const isOpen = item.status === "terbuka";

                let aksi = "<button type=\"button\" class=\"btn-lihat-absensi\" data-id=\"" +
                    idAbsensi + "\">Lihat kehadiran</button>";
                if (isOpen) {
                    aksi += " <button type=\"button\" class=\"btn-tutup-absensi\" data-id=\"" +
                        idAbsensi + "\">Tutup</button>";
                }

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.tanggal) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_buka) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_tutup || "-") + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(getStatusLabel(item)) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.jumlah_hadir) + " / " +
                        PengajarCommon.escapeHtml(jumlahSiswa) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            sesiBody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage("absensiListMessage", error.message || "Gagal mengambil daftar sesi.", "error");
        }
    }

    function renderKehadiran(payload) {
        const absensi = payload.absensi || {};
        const statistik = payload.statistik || {};
        const siswa = payload.siswa || [];

        document.getElementById("attendanceMeta").textContent =
            "Tanggal: " + (absensi.tanggal || "-") +
            " | Status: " + (absensi.status || "-") +
            " | Dibuka: " + (absensi.waktu_buka || "-") +
            " | Ditutup: " + (absensi.waktu_tutup || "-");

        document.getElementById("attendanceStat").textContent =
            "Siswa: " + (statistik.jumlah_siswa || 0) +
            " | Hadir: " + (statistik.hadir || 0) +
            " | Izin: " + (statistik.izin || 0) +
            " | Sakit: " + (statistik.sakit || 0) +
            " | Alpa: " + (statistik.alpa || 0) +
            " | Belum absen: " + (statistik.belum_absen || 0);

        if (siswa.length === 0) {
            detailBody.innerHTML = "<tr><td colspan=\"5\">Belum ada siswa di kelas ini.</td></tr>";
            return;
        }

        detailBody.innerHTML = siswa.map(function (item, index) {
            return (
                "<tr>" +
                "<td>" + (index + 1) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(item.kode_siswa) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(item.nama) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(item.status_kehadiran || "Belum absen") + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(item.waktu_absen || "-") + "</td>" +
                "</tr>"
            );
        }).join("");
    }

    async function showKehadiran(idAbsensi) {
        shownIdAbsensi = idAbsensi;
        detailSection.hidden = false;
        PengajarCommon.hideMessage("attendanceMessage");
        detailBody.innerHTML = "<tr><td colspan=\"5\">Memuat data...</td></tr>";

        try {
            const url = API.attendance + "?id_absensi=" + encodeURIComponent(idAbsensi);
            const result = await apiRequest(url, { method: "GET" });
            renderKehadiran(result.data || {});
        } catch (error) {
            detailBody.innerHTML = "<tr><td colspan=\"5\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage("attendanceMessage", error.message || "Gagal mengambil data kehadiran.", "error");
        }
    }

    async function closeSesi(idAbsensi, button) {
        if (!window.confirm("Tutup sesi absensi ini? Siswa tidak bisa mengisi kehadiran lagi.")) {
            return;
        }

        PengajarCommon.hideMessage("absensiListMessage");
        button.disabled = true;
        button.textContent = "...";

        try {
            const result = await apiRequest(API.close, {
                method: "POST",
                body: { id_absensi: idAbsensi }
            });
            PengajarCommon.showMessage("absensiListMessage", result.message || "Sesi absensi berhasil ditutup.", "success");
            loadSesiList();
            if (shownIdAbsensi === idAbsensi) showKehadiran(idAbsensi);
        } catch (error) {
            PengajarCommon.showMessage("absensiListMessage", error.message || "Gagal menutup sesi absensi.", "error");
            button.disabled = false;
            button.textContent = "Tutup";
        }
    }

    async function submitOpenSesi(event) {
        event.preventDefault();
        PengajarCommon.hideMessage("openAbsensiMessage");

        const tanggal = tanggalInput.value;
        const jamBuka = jamBukaInput.value;
        const jamTutup = jamTutupInput.value;
        const button = document.getElementById("openAbsensiButton");

        if (!tanggal) {
            PengajarCommon.showMessage("openAbsensiMessage", "Tanggal wajib diisi.", "error");
            return;
        }
        if (!jamBuka || !jamTutup) {
            PengajarCommon.showMessage("openAbsensiMessage", "Waktu buka dan waktu tutup wajib diisi.", "error");
            return;
        }
        // Format HH:MM berurutan secara alfabet sama dengan urutan waktunya.
        if (jamTutup <= jamBuka) {
            PengajarCommon.showMessage("openAbsensiMessage", "Waktu tutup harus lebih akhir dari waktu buka.", "error");
            return;
        }

        PengajarCommon.setButtonLoading(button, true, "Buka Absensi");

        try {
            const result = await apiRequest(API.open, {
                method: "POST",
                body: { id_kelas: idKelas, tanggal: tanggal, jam_buka: jamBuka, jam_tutup: jamTutup }
            });
            PengajarCommon.showMessage("openAbsensiMessage", result.message || "Sesi absensi berhasil dibuka.", "success");
            await loadSesiList();

            // Sesi baru langsung ditampilkan supaya pengajar bisa memantau siswa yang hadir.
            const idBaru = result.data && result.data.id_absensi ? parseInt(result.data.id_absensi, 10) : null;
            if (idBaru) showKehadiran(idBaru);
        } catch (error) {
            PengajarCommon.showMessage("openAbsensiMessage", error.message || "Gagal membuka absensi.", "error");
        } finally {
            PengajarCommon.setButtonLoading(button, false, "Buka Absensi");
        }
    }

    sesiBody.addEventListener("click", function (event) {
        const lihatButton = event.target.closest(".btn-lihat-absensi");
        if (lihatButton) {
            const idAbsensi = parseInt(lihatButton.getAttribute("data-id"), 10);
            if (idAbsensi) showKehadiran(idAbsensi);
            return;
        }

        const tutupButton = event.target.closest(".btn-tutup-absensi");
        if (tutupButton) {
            const idAbsensi = parseInt(tutupButton.getAttribute("data-id"), 10);
            if (idAbsensi) closeSesi(idAbsensi, tutupButton);
        }
    });

    openForm.addEventListener("submit", submitOpenSesi);

    // Tautan ke halaman report membawa id kelas yang sedang dibuka.
    document.getElementById("absensiReportLink").href =
        "absensi-report.html?id_kelas=" + encodeURIComponent(idKelas);

    KelasPage.registerTab("absensi", function () {
        tanggalInput.value = getTodayDate();
        setDefaultTimes();
        loadSesiList();
    });
})();