/*
================================================================================
LOKASI FILE : frontend/assets/js/admin-tahun-ajaran.js
GUNA FILE   : Logika halaman frontend/admin/tahun-ajaran.html (kelola tahun ajaran).
              Hanya mengurus tahun ajaran: daftar, tambah, ubah, aktif/nonaktif,
              dan hapus.
                GET  ../../backend/api/tahun_ajaran/list.php
                POST ../../backend/api/admin/tahun_ajaran/create.php
                POST ../../backend/api/admin/tahun_ajaran/update.php
                POST ../../backend/api/admin/tahun_ajaran/set_status.php
                POST ../../backend/api/admin/tahun_ajaran/delete.php
              Beberapa tahun ajaran boleh aktif bersamaan; status hanya penanda.
              Tahun ajaran yang masih dipakai kelas tidak bisa dihapus.
              Bergantung pada: api.js (apiRequest) dan auth.js (requireAuth).
              Salam pembuka dan tombol keluar diurus oleh admin.js.
================================================================================
*/

(function () {
    "use strict";

    if (!requireAuth("admin")) return;

    const API = {
        list: "../../backend/api/tahun_ajaran/list.php",
        create: "../../backend/api/admin/tahun_ajaran/create.php",
        update: "../../backend/api/admin/tahun_ajaran/update.php",
        setStatus: "../../backend/api/admin/tahun_ajaran/set_status.php",
        remove: "../../backend/api/admin/tahun_ajaran/delete.php"
    };

    const NAMA_MAX_LENGTH = 20;
    const MESSAGE_ID = "tahunMessage";
    const SAVE_LABEL = "Simpan";

    const form = document.getElementById("tahunAjaranForm");
    const tbody = document.getElementById("tahunTableBody");
    const formTitle = document.getElementById("formTitle");
    const saveButton = document.getElementById("simpanTahunButton");
    const cancelButton = document.getElementById("batalUbahButton");

    if (!form || !tbody) return;

    // Daftar terakhir dari server, dipakai saat mengisi form untuk "Ubah".
    let tahunList = [];

    function escapeHtml(text) {
        return String(text == null ? "" : text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function showMessage(text, type) {
        const el = document.getElementById(MESSAGE_ID);
        if (!el) return;

        el.hidden = false;
        el.textContent = text;
        el.dataset.type = type || "error";
        el.style.color = type === "success" ? "#1b5e20" : "#b71c1c";
    }

    function hideMessage() {
        const el = document.getElementById(MESSAGE_ID);
        if (!el) return;

        el.hidden = true;
        el.textContent = "";
    }

    function setButtonLoading(button, isLoading, labelIdle) {
        button.disabled = isLoading;
        button.textContent = isLoading ? "Memproses..." : labelIdle;
    }

    // Mengembalikan form ke mode "Tambah".
    function resetForm() {
        form.reset();
        document.getElementById("idTahunAjaran").value = "";
        document.getElementById("statusTahun").value = "aktif";
        formTitle.textContent = "Tambah Tahun Ajaran";
        cancelButton.hidden = true;
    }

    async function loadList() {
        tbody.innerHTML = '<tr><td colspan="7">Memuat data...</td></tr>';

        try {
            const result = await apiRequest(API.list, { method: "GET" });
            tahunList = result.data || [];

            if (tahunList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7">Belum ada tahun ajaran.</td></tr>';
                return;
            }

            tbody.innerHTML = tahunList.map(function (item, index) {
                const id = Number(item.id_tahun_ajaran);
                const isActive = item.status === "aktif";

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + escapeHtml(item.nama_tahun_ajaran) + "</td>" +
                    "<td>" + escapeHtml(item.tanggal_mulai) + "</td>" +
                    "<td>" + escapeHtml(item.tanggal_selesai) + "</td>" +
                    "<td>" + escapeHtml(item.status) + "</td>" +
                    "<td>" + Number(item.jumlah_kelas) + "</td>" +
                    "<td>" +
                    '<button type="button" class="btn-ubah-tahun" data-id="' + id + '">Ubah</button> ' +
                    '<button type="button" class="btn-status-tahun" data-id="' + id +
                        '" data-status="' + (isActive ? "nonaktif" : "aktif") + '">' +
                        (isActive ? "Nonaktifkan" : "Aktifkan") + "</button> " +
                    '<button type="button" class="btn-hapus-tahun" data-id="' + id + '">Hapus</button>' +
                    "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML =
                '<tr><td colspan="7">Gagal memuat: ' + escapeHtml(error.message) + "</td></tr>";
        }
    }

    function readForm() {
        return {
            id: document.getElementById("idTahunAjaran").value,
            nama: document.getElementById("namaTahunAjaran").value.trim(),
            mulai: document.getElementById("tanggalMulai").value,
            selesai: document.getElementById("tanggalSelesai").value,
            status: document.getElementById("statusTahun").value
        };
    }

    async function submitForm(event) {
        event.preventDefault();
        hideMessage();

        const values = readForm();

        if (!values.nama) {
            showMessage("Nama tahun ajaran wajib diisi.", "error");
            return;
        }
        if (values.nama.length > NAMA_MAX_LENGTH) {
            showMessage("Nama tahun ajaran maksimal " + NAMA_MAX_LENGTH + " karakter.", "error");
            return;
        }
        if (!values.mulai || !values.selesai) {
            showMessage("Tanggal mulai dan tanggal selesai wajib diisi.", "error");
            return;
        }
        if (values.selesai <= values.mulai) {
            showMessage("Tanggal selesai harus setelah tanggal mulai.", "error");
            return;
        }

        const isEdit = values.id !== "";
        const body = {
            nama_tahun_ajaran: values.nama,
            tanggal_mulai: values.mulai,
            tanggal_selesai: values.selesai,
            status: values.status
        };

        if (isEdit) body.id_tahun_ajaran = parseInt(values.id, 10);

        setButtonLoading(saveButton, true, SAVE_LABEL);

        try {
            const result = await apiRequest(isEdit ? API.update : API.create, {
                method: "POST",
                body: body
            });

            resetForm();
            await loadList();
            showMessage(result.message || "Tahun ajaran berhasil disimpan.", "success");
        } catch (error) {
            showMessage(error.message || "Gagal menyimpan tahun ajaran.", "error");
        } finally {
            setButtonLoading(saveButton, false, SAVE_LABEL);
        }
    }

    function startEdit(id) {
        const item = tahunList.find(function (row) {
            return row.id_tahun_ajaran === id;
        });

        if (!item) return;

        hideMessage();
        document.getElementById("idTahunAjaran").value = String(item.id_tahun_ajaran);
        document.getElementById("namaTahunAjaran").value = item.nama_tahun_ajaran;
        document.getElementById("tanggalMulai").value = item.tanggal_mulai;
        document.getElementById("tanggalSelesai").value = item.tanggal_selesai;
        document.getElementById("statusTahun").value = item.status;
        formTitle.textContent = "Ubah Tahun Ajaran";
        cancelButton.hidden = false;
        form.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    async function changeStatus(id, status, button) {
        hideMessage();
        button.disabled = true;

        try {
            const result = await apiRequest(API.setStatus, {
                method: "POST",
                body: { id_tahun_ajaran: id, status: status }
            });

            await loadList();
            showMessage(result.message || "Status tahun ajaran diperbarui.", "success");
        } catch (error) {
            showMessage(error.message || "Gagal mengubah status.", "error");
            button.disabled = false;
        }
    }

    async function removeTahun(id, button) {
        const item = tahunList.find(function (row) {
            return row.id_tahun_ajaran === id;
        });
        const nama = item ? item.nama_tahun_ajaran : "ini";

        if (!window.confirm("Hapus tahun ajaran \"" + nama + "\"?\nTindakan ini tidak dapat dibatalkan.")) {
            return;
        }

        hideMessage();
        button.disabled = true;

        try {
            const result = await apiRequest(API.remove, {
                method: "POST",
                body: { id_tahun_ajaran: id }
            });

            // Jika yang dihapus sedang diubah di form, kembalikan form ke mode tambah.
            if (document.getElementById("idTahunAjaran").value === String(id)) resetForm();

            await loadList();
            showMessage(result.message || "Tahun ajaran berhasil dihapus.", "success");
        } catch (error) {
            showMessage(error.message || "Gagal menghapus tahun ajaran.", "error");
            button.disabled = false;
        }
    }

    tbody.addEventListener("click", function (event) {
        const ubah = event.target.closest(".btn-ubah-tahun");
        const status = event.target.closest(".btn-status-tahun");
        const hapus = event.target.closest(".btn-hapus-tahun");
        const button = ubah || status || hapus;
        if (!button) return;

        const id = parseInt(button.getAttribute("data-id"), 10);
        if (!id) return;

        if (ubah) startEdit(id);
        else if (status) changeStatus(id, button.getAttribute("data-status"), button);
        else removeTahun(id, button);
    });

    form.addEventListener("submit", submitForm);
    cancelButton.addEventListener("click", function () {
        hideMessage();
        resetForm();
    });

    loadList();
})();