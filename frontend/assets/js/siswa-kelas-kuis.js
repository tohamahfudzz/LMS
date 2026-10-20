/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-kuis.js
GUNA FILE   : Tab Kuis pada halaman kelas siswa (frontend/siswa/kelas-detail.html).
              - Daftar kuis : GET ../../backend/api/kuis/tersedia.php
                              (mengembalikan kuis semua kelas siswa; di sini disaring
                              hanya kuis dari kelas yang sedang dibuka)
              Daftar kuis ada di dalam kelas, tetapi mengerjakan kuis dibuka di halaman
              tersendiri: kuis-kerjakan.html?id_kuis=..&id_kelas=.. (id_kelas dipakai
              untuk tombol "Kembali ke kelas").
              Kuis hanya bisa dikerjakan satu kali:
                - belum dikerjakan + dibuka -> tombol "Kerjakan" (ada konfirmasi,
                                               karena waktu langsung berjalan)
                - sedang dikerjakan         -> tombol "Lanjutkan"
                - menunggu penilaian/selesai-> tanpa tombol (nilai tampil bila sudah ada)
              Bergantung pada: siswa-common.js dan siswa-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API_TERSEDIA = "../../backend/api/kuis/tersedia.php";

    const tbody = document.getElementById("kuisTableBody");

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

    function buildKuisUrl(idKuis) {
        return "kuis-kerjakan.html?id_kuis=" + encodeURIComponent(idKuis) +
            "&id_kelas=" + encodeURIComponent(idKelas);
    }

    function buildActionHtml(item) {
        const idKuis = parseInt(item.id_kuis, 10);

        if (item.status_pengerjaan === "sedang_dikerjakan") {
            return "<a href=\"" + buildKuisUrl(idKuis) + "\">Lanjutkan</a>";
        }
        if (item.status_pengerjaan === "belum_dikerjakan" && item.ketersediaan === "dibuka") {
            return "<button type=\"button\" class=\"btn-kerjakan-kuis\" data-id=\"" + idKuis + "\">Kerjakan</button>";
        }
        return "-";
    }

    async function loadKuisList() {
        tbody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";
        SiswaCommon.hideMessage("kuisListMessage");

        try {
            const result = await apiRequest(API_TERSEDIA, { method: "GET" });
            const rows = (result.data || []).filter(function (item) {
                return parseInt(item.id_kelas, 10) === idKelas;
            });

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"7\">Belum ada kuis di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                const nilai = item.nilai_total === null || item.nilai_total === undefined ? "-" : item.nilai_total;

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.durasi_menit) + " menit</td>" +
                    "<td>" + SiswaCommon.escapeHtml(KETERSEDIAAN_LABEL[item.ketersediaan] || item.ketersediaan) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(PENGERJAAN_LABEL[item.status_pengerjaan] || item.status_pengerjaan) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(nilai) + "</td>" +
                    "<td>" + buildActionHtml(item) + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage("kuisListMessage", error.message || "Gagal mengambil daftar kuis.", "error");
        }
    }

    tbody.addEventListener("click", function (event) {
        const button = event.target.closest(".btn-kerjakan-kuis");
        if (!button) return;

        const idKuis = parseInt(button.getAttribute("data-id"), 10);
        if (!idKuis) return;

        // Kuis hanya bisa dikerjakan sekali dan timer langsung jalan, jadi minta konfirmasi dulu.
        if (window.confirm("Kuis hanya bisa dikerjakan satu kali dan waktu langsung berjalan.\nMulai sekarang?")) {
            window.location.href = buildKuisUrl(idKuis);
        }
    });

    KelasPage.registerTab("kuis", loadKuisList);
})();