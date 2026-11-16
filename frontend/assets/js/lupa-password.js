/*
================================================================================
LOKASI FILE : frontend/assets/js/lupa-password.js
GUNA FILE   : Logika halaman frontend/lupa-password.html.
              Hanya satu tugas: memeriksa kode login yang diketik lalu meminta
              backend mengirim tautan reset password ke email akun pemilik kode itu.
                  POST ../backend/api/auth/forgot_password.php
                  Body: { kode }
              Pesan balasan selalu sama, baik kode terdaftar maupun tidak.
              Bergantung pada: api.js (apiRequest).
================================================================================
*/

(function () {
    "use strict";

    const API_FORGOT = "../backend/api/auth/forgot_password.php";
    const COOLDOWN_SECONDS = 60;
    const KODE_MIN_LENGTH = 2;
    const KODE_MAX_LENGTH = 20;
    const BUTTON_LABEL = "Kirim Tautan Reset";

    const form = document.getElementById("lupaPasswordForm");
    const messageEl = document.getElementById("lupaMessage");
    const button = document.getElementById("lupaPasswordButton");

    if (!form || !messageEl || !button) return;

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

    async function submitKode(event) {
        event.preventDefault();
        hideMessage();

        const kode = document.getElementById("lupaKode").value.trim();

        if (!kode) {
            showMessage("Kode wajib diisi.", "error");
            return;
        }
        if (kode.length < KODE_MIN_LENGTH || kode.length > KODE_MAX_LENGTH) {
            showMessage(
                "Kode harus " + KODE_MIN_LENGTH + " sampai " + KODE_MAX_LENGTH + " karakter.",
                "error"
            );
            return;
        }

        button.disabled = true;
        button.textContent = "Mengirim...";

        try {
            const result = await apiRequest(API_FORGOT, { method: "POST", body: { kode: kode } });

            showMessage(
                result.message || "Jika kode terdaftar dan akun memiliki email, tautan reset telah dikirim.",
                "success"
            );
            startCooldown();
        } catch (error) {
            showMessage(error.message || "Gagal memproses permintaan.", "error");
            button.disabled = false;
            button.textContent = BUTTON_LABEL;
        }
    }

    form.addEventListener("submit", submitKode);
})();