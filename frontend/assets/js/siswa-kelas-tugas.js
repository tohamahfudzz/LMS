/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-tugas.js
GUNA FILE   : Tab Tugas pada halaman kelas siswa (frontend/siswa/kelas-detail.html).
              - Daftar tugas kelas ini    : GET  ../../backend/api/siswa/tugas/list.php?id_kelas=
              - Unduh soal (lampiran)     : GET  ../../backend/api/siswa/tugas/download_lampiran.php?id_tugas=
              - Kumpulkan / kirim ulang   : POST ../../backend/api/pengumpulan_tugas/upload.php (multipart)
              - Hapus file pengumpulan    : POST ../../backend/api/pengumpulan_tugas/delete.php  Body: { id_tugas }
                                            (teks jawaban tetap tersimpan)
              Siswa tidak mengetik ID tugas: tombol "Kumpulkan" pada baris tugas
              membuka formulir untuk tugas itu.
              Bergantung pada: siswa-common.js, siswa-kelas-detail.js,
              dan pengajar-kelas-download.js (helper unduh).
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        tugasList: "../../backend/api/siswa/tugas/list.php",
        lampiranDownload: "../../backend/api/siswa/tugas/download_lampiran.php",
        upload: "../../backend/api/pengumpulan_tugas/upload.php",
        deleteFile: "../../backend/api/pengumpulan_tugas/delete.php"
    };

    const tbody = document.getElementById("tugasTableBody");
    const submitSection = document.getElementById("submitSection");
    const form = document.getElementById("uploadTugasForm");

    // Tugas yang sedang dipilih untuk dikumpulkan (null bila formulir tertutup).
    let selectedIdTugas = null;

    function describePengumpulan(item) {
        if (!item.sudah_mengumpulkan) return "Belum";

        const data = item.pengumpulan || {};
        const isi = [];
        if (data.teks) isi.push("teks");
        if (data.file) isi.push("file");

        return "Sudah dikumpulkan" +
            (data.waktu_pengumpulan ? " (" + data.waktu_pengumpulan + ")" : "") +
            (isi.length > 0 ? " - " + isi.join(" + ") : "");
    }

    function buildTitleHtml(item) {
        let html = SiswaCommon.escapeHtml(item.judul);

        if (item.deskripsi) {
            html += "<details><summary>Lihat deskripsi</summary><div class=\"submission-text\">" +
                SiswaCommon.escapeHtml(item.deskripsi) + "</div></details>";
        }
        return html;
    }

    function buildActionHtml(item) {
        const idTugas = parseInt(item.id_tugas, 10);
        const isOpen = item.status_tugas === "terbuka";
        const actions = [];

        if (item.file) {
            actions.push("<button type=\"button\" class=\"btn-unduh-soal\" data-id=\"" + idTugas +
                "\">Unduh soal</button>");
        }
        if (isOpen) {
            actions.push("<button type=\"button\" class=\"btn-pilih-tugas\" data-id=\"" + idTugas + "\">" +
                (item.sudah_mengumpulkan ? "Kirim ulang" : "Kumpulkan") + "</button>");
        }
        if (isOpen && item.sudah_mengumpulkan && item.pengumpulan && item.pengumpulan.file) {
            actions.push("<button type=\"button\" class=\"btn-hapus-file-tugas\" data-id=\"" + idTugas +
                "\">Hapus file</button>");
        }

        return actions.length > 0 ? actions.join(" ") : "-";
    }

    let tugasRows = [];

    async function loadTugasList() {
        tbody.innerHTML = "<tr><td colspan=\"6\">Memuat data...</td></tr>";

        try {
            const url = API.tugasList + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            tugasRows = result.data || [];

            if (tugasRows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"6\">Belum ada tugas di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = tugasRows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + buildTitleHtml(item) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.deadline) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(item.status_tugas) + "</td>" +
                    "<td>" + SiswaCommon.escapeHtml(describePengumpulan(item)) + "</td>" +
                    "<td>" + buildActionHtml(item) + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"6\">Gagal memuat data.</td></tr>";
            SiswaCommon.showMessage("tugasListMessage", error.message || "Gagal mengambil daftar tugas.", "error");
        }
    }

    // ---------- Formulir pengumpulan ----------

    function openSubmitForm(idTugas) {
        const item = tugasRows.find(function (row) {
            return parseInt(row.id_tugas, 10) === idTugas;
        });
        if (!item) return;

        selectedIdTugas = idTugas;
        form.reset();
        SiswaCommon.hideMessage("uploadMessage");

        document.getElementById("submitTitle").textContent =
            (item.sudah_mengumpulkan ? "Kirim Ulang Tugas: " : "Kumpulkan Tugas: ") + item.judul;

        submitSection.hidden = false;
        submitSection.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function closeSubmitForm() {
        selectedIdTugas = null;
        form.reset();
        submitSection.hidden = true;
    }

    async function submitPengumpulan(event) {
        event.preventDefault();
        SiswaCommon.hideMessage("uploadMessage");

        const teks = document.getElementById("uploadTeks").value.trim();
        const fileInput = document.getElementById("uploadFile");
        const button = document.getElementById("uploadTugasButton");
        const hasFile = fileInput.files && fileInput.files.length > 0;

        if (!selectedIdTugas) {
            SiswaCommon.showMessage("uploadMessage", "Pilih tugas dari daftar terlebih dahulu.", "error");
            return;
        }
        if (!teks && !hasFile) {
            SiswaCommon.showMessage("uploadMessage", "Isi jawaban teks atau unggah file (minimal salah satu).", "error");
            return;
        }

        const formData = new FormData();
        formData.append("id_tugas", selectedIdTugas);
        if (teks) formData.append("teks", teks);
        if (hasFile) formData.append("file", fileInput.files[0]);

        SiswaCommon.setButtonLoading(button, true, "Kirim Pengumpulan");

        try {
            const response = await fetch(API.upload, {
                method: "POST",
                credentials: "include",
                body: formData
            });

            let data;
            try {
                data = await response.json();
            } catch (parseError) {
                throw new Error("Respons server tidak valid.");
            }

            if (!response.ok || !data.success) {
                throw new Error((data && data.message) || "Gagal mengumpulkan tugas.");
            }

            SiswaCommon.showMessage("tugasListMessage", data.message || "Tugas berhasil dikumpulkan.", "success");
            closeSubmitForm();
            loadTugasList();
        } catch (error) {
            SiswaCommon.showMessage("uploadMessage", error.message || "Gagal mengumpulkan tugas.", "error");
        } finally {
            SiswaCommon.setButtonLoading(button, false, "Kirim Pengumpulan");
        }
    }

    // ---------- Unduh soal dan hapus file ----------

    async function downloadSoal(idTugas, button) {
        SiswaCommon.hideMessage("tugasListMessage");
        button.disabled = true;
        button.textContent = "Mengunduh...";

        try {
            await KelasDownload.saveFromUrl(
                API.lampiranDownload + "?id_tugas=" + encodeURIComponent(idTugas),
                "lampiran-tugas"
            );
        } catch (error) {
            SiswaCommon.showMessage("tugasListMessage", error.message || "Gagal mengunduh soal.", "error");
        } finally {
            button.disabled = false;
            button.textContent = "Unduh soal";
        }
    }

    async function deleteFile(idTugas, button) {
        if (!window.confirm("Hapus file pengumpulan untuk tugas ini?\nJawaban teks tetap tersimpan.")) {
            return;
        }

        SiswaCommon.hideMessage("tugasListMessage");
        button.disabled = true;
        button.textContent = "...";

        try {
            const result = await apiRequest(API.deleteFile, {
                method: "POST",
                body: { id_tugas: idTugas }
            });
            SiswaCommon.showMessage("tugasListMessage", result.message || "File berhasil dihapus.", "success");
            loadTugasList();
        } catch (error) {
            SiswaCommon.showMessage("tugasListMessage", error.message || "Gagal menghapus file.", "error");
            button.disabled = false;
            button.textContent = "Hapus file";
        }
    }

    tbody.addEventListener("click", function (event) {
        const pilihButton = event.target.closest(".btn-pilih-tugas");
        if (pilihButton) {
            const idTugas = parseInt(pilihButton.getAttribute("data-id"), 10);
            if (idTugas) openSubmitForm(idTugas);
            return;
        }

        const soalButton = event.target.closest(".btn-unduh-soal");
        if (soalButton) {
            const idTugas = parseInt(soalButton.getAttribute("data-id"), 10);
            if (idTugas) downloadSoal(idTugas, soalButton);
            return;
        }

        const hapusButton = event.target.closest(".btn-hapus-file-tugas");
        if (hapusButton) {
            const idTugas = parseInt(hapusButton.getAttribute("data-id"), 10);
            if (idTugas) deleteFile(idTugas, hapusButton);
        }
    });

    form.addEventListener("submit", submitPengumpulan);
    document.getElementById("cancelUploadButton").addEventListener("click", closeSubmitForm);

    KelasPage.registerTab("tugas", loadTugasList);
})();