/*
================================================================================
LOKASI FILE : frontend/assets/js/auth.js
GUNA FILE   : Login + redirect role.
              Setiap error dari backend (401, dll.) ditampilkan di #loginMessage
              agar user langsung paham, bukan hanya di console/Network.
================================================================================
*/

(function () {
    "use strict";

    // Dari frontend/login.html → naik 1 level ke root project
    const LOGIN_ENDPOINT = "../backend/api/auth/login.php";

    const form = document.getElementById("loginForm");
    const messageEl = document.getElementById("loginMessage");
    const submitBtn = document.getElementById("loginButton");

    if (!form || !messageEl) return;

    function showMessage(text, type) {
        messageEl.hidden = false;
        messageEl.textContent = text;

        // Warna sederhana tanpa file CSS terpisah
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

    function validateForm(kode, password, role) {
        if (!kode || kode.trim().length < 2) {
            return "Kode wajib diisi (minimal 2 karakter).";
        }
        if (!password || password.length < 4) {
            return "Password wajib diisi (minimal 4 karakter).";
        }
        if (!role || ["admin", "pengajar", "siswa"].indexOf(role) === -1) {
            return "Silakan pilih role (Admin / Pengajar / Siswa).";
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
        const role = document.getElementById("role").value;

        const validationError = validateForm(kode, password, role);
        if (validationError) {
            showMessage(validationError, "error");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Memproses...";

        try {
            const result = await apiRequest(LOGIN_ENDPOINT, {
                method: "POST",
                body: { kode: kode, password: password, role: role }
            });

            if (result.success) {
                if (result.session) {
                    sessionStorage.setItem("lms_user", JSON.stringify({
                        id: result.session.id,
                        role: result.session.role,
                        nama: result.session.nama
                    }));
                }
                showMessage(result.message || "Login berhasil. Mengalihkan...", "success");
                setTimeout(function () {
                    redirectByRole(role);
                }, 700);
            } else {
                // Backend mengembalikan success:false (jarang, biasanya lewat throw)
                showMessage(result.message || "Login gagal.", "error");
                submitBtn.disabled = false;
                submitBtn.textContent = "Masuk";
            }
        } catch (error) {
            // Termasuk 401: "Kode atau password salah."
            showMessage(
                error.message || "Login gagal. Periksa kode, password, dan role.",
                "error"
            );
            submitBtn.disabled = false;
            submitBtn.textContent = "Masuk";
        }

        document.getElementById("password").value = "";
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