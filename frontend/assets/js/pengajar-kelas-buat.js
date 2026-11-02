/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-buat.js
GUNA FILE   : Form buat materi / tugas / kuis (frontend/pengajar/kelas-buat.html).
              URL: kelas-buat.html?id_kelas=1&jenis=materi|tugas|kuis
                POST ../../backend/api/materi/create.php  (multipart)
                POST ../../backend/api/tugas/create.php   (multipart)
                POST ../../backend/api/kuis/create.php    (JSON)
              Setelah berhasil: materi/tugas kembali ke feed kelas, kuis langsung
              membuka halaman soal.
              Bergantung pada: api.js, auth.js, pengajar-common.js.
================================================================================
*/

(function () {
    "use strict";

    const API = {
        kelasDetail: "../../backend/api/pengajar/kelas/detail.php",
        materiCreate: "../../backend/api/materi/create.php",
        tugasCreate: "../../backend/api/tugas/create.php",
        kuisCreate: "../../backend/api/kuis/create.php"
    };

    const JENIS = {
        materi: { judul: "Unggah Materi Baru", section: "sectionMateri" },
        tugas: { judul: "Buat Tugas Baru", section: "sectionTugas" },
        kuis: { judul: "Buat Kuis Baru", section: "sectionKuis" }
    };

    const MESSAGE_ID = "pageMessage";

    if (!PengajarCommon.initPage()) return;

    const params = new URLSearchParams(window.location.search);
    const parsedIdKelas = parseInt(params.get("id_kelas"), 10);
    const idKelas = parsedIdKelas > 0 ? parsedIdKelas : null;
    const jenis = params.get("jenis");

    const backUrl = idKelas
        ? "kelas-detail.html?id_kelas=" + encodeURIComponent(idKelas) + "&tab=aktivitas"
        : "dashboard.html";

    document.getElementById("backToKelasLink").href = backUrl;

    function goBackSoon() {
        setTimeout(function () {
            window.location.href = backUrl;
        }, 800);
    }

    // ----- Materi -----
    async function submitMateri(event) {
        event.preventDefault();
        PengajarCommon.hideMessage(MESSAGE_ID);

        const judul = document.getElementById("materiJudul").value.trim();
        const tipe = document.getElementById("materiTipe").value;
        const link = document.getElementById("materiLink").value.trim();
        const fileInput = document.getElementById("materiFile");
        const button = document.getElementById("createMateriButton");

        if (!judul) {
            PengajarCommon.showMessage(MESSAGE_ID, "Judul wajib diisi.", "error");
            return;
        }
        if (!link && (!fileInput.files || !fileInput.files.length)) {
            PengajarCommon.showMessage(MESSAGE_ID, "Materi harus memiliki file atau link.", "error");
            return;
        }

        const formData = new FormData();
        formData.append("id_kelas", idKelas);
        formData.append("judul", judul);
        formData.append("tipe", tipe);
        if (link) formData.append("link", link);
        if (fileInput.files && fileInput.files[0]) formData.append("file", fileInput.files[0]);

        await sendMultipart(API.materiCreate, formData, button, "Simpan Materi", "Materi berhasil ditambahkan.");
    }

    // ----- Tugas -----
    async function submitTugas(event) {
        event.preventDefault();
        PengajarCommon.hideMessage(MESSAGE_ID);

        const judul = document.getElementById("tugasJudul").value.trim();
        const deskripsi = document.getElementById("tugasDeskripsi").value.trim();
        const waktuMulai = document.getElementById("tugasWaktuMulai").value;
        const deadline = document.getElementById("tugasDeadline").value;
        const fileInput = document.getElementById("tugasFile");
        const button = document.getElementById("createTugasButton");

        if (!judul) {
            PengajarCommon.showMessage(MESSAGE_ID, "Judul wajib diisi.", "error");
            return;
        }
        if (!waktuMulai || !deadline) {
            PengajarCommon.showMessage(MESSAGE_ID, "Waktu mulai dan deadline wajib diisi.", "error");
            return;
        }
        if (new Date(waktuMulai) >= new Date(deadline)) {
            PengajarCommon.showMessage(MESSAGE_ID, "Waktu mulai harus lebih awal dari deadline.", "error");
            return;
        }

        const formData = new FormData();
        formData.append("id_kelas", idKelas);
        formData.append("judul", judul);
        if (deskripsi) formData.append("deskripsi", deskripsi);
        formData.append("waktu_mulai", PengajarCommon.formatDateTimeLocal(waktuMulai));
        formData.append("deadline", PengajarCommon.formatDateTimeLocal(deadline));
        if (fileInput.files && fileInput.files[0]) formData.append("file", fileInput.files[0]);

        await sendMultipart(API.tugasCreate, formData, button, "Simpan Tugas", "Tugas berhasil dibuat.");
    }

    async function sendMultipart(url, formData, button, idleLabel, successText) {
        PengajarCommon.setButtonLoading(button, true, idleLabel);

        try {
            const response = await fetch(url, { method: "POST", credentials: "include", body: formData });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error((data && data.message) || "Gagal menyimpan data.");
            }

            PengajarCommon.showMessage(MESSAGE_ID, (data.message || successText) + " Kembali ke kelas...", "success");
            goBackSoon();
        } catch (error) {
            PengajarCommon.showMessage(MESSAGE_ID, error.message || "Gagal menyimpan data.", "error");
            PengajarCommon.setButtonLoading(button, false, idleLabel);
        }
    }

    // ----- Kuis -----
    async function submitKuis(event) {
        event.preventDefault();
        PengajarCommon.hideMessage(MESSAGE_ID);

        const judul = document.getElementById("kuisJudul").value.trim();
        const deskripsi = document.getElementById("kuisDeskripsi").value.trim();
        const durasi = parseInt(document.getElementById("kuisDurasi").value, 10);
        const waktuMulai = document.getElementById("kuisWaktuMulai").value;
        const waktuSelesai = document.getElementById("kuisWaktuSelesai").value;
        const button = document.getElementById("createKuisButton");

        if (!judul) {
            PengajarCommon.showMessage(MESSAGE_ID, "Judul wajib diisi.", "error");
            return;
        }
        if (!durasi || durasi < 1) {
            PengajarCommon.showMessage(MESSAGE_ID, "Durasi wajib diisi (minimal 1 menit).", "error");
            return;
        }
        if (waktuMulai && waktuSelesai && new Date(waktuMulai) >= new Date(waktuSelesai)) {
            PengajarCommon.showMessage(MESSAGE_ID, "Waktu mulai harus lebih awal dari waktu selesai.", "error");
            return;
        }

        const body = { id_kelas: idKelas, judul: judul, durasi_menit: durasi };
        if (deskripsi) body.deskripsi = deskripsi;
        if (waktuMulai) body.waktu_mulai = PengajarCommon.formatDateTimeLocal(waktuMulai);
        if (waktuSelesai) body.waktu_selesai = PengajarCommon.formatDateTimeLocal(waktuSelesai);

        PengajarCommon.setButtonLoading(button, true, "Simpan sebagai Draft");

        try {
            const result = await apiRequest(API.kuisCreate, { method: "POST", body: body });
            const idKuis = result.data && result.data.id_kuis ? result.data.id_kuis : null;

            PengajarCommon.showMessage(
                MESSAGE_ID,
                (result.message || "Kuis berhasil dibuat sebagai draft.") +
                    (idKuis ? " Membuka halaman soal..." : ""),
                "success"
            );

            // Kuis baru langsung dibuka di halaman soal agar soal bisa segera ditambahkan.
            setTimeout(function () {
                window.location.href = idKuis
                    ? "kuis-soal.html?id_kuis=" + encodeURIComponent(idKuis) +
                        "&id_kelas=" + encodeURIComponent(idKelas)
                    : backUrl;
            }, 800);
        } catch (error) {
            PengajarCommon.showMessage(MESSAGE_ID, error.message || "Gagal membuat kuis.", "error");
            PengajarCommon.setButtonLoading(button, false, "Simpan sebagai Draft");
        }
    }

    async function start() {
        if (idKelas === null || !JENIS[jenis]) {
            document.getElementById("pageHeading").textContent = "Halaman tidak valid";
            PengajarCommon.showMessage(MESSAGE_ID, "Buka halaman ini dari tombol \"+ Buat\" di kelas.", "error");
            return;
        }

        // Sekaligus memeriksa bahwa pengajar memang mengajar di kelas ini.
        try {
            const result = await apiRequest(
                API.kelasDetail + "?id_kelas=" + encodeURIComponent(idKelas),
                { method: "GET" }
            );

            document.getElementById("kelasNama").textContent = "Kelas: " + result.data.nama_kelas;
        } catch (error) {
            document.getElementById("pageHeading").textContent = "Kelas tidak dapat dibuka";
            PengajarCommon.showMessage(MESSAGE_ID, error.message || "Gagal memuat data kelas.", "error");
            return;
        }

        document.getElementById("pageHeading").textContent = JENIS[jenis].judul;
        document.title = JENIS[jenis].judul + " - Pengajar LMS";
        document.getElementById(JENIS[jenis].section).hidden = false;

        document.getElementById("createMateriForm").addEventListener("submit", submitMateri);
        document.getElementById("createTugasForm").addEventListener("submit", submitTugas);
        document.getElementById("createKuisForm").addEventListener("submit", submitKuis);
    }

    start();
})();