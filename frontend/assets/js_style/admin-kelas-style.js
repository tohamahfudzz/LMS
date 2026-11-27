
/*
================================================================================
LOKASI FILE : frontend/assets/js_style/admin-kelas-style.js
GUNA FILE   : Interaksi tampilan halaman Data Kelas Admin.
              Tidak mengubah logika utama admin.js maupun
              admin-kelas-tahun.js.
================================================================================
*/

document.addEventListener("DOMContentLoaded", function () {

    // =========================================================================
    // 1. SIDEBAR MOBILE
    // =========================================================================

    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("appSidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    function bukaSidebar() {
        if (!sidebar || !sidebarOverlay) return;

        sidebar.classList.add("open");
        sidebarOverlay.classList.add("show");

        if (menuToggle) {
            menuToggle.setAttribute("aria-expanded", "true");
        }
    }

    function tutupSidebar() {
        if (!sidebar || !sidebarOverlay) return;

        sidebar.classList.remove("open");
        sidebarOverlay.classList.remove("show");

        if (menuToggle) {
            menuToggle.setAttribute("aria-expanded", "false");
        }
    }

    if (menuToggle && sidebar) {
        menuToggle.addEventListener("click", function () {
            if (sidebar.classList.contains("open")) {
                tutupSidebar();
            } else {
                bukaSidebar();
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener("click", tutupSidebar);
    }

    // Tutup sidebar setelah memilih menu navigasi.
    if (sidebar) {
        const menuLinks = sidebar.querySelectorAll(".lms-sidebar-link");

        menuLinks.forEach(function (link) {
            link.addEventListener("click", tutupSidebar);
        });
    }

    // =========================================================================
    // 2. MODAL KONFIRMASI LOGOUT
    // =========================================================================

    const openLogoutModal = document.getElementById("openLogoutModal");
    const logoutModalBackdrop = document.getElementById("logoutModalBackdrop");

    const btnBatalLogout = document.getElementById("btnBatalLogout");
    const btnKonfirmasiLogout = document.getElementById("btnKonfirmasiLogout");

    // Tombol logout asli yang diproses oleh admin.js.
    const realLogoutButton = document.getElementById("logoutButton");

    function bukaModalLogout() {
        if (!logoutModalBackdrop) return;

        logoutModalBackdrop.classList.add("show");
        logoutModalBackdrop.setAttribute("aria-hidden", "false");

        if (btnBatalLogout) {
            btnBatalLogout.focus();
        }
    }

    function tutupModalLogout() {
        if (!logoutModalBackdrop) return;

        logoutModalBackdrop.classList.remove("show");
        logoutModalBackdrop.setAttribute("aria-hidden", "true");

        if (openLogoutModal) {
            openLogoutModal.focus();
        }
    }

    if (openLogoutModal) {
        openLogoutModal.addEventListener("click", bukaModalLogout);
    }

    if (btnBatalLogout) {
        btnBatalLogout.addEventListener("click", tutupModalLogout);
    }

    // Klik area luar modal untuk menutup modal.
    if (logoutModalBackdrop) {
        logoutModalBackdrop.addEventListener("click", function (event) {
            if (event.target === logoutModalBackdrop) {
                tutupModalLogout();
            }
        });
    }

    // Konfirmasi menggunakan tombol logout asli dari admin.js.
    if (btnKonfirmasiLogout && realLogoutButton) {
        btnKonfirmasiLogout.addEventListener("click", function () {
            realLogoutButton.click();
        });
    }

    // =========================================================================
    // 3. TOMBOL ESCAPE
    // =========================================================================

    document.addEventListener("keydown", function (event) {

        if (event.key !== "Escape") return;

        // Modal logout diprioritaskan jika sedang terbuka.
        if (
            logoutModalBackdrop &&
            logoutModalBackdrop.classList.contains("show")
        ) {
            tutupModalLogout();
            return;
        }

        // Jika modal tidak terbuka, tutup sidebar.
        tutupSidebar();
    });

    // =========================================================================
    // 4. PENYESUAIAN SIDEBAR SAAT UKURAN LAYAR BERUBAH
    // =========================================================================

    window.addEventListener("resize", function () {
        if (window.innerWidth > 991.98) {
            tutupSidebar();
        }
    });

});
