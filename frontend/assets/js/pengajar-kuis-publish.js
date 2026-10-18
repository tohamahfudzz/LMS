/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kuis-publish.js
GUNA FILE   : Hanya menerbitkan kuis (draft → terbit).
              POST ../../backend/api/kuis/publish.php
              Body: { id_kuis }
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API_PUBLISH = "../../backend/api/kuis/publish.php";

    document.addEventListener("click", async function (event) {
        const btn = event.target.closest(".btn-publish-kuis");
        if (!btn) return;

        const idKuis = parseInt(btn.getAttribute("data-id"), 10);
        if (!idKuis) return;

        if (!window.confirm("Terbitkan kuis ID " + idKuis + "?\nPastikan soal sudah lengkap.")) {
            return;
        }

        PengajarCommon.hideMessage("kuisListMessage");
        btn.disabled = true;
        btn.textContent = "...";

        try {
            const result = await apiRequest(API_PUBLISH, {
                method: "POST",
                body: { id_kuis: idKuis }
            });
            PengajarCommon.showMessage(
                "kuisListMessage",
                result.message || "Kuis berhasil diterbitkan.",
                "success"
            );
            if (typeof window.pengajarRefreshKuisList === "function") {
                window.pengajarRefreshKuisList();
            }
        } catch (error) {
            PengajarCommon.showMessage(
                "kuisListMessage",
                error.message || "Gagal menerbitkan kuis.",
                "error"
            );
            btn.disabled = false;
            btn.textContent = "Publish";
        }
    });
})();