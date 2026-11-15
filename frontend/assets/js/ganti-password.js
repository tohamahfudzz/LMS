/*
================================================================================
LOKASI FILE : frontend/assets/js/ganti-password.js
GUNA FILE   : Logika halaman frontend/ganti-password.html, yaitu halaman yang
              dibuka dari tautan di email "ganti password".
              Hanya satu tugas: membaca token dari alamat halaman, memeriksa
              password baru, lalu mengirimnya ke backend.
                  POST ../backend/api/profil/confirm_password_change.php
                  Body: { token, password_baru }
              Setelah berhasil, pengguna diarahkan ke login.html.
              Bergantung pada: api.js (apiRequest).
================================================================================
*/

(function () {
    "use strict";

    const API_CONFIRM = "../backend/api/profil/confirm_password_change.php";
    const PASSWORD_MIN_LENGTH = 8;
    const PASSWORD_MAX_LENGTH = 72;
    const TOKEN_PATTERN = /^[0-9a-f]{64}$/i;

    const messageEl = document.getElementById("gantiMessage");
    const form = document.getElementById("gantiPasswordForm");
    const button = document.getElementById("gantiPasswordButton");

    if (!messageEl || !form || !button) return;

    const token = new URLSearchParams(window.location.search).get("token") || "";

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

    async function submitPassword(event) {
        event.preventDefault();
        hideMessage();

        const passwordBaru = document.getElementById("passwordBaru").value;
        const passwordUlang = document.getElementById("passwordUlang").value;

        if (passwordBaru.length < PASSWORD_MIN_LENGTH) {
            showMessage("Password baru minimal " + PASSWORD_MIN_LENGTH + " karakter.", "error");
            return;
        }
        if (passwordBaru.length > PASSWORD_MAX_LENGTH) {
            showMessage("Password baru maksimal " + PASSWORD_MAX_LENGTH + " karakter.", "error");
            return;
        }
        if (passwordBaru !== passwordUlang) {
            showMessage("Konfirmasi password tidak sama.", "error");
            return;
        }

        button.disabled = true;
        button.textContent = "Menyimpan...";

        try {
            const result = await apiRequest(API_CONFIRM, {
                method: "POST",
                body: { token: token, password_baru: passwordBaru }
            });

            form.reset();
            form.hidden = true;
            showMessage(
                (result.message || "Password berhasil diganti.") + " Mengalihkan ke halaman login...",
                "success"
            );

            setTimeout(function () {
                window.location.href = "login.html";
            }, 2000);
        } catch (error) {
            showMessage(error.message || "Gagal mengganti password.", "error");
            button.disabled = false;
            button.textContent = "Simpan Password Baru";
        }
    }

    if (!TOKEN_PATTERN.test(token)) {
        form.hidden = true;
        showMessage("Tautan tidak valid. Minta tautan baru dari halaman profil.", "error");
        return;
    }

    form.addEventListener("submit", submitPassword);
})();