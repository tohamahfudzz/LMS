/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kuis-list.js
GUNA FILE   : Hanya menampilkan daftar kuis milik pengajar.
              GET ../../backend/api/kuis/list.php
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API_LIST = "../../backend/api/kuis/list.php";
    const tbody = document.getElementById("kuisTableBody");
    const filterSelect = document.getElementById("filterKelasKuis");
    if (!tbody) return;

    async function loadKuisList(idKelas) {
        tbody.innerHTML = "<tr><td colspan=\"7\">Memuat data...</td></tr>";
        PengajarCommon.hideMessage("kuisListMessage");

        let url = API_LIST;
        if (idKelas) url += "?id_kelas=" + encodeURIComponent(idKelas);

        try {
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"7\">Belum ada kuis.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                const status = item.status || "-";
                const isDraft = status === "draft";
                let aksi = '<a href="kuis-soal.html?id_kuis=' + encodeURIComponent(item.id_kuis) + '">Kelola soal</a>';

                if (isDraft) {
                    aksi +=
                        ' | <button type="button" class="btn-publish-kuis" data-id="' +
                        PengajarCommon.escapeHtml(item.id_kuis) +
                        '">Publish</button>';
                }

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.nama_kelas) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.durasi_menit) + " mnt</td>" +
                    "<td>" + PengajarCommon.escapeHtml(status) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.jumlah_soal) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"7\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage(
                "kuisListMessage",
                error.message || "Gagal mengambil daftar kuis.",
                "error"
            );
        }
    }

    async function initFilter() {
        const kelasList = await PengajarCommon.ensureKelasLoaded();
        if (filterSelect) {
            let html = '<option value="">Semua kelas</option>';
            kelasList.forEach(function (item) {
                html +=
                    '<option value="' + PengajarCommon.escapeHtml(item.id_kelas) + '">' +
                    PengajarCommon.escapeHtml(item.nama_kelas) +
                    "</option>";
            });
            filterSelect.innerHTML = html;
            filterSelect.addEventListener("change", function () {
                loadKuisList(filterSelect.value || null);
            });
        }
        loadKuisList(null);
    }

    window.pengajarRefreshKuisList = function () {
        const idKelas = filterSelect ? filterSelect.value || null : null;
        loadKuisList(idKelas);
    };

    initFilter();
})();