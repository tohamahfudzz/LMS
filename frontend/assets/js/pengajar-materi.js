/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-materi.js
GUNA FILE   : Hanya fitur materi pengajar — list + upload.
================================================================================
*/

(function () {
    "use strict";

    if (!PengajarCommon.initPage()) return;

    const API = {
        materiList:   "../../backend/api/pengajar/materi/list.php",
        materiCreate: "../../backend/api/materi/create.php"
    };

    const form = document.getElementById("createMateriForm");
    const filterSelect = document.getElementById("filterKelasMateri");
    const tbody = document.getElementById("materiTableBody");

    if (!form && !filterSelect && !tbody) return;

    async function loadMateriList(idKelas) {
        if (!tbody) return;
        tbody.innerHTML = "<tr><td colspan=\"5\">Memuat data...</td></tr>";

        let url = API.materiList;
        if (idKelas) url += "?id_kelas=" + encodeURIComponent(idKelas);

        try {
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];
            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"5\">Belum ada materi.</td></tr>";
                return;
            }
            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.nama_kelas) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.tipe) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.waktu_upload) + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"5\">Gagal memuat: " +
                PengajarCommon.escapeHtml(error.message) + "</td></tr>";
        }
    }

    async function init() {
        const kelasList = await PengajarCommon.ensureKelasLoaded();
        const params = new URLSearchParams(window.location.search);
        const idKelasUrl = params.get("id_kelas");

        PengajarCommon.fillSelect(
            "materiKelas",
            kelasList,
            "id_kelas",
            function (i) { return i.nama_kelas; },
            idKelasUrl
        );

        if (filterSelect) {
            let html = '<option value="">Semua kelas</option>';
            kelasList.forEach(function (item) {
                const selected = String(item.id_kelas) === String(idKelasUrl) ? " selected" : "";
                html += '<option value="' + PengajarCommon.escapeHtml(item.id_kelas) + '"' + selected + ">" +
                    PengajarCommon.escapeHtml(item.nama_kelas) + "</option>";
            });
            filterSelect.innerHTML = html;
            filterSelect.addEventListener("change", function () {
                loadMateriList(filterSelect.value || null);
            });
        }

        loadMateriList(idKelasUrl || null);
    }

    if (form) {
        form.addEventListener("submit", async function (event) {
            event.preventDefault();
            PengajarCommon.hideMessage("materiMessage");

            const idKelas = document.getElementById("materiKelas").value;
            const judul = document.getElementById("materiJudul").value.trim();
            const tipe = document.getElementById("materiTipe").value;
            const link = document.getElementById("materiLink").value.trim();
            const fileInput = document.getElementById("materiFile");
            const btn = document.getElementById("createMateriButton");

            if (!idKelas) {
                PengajarCommon.showMessage("materiMessage", "Silakan pilih kelas.", "error");
                return;
            }
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

            PengajarCommon.setButtonLoading(btn, true, "Simpan Materi");
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
                PengajarCommon.fillSelect(
                    "materiKelas",
                    PengajarCommon.getCachedKelas(),
                    "id_kelas",
                    function (i) { return i.nama_kelas; },
                    idKelas
                );
                loadMateriList(filterSelect ? filterSelect.value || null : null);
            } catch (error) {
                PengajarCommon.showMessage("materiMessage", error.message || "Gagal mengunggah materi.", "error");
            } finally {
                PengajarCommon.setButtonLoading(btn, false, "Simpan Materi");
            }
        });
    }

    init();
})();