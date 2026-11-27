
/*
================================================================================
LOKASI FILE : frontend/assets/js_style/admin-pengguna-style.js
GUNA FILE   : Interaksi tampilan halaman Data Pengguna Admin.
              - Toggle sidebar pada perangkat mobile.
              - Overlay sidebar.
              - Modal konfirmasi logout.
================================================================================
*/

(function () {
    "use strict";

    const sidebar = document.getElementById("appSidebar");
    const menuToggle = document.getElementById("menuToggle");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    const openLogoutModal = document.getElementById("openLogoutModal");
    const logoutModalBackdrop = document.getElementById("logoutModalBackdrop");
    const btnBatalLogout = document.getElementById("btnBatalLogout");
    const btnKonfirmasiLogout = document.getElementById("btnKonfirmasiLogout");
    const realLogoutButton = document.getElementById("logoutButton");

    // ================= SIDEBAR MOBILE =================

    function bukaSidebar() {
        if (!sidebar) return;

        sidebar.classList.add("open");

        if (sidebarOverlay) {
            sidebarOverlay.classList.add("show");
        }
    }

    function tutupSidebar() {
        if (!sidebar) return;

        sidebar.classList.remove("open");

        if (sidebarOverlay) {
            sidebarOverlay.classList.remove("show");
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener("click", function () {
            if (sidebar && sidebar.classList.contains("open")) {
                tutupSidebar();
            } else {
                bukaSidebar();
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener("click", tutupSidebar);
    }

    // Tutup sidebar setelah memilih menu pada perangkat mobile.
    if (sidebar) {
        const menuLinks = sidebar.querySelectorAll(".lms-sidebar-link");

        menuLinks.forEach(function (link) {
            link.addEventListener("click", tutupSidebar);
        });
    }

    // Tutup sidebar dengan tombol Escape.
    // Tombol Escape juga dapat menutup modal logout.
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            tutupSidebar();
            tutupModalLogout();
        }
    });

    // ================= MODAL LOGOUT =================

    function bukaModalLogout() {
        if (!logoutModalBackdrop) return;

        logoutModalBackdrop.classList.add("show");
    }

    function tutupModalLogout() {
        if (!logoutModalBackdrop) return;

        logoutModalBackdrop.classList.remove("show");
    }

    if (openLogoutModal) {
        openLogoutModal.addEventListener("click", bukaModalLogout);
    }

    if (btnBatalLogout) {
        btnBatalLogout.addEventListener("click", tutupModalLogout);
    }

    // Klik area gelap di luar kotak modal untuk menutup modal.
    if (logoutModalBackdrop) {
        logoutModalBackdrop.addEventListener("click", function (event) {
            if (event.target === logoutModalBackdrop) {
                tutupModalLogout();
            }
        });
    }

    // Tetap gunakan tombol logout asli dari admin.js.
    if (btnKonfirmasiLogout && realLogoutButton) {
        btnKonfirmasiLogout.addEventListener("click", function () {
            realLogoutButton.click();
        });
    }

    // Jika ukuran layar kembali ke desktop, tutup sidebar mobile.
    window.addEventListener("resize", function () {
        if (window.innerWidth > 991) {
            tutupSidebar();
        }
    });
})();
