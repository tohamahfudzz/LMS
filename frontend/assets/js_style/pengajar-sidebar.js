
/*
================================================================================
LOKASI FILE : frontend/assets/js_style/pengajar-sidebar.js
GUNA FILE   : Mengatur interaksi sidebar Dashboard Pengajar.
              Desktop : Sidebar dapat diciutkan dan diperluas.
              Mobile : Sidebar dapat dibuka dan ditutup.
================================================================================
*/

(function () {
    "use strict";

    const sidebar = document.getElementById("appSidebar");
    const toggleButton = document.getElementById("sidebarToggle");
    const backdrop = document.getElementById("sidebarBackdrop");

    // Pastikan seluruh elemen tersedia.
    if (!sidebar || !toggleButton || !backdrop) return;

    const body = document.body;

    const mobileBreakpoint = window.matchMedia(
        "(max-width: 991.98px)"
    );

    // ============================================================
    // MOBILE: BUKA SIDEBAR
    // ============================================================

    function openSidebar() {
        body.classList.add("sidebar-open");

        toggleButton.setAttribute("aria-expanded", "true");
        toggleButton.setAttribute(
            "aria-label",
            "Tutup menu navigasi"
        );
    }

    // ============================================================
    // MOBILE: TUTUP SIDEBAR
    // ============================================================

    function closeSidebar() {
        body.classList.remove("sidebar-open");

        toggleButton.setAttribute("aria-expanded", "false");
        toggleButton.setAttribute(
            "aria-label",
            "Buka menu navigasi"
        );
    }

    // ============================================================
    // DESKTOP: COLLAPSE / EXPAND SIDEBAR
    // ============================================================

    function toggleDesktopSidebar() {
        body.classList.toggle("sidebar-collapsed");

        const isCollapsed = body.classList.contains(
            "sidebar-collapsed"
        );

        toggleButton.setAttribute(
            "aria-expanded",
            String(!isCollapsed)
        );

        toggleButton.setAttribute(
            "aria-label",
            isCollapsed
                ? "Perluas sidebar"
                : "Ciutkan sidebar"
        );

        // Tambahkan tooltip pada menu ketika sidebar diciutkan.
        updateTooltips(isCollapsed);
    }

    function updateTooltips(isCollapsed) {
        const links = sidebar.querySelectorAll(
            ".lms-sidebar-link"
        );

        links.forEach(function (link) {
            const label = link.querySelector(
                "span:last-child"
            );

            if (isCollapsed && label) {
                link.setAttribute(
                    "title",
                    label.textContent.trim()
                );
            } else {
                link.removeAttribute("title");
            }
        });

        const logoutButton = document.getElementById(
            "logoutButton"
        );

        if (logoutButton) {
            if (isCollapsed) {
                logoutButton.setAttribute("title", "Keluar");
            } else {
                logoutButton.removeAttribute("title");
            }
        }
    }

    // ============================================================
    // TOMBOL TOGGLE
    // ============================================================

    toggleButton.addEventListener("click", function () {

        if (mobileBreakpoint.matches) {

            if (body.classList.contains("sidebar-open")) {
                closeSidebar();
            } else {
                openSidebar();
            }

        } else {
            toggleDesktopSidebar();
        }

    });

    // ============================================================
    // BACKDROP: TUTUP SIDEBAR
    // ============================================================

    backdrop.addEventListener("click", closeSidebar);

    // ============================================================
    // TUTUP SIDEBAR SETELAH MEMILIH MENU PADA MOBILE
    // ============================================================

    sidebar.querySelectorAll("a").forEach(function (link) {

        link.addEventListener("click", function () {

            if (mobileBreakpoint.matches) {
                closeSidebar();
            }

        });

    });

    // ============================================================
    // TOMBOL ESCAPE UNTUK MENUTUP SIDEBAR
    // ============================================================

    document.addEventListener("keydown", function (event) {

        if (
            event.key === "Escape" &&
            mobileBreakpoint.matches &&
            body.classList.contains("sidebar-open")
        ) {
            closeSidebar();
            toggleButton.focus();
        }

    });

    // ============================================================
    // PENYESUAIAN KETIKA UKURAN LAYAR BERUBAH
    // ============================================================

    mobileBreakpoint.addEventListener("change", function () {

        closeSidebar();

        // Hapus kondisi collapse ketika berpindah ukuran layar.
        body.classList.remove("sidebar-collapsed");

        updateTooltips(false);

        toggleButton.setAttribute("aria-expanded", "false");
        toggleButton.setAttribute(
            "aria-label",
            "Buka menu navigasi"
        );

    });

})();
