/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-feed.js
GUNA FILE   : Feed aktivitas kelas pengajar (frontend/pengajar/kelas-detail.html).
              Menggabungkan materi, tugas, dan kuis kelas ini menjadi satu daftar
              card, diurutkan dari yang terbaru ke terlama berdasarkan waktu upload.
                GET  ../../backend/api/pengajar/materi/list.php?id_kelas=
                GET  ../../backend/api/pengajar/tugas/list.php?id_kelas=
                GET  ../../backend/api/kuis/list.php?id_kelas=
              Card kuis punya tombol Publish (draft) dan Hapus.
                POST ../../backend/api/kuis/publish.php   Body: { id_kuis }
                POST ../../backend/api/kuis/delete.php    Body: { id_kuis }
              Bergantung pada: pengajar-common.js dan pengajar-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        materiList: "../../backend/api/pengajar/materi/list.php",
        tugasList: "../../backend/api/pengajar/tugas/list.php",
        kuisList: "../../backend/api/kuis/list.php",
        kuisPublish: "../../backend/api/kuis/publish.php",
        kuisDelete: "../../backend/api/kuis/delete.php"
    };

    const MESSAGE_ID = "feedMessage";
    const feedEl = document.getElementById("feedList");
    const esc = PengajarCommon.escapeHtml;
    const kelasQuery = "id_kelas=" + encodeURIComponent(idKelas);

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

    function setupToolbar() {
        ["materi", "tugas", "kuis"].forEach(function (jenis) {
            const link = document.getElementById("buat-" + jenis);
            if (link) link.href = "kelas-buat.html?" + kelasQuery + "&jenis=" + jenis;
        });
    }

    async function fetchList(url) {
        const result = await apiRequest(url + "?" + kelasQuery, { method: "GET" });
        return result.data || [];
    }

    function toItems(materiRows, tugasRows, kuisRows) {
        const items = [];

        materiRows.forEach(function (row) {
            items.push({
                jenis: "materi",
                id: row.id_materi,
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
                id: row.id_tugas,
                judul: row.judul,
                waktu: row.waktu_dibuat,
                labelWaktu: "Dibuat",
                href: "tugas-detail.html?id_tugas=" + encodeURIComponent(row.id_tugas) + "&" + kelasQuery,
                info: [
                    "Deadline: " + formatWaktu(row.deadline),
                    "Status: " + (row.status || "-") + " · " + row.jumlah_pengumpulan + " pengumpulan"
                ]
            });
        });

        kuisRows.forEach(function (row) {
            const info = [
                "Status: " + (row.status || "-") + " · " + row.durasi_menit + " menit · " +
                    row.jumlah_soal + " soal"
            ];

            if (row.jumlah_menunggu > 0) {
                info.push(row.jumlah_menunggu + " jawaban menunggu penilaian");
            }

            items.push({
                jenis: "kuis",
                id: row.id_kuis,
                judul: row.judul,
                waktu: row.waktu_dibuat,
                labelWaktu: "Dibuat",
                href: "kuis-soal.html?id_kuis=" + encodeURIComponent(row.id_kuis) + "&" + kelasQuery,
                info: info,
                status: row.status
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

    const JENIS_LABEL = { materi: "Materi", tugas: "Tugas", kuis: "Kuis" };

    function renderCard(item) {
        let actions = "";

        if (item.jenis === "kuis") {
            actions = '<div class="feed-card__actions">';
            if (item.status === "draft") {
                actions += '<button type="button" class="btn-publish-kuis" data-id="' +
                    esc(item.id) + '">Publish</button>';
            }
            actions += '<button type="button" class="btn-hapus-kuis" data-id="' +
                esc(item.id) + '">Hapus</button></div>';
        }

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
            actions +
            "</article>"
        );
    }

    async function loadFeed() {
        feedEl.innerHTML = "<p>Memuat aktivitas...</p>";
        PengajarCommon.hideMessage(MESSAGE_ID);

        const results = await Promise.allSettled([
            fetchList(API.materiList),
            fetchList(API.tugasList),
            fetchList(API.kuisList)
        ]);

        const failed = results.filter(function (result) {
            return result.status === "rejected";
        });

        if (failed.length > 0) {
            PengajarCommon.showMessage(
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
            feedEl.innerHTML = "<p>Belum ada aktivitas di kelas ini. Gunakan tombol \"+ Buat\" di atas.</p>";
            return;
        }

        feedEl.innerHTML = items.map(renderCard).join("");
    }

    async function runKuisAction(button, url, confirmText, successText, failText, idleLabel) {
        const idKuis = parseInt(button.getAttribute("data-id"), 10);
        if (!idKuis) return;

        if (!window.confirm(confirmText)) return;

        PengajarCommon.hideMessage(MESSAGE_ID);
        button.disabled = true;
        button.textContent = "...";

        try {
            const result = await apiRequest(url, { method: "POST", body: { id_kuis: idKuis } });
            await loadFeed();
            PengajarCommon.showMessage(MESSAGE_ID, result.message || successText, "success");
        } catch (error) {
            PengajarCommon.showMessage(MESSAGE_ID, error.message || failText, "error");
            button.disabled = false;
            button.textContent = idleLabel;
        }
    }

    feedEl.addEventListener("click", function (event) {
        const publishButton = event.target.closest(".btn-publish-kuis");
        const hapusButton = event.target.closest(".btn-hapus-kuis");
        const button = publishButton || hapusButton;
        if (!button) return;

        // Judul diambil dari card, jadi ID kuis tidak perlu ditampilkan.
        const judul = button.closest(".feed-card").querySelector(".feed-card__title").textContent;

        if (publishButton) {
            runKuisAction(
                button,
                API.kuisPublish,
                "Terbitkan kuis \"" + judul + "\"?\nPastikan soal sudah lengkap.",
                "Kuis berhasil diterbitkan.",
                "Gagal menerbitkan kuis.",
                "Publish"
            );
        } else {
            runKuisAction(
                button,
                API.kuisDelete,
                "Hapus kuis \"" + judul + "\"?\nSemua soal di dalamnya ikut terhapus dan tindakan ini tidak dapat dibatalkan.",
                "Kuis berhasil dihapus.",
                "Gagal menghapus kuis.",
                "Hapus"
            );
        }
    });

    setupToolbar();
    KelasPage.registerTab("aktivitas", loadFeed);
})();