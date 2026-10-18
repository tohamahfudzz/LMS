/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-absensi.js
GUNA FILE   : Hanya satu tugas — siswa menandai kehadiran (hadir).
              Endpoint: POST ../../backend/api/kehadiran/hadir.php
              Body    : { "id_absensi": number }
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const HADIR_ENDPOINT = "../../backend/api/kehadiran/hadir.php";
    const form = document.getElementById("hadirForm");
    if (!form) return;

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        SiswaCommon.hideMessage("hadirMessage");

        const idAbsensi = parseInt(document.getElementById("idAbsensi").value, 10);
        const btn = document.getElementById("hadirButton");

        if (!idAbsensi || idAbsensi < 1) {
            SiswaCommon.showMessage("hadirMessage", "ID absensi wajib diisi dengan angka valid.", "error");
            return;
        }

        SiswaCommon.setButtonLoading(btn, true, "Saya Hadir");

        try {
            const result = await apiRequest(HADIR_ENDPOINT, {
                method: "POST",
                body: { id_absensi: idAbsensi }
            });
            SiswaCommon.showMessage(
                "hadirMessage",
                result.message || "Kehadiran berhasil dicatat.",
                "success"
            );
        } catch (error) {
            SiswaCommon.showMessage(
                "hadirMessage",
                error.message || "Gagal mencatat kehadiran.",
                "error"
            );
        } finally {
            SiswaCommon.setButtonLoading(btn, false, "Saya Hadir");
        }
    });
})();