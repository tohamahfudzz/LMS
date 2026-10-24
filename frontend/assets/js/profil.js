/*
================================================================================
LOKASI FILE : frontend/assets/js/profil.js
GUNA FILE   : Logika halaman profil untuk ketiga peran:
              frontend/admin/profil.html, frontend/pengajar/profil.html,
              frontend/siswa/profil.html
              Peran halaman dibaca dari atribut <body data-role="...">.
              - Menampilkan nama, kode, peran, dan email saat ini
                  GET  ../../backend/api/profil/get.php
              - Mengganti email (email baru menimpa email lama)
                  POST ../../backend/api/profil/update_email.php  Body: { email }
              Bergantung pada: api.js (apiRequest) dan auth.js (requireAuth, logout).
================================================================================
*/

(function () {
    "use strict";

    const API = {
        get: "../../backend/api/profil/get.php",
        updateEmail: "../../backend/api/profil/update_email.php"
    };

    const EMAIL_MAX_LENGTH = 100;
    const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const ROLE_LABEL = { admin: "Admin", pengajar: "Pengajar", siswa: "Siswa" };

    // Proteksi UI: hanya peran yang sesuai halaman. Proteksi sesungguhnya ada di backend (session PHP).
    const user = requireAuth(document.body.getAttribute("data-role"));
    if (!user) return;

    const messageEl = document.getElementById("profilMessage");
    const form = document.getElementById("emailForm");
    const emailInput = document.getElementById("profilEmail");
    const saveButton = document.getElementById("saveEmailButton");

    let currentEmail = "";

    function showMessage(text, type) {
        messageEl.hidden = false;
        messageEl.textContent = text;

        if (type === "success") {
            messageEl.style.background = "#e8f8e8";
            messageEl.style.borderColor = "#2e7d32";
            messageEl.style.color = "#1b5e20";
        } else {
            messageEl.style.background = "#fdecea";
            messageEl.style.borderColor = "#c62828";
            messageEl.style.color = "#b71c1c";
        }
    }

    function hideMessage() {
        messageEl.hidden = true;
        messageEl.textContent = "";
    }

    function showCurrentEmail(email) {
        currentEmail = email || "";
        document.getElementById("profilEmailSaatIni").textContent = currentEmail || "Belum diisi";
    }

    async function loadProfil() {
        try {
            const result = await apiRequest(API.get, { method: "GET" });
            const data = result.data || {};

            document.getElementById("profilNama").textContent = data.nama || "-";
            document.getElementById("profilKode").textContent = data.kode || "-";
            document.getElementById("profilRole").textContent = ROLE_LABEL[data.role] || data.role || "-";
            showCurrentEmail(data.email);
        } catch (error) {
            showMessage(error.message || "Gagal memuat data profil.", "error");
        }
    }

    async function submitEmail(event) {
        event.preventDefault();
        hideMessage();

        const newEmail = emailInput.value.trim();

        if (!newEmail) {
            showMessage("Email baru wajib diisi.", "error");
            return;
        }
        if (newEmail.length > EMAIL_MAX_LENGTH) {
            showMessage("Email maksimal " + EMAIL_MAX_LENGTH + " karakter.", "error");
            return;
        }
        if (!EMAIL_PATTERN.test(newEmail)) {
            showMessage("Format email tidak valid. Contoh: nama@contoh.com", "error");
            return;
        }
        if (newEmail.toLowerCase() === currentEmail.toLowerCase()) {
            showMessage("Email baru sama dengan email saat ini.", "error");
            return;
        }

        saveButton.disabled = true;
        saveButton.textContent = "Menyimpan...";

        try {
            const result = await apiRequest(API.updateEmail, {
                method: "POST",
                body: { email: newEmail }
            });

            showCurrentEmail(result.data && result.data.email ? result.data.email : newEmail);
            showMessage(result.message || "Email berhasil diperbarui.", "success");
            form.reset();
        } catch (error) {
            showMessage(error.message || "Gagal memperbarui email.", "error");
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = "Simpan Email";
        }
    }

    document.getElementById("welcomeText").textContent = "Halo, " + (user.nama || "pengguna") + ".";
    document.getElementById("logoutButton").addEventListener("click", function () {
        logout();
    });

    form.addEventListener("submit", submitEmail);
    loadProfil();
})();