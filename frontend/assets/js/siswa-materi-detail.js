/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-materi-detail.js
GUNA FILE   : Detail satu materi (frontend/siswa/materi-detail.html).
                GET ../../backend/api/siswa/materi/list.php?id_kelas=
                    (materi dicari berdasarkan id_materi di URL)
                GET ../../backend/api/siswa/materi/download.php?id_materi=
              Bergantung pada: siswa-common.js dan pengajar-kelas-download.js.
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API_LIST = "../../backend/api/siswa/materi/list.php";
    const API_DOWNLOAD = "../../backend/api/siswa/materi/download.php";
    const MESSAGE_ID = "pageMessage";

    const params = new URLSearchParams(window.location.search);
    const idMateri = parseInt(params.get("id_materi"), 10);
    const idKelasUrl = parseInt(params.get("id_kelas"), 10);

    function formatWaktu(value) {
        if (!value) return "-";
        const date = new Date(String(value).replace(" ", "T"));
        if (isNaN(date.getTime())) return value;

        return date.toLocaleString("id-ID", {
            day: "numeric", month: "short", year: "numeric",
            hour: "2-digit", minute: "2-digit"
        });
    }

    function render(materi) {
        document.getElementById("backToKelasLink").href =
            "kelas-detail.html?id_kelas=" + encodeURIComponent(materi.id_kelas) + "&tab=aktivitas";

        document.getElementById("dJudul").textContent = materi.judul;
        document.getElementById("dKelas").textContent = materi.nama_kelas || "-";
        document.getElementById("dTipe").textContent = materi.tipe || "-";
        document.getElementById("dWaktu").textContent = formatWaktu(materi.waktu_upload);
        document.title = materi.judul + " - Siswa LMS";

        const aksiEl = document.getElementById("dAksi");
        aksiEl.textContent = "";

        if (materi.file) {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = "Unduh file";
            button.addEventListener("click", async function () {
                SiswaCommon.hideMessage(MESSAGE_ID);
                button.disabled = true;

                try {
                    await KelasDownload.saveFromUrl(
                        API_DOWNLOAD + "?id_materi=" + encodeURIComponent(materi.id_materi),
                        materi.judul
                    );
                } catch (error) {
                    SiswaCommon.showMessage(MESSAGE_ID, error.message || "Gagal mengunduh materi.", "error");
                } finally {
                    button.disabled = false;
                }
            });
            aksiEl.appendChild(button);
        }

        if (materi.link) {
            // Hanya http/https yang dijadikan tautan (mencegah javascript: dan sejenisnya).
            if (/^https?:\/\//i.test(materi.link)) {
                if (materi.file) aksiEl.appendChild(document.createTextNode(" "));
                const anchor = document.createElement("a");
                anchor.href = materi.link;
                anchor.target = "_blank";
                anchor.rel = "noopener noreferrer";
                anchor.textContent = "Buka link";
                aksiEl.appendChild(anchor);
            } else {
                aksiEl.appendChild(document.createTextNode(" Link tidak valid."));
            }
        }

        if (!materi.file && !materi.link) aksiEl.textContent = "Tidak ada file atau link.";

        document.getElementById("detailSection").hidden = false;
    }

    async function start() {
        if (!idMateri || idMateri < 1) {
            SiswaCommon.showMessage(MESSAGE_ID, "Materi tidak valid. Buka dari daftar aktivitas kelas.", "error");
            return;
        }

        try {
            const url = API_LIST + (idKelasUrl > 0 ? "?id_kelas=" + encodeURIComponent(idKelasUrl) : "");
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];
            const materi = rows.find(function (row) {
                return row.id_materi === idMateri;
            });

            if (!materi) {
                SiswaCommon.showMessage(MESSAGE_ID, "Materi tidak ditemukan.", "error");
                return;
            }

            render(materi);
        } catch (error) {
            SiswaCommon.showMessage(MESSAGE_ID, error.message || "Gagal memuat materi.", "error");
        }
    }

    start();
})();