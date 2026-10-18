/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kuis-submit.js
GUNA FILE   : Hanya mengumpulkan jawaban kuis.
              Endpoint: POST ../../backend/api/percobaan_kuis/submit.php
              Body: { id_percobaan, jawaban: [ { id_soal, jawaban } ] }
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const SUBMIT_ENDPOINT = "../../backend/api/percobaan_kuis/submit.php";
    const form = document.getElementById("kuisForm");
    if (!form) return;

    function collectJawaban() {
        const jawaban = [];
        const fieldsets = form.querySelectorAll("fieldset[data-id-soal]");

        fieldsets.forEach(function (fs) {
            const idSoal = parseInt(fs.getAttribute("data-id-soal"), 10);
            const tipe = fs.getAttribute("data-tipe") || "";
            let teks = null;

            if (tipe === "pilihan_ganda") {
                const checked = fs.querySelector('input[type="radio"]:checked');
                teks = checked ? checked.value : null;
            } else {
                const ta = fs.querySelector("textarea");
                teks = ta ? ta.value.trim() : null;
                if (teks === "") teks = null;
            }

            jawaban.push({
                id_soal: idSoal,
                jawaban: teks
            });
        });

        return jawaban;
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        SiswaCommon.hideMessage("kuisMessage");

        const idPercobaan = parseInt(document.getElementById("idPercobaan").value, 10);
        const btn = document.getElementById("submitKuisButton");

        if (!idPercobaan || idPercobaan < 1) {
            SiswaCommon.showMessage(
                "kuisMessage",
                "Percobaan belum siap. Muat ulang halaman atau mulai kuis dari daftar.",
                "error"
            );
            return;
        }

        if (!window.confirm("Kumpulkan jawaban sekarang? Setelah dikumpulkan biasanya tidak bisa diubah.")) {
            return;
        }

        const jawaban = collectJawaban();
        SiswaCommon.setButtonLoading(btn, true, "Kumpulkan Jawaban");

        try {
            const result = await apiRequest(SUBMIT_ENDPOINT, {
                method: "POST",
                body: {
                    id_percobaan: idPercobaan,
                    jawaban: jawaban
                }
            });

            const data = result.data || {};
            let msg = result.message || "Jawaban berhasil dikumpulkan.";
            if (data.nilai_akhir !== undefined && data.nilai_akhir !== null) {
                msg += " Nilai: " + data.nilai_akhir;
            }

            SiswaCommon.showMessage("kuisMessage", msg, "success");
            if (btn) btn.disabled = true;

            // Nonaktifkan form
            form.querySelectorAll("input, textarea, button").forEach(function (el) {
                el.disabled = true;
            });
        } catch (error) {
            SiswaCommon.showMessage(
                "kuisMessage",
                error.message || "Gagal mengumpulkan jawaban.",
                "error"
            );
            SiswaCommon.setButtonLoading(btn, false, "Kumpulkan Jawaban");
        }
    });
})();