/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kuis-soal-create.js
GUNA FILE   : Hanya menambah soal ke kuis draft.
              POST ../../backend/api/soal_kuis/create.php
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API_CREATE = "../../backend/api/soal_kuis/create.php";
    const form = document.getElementById("createSoalForm");
    if (!form) return;

    const idKuis = window.pengajarCurrentIdKuis ||
        parseInt(new URLSearchParams(window.location.search).get("id_kuis"), 10);

    const tipeSelect = document.getElementById("tipeSoal");
    const blokPg = document.getElementById("blokPilihanGanda");

    function togglePilihan() {
        if (!blokPg || !tipeSelect) return;
        blokPg.style.display = tipeSelect.value === "pilihan_ganda" ? "block" : "none";
    }

    if (tipeSelect) {
        tipeSelect.addEventListener("change", togglePilihan);
        togglePilihan();
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        PengajarCommon.hideMessage("createSoalMessage");

        if (!idKuis || idKuis < 1) {
            PengajarCommon.showMessage("createSoalMessage", "ID kuis tidak valid.", "error");
            return;
        }

        const tipe = document.getElementById("tipeSoal").value;
        const soal = document.getElementById("teksSoal").value.trim();
        const poin = parseFloat(document.getElementById("poinSoal").value) || 1;
        const btn = document.getElementById("createSoalButton");

        if (!soal) {
            PengajarCommon.showMessage("createSoalMessage", "Teks soal wajib diisi.", "error");
            return;
        }

        const body = {
            id_kuis: idKuis,
            tipe_soal: tipe,
            soal: soal,
            poin: poin
        };

        if (tipe === "pilihan_ganda") {
            const a = document.getElementById("pilihanA").value.trim();
            const b = document.getElementById("pilihanB").value.trim();
            const c = document.getElementById("pilihanC").value.trim();
            const d = document.getElementById("pilihanD").value.trim();
            const kunci = document.getElementById("kunciJawaban").value;

            if (!a || !b || !c || !d) {
                PengajarCommon.showMessage(
                    "createSoalMessage",
                    "Semua pilihan A–D wajib diisi untuk pilihan ganda.",
                    "error"
                );
                return;
            }

            body.pilihan_a = a;
            body.pilihan_b = b;
            body.pilihan_c = c;
            body.pilihan_d = d;
            body.kunci_jawaban = kunci;
        }

        PengajarCommon.setButtonLoading(btn, true, "Tambah Soal");

        try {
            const result = await apiRequest(API_CREATE, {
                method: "POST",
                body: body
            });
            PengajarCommon.showMessage(
                "createSoalMessage",
                result.message || "Soal berhasil ditambahkan.",
                "success"
            );
            form.reset();
            togglePilihan();
            if (typeof window.pengajarRefreshSoalList === "function") {
                window.pengajarRefreshSoalList();
            }
        } catch (error) {
            PengajarCommon.showMessage(
                "createSoalMessage",
                error.message || "Gagal menambah soal.",
                "error"
            );
        } finally {
            PengajarCommon.setButtonLoading(btn, false, "Tambah Soal");
        }
    });
})();