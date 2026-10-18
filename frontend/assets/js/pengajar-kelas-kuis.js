/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-kuis.js
GUNA FILE   : Tab Kuis pada halaman kelas pengajar (frontend/pengajar/kelas-detail.html).
              - Daftar kuis kelas ini : GET  ../../backend/api/kuis/list.php?id_kelas=
              - Buat kuis (draft)     : POST ../../backend/api/kuis/create.php
              - Terbitkan kuis        : POST ../../backend/api/kuis/publish.php  Body: { id_kuis }
              Daftar kuis ada di dalam kelas, tetapi mengelola soal dibuka di halaman
              tersendiri: kuis-soal.html?id_kuis=..&id_kelas=.. (id_kelas dipakai untuk
              tombol "Kembali ke kelas").
              Bergantung pada: pengajar-common.js dan pengajar-kelas-detail.js.
================================================================================
*/

(function () {
    "use strict";

    const idKelas = KelasPage.getIdKelas();
    if (idKelas === null) return;

    const API = {
        kuisList: "../../backend/api/kuis/list.php",
        kuisCreate: "../../backend/api/kuis/create.php",
        kuisPublish: "../../backend/api/kuis/publish.php"
    };

    const form = document.getElementById("createKuisForm");
    const tbody = document.getElementById("kuisTableBody");

    function buildSoalUrl(idKuis) {
        return "kuis-soal.html?id_kuis=" + encodeURIComponent(idKuis) +
            "&id_kelas=" + encodeURIComponent(idKelas);
    }

    async function loadKuisList() {
        tbody.innerHTML = "<tr><td colspan=\"6\">Memuat data...</td></tr>";
        PengajarCommon.hideMessage("kuisListMessage");

        try {
            const url = API.kuisList + "?id_kelas=" + encodeURIComponent(idKelas);
            const result = await apiRequest(url, { method: "GET" });
            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"6\">Belum ada kuis di kelas ini.</td></tr>";
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                const idKuis = parseInt(item.id_kuis, 10);
                const status = item.status || "-";
                let aksi = "<a href=\"" + buildSoalUrl(idKuis) + "\">Kelola soal</a>";

                if (status === "draft") {
                    aksi += " | <button type=\"button\" class=\"btn-publish-kuis\" data-id=\"" +
                        idKuis + "\">Publish</button>";
                }

                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.judul) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.durasi_menit) + " mnt</td>" +
                    "<td>" + PengajarCommon.escapeHtml(status) + "</td>" +
                    "<td>" + PengajarCommon.escapeHtml(item.jumlah_soal) + "</td>" +
                    "<td>" + aksi + "</td>" +
                    "</tr>"
                );
            }).join("");
        } catch (error) {
            tbody.innerHTML = "<tr><td colspan=\"6\">Gagal memuat data.</td></tr>";
            PengajarCommon.showMessage("kuisListMessage", error.message || "Gagal mengambil daftar kuis.", "error");
        }
    }

    async function publishKuis(idKuis, button) {
        // Judul diambil dari sel tabel agar tidak perlu disimpan di atribut HTML.
        const judul = button.closest("tr").children[1].textContent;
        if (!window.confirm("Terbitkan kuis \"" + judul + "\"?\nPastikan soal sudah lengkap.")) {
            return;
        }

        PengajarCommon.hideMessage("kuisListMessage");
        button.disabled = true;
        button.textContent = "...";

        try {
            const result = await apiRequest(API.kuisPublish, {
                method: "POST",
                body: { id_kuis: idKuis }
            });
            PengajarCommon.showMessage("kuisListMessage", result.message || "Kuis berhasil diterbitkan.", "success");
            loadKuisList();
        } catch (error) {
            PengajarCommon.showMessage("kuisListMessage", error.message || "Gagal menerbitkan kuis.", "error");
            button.disabled = false;
            button.textContent = "Publish";
        }
    }

    async function submitKuis(event) {
        event.preventDefault();
        PengajarCommon.hideMessage("createKuisMessage");

        const judul = document.getElementById("kuisJudul").value.trim();
        const deskripsi = document.getElementById("kuisDeskripsi").value.trim();
        const durasi = parseInt(document.getElementById("kuisDurasi").value, 10);
        const waktuMulai = document.getElementById("kuisWaktuMulai").value;
        const waktuSelesai = document.getElementById("kuisWaktuSelesai").value;
        const button = document.getElementById("createKuisButton");

        if (!judul) {
            PengajarCommon.showMessage("createKuisMessage", "Judul wajib diisi.", "error");
            return;
        }
        if (!durasi || durasi < 1) {
            PengajarCommon.showMessage("createKuisMessage", "Durasi wajib diisi (minimal 1 menit).", "error");
            return;
        }
        if (waktuMulai && waktuSelesai && new Date(waktuMulai) >= new Date(waktuSelesai)) {
            PengajarCommon.showMessage("createKuisMessage", "Waktu mulai harus lebih awal dari waktu selesai.", "error");
            return;
        }

        const body = {
            id_kelas: idKelas,
            judul: judul,
            durasi_menit: durasi
        };
        if (deskripsi) body.deskripsi = deskripsi;
        if (waktuMulai) body.waktu_mulai = PengajarCommon.formatDateTimeLocal(waktuMulai);
        if (waktuSelesai) body.waktu_selesai = PengajarCommon.formatDateTimeLocal(waktuSelesai);

        PengajarCommon.setButtonLoading(button, true, "Simpan sebagai Draft");

        try {
            const result = await apiRequest(API.kuisCreate, { method: "POST", body: body });
            const idKuis = result.data && result.data.id_kuis ? result.data.id_kuis : null;

            PengajarCommon.showMessage(
                "createKuisMessage",
                (result.message || "Kuis berhasil dibuat sebagai draft.") +
                    (idKuis ? " Membuka halaman soal..." : ""),
                "success"
            );
            form.reset();
            document.getElementById("kuisDurasi").value = 30;
            loadKuisList();

            // Kuis baru langsung dibuka di halaman soal agar soal bisa segera ditambahkan.
            if (idKuis) {
                setTimeout(function () {
                    window.location.href = buildSoalUrl(idKuis);
                }, 800);
            }
        } catch (error) {
            PengajarCommon.showMessage("createKuisMessage", error.message || "Gagal membuat kuis.", "error");
        } finally {
            PengajarCommon.setButtonLoading(button, false, "Simpan sebagai Draft");
        }
    }

    tbody.addEventListener("click", function (event) {
        const button = event.target.closest(".btn-publish-kuis");
        if (!button) return;

        const idKuis = parseInt(button.getAttribute("data-id"), 10);
        if (idKuis) publishKuis(idKuis, button);
    });

    form.addEventListener("submit", submitKuis);
    KelasPage.registerTab("kuis", loadKuisList);
})();
