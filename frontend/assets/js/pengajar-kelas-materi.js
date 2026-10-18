/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-materi.js
GUNA FILE   : Tab Materi pada halaman kelas pengajar (frontend/pengajar/kelas-detail.html).
              - Daftar materi kelas ini : GET  ../../backend/api/pengajar/materi/list.php?id_kelas=
              - Unggah materi           : POST ../../backend/api/materi/create.php (multipart)
              id_kelas diambil dari URL halaman (KelasPage), bukan dipilih manual.
              Bergantung pada: pengajar-common.js dan pengajar-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        materiList: "../../backend/api/pengajar/materi/list.php",
        materiCreate: "../../backend/api/materi/create.php"
    };

    const form = document.getElementById("createMateriForm");
    const tbody = document.getElementById("materiTableBody");

    async function loadMateriList() {
        tbody.innerHTML = "<tr><td colspan=\"4\">Memuat data...</td></tr>";

        try {
            const url = API.materiList + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"4\">Belum ada materi di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.tipe) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_upload) + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"4\">Gagal memuat: " +
                PengajarCommon.escapeHtml(error.message) + "</td></tr>";
        }
    }

    async function submitMateri(event) {
        event.preventDefault();
        PengajarCommon.hideMessage("materiMessage");

        const judul = document.getElementById("materiJudul").value.trim();
        const tipe = document.getElementById("materiTipe").value;
        const link = document.getElementById("materiLink").value.trim();
        const fileInput = document.getElementById("materiFile");
        const button = document.getElementById("createMateriButton");

        if (!judul) {
            PengajarCommon.showMessage("materiMessage", "Judul wajib diisi.", "error");
            return;
        }
        if (!link && (!fileInput.files || !fileInput.files.length)) {
            PengajarCommon.showMessage("materiMessage", "Materi harus memiliki file atau link.", "error");
            return;
        }

        const formData = new FormData();
        formData.append("id_kelas", idKelas);
        formData.append("judul", judul);
        formData.append("tipe", tipe);
        if (link) formData.append("link", link);
        if (fileInput.files && fileInput.files[0]) formData.append("file", fileInput.files[0]);

        PengajarCommon.setButtonLoading(button, true, "Simpan Materi");

        try {
            const response = await fetch(API.materiCreate, {
                method: "POST",
                credentials: "include",
                body: formData
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error((data && data.message) || "Gagal mengunggah materi.");
            }

            PengajarCommon.showMessage("materiMessage", data.message || "Materi berhasil ditambahkan.", "success");
            form.reset();
            loadMateriList();
        } catch (error) {
            PengajarCommon.showMessage("materiMessage", error.message || "Gagal mengunggah materi.", "error");
        } finally {
            PengajarCommon.setButtonLoading(button, false, "Simpan Materi");
        }
    }

    form.addEventListener("submit", submitMateri);
    KelasPage.registerTab("materi", loadMateriList);
})();
