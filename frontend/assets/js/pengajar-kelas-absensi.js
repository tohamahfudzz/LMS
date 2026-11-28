/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-absensi.js
GUNA FILE   : Tab Absensi pada halaman kelas pengajar (frontend/pengajar/kelas-detail.html).
              Pengajar tidak perlu mengetik ID absensi: semua sesi kelas ini tampil
              dalam tabel dengan tombol aksi.
              - Daftar sesi kelas ini : GET  ../../backend/api/pengajar/absensi/list.php?id_kelas=
              - Buka sesi             : POST ../../backend/api/absensi/open.php
                                        Body: { id_kelas, tanggal, mode, jam_buka, jam_tutup }
                                        mode 'siswa'  : siswa mengisi sendiri (Hadir atau Izin)
                                                        di antara waktu buka dan waktu tutup;
                                                        jam wajib diisi.
                                        mode 'manual' : pengajar yang mengisi; jam boleh
                                                        dikosongkan (sesi berlaku seharian).
                                        Mode dikunci saat sesi dibuat.
              - Tutup sesi            : POST ../../backend/api/absensi/close.php
                                        Body: { id_absensi }
              - Lihat kehadiran sesi  : GET  ../../backend/api/pengajar/absensi/attendance.php?id_absensi=
              - Simpan kehadiran      : POST ../../backend/api/pengajar/absensi/set_status.php
                                        Body: { id_absensi, data: [{ id_siswa, status, catatan }] }
                                        Pengajar memilih Hadir/Izin/Alpa per siswa dan boleh
                                        menimpa status yang diisi siswa. Hanya baris yang
                                        diubah yang dikirim.
              - Report absensi kelas ini (halaman sendiri): absensi-report.html?id_kelas=
                (tautannya diisi otomatis di sini)
              Bergantung pada: pengajar-common.js dan pengajar-kelas-detail.js.
              Perubahan: pilihan mode di form buka sesi, kolom mode dan hitungan
                         hadir/izin/alpa di daftar sesi, tabel kehadiran berupa ceklis
                         dengan catatan dan tombol simpan.
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
        attendance: "../../backend/api/pengajar/absensi/attendance.php",
        setStatus: "../../backend/api/pengajar/absensi/set_status.php"
    };

    // Status yang bisa dipilih pengajar (harus sama dengan ABSENSI_STATUS_PENGAJAR di backend).
    const STATUS_OPTIONS = [
        { value: "hadir", label: "Hadir" },
        { value: "izin", label: "Izin" },
        { value: "alpa", label: "Alpa" }
    ];

    const MODE_LABEL = {
        siswa: "Siswa mengisi",
        manual: "Manual (pengajar)"
    };

    const MODE_HINT = {
        siswa: "Siswa mengisi sendiri (Hadir atau Izin) selama sesi terbuka. Anda tetap bisa mengubah status siswa.",
        manual: "Anda mengisi kehadiran setiap siswa sendiri; siswa tidak bisa mengisi. Jam boleh dikosongkan (sesi berlaku seharian)."
    };

    const openForm = document.getElementById("openAbsensiForm");
    const tanggalInput = document.getElementById("absensiTanggal");
    const modeSelect = document.getElementById("absensiMode");
    const modeHint = document.getElementById("absensiModeHint");
    const jamBukaInput = document.getElementById("absensiJamBuka");
    const jamTutupInput = document.getElementById("absensiJamTutup");
    const sesiBody = document.getElementById("absensiTableBody");
    const detailSection = document.getElementById("attendanceSection");
    const detailBody = document.getElementById("attendanceTableBody");
    const detailModeHint = document.getElementById("attendanceModeHint");
    const saveButton = document.getElementById("attendanceSaveButton");
    const markAllButton = document.getElementById("attendanceMarkAllButton");

    // ID sesi yang sedang ditampilkan di bagian "Kehadiran Siswa" (null jika belum ada).
    let shownIdAbsensi = null;

    // Status dan catatan setiap siswa saat data dimuat: { id_siswa: { status, catatan } }.
    // Dipakai untuk mengirim hanya baris yang benar-benar diubah pengajar.
    let initialState = {};

    // Escape untuk nilai atribut HTML (escapeHtml bawaan tidak meng-escape tanda kutip).
    function escapeAttr(text) {
        return String(text == null ? "" : text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

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

    // Menyesuaikan formulir dengan mode terpilih: mode manual mengosongkan jam
    // (opsional), mode siswa mengisi saran jam bila masih kosong atau bila diminta.
    function applyModeToForm(forceDefaults) {
        const mode = modeSelect.value;
        modeHint.textContent = MODE_HINT[mode] || "";

        if (mode === "manual") {
            jamBukaInput.value = "";
            jamTutupInput.value = "";
        } else if (forceDefaults || (!jamBukaInput.value && !jamTutupInput.value)) {
            setDefaultTimes();
        }
    }

    // Sesi yang waktu bukanya masih di masa depan ditandai "terjadwal".
    function getStatusLabel(item) {
        if (item.status === "terbuka" && item.belum_dimulai) {
            return "terjadwal (belum dimulai)";
        }
        return item.status;
    }

    async function loadSesiList() {
        sesiBody.innerHTML = "<tr><td colspan=\"8\">Memuat data...</td></tr>";

        try {
            const url = API.list + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const payload = result.data || {};
            const rows = payload.sesi || [];

            if (rows.length === 0) {
                sesiBody.innerHTML = "<tr><td colspan=\"8\">Belum ada sesi absensi. Buka sesi baru di atas.</td></tr>";
                return;
            }

            sesiBody.innerHTML = rows.map(function (item, index) {
                const idAbsensi = parseInt(item.id_absensi, 10);
                const isOpen = item.status === "terbuka";

                let aksi = "<button type=\"button\" class=\"btn-lihat-absensi\" data-id=\"" +
                    idAbsensi + "\">Lihat / isi kehadiran</button>";
                if (isOpen) {
                    aksi += " <button type=\"button\" class=\"btn-tutup-absensi\" data-id=\"" +
                        idAbsensi + "\">Tutup</button>";
                }

                const hitungan = (item.jumlah_hadir || 0) + " / " +
                    (item.jumlah_izin || 0) + " / " + (item.jumlah_alpa || 0);

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.tanggal) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_buka) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_tutup || "-") + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(MODE_LABEL[item.mode] || MODE_LABEL.siswa) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(getStatusLabel(item)) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(hitungan) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            sesiBody.innerHTML = "<tr><td colspan=\"8\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage("absensiListMessage", error.message || "Gagal mengambil daftar sesi.", "error");
        }
    }

    // Satu baris siswa: tiga pilihan status (radio) dan kolom catatan pengajar.
    function buildSiswaRowHtml(item, index) {
        const idSiswa = parseInt(item.id_siswa, 10);

        const radios = STATUS_OPTIONS.map(function (option) {
            const checked = item.status_kehadiran === option.value ? " checked" : "";
            return "<label><input type=\"radio\" name=\"status-" + idSiswa + "\" value=\"" +
                option.value + "\"" + checked + "> " + option.label + "</label>";
        }).join(" ");

        const statusText = item.status_kehadiran ? "" : "<br><small>Belum diisi</small>";

        return (
            "<tr data-id-siswa=\"" + idSiswa + "\">" +
            "<td>" + (index + 1) + "</td>" +
            "<td>" + PengajarCommon.escapeHtml(item.kode_siswa) + "</td>" +
            "<td>" + PengajarCommon.escapeHtml(item.nama) + "</td>" +
            "<td>" + radios + statusText + "</td>" +
            "<td>" + PengajarCommon.escapeHtml(item.catatan_siswa || "-") + "</td>" +
            "<td><input type=\"text\" class=\"input-catatan-pengajar\" maxlength=\"255\" " +
                "placeholder=\"Catatan (opsional)\" value=\"" + escapeAttr(item.catatan_pengajar || "") + "\"></td>" +
            "<td>" + PengajarCommon.escapeHtml(item.waktu_absen || "-") + "</td>" +
            "</tr>"
        );
    }

    function renderKehadiran(payload) {
        const absensi = payload.absensi || {};
        const statistik = payload.statistik || {};
        const siswa = payload.siswa || [];
        const mode = absensi.mode === "manual" ? "manual" : "siswa";

        document.getElementById("attendanceMeta").textContent =
            "Tanggal: " + (absensi.tanggal || "-") +
            " | Mode: " + MODE_LABEL[mode] +
            " | Status: " + (absensi.status || "-") +
            " | Dibuka: " + (absensi.waktu_buka || "-") +
            " | Ditutup: " + (absensi.waktu_tutup || "-");

        document.getElementById("attendanceStat").textContent =
            "Siswa: " + (statistik.jumlah_siswa || 0) +
            " | Hadir: " + (statistik.hadir || 0) +
            " | Izin: " + (statistik.izin || 0) +
            " | Alpa: " + (statistik.alpa || 0) +
            " | Belum diisi: " + (statistik.belum_absen || 0);

        detailModeHint.textContent = MODE_HINT[mode];

        initialState = {};

        if (siswa.length === 0) {
            detailBody.innerHTML = "<tr><td colspan=\"7\">Belum ada siswa di kelas ini.</td></tr>";
            saveButton.disabled = true;
            markAllButton.disabled = true;
            return;
        }

        saveButton.disabled = false;
        markAllButton.disabled = false;

        siswa.forEach(function (item) {
            initialState[parseInt(item.id_siswa, 10)] = {
                status: item.status_kehadiran || null,
                catatan: (item.catatan_pengajar || "").trim()
            };
        });

        detailBody.innerHTML = siswa.map(buildSiswaRowHtml).join("");
    }

    async function showKehadiran(idAbsensi, keepMessage) {
        shownIdAbsensi = idAbsensi;
        detailSection.hidden = false;
        if (!keepMessage) PengajarCommon.hideMessage("attendanceMessage");
        detailBody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";

        try {
            const url = API.attendance + "?id_absensi=" + encodeURIComponent(idAbsensi);
            const result = await apiRequest(url, { method: "GET" });
            renderKehadiran(result.data || {});
        } catch (error) {
            detailBody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage("attendanceMessage", error.message || "Gagal mengambil data kehadiran.", "error");
        }
    }

    // Mengumpulkan baris yang diubah pengajar. Mengembalikan null (dan menampilkan pesan)
    // bila ada siswa yang diberi catatan tetapi statusnya belum dipilih.
    function collectChanges() {
        const changes = [];
        const incomplete = [];

        detailBody.querySelectorAll("tr[data-id-siswa]").forEach(function (row) {
            const idSiswa = parseInt(row.getAttribute("data-id-siswa"), 10);
            const checked = row.querySelector("input[type=radio]:checked");
            const catatan = row.querySelector(".input-catatan-pengajar").value.trim();

            if (!checked) {
                if (catatan !== "") incomplete.push(row.children[2].textContent);
                return;
            }

            const awal = initialState[idSiswa] || { status: null, catatan: "" };
            if (checked.value !== awal.status || catatan !== awal.catatan) {
                changes.push({ id_siswa: idSiswa, status: checked.value, catatan: catatan });
            }
        });

        if (incomplete.length > 0) {
            PengajarCommon.showMessage(
                "attendanceMessage",
                "Pilih status lebih dulu untuk siswa yang diberi catatan: " + incomplete.join(", ") + ".",
                "error"
            );
            return null;
        }

        return changes;
    }

    async function saveKehadiran() {
        if (shownIdAbsensi === null) return;

        PengajarCommon.hideMessage("attendanceMessage");

        const changes = collectChanges();
        if (changes === null) return;

        if (changes.length === 0) {
            PengajarCommon.showMessage("attendanceMessage", "Tidak ada perubahan untuk disimpan.", "error");
            return;
        }

        PengajarCommon.setButtonLoading(saveButton, true, "Simpan Kehadiran");

        try {
            const result = await apiRequest(API.setStatus, {
                method: "POST",
                body: { id_absensi: shownIdAbsensi, data: changes }
            });

            // Muat ulang data dari server, lalu tampilkan pesan sukses.
            await showKehadiran(shownIdAbsensi, true);
            PengajarCommon.showMessage("attendanceMessage", result.message || "Kehadiran siswa berhasil disimpan.", "success");
            loadSesiList();
        } catch (error) {
            PengajarCommon.showMessage("attendanceMessage", error.message || "Gagal menyimpan kehadiran.", "error");
        } finally {
            PengajarCommon.setButtonLoading(saveButton, false, "Simpan Kehadiran");
        }
    }

    // Pintasan: semua siswa yang belum punya pilihan ditandai Hadir (belum tersimpan sampai
    // pengajar menekan Simpan). Pengajar lalu cukup mengubah siswa yang izin atau alpa.
    function markAllUnfilledPresent() {
        detailBody.querySelectorAll("tr[data-id-siswa]").forEach(function (row) {
            if (row.querySelector("input[type=radio]:checked")) return;
            const hadirRadio = row.querySelector("input[type=radio][value=hadir]");
            if (hadirRadio) hadirRadio.checked = true;
        });
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
        const mode = modeSelect.value === "manual" ? "manual" : "siswa";
        const jamBuka = jamBukaInput.value;
        const jamTutup = jamTutupInput.value;
        const button = document.getElementById("openAbsensiButton");

        if (!tanggal) {
            PengajarCommon.showMessage("openAbsensiMessage", "Tanggal wajib diisi.", "error");
            return;
        }

        if (mode === "siswa" && (!jamBuka || !jamTutup)) {
            PengajarCommon.showMessage("openAbsensiMessage", "Waktu buka dan waktu tutup wajib diisi.", "error");
            return;
        }
        // Mode manual: jam boleh dikosongkan keduanya (berlaku seharian), tetapi tidak boleh hanya satu.
        if (mode === "manual" && (!jamBuka !== !jamTutup)) {
            PengajarCommon.showMessage("openAbsensiMessage", "Isi waktu buka dan waktu tutup, atau kosongkan keduanya.", "error");
            return;
        }
        // Format HH:MM berurutan secara alfabet sama dengan urutan waktunya.
        if (jamBuka && jamTutup && jamTutup <= jamBuka) {
            PengajarCommon.showMessage("openAbsensiMessage", "Waktu tutup harus lebih akhir dari waktu buka.", "error");
            return;
        }

        const body = { id_kelas: idKelas, tanggal: tanggal, mode: mode };
        if (jamBuka && jamTutup) {
            body.jam_buka = jamBuka;
            body.jam_tutup = jamTutup;
        }

        PengajarCommon.setButtonLoading(button, true, "Buka Absensi");

        try {
            const result = await apiRequest(API.open, { method: "POST", body: body });
            PengajarCommon.showMessage("openAbsensiMessage", result.message || "Sesi absensi berhasil dibuka.", "success");
            await loadSesiList();

            // Sesi baru langsung ditampilkan: pada mode siswa untuk memantau, pada mode manual
            // supaya pengajar bisa langsung mengisi.
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
    modeSelect.addEventListener("change", function () { applyModeToForm(false); });
    saveButton.addEventListener("click", saveKehadiran);
    markAllButton.addEventListener("click", markAllUnfilledPresent);

    // Tautan ke halaman report membawa id kelas yang sedang dibuka.
    document.getElementById("absensiReportLink").href =
        "absensi-report.html?id_kelas=" + encodeURIComponent(idKelas);

    KelasPage.registerTab("absensi", function () {
        tanggalInput.value = getTodayDate();
        applyModeToForm(true);
        loadSesiList();
    });
})();