/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-tugas-delete.js
GUNA FILE   : Hanya satu tugas — menghapus FILE lampiran pengumpulan tugas.
              Teks jawaban & baris pengumpulan tetap ada (sesuai API).
              Endpoint: POST ../../backend/api/pengumpulan_tugas/delete.php
              Body    : { "id_tugas": number }  (JSON)
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const DELETE_ENDPOINT = "../../backend/api/pengumpulan_tugas/delete.php";

    /**
     * Dipanggil dari tombol di tabel list (data-id = id_tugas).
     */
    async function deleteSubmissionFile(idTugas, buttonEl) {
        if (!idTugas || idTugas < 1) {
            SiswaCommon.showMessage(
                "tugasListMessage",
                "ID tugas tidak valid.",
                "error"
            );
            return;
        }

        if (!window.confirm(
            "Hapus file lampiran pengumpulan untuk tugas ID " + idTugas + "?\n" +
            "Jawaban teks tetap tersimpan."
        )) {
            return;
        }

        SiswaCommon.hideMessage("tugasListMessage");

        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.textContent = "...";
        }

        try {
            const result = await apiRequest(DELETE_ENDPOINT, {
                method: "POST",
                body: { id_tugas: idTugas }
            });

            SiswaCommon.showMessage(
                "tugasListMessage",
                result.message || "File lampiran berhasil dihapus.",
                "success"
            );

            if (typeof window.siswaRefreshTugasList === "function") {
                window.siswaRefreshTugasList();
            }
        } catch (error) {
            SiswaCommon.showMessage(
                "tugasListMessage",
                error.message || "Gagal menghapus file lampiran.",
                "error"
            );
            if (buttonEl) {
                buttonEl.disabled = false;
                buttonEl.textContent = "Hapus file";
            }
        }
    }

    // Event delegation: tombol .btn-hapus-file-tugas di tabel
    document.addEventListener("click", function (event) {
        const btn = event.target.closest(".btn-hapus-file-tugas");
        if (!btn) return;

        const idTugas = parseInt(btn.getAttribute("data-id"), 10);
        deleteSubmissionFile(idTugas, btn);
    });
})();