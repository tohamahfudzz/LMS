/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kuis-detail.js
GUNA FILE   : Detail satu kuis (frontend/siswa/kuis-detail.html).
                GET ../../backend/api/kuis/tersedia.php
                    (kuis dicari berdasarkan id_kuis di URL)
              Kuis baru dimulai setelah siswa menekan "Kerjakan" dan menyetujui
              konfirmasi; setelah itu halaman dialihkan ke kuis-kerjakan.html.
              Bergantung pada: siswa-common.js.
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API_TERSEDIA = "../../backend/api/kuis/tersedia.php";
    const MESSAGE_ID = "pageMessage";

    const KETERSEDIAAN_LABEL = {
        dibuka: "Dibuka",
        belum_dimulai: "Belum dimulai",
        berakhir: "Berakhir",
        ditutup: "Ditutup"
    };

    const PENGERJAAN_LABEL = {
        belum_dikerjakan: "Belum dikerjakan",
        sedang_dikerjakan: "Sedang dikerjakan",
        menunggu_penilaian: "Menunggu penilaian",
        selesai: "Selesai"
    };

    const params = new URLSearchParams(window.location.search);
    const idKuis = parseInt(params.get("id_kuis"), 10);
    const idKelasUrl = parseInt(params.get("id_kelas"), 10);

    function formatWaktu(value) {
        if (!value) return "-";
        const date = new Date(String(value).replace(" ", "T"));
        if (isNaN(date.getTime())) return value;

        return date.toLocaleString("id-ID", {
            day: "numeric", month: "short", year: "numeric",
            hour: "2-digit", minute: "2-digit"
        });
    }

    function buildKerjakanUrl(kuis) {
        return "kuis-kerjakan.html?id_kuis=" + encodeURIComponent(kuis.id_kuis) +
            "&id_kelas=" + encodeURIComponent(kuis.id_kelas);
    }

    function renderAksi(kuis) {
        const aksiEl = document.getElementById("dAksi");
        aksiEl.textContent = "";

        if (kuis.status_pengerjaan === "sedang_dikerjakan") {
            const link = document.createElement("a");
            link.href = buildKerjakanUrl(kuis);
            link.textContent = "Lanjutkan";
            aksiEl.appendChild(link);
            return;
        }

        if (kuis.status_pengerjaan === "belum_dikerjakan" && kuis.ketersediaan === "dibuka") {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = "Kerjakan";
            button.addEventListener("click", function () {
                // Waktu langsung berjalan dan kuis hanya satu kali, jadi minta konfirmasi dulu.
                if (window.confirm("Kuis hanya bisa dikerjakan satu kali dan waktu langsung berjalan.\nMulai sekarang?")) {
                    window.location.href = buildKerjakanUrl(kuis);
                }
            });
            aksiEl.appendChild(button);
            return;
        }

        if (kuis.status_pengerjaan === "belum_dikerjakan") {
            const alasan = {
                belum_dimulai: "Kuis belum dibuka.",
                berakhir: "Waktu kuis sudah berakhir.",
                ditutup: "Kuis sudah ditutup."
            };
            aksiEl.textContent = alasan[kuis.ketersediaan] || "-";
            return;
        }

        aksiEl.textContent = "-";
    }

    function render(kuis) {
        document.getElementById("backToKelasLink").href =
            "kelas-detail.html?id_kelas=" + encodeURIComponent(kuis.id_kelas) + "&tab=aktivitas";

        document.getElementById("dJudul").textContent = kuis.judul;
        document.getElementById("dKelas").textContent = kuis.nama_kelas || "-";
        document.getElementById("dDibuat").textContent = formatWaktu(kuis.waktu_dibuat);
        document.getElementById("dDeskripsi").textContent = kuis.deskripsi || "-";
        document.getElementById("dDurasi").textContent = kuis.durasi_menit + " menit";
        document.getElementById("dSoal").textContent = kuis.jumlah_soal + " soal (total " + kuis.total_poin + " poin)";
        document.getElementById("dMulai").textContent = formatWaktu(kuis.waktu_mulai);
        document.getElementById("dSelesai").textContent = formatWaktu(kuis.waktu_selesai);
        document.getElementById("dKetersediaan").textContent =
            KETERSEDIAAN_LABEL[kuis.ketersediaan] || kuis.ketersediaan;
        document.getElementById("dStatus").textContent =
            PENGERJAAN_LABEL[kuis.status_pengerjaan] || kuis.status_pengerjaan;
        document.getElementById("dNilai").textContent =
            kuis.nilai_total === null || kuis.nilai_total === undefined ? "-" : kuis.nilai_total;
        document.title = kuis.judul + " - Siswa LMS";

        renderAksi(kuis);
        document.getElementById("detailSection").hidden = false;
    }

    async function start() {
        if (!idKuis || idKuis < 1) {
            SiswaCommon.showMessage(MESSAGE_ID, "Kuis tidak valid. Buka dari daftar aktivitas kelas.", "error");
            return;
        }

        try {
            const result = await apiRequest(API_TERSEDIA, { method: "GET" });
            const rows = result.data || [];
            const kuis = rows.find(function (row) {
                return row.id_kuis === idKuis &&
                    (!(idKelasUrl > 0) || row.id_kelas === idKelasUrl);
            });

            if (!kuis) {
                SiswaCommon.showMessage(MESSAGE_ID, "Kuis tidak ditemukan atau belum tersedia.", "error");
                return;
            }

            render(kuis);
        } catch (error) {
            SiswaCommon.showMessage(MESSAGE_ID, error.message || "Gagal memuat kuis.", "error");
        }
    }

    start();
})();