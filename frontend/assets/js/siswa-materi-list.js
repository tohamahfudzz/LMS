/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-materi-list.js
GUNA FILE   : Hanya satu tugas — menampilkan daftar materi siswa.
              Endpoint:
                GET ../../backend/api/siswa/materi/list.php
                GET ../../backend/api/siswa/kelas/list.php  (isi filter)
              Tidak menangani upload, tugas, atau absensi.
================================================================================
*/

(function () {
    "use strict";

    if (!SiswaCommon.initPage()) return;

    const API = {
        materiList: "../../backend/api/siswa/materi/list.php",
        kelasList:  "../../backend/api/siswa/kelas/list.php"
    };

    const tbody = document.getElementById("materiTableBody");
    const filterSelect = document.getElementById("filterKelasMateri");

    if (!tbody) return;

    function renderLinkOrFile(item) {
        if (item.link) {
            const safeUrl = SiswaCommon.escapeHtml(item.link);
            return '<a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer">Buka link</a>';
        }
        if (item.file) {
            return SiswaCommon.escapeHtml(item.file);
        }
        return "-";
    }

    async function loadMateriList(idKelas) {
        tbody.innerHTML = "<tr><td colspan=\"6\">Memuat data...</td></tr>";
        SiswaCommon.hideMessage("materiMessage");

        let url = API.materiList;
        if (idKelas) {
            url += "?id_kelas=" + encodeURIComponent(idKelas);
        }

        try {
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"6\">Belum ada materi.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.nama_kelas) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.tipe) + "</td>" +
                    "<td>" + renderLinkOrFile(item) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.waktu_upload) + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"6\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage(
                "materiMessage",
                error.message || "Gagal mengambil daftar materi.",
                "error"
            );
        }
    }

    async function loadFilterKelas() {
        if (!filterSelect) return;

        const params = new URLSearchParams(window.location.search);
        const idKelasUrl = params.get("id_kelas");

        try {
            const result = await apiRequest(API.kelasList, { method: "GET" });
            const kelasList = result.data || [];

            let html = '<option value="">Semua kelas</option>';
            kelasList.forEach(function (item) {
                const selected = String(item.id_kelas) === String(idKelasUrl) ? " selected" : "";
                html +=
                    '<option value="' + SiswaCommon.escapeHtml(item.id_kelas) + '"' + selected + ">" +
                    SiswaCommon.escapeHtml(item.nama_kelas) +
                    "</option>";
            });
            filterSelect.innerHTML = html;

            filterSelect.addEventListener("change", function () {
                loadMateriList(filterSelect.value || null);
            });

            loadMateriList(idKelasUrl || filterSelect.value || null);
        } catch (error) {
            filterSelect.innerHTML = '<option value="">Gagal memuat kelas</option>';
            SiswaCommon.showMessage(
                "materiMessage",
                error.message || "Gagal memuat daftar kelas untuk filter.",
                "error"
            );
            loadMateriList(null);
        }
    }

    loadFilterKelas();
})();