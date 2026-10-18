/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-absensi.js
GUNA FILE   : Fitur absensi pengajar.
              - Buka sesi → tampilkan & isi otomatis id_absensi
              - Tutup sesi
              - Lihat kehadiran
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API = {
        open:       "../../backend/api/absensi/open.php",
        close:      "../../backend/api/absensi/close.php",
        attendance: "../../backend/api/pengajar/absensi/attendance.php"
    };

    // ---------- Init form ----------
    async function initForm() {
        const kelasList = await PengajarCommon.ensureKelasLoaded();
        const params = new URLSearchParams(window.location.search);
        const idKelasUrl = params.get("id_kelas");

        PengajarCommon.fillSelect(
            "absensiKelas",
            kelasList,
            "id_kelas",
            function (item) { return item.nama_kelas; },
            idKelasUrl
        );

        const tanggalInput = document.getElementById("absensiTanggal");
        if (tanggalInput && !tanggalInput.value) {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, "0");
            const dd = String(today.getDate()).padStart(2, "0");
            tanggalInput.value = yyyy + "-" + mm + "-" + dd;
        }
    }

    /** Isi field tutup + lihat kehadiran dengan id_absensi */
    function fillAbsensiIdFields(idAbsensi) {
        if (!idAbsensi) return;
        const closeInput = document.getElementById("closeIdAbsensi");
        const viewInput = document.getElementById("viewIdAbsensi");
        if (closeInput) closeInput.value = idAbsensi;
        if (viewInput) viewInput.value = idAbsensi;
    }

    // ---------- Buka absensi ----------
    const openForm = document.getElementById("openAbsensiForm");
    if (openForm) {
        openForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            PengajarCommon.hideMessage("openAbsensiMessage");

            const idKelas = document.getElementById("absensiKelas").value;
            const tanggal = document.getElementById("absensiTanggal").value;
            const btn = document.getElementById("openAbsensiButton");

            if (!idKelas) {
                PengajarCommon.showMessage("openAbsensiMessage", "Silakan pilih kelas.", "error");
                return;
            }
            if (!tanggal) {
                PengajarCommon.showMessage("openAbsensiMessage", "Tanggal wajib diisi.", "error");
                return;
            }

            PengajarCommon.setButtonLoading(btn, true, "Buka Absensi");

            try {
                const result = await apiRequest(API.open, {
                    method: "POST",
                    body: {
                        id_kelas: parseInt(idKelas, 10),
                        tanggal: tanggal
                    }
                });

                const idBaru = result.data && result.data.id_absensi
                    ? result.data.id_absensi
                    : null;

                let msg = result.message || "Sesi absensi berhasil dibuka.";
                if (idBaru) {
                    msg += " ID absensi: " + idBaru + ".";
                    fillAbsensiIdFields(idBaru);
                }

                PengajarCommon.showMessage("openAbsensiMessage", msg, "success");
            } catch (error) {
                // Jika 409 (sesi sudah terbuka), backend bisa kirim id_absensi yang ada
                const existingId = error.data && error.data.data && error.data.data.id_absensi
                    ? error.data.data.id_absensi
                    : (error.data && error.data.id_absensi ? error.data.id_absensi : null);

                if (existingId) {
                    fillAbsensiIdFields(existingId);
                    PengajarCommon.showMessage(
                        "openAbsensiMessage",
                        (error.message || "Sesi sudah terbuka.") + " ID absensi: " + existingId + ".",
                        "error"
                    );
                } else {
                    PengajarCommon.showMessage(
                        "openAbsensiMessage",
                        error.message || "Gagal membuka absensi.",
                        "error"
                    );
                }
            } finally {
                PengajarCommon.setButtonLoading(btn, false, "Buka Absensi");
            }
        });
    }

    // ---------- Tutup absensi ----------
    const closeForm = document.getElementById("closeAbsensiForm");
    if (closeForm) {
        closeForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            PengajarCommon.hideMessage("closeAbsensiMessage");

            const idAbsensi = parseInt(document.getElementById("closeIdAbsensi").value, 10);
            const btn = document.getElementById("closeAbsensiButton");

            if (!idAbsensi || idAbsensi < 1) {
                PengajarCommon.showMessage(
                    "closeAbsensiMessage",
                    "ID absensi wajib diisi dengan angka valid.",
                    "error"
                );
                return;
            }

            if (!window.confirm("Tutup sesi absensi ID " + idAbsensi + "?")) {
                return;
            }

            PengajarCommon.setButtonLoading(btn, true, "Tutup Absensi");

            try {
                const result = await apiRequest(API.close, {
                    method: "POST",
                    body: { id_absensi: idAbsensi }
                });
                PengajarCommon.showMessage(
                    "closeAbsensiMessage",
                    result.message || "Sesi absensi berhasil ditutup.",
                    "success"
                );
            } catch (error) {
                PengajarCommon.showMessage(
                    "closeAbsensiMessage",
                    error.message || "Gagal menutup absensi.",
                    "error"
                );
            } finally {
                PengajarCommon.setButtonLoading(btn, false, "Tutup Absensi");
            }
        });
    }

    // ---------- Lihat kehadiran ----------
    const attendanceForm = document.getElementById("attendanceForm");
    if (attendanceForm) {
        attendanceForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            PengajarCommon.hideMessage("attendanceMessage");

            const idAbsensi = parseInt(document.getElementById("viewIdAbsensi").value, 10);
            const btn = document.getElementById("viewAttendanceButton");
            const tbody = document.getElementById("attendanceTableBody");
            const infoBox = document.getElementById("attendanceInfo");
            const metaEl = document.getElementById("attendanceMeta");
            const statEl = document.getElementById("attendanceStat");

            if (!idAbsensi || idAbsensi < 1) {
                PengajarCommon.showMessage(
                    "attendanceMessage",
                    "ID absensi wajib diisi dengan angka valid.",
                    "error"
                );
                return;
            }

            PengajarCommon.setButtonLoading(btn, true, "Tampilkan Kehadiran");
            if (tbody) {
                tbody.innerHTML = "<tr><td colspan=\"5\">Memuat data...</td></tr>";
            }

            try {
                const url = API.attendance + "?id_absensi=" + encodeURIComponent(idAbsensi);
                const result = await apiRequest(url, { method: "GET" });
                const payload = result.data || {};
                const absensi = payload.absensi || {};
                const statistik = payload.statistik || {};
                const siswaList = payload.siswa || [];

                if (infoBox) infoBox.hidden = false;

                if (metaEl) {
                    metaEl.textContent =
                        "Kelas: " + (absensi.nama_kelas || "-") +
                        " | Tanggal: " + (absensi.tanggal || "-") +
                        " | Status: " + (absensi.status || "-") +
                        " | Buka: " + (absensi.waktu_buka || "-") +
                        " | Tutup: " + (absensi.waktu_tutup || "-");
                }

                if (statEl) {
                    statEl.textContent =
                        "Siswa: " + (statistik.jumlah_siswa || 0) +
                        " | Hadir: " + (statistik.hadir || 0) +
                        " | Izin: " + (statistik.izin || 0) +
                        " | Sakit: " + (statistik.sakit || 0) +
                        " | Alpa: " + (statistik.alpa || 0) +
                        " | Belum absen: " + (statistik.belum_absen || 0);
                }

                if (tbody) {
                    if (siswaList.length === 0) {
                        tbody.innerHTML =
                            "<tr><td colspan=\"5\">Tidak ada data siswa di kelas ini.</td></tr>";
                    } else {
                        tbody.innerHTML = siswaList.map(function (item, index) {
                            const status = item.status_kehadiran || "belum absen";
                            const waktu = item.waktu_absen || "-";
                            const kode = item.kode_siswa || item.kode || "-";
                            const nama = item.nama || item.nama_siswa || "-";
                            return (
                                "<tr>" +
                                "<td>" + (index + 1) + "</td>" +
                                "<td>" + PengajarCommon.escapeHtml(kode) + "</td>" +
                                "<td>" + PengajarCommon.escapeHtml(nama) + "</td>" +
                                "<td>" + PengajarCommon.escapeHtml(status) + "</td>" +
                                "<td>" + PengajarCommon.escapeHtml(waktu) + "</td>" +
                                "</tr>"
                            );
                        }).join("");
                    }
                }

                PengajarCommon.showMessage(
                    "attendanceMessage",
                    result.message || "Data kehadiran berhasil dimuat.",
                    "success"
                );
            } catch (error) {
                if (tbody) {
                    tbody.innerHTML = "<tr><td colspan=\"5\">Gagal memuat data.</td></tr>";
                }
                if (infoBox) infoBox.hidden = true;
                PengajarCommon.showMessage(
                    "attendanceMessage",
                    error.message || "Gagal mengambil data kehadiran.",
                    "error"
                );
            } finally {
                PengajarCommon.setButtonLoading(btn, false, "Tampilkan Kehadiran");
            }
        });
    }

    initForm();
})();