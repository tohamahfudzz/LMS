(function () {
    "use strict";

    const FORGOT_PASSWORD_ENDPOINT =
        "../../backend/api/auth/forgot_password.php";
    const RESET_PASSWORD_ENDPOINT =
        "../../backend/api/auth/reset_password.php";
    const RESET_PASSWORD_PAGE = "reset-password.html";
    const RESET_EMAIL_STORAGE_KEY = "lmsResetEmail";

    function showMessage(messageElement, message, success) {
        messageElement.hidden = false;
        messageElement.textContent = message;
        messageElement.classList.toggle("auth-message--success", success);
        messageElement.classList.toggle("auth-message--error", !success);
    }

    function setButtonBusy(button, busy, defaultText) {
        button.disabled = busy;
        button.textContent = busy ? "Memproses..." : defaultText;
    }

    // Email disimpan sementara agar halaman reset terisi otomatis.
    // Hanya berlaku selama tab terbuka; kode pemulihan tidak pernah disimpan.
    function saveResetEmail(email) {
        try {
            sessionStorage.setItem(RESET_EMAIL_STORAGE_KEY, email);
        } catch (error) {
            console.error(error);
        }
    }

    function loadResetEmail() {
        try {
            return sessionStorage.getItem(RESET_EMAIL_STORAGE_KEY) || "";
        } catch (error) {
            console.error(error);
            return "";
        }
    }

    function clearResetEmail() {
        try {
            sessionStorage.removeItem(RESET_EMAIL_STORAGE_KEY);
        } catch (error) {
            console.error(error);
        }
    }

    function initForgotPasswordForm(forgotPasswordForm) {
        const messageElement = document.getElementById("forgotPasswordMessage");
        const submitButton = document.getElementById("forgotPasswordButton");

        forgotPasswordForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            messageElement.hidden = true;

            const email = document.getElementById("email").value.trim();

            if (!email) {
                showMessage(messageElement, "Alamat email wajib diisi.", false);
                return;
            }

            setButtonBusy(submitButton, true, "Kirim Kode");

            try {
                await apiRequest(FORGOT_PASSWORD_ENDPOINT, {
                    method: "POST",
                    body: { email: email }
                });

                saveResetEmail(email);
                window.location.assign(RESET_PASSWORD_PAGE);
            } catch (error) {
                console.error(error);
                showMessage(messageElement, error.message, false);
                setButtonBusy(submitButton, false, "Kirim Kode");
            }
        });
    }

    function initResetPasswordForm(resetPasswordForm) {
        const messageElement = document.getElementById("resetPasswordMessage");
        const submitButton = document.getElementById("resetPasswordButton");
        const emailInput = document.getElementById("email");
        const tokenInput = document.getElementById("token");

        emailInput.value = loadResetEmail();

        if (emailInput.value) {
            tokenInput.focus();
        }

        resetPasswordForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            messageElement.hidden = true;

            const email = emailInput.value.trim();
            const token = tokenInput.value.trim().toUpperCase();
            const passwordBaru = document.getElementById("password_baru").value;

            if (!email || !token) {
                showMessage(
                    messageElement,
                    "Email dan kode pemulihan wajib diisi.",
                    false
                );
                return;
            }

            if (passwordBaru.length < 8) {
                showMessage(
                    messageElement,
                    "Kata sandi baru harus memiliki minimal 8 karakter.",
                    false
                );
                return;
            }

            setButtonBusy(submitButton, true, "Simpan Kata Sandi");

            try {
                const result = await apiRequest(RESET_PASSWORD_ENDPOINT, {
                    method: "POST",
                    body: {
                        email: email,
                        token: token,
                        password_baru: passwordBaru
                    }
                });

                clearResetEmail();
                resetPasswordForm.reset();
                showMessage(messageElement, result.message, true);
            } catch (error) {
                console.error(error);
                showMessage(messageElement, error.message, false);
            } finally {
                setButtonBusy(submitButton, false, "Simpan Kata Sandi");
            }
        });
    }

    const forgotPasswordForm = document.getElementById("forgotPasswordForm");

    if (forgotPasswordForm) {
        initForgotPasswordForm(forgotPasswordForm);
    }

    const resetPasswordForm = document.getElementById("resetPasswordForm");

    if (resetPasswordForm) {
        initResetPasswordForm(resetPasswordForm);
    }
})();