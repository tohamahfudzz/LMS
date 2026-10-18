/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kuis-mulai.js
GUNA FILE   : Hanya memulai / melanjutkan kuis dan menampilkan soal + timer.
              POST kuis/mulai.php  atau  GET percobaan_kuis/soal.php?id_kuis=
              Tidak mengurus submit jawaban.
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API = {
        mulai: "../../backend/api/kuis/mulai.php",
        soal:  "../../backend/api/percobaan_kuis/soal.php"
    };

    const params = new URLSearchParams(window.location.search);
    const idKuis = parseInt(params.get("id_kuis"), 10);

    const container = document.getElementById("soalContainer");
    const judulEl = document.getElementById("kuisJudul");
    const timerEl = document.getElementById("kuisTimer");
    const idPercobaanInput = document.getElementById("idPercobaan");

    if (!idKuis || idKuis < 1) {
        SiswaCommon.showMessage("kuisMessage", "ID kuis tidak valid. Kembali ke daftar kuis.", "error");
        if (container) container.innerHTML = "";
        return;
    }

    let timerInterval = null;

    function startTimer(sisaDetik) {
        if (!timerEl) return;
        let sisa = parseInt(sisaDetik, 10);
        if (isNaN(sisa) || sisa < 0) sisa = 0;

        function tick() {
            const m = Math.floor(sisa / 60);
            const s = sisa % 60;
            timerEl.textContent = "Sisa waktu: " + m + ":" + (s < 10 ? "0" : "") + s;
            if (sisa <= 0) {
                clearInterval(timerInterval);
                timerEl.textContent = "Waktu habis";
                SiswaCommon.showMessage(
                    "kuisMessage",
                    "Waktu pengerjaan habis. Kumpulkan jawaban sekarang.",
                    "error"
                );
                return;
            }
            sisa -= 1;
        }

        tick();
        timerInterval = setInterval(tick, 1000);
    }

    function renderSoal(soalList) {
        if (!container) return;

        if (!soalList || soalList.length === 0) {
            container.innerHTML = "<p>Tidak ada soal.</p>";
            return;
        }

        container.innerHTML = soalList.map(function (soal, index) {
            const idSoal = soal.id_soal;
            const tipe = soal.tipe_soal || "";
            let body = "";

            if (tipe === "pilihan_ganda" && soal.pilihan) {
                const keys = ["A", "B", "C", "D"];
                body = keys.map(function (k) {
                    const teks = soal.pilihan[k] || soal["pilihan_" + k.toLowerCase()] || "";
                    if (!teks) return "";
                    const name = "soal_" + idSoal;
                    const inputId = name + "_" + k;
                    return (
                        "<div>" +
                        '<label for="' + inputId + '">' +
                        '<input type="radio" name="' + name + '" id="' + inputId +
                        '" value="' + k + '"> ' +
                        k + ". " + SiswaCommon.escapeHtml(teks) +
                        "</label></div>"
                    );
                }).join("");
            } else {
                // isian / default
                body =
                    '<textarea name="soal_' + idSoal + '" id="soal_' + idSoal +
                    '" rows="3" maxlength="5000" data-id-soal="' + idSoal +
                    '" placeholder="Tulis jawaban..."></textarea>';
            }

            return (
                '<fieldset data-id-soal="' + idSoal + '" data-tipe="' +
                SiswaCommon.escapeHtml(tipe) + '">' +
                "<legend>Soal " + (index + 1) + " (" + SiswaCommon.escapeHtml(tipe) +
                ", " + SiswaCommon.escapeHtml(soal.poin) + " poin)</legend>" +
                "<p>" + SiswaCommon.escapeHtml(soal.soal) + "</p>" +
                body +
                "</fieldset><br>"
            );
        }).join("");
    }

    function applyPayload(data) {
        if (judulEl && data.judul) {
            judulEl.textContent = data.judul;
        }
        if (idPercobaanInput && data.id_percobaan) {
            idPercobaanInput.value = data.id_percobaan;
        }
        renderSoal(data.soal || []);
        if (data.sisa_detik !== undefined) {
            startTimer(data.sisa_detik);
        }
    }

    async function init() {
        SiswaCommon.hideMessage("kuisMessage");

        // Coba lanjutkan percobaan yang sedang berjalan
        try {
            const resume = await apiRequest(
                API.soal + "?id_kuis=" + encodeURIComponent(idKuis),
                { method: "GET" }
            );
            applyPayload(resume.data || resume);
            SiswaCommon.showMessage("kuisMessage", "Melanjutkan percobaan yang sedang berjalan.", "success");
            return;
        } catch (resumeError) {
            // 404 = belum mulai → lanjut ke mulai
            // error lain (habis waktu, dll.) tampilkan pesan
            if (resumeError.status && resumeError.status !== 404) {
                SiswaCommon.showMessage(
                    "kuisMessage",
                    resumeError.message || "Tidak dapat memuat soal.",
                    "error"
                );
                if (container) container.innerHTML = "";
                // Jika 409 sudah selesai, jangan auto-mulai
                if (resumeError.status === 409 || resumeError.status === 403) {
                    return;
                }
            }
        }

        // Mulai baru
        try {
            const result = await apiRequest(API.mulai, {
                method: "POST",
                body: { id_kuis: idKuis }
            });
            applyPayload(result.data || result);
            SiswaCommon.showMessage("kuisMessage", result.message || "Kuis dimulai.", "success");
        } catch (error) {
            SiswaCommon.showMessage(
                "kuisMessage",
                error.message || "Gagal memulai kuis.",
                "error"
            );
            if (container) container.innerHTML = "";
        }
    }

    init();
})();