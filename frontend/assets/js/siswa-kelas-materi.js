/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-materi.js
GUNA FILE   : Tab Materi pada halaman kelas siswa (frontend/siswa/kelas-detail.html).
              - Daftar materi kelas ini : GET ../../backend/api/siswa/materi/list.php?id_kelas=
              - Unduh file materi       : GET ../../backend/api/siswa/materi/download.php?id_materi=
              Materi bertipe link tidak punya file: kolom "File / Link" menampilkan tautan
              yang dibuka di tab baru (hanya alamat http/https).
              Bergantung pada: siswa-common.js, siswa-kelas-detail.js,
              dan pengajar-kelas-download.js (helper unduh).
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        materiList: "../../backend/api/siswa/materi/list.php",
        materiDownload: "../../backend/api/siswa/materi/download.php"
    };

    const tbody = document.getElementById("materiTableBody");

    // Hanya alamat http/https yang dijadikan tautan; selain itu (misal javascript:) diabaikan.
    function buildSafeLink(rawLink) {
        if (!rawLink) return "";

        try {
            const parsed = new URL(rawLink);
            if (parsed.protocol !== "http:" && parsed.protocol !== "https:") return "";
            return parsed.href.replace(/"/g, "%22");
        } catch (error) {
            return "";
        }
    }

    function buildActionHtml(item) {
        const actions = [];
        const idMateri = parseInt(item.id_materi, 10);
        const safeLink = buildSafeLink(item.link);

        if (item.file) {
            actions.push("<button type=\"button\" class=\"btn-unduh-materi\" data-id=\"" +
                idMateri + "\">Unduh file</button>");
        }
        if (safeLink) {
            actions.push("<a href=\"" + safeLink + "\" target=\"_blank\" rel=\"noopener noreferrer\">Buka link</a>");
        }

        return actions.length > 0 ? actions.join(" ") : "-";
    }

    async function loadMateriList() {
        tbody.innerHTML = "<tr><td colspan=\"5\">Memuat data...</td></tr>";
        SiswaCommon.hideMessage("materiMessage");

        try {
            const url = API.materiList + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"5\">Belum ada materi di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.tipe) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.waktu_upload) + "</td>" +
                    "<td>" + buildActionHtml(item) + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"5\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage("materiMessage", error.message || "Gagal mengambil daftar materi.", "error");
        }
    }

    async function downloadMateri(idMateri, button) {
        SiswaCommon.hideMessage("materiMessage");
        button.disabled = true;
        button.textContent = "Mengunduh...";

        try {
            await KelasDownload.saveFromUrl(
                API.materiDownload + "?id_materi=" + encodeURIComponent(idMateri),
                "materi"
            );
        } catch (error) {
            SiswaCommon.showMessage("materiMessage", error.message || "Gagal mengunduh materi.", "error");
        } finally {
            button.disabled = false;
            button.textContent = "Unduh file";
        }
    }

    tbody.addEventListener("click", function (event) {
        const button = event.target.closest(".btn-unduh-materi");
        if (!button) return;

        const idMateri = parseInt(button.getAttribute("data-id"), 10);
        if (idMateri) downloadMateri(idMateri, button);
    });

    KelasPage.registerTab("materi", loadMateriList);
})();