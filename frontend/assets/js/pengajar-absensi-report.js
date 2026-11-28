/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-absensi-report.js
GUNA FILE   : Halaman report absensi pengajar (frontend/pengajar/absensi-report.html).
              - Membaca id_kelas dari URL
              - Mengambil data : GET ../../backend/api/pengajar/absensi/report.php?id_kelas=
              - Menampilkan tabel siswa x sesi:
                  Hadir / Izin / Alpa -> status yang tercatat di tabel kehadiran
                                         (diisi siswa atau pengajar); tanda * dan tooltip
                                         bila ada catatan
                  Belum diisi         -> sesi sudah dimulai dan belum ada catatan
                  -                   -> sesi terjadwal (belum dimulai)
              - Kolom akhir: hadir (x / jumlah sesi), izin, alpa, belum diisi, dan
                persentase kehadiran (hanya dari hadir) per siswa
              Perubahan: hitungan izin/alpa/belum diisi terpisah, label "Belum diisi"
                         menggantikan "Tidak hadir" dan "Belum", catatan sebagai tooltip.
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
        alpa: "Alpa"
    };

    // Escape untuk nilai atribut HTML (escapeHtml bawaan tidak meng-escape tanda kutip).
    function escapeAttr(text) {
        return String(text == null ? "" : text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

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
            "<th>Hadir</th><th>Izin</th><th>Alpa</th><th>Belum diisi</th><th>Persentase</th></tr>";
    }

    // Satu sel status siswa pada satu sesi. Bila ada catatan, sel diberi tanda * dan tooltip.
    function buildCellHtml(student, session) {
        const record = student.kehadiran ? student.kehadiran[String(session.id_absensi)] : null;

        if (record) {
            const label = STATUS_LABEL[record.status] || record.status;
            const notes = [];

            if (record.catatan_siswa) notes.push("Siswa: " + record.catatan_siswa);
            if (record.catatan_pengajar) notes.push("Pengajar: " + record.catatan_pengajar);

            if (notes.length > 0) {
                return "<td title=\"" + escapeAttr(notes.join(" | ")) + "\">" +
                    PengajarCommon.escapeHtml(label) + " *</td>";
            }

            return "<td>" + PengajarCommon.escapeHtml(label) + "</td>";
        }

        return "<td>" + (session.belum_dimulai ? "-" : "Belum diisi") + "</td>";
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
        const columnCount = 8 + sessions.length;

        headEl.innerHTML = buildHeaderHtml(sessions);

        if (students.length === 0) {
            bodyEl.innerHTML = "<tr><td colspan=\"" + columnCount + "\">Belum ada siswa di kelas ini.</td></tr>";
            return;
        }

        bodyEl.innerHTML = students.map(function (student, index) {
            const cells = sessions.map(function (session) {
                return buildCellHtml(student, session);
            }).join("");

            return (
                "<tr>" +
                "<td>" + (index + 1) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(student.kode_siswa) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(student.nama) + "</td>" +
                cells +
                "<td>" + PengajarCommon.escapeHtml(student.jumlah_hadir) + " / " +
                    PengajarCommon.escapeHtml(payload.jumlah_sesi_dihitung || 0) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(student.jumlah_izin || 0) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(student.jumlah_alpa || 0) + "</td>" +
                "<td>" + PengajarCommon.escapeHtml(student.jumlah_belum_diisi || 0) + "</td>" +
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
            bodyEl.innerHTML = "<tr><td colspan=\"8\">Report tidak dapat dibuka.</td></tr>";
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
            bodyEl.innerHTML = "<tr><td colspan=\"8\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage("reportMessage", error.message || "Gagal mengambil report absensi.", "error");
        }
    }

    loadReport();
})();