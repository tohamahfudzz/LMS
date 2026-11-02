/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-feed.js
GUNA FILE   : Feed aktivitas kelas siswa (frontend/siswa/kelas-detail.html).
              Menggabungkan materi, tugas, dan kuis kelas ini menjadi satu daftar
              card, diurutkan dari yang terbaru ke terlama berdasarkan waktu upload.
                GET ../../backend/api/siswa/materi/list.php?id_kelas=
                GET ../../backend/api/siswa/tugas/list.php?id_kelas=
                GET ../../backend/api/kuis/tersedia.php  (disaring ke kelas ini)
              Setiap card membuka halamannya sendiri:
                materi-detail.html, tugas-detail.html, kuis-detail.html
              Bergantung pada: siswa-common.js dan siswa-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        materiList: "../../backend/api/siswa/materi/list.php",
        tugasList: "../../backend/api/siswa/tugas/list.php",
        kuisTersedia: "../../backend/api/kuis/tersedia.php"
    };

    const MESSAGE_ID = "feedMessage";
    const feedEl = document.getElementById("feedList");
    const esc = SiswaCommon.escapeHtml;
    const kelasQuery = "id_kelas=" + encodeURIComponent(idKelas);

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

    const JENIS_LABEL = { materi: "Materi", tugas: "Tugas", kuis: "Kuis" };

    // "2026-11-02 14:30:00" (jam server) -> Date lokal.
    function parseWaktu(value) {
        if (!value) return null;
        const date = new Date(String(value).replace(" ", "T"));
        return isNaN(date.getTime()) ? null : date;
    }

    function formatWaktu(value) {
        const date = parseWaktu(value);
        if (!date) return "-";

        return date.toLocaleString("id-ID", {
            day: "numeric",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    }

    async function fetchMateri() {
        const result = await apiRequest(API.materiList + "?" + kelasQuery, { method: "GET" });
        return result.data || [];
    }

    async function fetchTugas() {
        const result = await apiRequest(API.tugasList + "?" + kelasQuery, { method: "GET" });
        return result.data || [];
    }

    // tersedia.php mengembalikan kuis semua kelas siswa, jadi disaring di sini.
    async function fetchKuis() {
        const result = await apiRequest(API.kuisTersedia, { method: "GET" });

        return (result.data || []).filter(function (row) {
            return parseInt(row.id_kelas, 10) === idKelas;
        });
    }

    function toItems(materiRows, tugasRows, kuisRows) {
        const items = [];

        materiRows.forEach(function (row) {
            items.push({
                jenis: "materi",
                judul: row.judul,
                waktu: row.waktu_upload,
                labelWaktu: "Diunggah",
                href: "materi-detail.html?id_materi=" + encodeURIComponent(row.id_materi) + "&" + kelasQuery,
                info: ["Tipe: " + (row.tipe || "-")]
            });
        });

        tugasRows.forEach(function (row) {
            items.push({
                jenis: "tugas",
                judul: row.judul,
                waktu: row.waktu_dibuat,
                labelWaktu: "Dibuat",
                href: "tugas-detail.html?id_tugas=" + encodeURIComponent(row.id_tugas) + "&" + kelasQuery,
                info: [
                    "Deadline: " + formatWaktu(row.deadline),
                    "Status: " + (row.status_tugas || "-") + " · Pengumpulan saya: " +
                        (row.sudah_mengumpulkan ? "Sudah" : "Belum")
                ]
            });
        });

        kuisRows.forEach(function (row) {
            const info = [
                "Ketersediaan: " + (KETERSEDIAAN_LABEL[row.ketersediaan] || row.ketersediaan) +
                    " · " + row.durasi_menit + " menit",
                "Status: " + (PENGERJAAN_LABEL[row.status_pengerjaan] || row.status_pengerjaan)
            ];

            if (row.nilai_total !== null && row.nilai_total !== undefined) {
                info[1] += " · Nilai: " + row.nilai_total;
            }

            items.push({
                jenis: "kuis",
                judul: row.judul,
                // waktu_dibuat dikirim backend; waktu_mulai hanya cadangan.
                waktu: row.waktu_dibuat || row.waktu_mulai,
                labelWaktu: "Dibuat",
                href: "kuis-detail.html?id_kuis=" + encodeURIComponent(row.id_kuis) + "&" + kelasQuery,
                info: info
            });
        });

        // Terbaru di atas; waktu yang tidak terbaca dianggap paling lama.
        items.sort(function (a, b) {
            const timeA = parseWaktu(a.waktu);
            const timeB = parseWaktu(b.waktu);
            return (timeB ? timeB.getTime() : 0) - (timeA ? timeA.getTime() : 0);
        });

        return items;
    }

    function renderCard(item) {
        return (
            '<article class="feed-card feed-card--' + item.jenis + '">' +
            '<a class="feed-card__link" href="' + esc(item.href) + '">' +
            '<span class="feed-card__badge">' + JENIS_LABEL[item.jenis] + "</span>" +
            '<h3 class="feed-card__title">' + esc(item.judul) + "</h3>" +
            '<p class="feed-card__time">' + item.labelWaktu + " " + esc(formatWaktu(item.waktu)) + "</p>" +
            item.info.map(function (line) {
                return '<p class="feed-card__info">' + esc(line) + "</p>";
            }).join("") +
            "</a>" +
            "</article>"
        );
    }

    async function loadFeed() {
        feedEl.innerHTML = "<p>Memuat aktivitas...</p>";
        SiswaCommon.hideMessage(MESSAGE_ID);

        const results = await Promise.allSettled([fetchMateri(), fetchTugas(), fetchKuis()]);

        const failed = results.filter(function (result) {
            return result.status === "rejected";
        });

        if (failed.length > 0) {
            SiswaCommon.showMessage(
                MESSAGE_ID,
                "Sebagian data gagal dimuat: " + (failed[0].reason && failed[0].reason.message || "kesalahan tidak diketahui"),
                "error"
            );
        }

        const rows = results.map(function (result) {
            return result.status === "fulfilled" ? result.value : [];
        });

        const items = toItems(rows[0], rows[1], rows[2]);

        if (items.length === 0) {
            feedEl.innerHTML = "<p>Belum ada aktivitas di kelas ini.</p>";
            return;
        }

        feedEl.innerHTML = items.map(renderCard).join("");
    }

    KelasPage.registerTab("aktivitas", loadFeed);
})();