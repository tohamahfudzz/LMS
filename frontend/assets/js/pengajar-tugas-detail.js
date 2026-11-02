/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-tugas-detail.js
GUNA FILE   : Detail satu tugas (frontend/pengajar/tugas-detail.html).
                GET  ../../backend/api/pengajar/tugas/detail.php?id_tugas=
                GET  ../../backend/api/pengajar/tugas/submissions.php?id_tugas=
                GET  ../../backend/api/pengajar/tugas/download_lampiran.php?id_tugas=
                GET  ../../backend/api/pengajar/tugas/download.php?id_pengumpulan=
                POST ../../backend/api/tugas/close.php   Body: { id_tugas }
              Bergantung pada: pengajar-common.js dan pengajar-kelas-download.js.
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API = {
        detail: "../../backend/api/pengajar/tugas/detail.php",
        submissions: "../../backend/api/pengajar/tugas/submissions.php",
        lampiran: "../../backend/api/pengajar/tugas/download_lampiran.php",
        jawaban: "../../backend/api/pengajar/tugas/download.php",
        close: "../../backend/api/tugas/close.php"
    };

    const MESSAGE_ID = "pageMessage";
    const esc = PengajarCommon.escapeHtml;
    const tbody = document.getElementById("submissionTableBody");

    const params = new URLSearchParams(window.location.search);
    const idTugas = parseInt(params.get("id_tugas"), 10);

    function formatWaktu(value) {
        if (!value) return "-";
        const date = new Date(String(value).replace(" ", "T"));
        if (isNaN(date.getTime())) return value;

        return date.toLocaleString("id-ID", {
            day: "numeric", month: "short", year: "numeric",
            hour: "2-digit", minute: "2-digit"
        });
    }

    async function download(button, url, fallbackName) {
        PengajarCommon.hideMessage(MESSAGE_ID);
        button.disabled = true;

        try {
            await KelasDownload.saveFromUrl(url, fallbackName);
        } catch (error) {
            PengajarCommon.showMessage(MESSAGE_ID, error.message || "Gagal mengunduh file.", "error");
        } finally {
            button.disabled = false;
        }
    }

    function render(tugas) {
        document.getElementById("backToKelasLink").href =
            "kelas-detail.html?id_kelas=" + encodeURIComponent(tugas.id_kelas) + "&tab=aktivitas";

        document.getElementById("dJudul").textContent = tugas.judul;
        document.getElementById("dKelas").textContent = tugas.nama_kelas || "-";
        document.getElementById("dDibuat").textContent = formatWaktu(tugas.waktu_dibuat);
        document.getElementById("dMulai").textContent = formatWaktu(tugas.waktu_mulai);
        document.getElementById("dDeadline").textContent = formatWaktu(tugas.deadline);
        document.getElementById("dStatus").textContent = tugas.status || "-";
        document.getElementById("dDeskripsi").textContent = tugas.deskripsi || "-";
        document.title = tugas.judul + " - Pengajar LMS";

        const lampiranEl = document.getElementById("dLampiran");
        lampiranEl.textContent = "";

        if (tugas.file) {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = "Unduh lampiran";
            button.addEventListener("click", function () {
                download(button, API.lampiran + "?id_tugas=" + encodeURIComponent(tugas.id_tugas), tugas.judul);
            });
            lampiranEl.appendChild(button);
        } else {
            lampiranEl.textContent = "Tidak ada lampiran.";
        }

        const aksiEl = document.getElementById("dAksi");
        aksiEl.textContent = "";

        if (tugas.status === "terbuka") {
            const closeButton = document.createElement("button");
            closeButton.type = "button";
            closeButton.textContent = "Tutup pengumpulan";
            closeButton.addEventListener("click", function () {
                closeTugas(closeButton);
            });
            aksiEl.appendChild(closeButton);
        } else {
            aksiEl.textContent = "-";
        }

        document.getElementById("detailSection").hidden = false;
    }

    async function closeTugas(button) {
        if (!window.confirm("Tutup pengumpulan tugas ini? Siswa tidak bisa mengumpulkan lagi.")) return;

        PengajarCommon.hideMessage(MESSAGE_ID);
        button.disabled = true;
        button.textContent = "...";

        try {
            const result = await apiRequest(API.close, { method: "POST", body: { id_tugas: idTugas } });
            await loadAll();
            PengajarCommon.showMessage(MESSAGE_ID, result.message || "Tugas berhasil ditutup.", "success");
        } catch (error) {
            PengajarCommon.showMessage(MESSAGE_ID, error.message || "Gagal menutup tugas.", "error");
            button.disabled = false;
            button.textContent = "Tutup pengumpulan";
        }
    }

    async function loadSubmissions() {
        tbody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";

        try {
            const result = await apiRequest(
                API.submissions + "?id_tugas=" + encodeURIComponent(idTugas),
                { method: "GET" }
            );
            const data = result.data;
            const siswa = data.siswa || [];

            document.getElementById("submissionStat").textContent =
                "Siswa: " + data.statistik.jumlah_siswa +
                " · Sudah mengumpulkan: " + data.statistik.sudah_mengumpulkan +
                " · Belum: " + data.statistik.belum_mengumpulkan;

            if (siswa.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"7\">Belum ada siswa di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = siswa.map(function (row, index) {
                const sudah = row.status_pengumpulan === "sudah";
                const fileCell = sudah && row.file
                    ? '<button type="button" class="btn-unduh-jawaban" data-id="' +
                        esc(row.id_pengumpulan) + '" data-nama="' + esc(row.nama) + '">Unduh</button>'
                    : "-";

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + esc(row.kode_siswa) + "</td>" +
                    "<td>" + esc(row.nama) + "</td>" +
                    "<td>" + (sudah ? "Sudah" : "Belum") + "</td>" +
                    "<td>" + (sudah ? esc(formatWaktu(row.waktu_pengumpulan)) : "-") + "</td>" +
                    "<td>" + (row.teks ? esc(row.teks) : "-") + "</td>" +
                    "<td>" + fileCell + "</td>" +
                    "</tr>"
                );
            }).join("");

            document.getElementById("submissionSection").hidden = false;
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat: " + esc(error.message) + "</td></tr>";
            document.getElementById("submissionSection").hidden = false;
        }
    }

    async function loadAll() {
        const result = await apiRequest(
            API.detail + "?id_tugas=" + encodeURIComponent(idTugas),
            { method: "GET" }
        );

        render(result.data);
        await loadSubmissions();
    }

    tbody.addEventListener("click", function (event) {
        const button = event.target.closest(".btn-unduh-jawaban");
        if (!button) return;

        const idPengumpulan = parseInt(button.getAttribute("data-id"), 10);
        if (!idPengumpulan) return;

        download(
            button,
            API.jawaban + "?id_pengumpulan=" + encodeURIComponent(idPengumpulan),
            "jawaban-" + button.getAttribute("data-nama")
        );
    });

    async function start() {
        if (!idTugas || idTugas < 1) {
            PengajarCommon.showMessage(MESSAGE_ID, "Tugas tidak valid. Buka dari daftar aktivitas kelas.", "error");
            return;
        }

        try {
            await loadAll();
        } catch (error) {
            PengajarCommon.showMessage(MESSAGE_ID, error.message || "Gagal memuat tugas.", "error");
        }
    }

    start();
})();