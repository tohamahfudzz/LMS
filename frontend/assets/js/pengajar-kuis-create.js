/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kuis-create.js
GUNA FILE   : Hanya membuat kuis baru (status draft).
              POST ../../backend/api/kuis/create.php
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API_CREATE = "../../backend/api/kuis/create.php";
    const form = document.getElementById("createKuisForm");
    if (!form) return;

    async function initKelasSelect() {
        const kelasList = await PengajarCommon.ensureKelasLoaded();
        PengajarCommon.fillSelect(
            "kuisKelas",
            kelasList,
            "id_kelas",
            function (item) { return item.nama_kelas; },
            null
        );
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        PengajarCommon.hideMessage("createKuisMessage");

        const idKelas = parseInt(document.getElementById("kuisKelas").value, 10);
        const judul = document.getElementById("kuisJudul").value.trim();
        const deskripsi = document.getElementById("kuisDeskripsi").value.trim();
        const durasi = parseInt(document.getElementById("kuisDurasi").value, 10);
        const waktuMulaiRaw = document.getElementById("kuisWaktuMulai").value;
        const waktuSelesaiRaw = document.getElementById("kuisWaktuSelesai").value;
        const btn = document.getElementById("createKuisButton");

        if (!idKelas) {
            PengajarCommon.showMessage("createKuisMessage", "Pilih kelas.", "error");
            return;
        }
        if (!judul) {
            PengajarCommon.showMessage("createKuisMessage", "Judul wajib diisi.", "error");
            return;
        }
        if (!durasi || durasi < 1) {
            PengajarCommon.showMessage("createKuisMessage", "Durasi wajib diisi (minimal 1 menit).", "error");
            return;
        }

        const body = {
            id_kelas: idKelas,
            judul: judul,
            durasi_menit: durasi
        };
        if (deskripsi) body.deskripsi = deskripsi;
        if (waktuMulaiRaw) {
            body.waktu_mulai = PengajarCommon.formatDateTimeLocal(waktuMulaiRaw);
        }
        if (waktuSelesaiRaw) {
            body.waktu_selesai = PengajarCommon.formatDateTimeLocal(waktuSelesaiRaw);
        }

        PengajarCommon.setButtonLoading(btn, true, "Simpan sebagai Draft");

        try {
            const result = await apiRequest(API_CREATE, {
                method: "POST",
                body: body
            });

            const idKuis = result.data && result.data.id_kuis ? result.data.id_kuis : null;
            let msg = result.message || "Kuis berhasil dibuat sebagai draft.";
            if (idKuis) {
                msg += " ID: " + idKuis + ". Lanjut kelola soal.";
            }
            PengajarCommon.showMessage("createKuisMessage", msg, "success");
            form.reset();
            initKelasSelect();

            if (typeof window.pengajarRefreshKuisList === "function") {
                window.pengajarRefreshKuisList();
            }

            if (idKuis) {
                setTimeout(function () {
                    window.location.href = "kuis-soal.html?id_kuis=" + encodeURIComponent(idKuis);
                }, 800);
            }
        } catch (error) {
            PengajarCommon.showMessage(
                "createKuisMessage",
                error.message || "Gagal membuat kuis.",
                "error"
            );
        } finally {
            PengajarCommon.setButtonLoading(btn, false, "Simpan sebagai Draft");
        }
    });

    initKelasSelect();
})();