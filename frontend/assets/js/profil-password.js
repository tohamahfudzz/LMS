/*
================================================================================
LOKASI FILE : frontend/assets/js/profil-password.js
GUNA FILE   : Logika bagian "Ganti Password" di halaman profil, dipakai bersama oleh
              frontend/admin/profil.html, frontend/pengajar/profil.html, dan
              frontend/siswa/profil.html.
              Hanya satu tugas: meminta backend mengirim tautan ganti password
              ke email pengguna yang sedang login.
                  POST ../../backend/api/profil/request_password_change.php
              Password baru TIDAK diisi di halaman ini; pengguna mengisinya setelah
              membuka tautan di email (frontend/ganti-password.html).
              Bergantung pada: api.js (apiRequest). Dimuat setelah profil.js.
================================================================================
*/

(function () {
    "use strict";

    const API_REQUEST = "../../backend/api/profil/request_password_change.php";
    const COOLDOWN_SECONDS = 60;
    const BUTTON_LABEL = "Kirim Tautan ke Email Saya";

    const messageEl = document.getElementById("passwordMessage");
    const button = document.getElementById("requestPasswordButton");

    if (!messageEl || !button) return;

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

    // Tombol dikunci sebentar setelah berhasil, sejalan dengan jeda kirim ulang di backend.
    function startCooldown() {
        let remaining = COOLDOWN_SECONDS;
        button.disabled = true;
        button.textContent = "Kirim ulang dalam " + remaining + " detik";

        const timer = setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                clearInterval(timer);
                button.disabled = false;
                button.textContent = BUTTON_LABEL;
                return;
            }

            button.textContent = "Kirim ulang dalam " + remaining + " detik";
        }, 1000);
    }

    async function requestPasswordChange() {
        hideMessage();
        button.disabled = true;
        button.textContent = "Mengirim...";

        try {
            const result = await apiRequest(API_REQUEST, { method: "POST", body: {} });

            showMessage(result.message || "Tautan ganti password telah dikirim ke email Anda.", "success");
            startCooldown();
        } catch (error) {
            showMessage(error.message || "Gagal meminta tautan ganti password.", "error");
            button.disabled = false;
            button.textContent = BUTTON_LABEL;
        }
    }

    button.addEventListener("click", requestPasswordChange);
})();