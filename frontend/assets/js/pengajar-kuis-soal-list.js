/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kuis-soal-list.js
GUNA FILE   : Hanya menampilkan daftar soal satu kuis.
              GET ../../backend/api/soal_kuis/list.php?id_kuis=
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const params = new URLSearchParams(window.location.search);
    const idKuis = parseInt(params.get("id_kuis"), 10);
    const tbody = document.getElementById("soalTableBody");
    const labelId = document.getElementById("labelIdKuis");

    if (labelId) labelId.textContent = idKuis || "-";

    if (!idKuis || idKuis < 1) {
        PengajarCommon.showMessage("soalListMessage", "ID kuis tidak valid.", "error");
        if (tbody) tbody.innerHTML = "";
        return;
    }

    if (!tbody) return;

    const API_LIST = "../../backend/api/soal_kuis/list.php?id_kuis=" + encodeURIComponent(idKuis);

    async function loadSoalList() {
        tbody.innerHTML = "<tr><td colspan=\"6\">Memuat data...</td></tr>";
        PengajarCommon.hideMessage("soalListMessage");

        try {
            const result = await apiRequest(API_LIST, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"6\">Belum ada soal. Tambah di form atas.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item) {
                const kunci = item.kunci_jawaban || "-";
                const potong = (item.soal || "").length > 80
                    ? (item.soal || "").slice(0, 80) + "…"
                    : (item.soal || "");
                const dapatEdit = item.dapat_diedit !== false;

                const aksi = dapatEdit
                    ? '<button type="button" class="btn-hapus-soal" data-id="' +
                      PengajarCommon.escapeHtml(item.id_soal) +
                      '">Hapus</button>'
                    : "-";

                return (
                    "<tr>" +
                    "<td>" + PengajarCommon.escapeHtml(item.urutan) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.tipe_soal) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(potong) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.poin) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(kunci) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"6\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage(
                "soalListMessage",
                error.message || "Gagal mengambil daftar soal.",
                "error"
            );
        }
    }

    window.pengajarRefreshSoalList = loadSoalList;
    window.pengajarCurrentIdKuis = idKuis;

    loadSoalList();
})();