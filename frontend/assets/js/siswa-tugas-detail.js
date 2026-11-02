/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-tugas-detail.js
GUNA FILE   : Detail satu tugas (frontend/siswa/tugas-detail.html).
                GET  ../../backend/api/siswa/tugas/list.php?id_kelas=
                     (tugas dicari berdasarkan id_tugas di URL)
                GET  ../../backend/api/siswa/tugas/download_lampiran.php?id_tugas=
                POST ../../backend/api/pengumpulan_tugas/upload.php  (multipart)
                POST ../../backend/api/pengumpulan_tugas/delete.php  Body: { id_tugas }
                     (hanya menghapus file; teks jawaban tetap tersimpan)
              Bergantung pada: siswa-common.js dan pengajar-kelas-download.js.
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API = {
        tugasList: "../../backend/api/siswa/tugas/list.php",
        lampiran: "../../backend/api/siswa/tugas/download_lampiran.php",
        upload: "../../backend/api/pengumpulan_tugas/upload.php",
        deleteFile: "../../backend/api/pengumpulan_tugas/delete.php"
    };

    const MESSAGE_ID = "pageMessage";
    const UPLOAD_MESSAGE_ID = "uploadMessage";

    const params = new URLSearchParams(window.location.search);
    const idTugas = parseInt(params.get("id_tugas"), 10);
    const idKelasUrl = parseInt(params.get("id_kelas"), 10);

    const submitSection = document.getElementById("submitSection");
    const form = document.getElementById("uploadTugasForm");

    function formatWaktu(value) {
        if (!value) return "-";
        const date = new Date(String(value).replace(" ", "T"));
        if (isNaN(date.getTime())) return value;

        return date.toLocaleString("id-ID", {
            day: "numeric", month: "short", year: "numeric",
            hour: "2-digit", minute: "2-digit"
        });
    }

    function describePengumpulan(tugas) {
        if (!tugas.sudah_mengumpulkan) return "Belum mengumpulkan.";

        const data = tugas.pengumpulan || {};
        const isi = [];
        if (data.teks) isi.push("teks");
        if (data.file) isi.push("file");

        return "Sudah dikumpulkan" +
            (data.waktu_pengumpulan ? " (" + formatWaktu(data.waktu_pengumpulan) + ")" : "") +
            (isi.length > 0 ? " - " + isi.join(" + ") : "") + ".";
    }

    function render(tugas) {
        const isOpen = tugas.status_tugas === "terbuka";

        document.getElementById("backToKelasLink").href =
            "kelas-detail.html?id_kelas=" + encodeURIComponent(tugas.id_kelas) + "&tab=aktivitas";

        document.getElementById("dJudul").textContent = tugas.judul;
        document.getElementById("dKelas").textContent = tugas.nama_kelas || "-";
        document.getElementById("dDibuat").textContent = formatWaktu(tugas.waktu_dibuat);
        document.getElementById("dMulai").textContent = formatWaktu(tugas.waktu_mulai);
        document.getElementById("dDeadline").textContent = formatWaktu(tugas.deadline);
        document.getElementById("dStatus").textContent = tugas.status_tugas || "-";
        document.getElementById("dDeskripsi").textContent = tugas.deskripsi || "-";
        document.title = tugas.judul + " - Siswa LMS";

        // Soal / lampiran dari pengajar.
        const lampiranEl = document.getElementById("dLampiran");
        lampiranEl.textContent = "";

        if (tugas.file) {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = "Unduh soal";
            button.addEventListener("click", async function () {
                SiswaCommon.hideMessage(MESSAGE_ID);
                button.disabled = true;

                try {
                    await KelasDownload.saveFromUrl(
                        API.lampiran + "?id_tugas=" + encodeURIComponent(tugas.id_tugas),
                        tugas.judul
                    );
                } catch (error) {
                    SiswaCommon.showMessage(MESSAGE_ID, error.message || "Gagal mengunduh soal.", "error");
                } finally {
                    button.disabled = false;
                }
            });
            lampiranEl.appendChild(button);
        } else {
            lampiranEl.textContent = "Tidak ada lampiran.";
        }

        // Status pengumpulan + tombol hapus file (hanya selama tugas terbuka).
        const pengumpulanEl = document.getElementById("dPengumpulan");
        pengumpulanEl.textContent = describePengumpulan(tugas);

        if (isOpen && tugas.sudah_mengumpulkan && tugas.pengumpulan && tugas.pengumpulan.file) {
            const hapusButton = document.createElement("button");
            hapusButton.type = "button";
            hapusButton.textContent = "Hapus file";
            hapusButton.addEventListener("click", function () {
                hapusFile(hapusButton);
            });
            pengumpulanEl.appendChild(document.createTextNode(" "));
            pengumpulanEl.appendChild(hapusButton);
        }

        // Formulir kumpul hanya muncul selama tugas masih terbuka.
        if (isOpen) {
            document.getElementById("submitTitle").textContent =
                tugas.sudah_mengumpulkan ? "Kirim Ulang Tugas" : "Kumpulkan Tugas";
            submitSection.hidden = false;
        } else {
            submitSection.hidden = true;
        }

        document.getElementById("detailSection").hidden = false;
    }

    async function loadTugas() {
        const url = API.tugasList + (idKelasUrl > 0 ? "?id_kelas=" + encodeURIComponent(idKelasUrl) : "");
        const result = await apiRequest(url, { method: "GET" });
        const rows = result.data || [];
        const tugas = rows.find(function (row) {
            return row.id_tugas === idTugas;
        });

        if (!tugas) throw new Error("Tugas tidak ditemukan.");

        render(tugas);
    }

    async function hapusFile(button) {
        if (!window.confirm("Hapus file pengumpulan untuk tugas ini?\nJawaban teks tetap tersimpan.")) return;

        SiswaCommon.hideMessage(MESSAGE_ID);
        button.disabled = true;
        button.textContent = "...";

        try {
            const result = await apiRequest(API.deleteFile, { method: "POST", body: { id_tugas: idTugas } });
            await loadTugas();
            SiswaCommon.showMessage(MESSAGE_ID, result.message || "File berhasil dihapus.", "success");
        } catch (error) {
            SiswaCommon.showMessage(MESSAGE_ID, error.message || "Gagal menghapus file.", "error");
            button.disabled = false;
            button.textContent = "Hapus file";
        }
    }

    async function submitPengumpulan(event) {
        event.preventDefault();
        SiswaCommon.hideMessage(UPLOAD_MESSAGE_ID);

        const teks = document.getElementById("uploadTeks").value.trim();
        const fileInput = document.getElementById("uploadFile");
        const button = document.getElementById("uploadTugasButton");
        const hasFile = fileInput.files && fileInput.files.length > 0;

        if (!teks && !hasFile) {
            SiswaCommon.showMessage(UPLOAD_MESSAGE_ID, "Isi jawaban teks atau unggah file (minimal salah satu).", "error");
            return;
        }

        const formData = new FormData();
        formData.append("id_tugas", idTugas);
        if (teks) formData.append("teks", teks);
        if (hasFile) formData.append("file", fileInput.files[0]);

        SiswaCommon.setButtonLoading(button, true, "Kirim Pengumpulan");

        try {
            const response = await fetch(API.upload, { method: "POST", credentials: "include", body: formData });

            let data;
            try {
                data = await response.json();
            } catch (parseError) {
                throw new Error("Respons server tidak valid.");
            }

            if (!response.ok || !data.success) {
                throw new Error((data && data.message) || "Gagal mengumpulkan tugas.");
            }

            form.reset();
            await loadTugas();
            SiswaCommon.showMessage(MESSAGE_ID, data.message || "Tugas berhasil dikumpulkan.", "success");
        } catch (error) {
            SiswaCommon.showMessage(UPLOAD_MESSAGE_ID, error.message || "Gagal mengumpulkan tugas.", "error");
        } finally {
            SiswaCommon.setButtonLoading(button, false, "Kirim Pengumpulan");
        }
    }

    async function start() {
        if (!idTugas || idTugas < 1) {
            SiswaCommon.showMessage(MESSAGE_ID, "Tugas tidak valid. Buka dari daftar aktivitas kelas.", "error");
            return;
        }

        form.addEventListener("submit", submitPengumpulan);

        try {
            await loadTugas();
        } catch (error) {
            SiswaCommon.showMessage(MESSAGE_ID, error.message || "Gagal memuat tugas.", "error");
        }
    }

    start();
})();