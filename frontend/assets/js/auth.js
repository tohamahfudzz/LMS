/*
================================================================================
LOKASI FILE : frontend/assets/js/auth.js
GUNA FILE   :
    1. Memvalidasi formulir login.
    2. Mengambil token Cloudflare Turnstile CAPTCHA.
    3. Mengirim kode, password, dan token CAPTCHA ke backend.
    4. Menampilkan pesan error dari backend.
    5. Mengarahkan pengguna ke dashboard sesuai role dari backend.
================================================================================
*/

(function () {
    "use strict";

    // Endpoint login backend
    const LOGIN_ENDPOINT = "../backend/api/auth/login.php";

    const form = document.getElementById("loginForm");
    const messageEl = document.getElementById("loginMessage");
    const submitBtn = document.getElementById("loginButton");

    if (!form || !messageEl) return;

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

    function resetSubmitButton() {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Masuk";
        }
    }

    function validateForm(kode, password) {
        if (!kode || kode.trim().length < 2) {
            return "Kode wajib diisi (minimal 2 karakter).";
        }

        if (!password || password.length < 4) {
            return "Password wajib diisi (minimal 4 karakter).";
        }

        return null;
    }

    function redirectByRole(role) {
        const routes = {
            admin: "admin/dashboard.html",
            pengajar: "pengajar/dashboard.html",
            siswa: "siswa/dashboard.html"
        };

        window.location.href = routes[role] || "login.html";
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        hideMessage();

        const kode = document.getElementById("kode").value.trim();
        const password = document.getElementById("password").value;

        // Validasi formulir login
        const validationError = validateForm(kode, password);

        if (validationError) {
            showMessage(validationError, "error");
            return;
        }

        /*
         * Ambil token CAPTCHA ketika formulir dikirim,
         * bukan ketika file auth.js pertama kali dijalankan.
         */
        const captchaInput = form.querySelector(
            '[name="cf-turnstile-response"]'
        );

        const captchaToken = captchaInput
            ? captchaInput.value.trim()
            : "";

        // Jangan mengirim login jika CAPTCHA belum menghasilkan token.
        if (!captchaToken) {
            showMessage(
                "Silakan selesaikan verifikasi CAPTCHA terlebih dahulu.",
                "error"
            );
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Memproses...";
        }

        try {
            const result = await apiRequest(LOGIN_ENDPOINT, {
                method: "POST",
                body: {
                    kode: kode,
                    password: password,
                    captchaToken: captchaToken
                }
            });

            // Role ditentukan backend dari kode, bukan dari browser.
            const role = result.session ? result.session.role : null;

            if (result.success && role) {
                sessionStorage.setItem(
                    "lms_user",
                    JSON.stringify({
                        id: result.session.id,
                        role: role,
                        nama: result.session.nama
                    })
                );

                showMessage(
                    result.message || "Login berhasil. Mengalihkan...",
                    "success"
                );

                setTimeout(function () {
                    redirectByRole(role);
                }, 700);

            } else {
                showMessage(
                    result.message || "Login gagal.",
                    "error"
                );

                resetSubmitButton();
            }

        } catch (error) {
            showMessage(
                error.message ||
                "Login gagal. Periksa kode dan password.",
                "error"
            );

            resetSubmitButton();

        } finally {
            // Kosongkan password setelah proses selesai.
            document.getElementById("password").value = "";
        }
    });
})();

/* Helper untuk halaman lain */

function getCurrentUser() {
    try {
        const raw = sessionStorage.getItem("lms_user");
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

function requireAuth(expectedRole) {
    const user = getCurrentUser();

    if (!user) {
        window.location.href = "../login.html";
        return null;
    }

    if (expectedRole && user.role !== expectedRole) {
        window.location.href = "../login.html";
        return null;
    }

    return user;
}

function logout() {
    sessionStorage.removeItem("lms_user");
    window.location.href = "../login.html";
}