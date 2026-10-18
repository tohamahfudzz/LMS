/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kuis-soal-delete.js
GUNA FILE   : Hanya menghapus satu soal kuis.
              POST ../../backend/api/soal_kuis/delete.php
              Body: { id_soal }
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API_DELETE = "../../backend/api/soal_kuis/delete.php";

    document.addEventListener("click", async function (event) {
        const btn = event.target.closest(".btn-hapus-soal");
        if (!btn) return;

        const idSoal = parseInt(btn.getAttribute("data-id"), 10);
        if (!idSoal) return;

        if (!window.confirm("Hapus soal ID " + idSoal + "?")) return;

        PengajarCommon.hideMessage("soalListMessage");
        btn.disabled = true;
        btn.textContent = "...";

        try {
            const result = await apiRequest(API_DELETE, {
                method: "POST",
                body: { id_soal: idSoal }
            });
            PengajarCommon.showMessage(
                "soalListMessage",
                result.message || "Soal berhasil dihapus.",
                "success"
            );
            if (typeof window.pengajarRefreshSoalList === "function") {
                window.pengajarRefreshSoalList();
            }
        } catch (error) {
            PengajarCommon.showMessage(
                "soalListMessage",
                error.message || "Gagal menghapus soal.",
                "error"
            );
            btn.disabled = false;
            btn.textContent = "Hapus";
        }
    });
})();