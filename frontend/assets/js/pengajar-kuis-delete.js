/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kuis-delete.js
GUNA FILE   : Hanya menghapus kuis (halaman Kuis pengajar).
              POST ../../backend/api/kuis/delete.php
              Body: { id_kuis }
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API_DELETE = "../../backend/api/kuis/delete.php";

    document.addEventListener("click", async function (event) {
        const btn = event.target.closest(".btn-hapus-kuis");
        if (!btn) return;

        const idKuis = parseInt(btn.getAttribute("data-id"), 10);
        if (!idKuis) return;

        // Judul diambil dari sel tabel (kolom ke-2) agar ID tidak perlu ditampilkan.
        const judul = btn.closest("tr").children[1].textContent;

        if (!window.confirm(
            "Hapus kuis \"" + judul + "\"?\n" +
            "Semua soal di dalamnya ikut terhapus dan tindakan ini tidak dapat dibatalkan."
        )) {
            return;
        }

        PengajarCommon.hideMessage("kuisListMessage");
        btn.disabled = true;
        btn.textContent = "...";

        try {
            const result = await apiRequest(API_DELETE, {
                method: "POST",
                body: { id_kuis: idKuis }
            });
            PengajarCommon.showMessage(
                "kuisListMessage",
                result.message || "Kuis berhasil dihapus.",
                "success"
            );
            if (typeof window.pengajarRefreshKuisList === "function") {
                window.pengajarRefreshKuisList();
            }
        } catch (error) {
            PengajarCommon.showMessage(
                "kuisListMessage",
                error.message || "Gagal menghapus kuis.",
                "error"
            );
            btn.disabled = false;
            btn.textContent = "Hapus";
        }
    });
})();