/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-absensi-report.js
GUNA FILE   : Halaman report absensi pengajar (frontend/pengajar/absensi-report.html).
              - Membaca id_kelas dari URL
              - Mengambil data : GET ../../backend/api/pengajar/absensi/report.php?id_kelas=
              - Menampilkan tabel siswa x sesi:
                  Hadir        -> siswa mengisi absensi pada sesi itu
                  Izin/Sakit/Alpa -> status lain bila tercatat di tabel kehadiran
                  Tidak hadir  -> sesi sudah tertutup dan siswa tidak mengisi
                  Belum        -> sesi masih terbuka dan siswa belum mengisi
                  -            -> sesi terjadwal (belum dimulai)
              - Kolom akhir: total hadir dan persentase kehadiran per siswa
              Bergantung pada: pengajar-common.js.
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API_REPORT = "../../backend/api/pengajar/absensi/report.php";

    const STATUS_LABEL = {
        hadir: "Hadir",
        izin: "Izin",
        sakit: "Sakit",
        alpa: "Alpa"
    };

    const params = new URLSearchParams(window.location.search);
    const parsedIdKelas = parseInt(params.get("id_kelas"), 10);
    const idKelas = parsedIdKelas > 0 ? parsedIdKelas : null;

    const headEl = document.getElementById("reportTableHead");
    const bodyEl = document.getElementById("reportTableBody");

    // "2026-10-25 08:00:00" -> "08:00"
    function toHourMinute(dateTime) {
        return dateTime ? String(dateTime).slice(11, 16) : "";
    }

    function getSessionStatusLabel(session) {
        if (session.belum_dimulai) return "terjadwal";
        return session.status === "terbuka" ? "terbuka" : "tertutup";
    }

    function buildHeaderHtml(sessions) {
        const sessionHeaders = sessions.map(function (session) {
            const timeRange = toHourMinute(session.waktu_buka) +
                (session.waktu_tutup ? " - " + toHourMinute(session.waktu_tutup) : "");

            return "<th>" + PengajarCommon.escapeHtml(session.tanggal) +
                "<br><small>" + PengajarCommon.escapeHtml(timeRange) + "</small>" +
                "<br><small>" + PengajarCommon.escapeHtml(getSessionStatusLabel(session)) + "</small></th>";
        }).join("");

        return "<tr><th>No</th><th>Kode Siswa</th><th>Nama</th>" + sessionHeaders +
            "<th>Hadir</th><th>Persentase</th></tr>";
    }

    function getCellText(student, session) {
        const record = student.kehadiran ? student.kehadiran[String(session.id_absensi)] : null;

        if (record) {
            return STATUS_LABEL[record.status] || record.status;
        }
        if (session.belum_dimulai) return "-";
        return session.status === "terbuka" ? "Belum" : "Tidak hadir";
    }

    function formatPercentage(value) {
        return value === null || value === undefined ? "-" : String(value).replace(".", ",") + "%";
    }

    function renderSummary(payload) {
        const students = payload.siswa || [];
        const percentages = students
            .map(function (student) { return student.persentase; })
            .filter(function (value) { return value !== null && value !== undefined; });

        let averageText = "-";
        if (percentages.length > 0) {
            const total = percentages.reduce(function (sum, value) { return sum + value; }, 0);
            averageText = formatPercentage(Math.round((total / percentages.length) * 10) / 10);
        }

        document.getElementById("reportSummary").textContent =
            "Kelas: " + payload.kelas.nama_kelas +
            " | Jumlah siswa: " + students.length +
            " | Total sesi: " + (payload.sesi || []).length +
            " | Sesi dihitung: " + (payload.jumlah_sesi_dihitung || 0) +
            " | Rata-rata kehadiran: " + averageText;
    }

    function renderTable(payload) {
        const sessions = payload.sesi || [];
        const students = payload.siswa || [];
        const columnCount = 5 + sessions.length;

        headEl.innerHTML = buildHeaderHtml(sessions);

        if (students.length === 0) {
            bodyEl.innerHTML = "<tr><td colspan=\"" + columnCount + "\">Belum ada siswa di kelas ini.</td></tr>";
            return;
        }

        bodyEl.innerHTML = students.map(function (student, index) {
            const cells = sessions.map(function (session) {
                return "<td>" + PengajarCommon.escapeHtml(getCellText(student, session)) + "</td>";
            }).join("");

            return (
                "<tr>" +
                "<td>" + (index + 1) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(student.kode_siswa) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(student.nama) + "</td>" +
                cells +
                "<td>" + PengajarCommon.escapeHtml(student.jumlah_hadir) + " / " +
                    PengajarCommon.escapeHtml(payload.jumlah_sesi_dihitung || 0) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(formatPercentage(student.persentase)) + "</td>" +
                "</tr>"
            );
        }).join("");

        if (sessions.length === 0) {
            PengajarCommon.showMessage("reportMessage", "Belum ada sesi absensi di kelas ini.", "error");
        }
    }

    async function loadReport() {
        if (idKelas === null) {
            document.getElementById("reportSummary").textContent = "-";
            bodyEl.innerHTML = "<tr><td colspan=\"5\">Report tidak dapat dibuka.</td></tr>";
            PengajarCommon.showMessage("reportMessage", "ID kelas tidak valid. Buka report dari tab Absensi di kelas.", "error");
            return;
        }

        document.getElementById("backToKelasLink").href =
            "kelas-detail.html?id_kelas=" + encodeURIComponent(idKelas) + "&tab=absensi";

        try {
            const url = API_REPORT + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const payload = result.data || {};

            document.getElementById("reportTitle").textContent = "Report Absensi - " + payload.kelas.nama_kelas;
            document.title = "Report Absensi " + payload.kelas.nama_kelas + " - Pengajar LMS";

            renderSummary(payload);
            renderTable(payload);
        } catch (error) {
            document.getElementById("reportSummary").textContent = "-";
            bodyEl.innerHTML = "<tr><td colspan=\"5\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage("reportMessage", error.message || "Gagal mengambil report absensi.", "error");
        }
    }

    loadReport();
})();