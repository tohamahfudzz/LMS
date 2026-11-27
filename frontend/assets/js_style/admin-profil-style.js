
/*
================================================================================
LOKASI FILE : frontend/assets/js_style/admin-profil-style.js
GUNA FILE   : Interaksi tampilan halaman Profil Admin.
              - Tab Informasi Akun dan Keamanan.
              - Sidebar mobile.
              - Modal konfirmasi logout.
              Tidak mengubah logika utama LMS.
================================================================================
*/

document.addEventListener("DOMContentLoaded", function () {

    // =========================================================
    // 1. TAB PROFIL
    // =========================================================

    const tabBtnEmail = document.getElementById("tabBtnEmail");
    const tabBtnKeamanan = document.getElementById("tabBtnKeamanan");

    const contentEmail = document.getElementById("contentTabEmail");
    const contentKeamanan = document.getElementById("contentTabKeamanan");

    function aktifkanTab(tabAktif) {
        if (
            !tabBtnEmail ||
            !tabBtnKeamanan ||
            !contentEmail ||
            !contentKeamanan
        ) {
            return;
        }

        const tabEmailAktif = tabAktif === "email";

        tabBtnEmail.classList.toggle("active", tabEmailAktif);
        tabBtnKeamanan.classList.toggle("active", !tabEmailAktif);

        tabBtnEmail.setAttribute("aria-selected", String(tabEmailAktif));
        tabBtnKeamanan.setAttribute("aria-selected", String(!tabEmailAktif));

        contentEmail.classList.toggle("d-none", !tabEmailAktif);
        contentKeamanan.classList.toggle("d-none", tabEmailAktif);

        contentEmail.hidden = !tabEmailAktif;
        contentKeamanan.hidden = tabEmailAktif;
    }

    if (tabBtnEmail) {
        tabBtnEmail.addEventListener("click", function () {
            aktifkanTab("email");
        });
    }

    if (tabBtnKeamanan) {
        tabBtnKeamanan.addEventListener("click", function () {
            aktifkanTab("keamanan");
        });
    }


    // =========================================================
    // 2. SIDEBAR MOBILE
    // =========================================================

    const toggleBtn = document.getElementById("menuToggle");
    const sidebar = document.getElementById("appSidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    function bukaSidebar() {
        if (!sidebar) return;

        sidebar.classList.add("open");

        if (sidebarOverlay) {
            sidebarOverlay.classList.add("show");
        }

        if (toggleBtn) {
            toggleBtn.setAttribute("aria-expanded", "true");
        }
    }

    function tutupSidebar() {
        if (!sidebar) return;

        sidebar.classList.remove("open");

        if (sidebarOverlay) {
            sidebarOverlay.classList.remove("show");
        }

        if (toggleBtn) {
            toggleBtn.setAttribute("aria-expanded", "false");
        }
    }

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener("click", function () {
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

    // Tutup sidebar setelah pengguna memilih menu.
    if (sidebar) {
        sidebar.querySelectorAll(".lms-sidebar-link").forEach(function (link) {
            link.addEventListener("click", tutupSidebar);
        });
    }

    // Tutup sidebar jika tombol Escape ditekan.
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            tutupSidebar();
            tutupModalLogout();
        }
    });


    // =========================================================
    // 3. MODAL KONFIRMASI LOGOUT
    // =========================================================

    const openModalBtn = document.getElementById("openLogoutModal");
    const modalBackdrop = document.getElementById("logoutModalBackdrop");

    const btnBatalLogout = document.getElementById("btnBatalLogout");
    const btnKonfirmasiLogout = document.getElementById("btnKonfirmasiLogout");

    const realLogoutBtn = document.getElementById("logoutButton");

    function bukaModalLogout() {
        if (!modalBackdrop) return;

        modalBackdrop.classList.add("show");
        modalBackdrop.setAttribute("aria-hidden", "false");

        if (btnBatalLogout) {
            btnBatalLogout.focus();
        }
    }

    function tutupModalLogout() {
        if (!modalBackdrop) return;

        modalBackdrop.classList.remove("show");
        modalBackdrop.setAttribute("aria-hidden", "true");
    }

    if (openModalBtn) {
        openModalBtn.addEventListener("click", bukaModalLogout);
    }

    if (btnBatalLogout) {
        btnBatalLogout.addEventListener("click", tutupModalLogout);
    }

    // Klik area luar modal untuk menutup modal.
    if (modalBackdrop) {
        modalBackdrop.addEventListener("click", function (event) {
            if (event.target === modalBackdrop) {
                tutupModalLogout();
            }
        });
    }

    // Jalankan tombol logout asli dari auth.js.
    if (btnKonfirmasiLogout && realLogoutBtn) {
        btnKonfirmasiLogout.addEventListener("click", function () {
            realLogoutBtn.click();
        });
    }

});
