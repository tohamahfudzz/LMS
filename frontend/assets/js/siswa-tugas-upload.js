/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-tugas-upload.js
GUNA FILE   : Hanya satu tugas — upload/kumpul jawaban tugas siswa.
              Endpoint: POST ../../backend/api/pengumpulan_tugas/upload.php
              Format  : multipart/form-data (id_tugas, teks?, file?)
              Tidak mengurus list tugas.
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const UPLOAD_ENDPOINT = "../../backend/api/pengumpulan_tugas/upload.php";
    const form = document.getElementById("uploadTugasForm");
    if (!form) return;

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        SiswaCommon.hideMessage("uploadMessage");

        const idTugas = parseInt(document.getElementById("uploadIdTugas").value, 10);
        const teks = document.getElementById("uploadTeks").value.trim();
        const fileInput = document.getElementById("uploadFile");
        const btn = document.getElementById("uploadTugasButton");

        if (!idTugas || idTugas < 1) {
            SiswaCommon.showMessage("uploadMessage", "ID tugas wajib diisi.", "error");
            return;
        }

        const hasFile = fileInput.files && fileInput.files.length > 0;
        if (!teks && !hasFile) {
            SiswaCommon.showMessage(
                "uploadMessage",
                "Isi jawaban teks atau unggah file (minimal salah satu).",
                "error"
            );
            return;
        }

        const formData = new FormData();
        formData.append("id_tugas", idTugas);
        if (teks) formData.append("teks", teks);
        if (hasFile) formData.append("file", fileInput.files[0]);

        SiswaCommon.setButtonLoading(btn, true, "Kirim Pengumpulan");

        try {
            const response = await fetch(UPLOAD_ENDPOINT, {
                method: "POST",
                credentials: "include",
                body: formData
            });

            let data;
            try {
                data = await response.json();
            } catch (e) {
                throw new Error("Respons server tidak valid.");
            }

            if (!response.ok || !data.success) {
                throw new Error((data && data.message) || "Gagal mengumpulkan tugas.");
            }

            SiswaCommon.showMessage(
                "uploadMessage",
                data.message || "Tugas berhasil dikumpulkan.",
                "success"
            );
            form.reset();

            if (typeof window.siswaRefreshTugasList === "function") {
                window.siswaRefreshTugasList();
            }
        } catch (error) {
            SiswaCommon.showMessage(
                "uploadMessage",
                error.message || "Gagal mengumpulkan tugas.",
                "error"
            );
        } finally {
            SiswaCommon.setButtonLoading(btn, false, "Kirim Pengumpulan");
        }
    });
})();