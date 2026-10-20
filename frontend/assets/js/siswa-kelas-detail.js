/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kelas-detail.js
GUNA FILE   : Kerangka halaman satu kelas siswa (frontend/siswa/kelas-detail.html).
              - Membaca id_kelas dari URL dan memastikan siswa terdaftar di kelas itu
                lewat GET ../../backend/api/siswa/kelas/list.php
              - Menampilkan nama kelas
              - Mengatur perpindahan tab (materi, tugas, kuis, absensi)
              - Menyediakan objek KelasPage untuk file tab:
                    KelasPage.getIdKelas()          -> id kelas dari URL (angka) atau null
                    KelasPage.getNamaKelas()        -> nama kelas
                    KelasPage.registerTab(nama, fn) -> fn dijalankan sekali saat tab pertama dibuka
              Harus dimuat SEBELUM siswa-kelas-materi/tugas/kuis/absensi.js.
================================================================================
*/

const KelasPage = (function () {
    "use strict";

    const API_KELAS = "../../backend/api/siswa/kelas/list.php";
    const TAB_NAMES = ["materi", "tugas", "kuis", "absensi"];
    const DEFAULT_TAB = "materi";

    const params = new URLSearchParams(window.location.search);
    const parsedIdKelas = parseInt(params.get("id_kelas"), 10);
    const idKelas = parsedIdKelas > 0 ? parsedIdKelas : null;

    const tabLoaders = {};
    const loadedTabs = {};
    let kelasInfo = null;
    let accessGranted = false;

    function getIdKelas() {
        return idKelas;
    }

    function getNamaKelas() {
        return kelasInfo ? kelasInfo.nama_kelas : "";
    }

    function registerTab(name, loadFunction) {
        tabLoaders[name] = loadFunction;
    }

    function readInitialTab() {
        const requestedTab = params.get("tab");
        return TAB_NAMES.indexOf(requestedTab) !== -1 ? requestedTab : DEFAULT_TAB;
    }

    // Menyimpan tab aktif di URL supaya refresh halaman tetap membuka tab yang sama.
    function updateUrl(tabName) {
        const url = new URL(window.location.href);
        url.searchParams.set("tab", tabName);
        window.history.replaceState(null, "", url);
    }

    function activateTab(tabName) {
        if (!accessGranted || TAB_NAMES.indexOf(tabName) === -1) return;

        TAB_NAMES.forEach(function (name) {
            const button = document.getElementById("tab-" + name);
            const panel = document.getElementById("panel-" + name);
            const isActive = name === tabName;

            button.setAttribute("aria-selected", isActive ? "true" : "false");
            button.classList.toggle("kelas-tabs__button--active", isActive);
            button.tabIndex = isActive ? 0 : -1;
            panel.hidden = !isActive;
        });

        updateUrl(tabName);

        // Data tab dimuat sekali saat pertama dibuka agar tidak memanggil API yang tidak perlu.
        if (!loadedTabs[tabName] && typeof tabLoaders[tabName] === "function") {
            loadedTabs[tabName] = true;
            tabLoaders[tabName]();
        }
    }

    function bindTabEvents() {
        const tabList = document.getElementById("kelasTabs");

        tabList.addEventListener("click", function (event) {
            const button = event.target.closest("[data-tab]");
            if (button) activateTab(button.getAttribute("data-tab"));
        });

        // Navigasi keyboard: panah kiri/kanan berpindah antar tab.
        tabList.addEventListener("keydown", function (event) {
            if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") return;

            const currentButton = event.target.closest("[data-tab]");
            if (!currentButton) return;

            const currentIndex = TAB_NAMES.indexOf(currentButton.getAttribute("data-tab"));
            const step = event.key === "ArrowRight" ? 1 : -1;
            const nextIndex = (currentIndex + step + TAB_NAMES.length) % TAB_NAMES.length;

            activateTab(TAB_NAMES[nextIndex]);
            document.getElementById("tab-" + TAB_NAMES[nextIndex]).focus();
        });
    }

    function showAccessError(message) {
        document.getElementById("kelasTitle").textContent = "Kelas tidak dapat dibuka";
        document.getElementById("kelasTabs").hidden = true;
        SiswaCommon.showMessage("kelasMessage", message, "error");
    }

    async function loadKelasInfo() {
        try {
            const result = await apiRequest(API_KELAS, { method: "GET" });
            const kelasList = result.data || [];

            kelasInfo = kelasList.find(function (item) {
                return parseInt(item.id_kelas, 10) === idKelas;
            });

            if (!kelasInfo) {
                throw new Error("Anda tidak terdaftar di kelas ini.");
            }

            accessGranted = true;
            document.getElementById("kelasTitle").textContent = kelasInfo.nama_kelas;
            document.title = kelasInfo.nama_kelas + " - Siswa LMS";

            activateTab(readInitialTab());
        } catch (error) {
            showAccessError(error.message || "Gagal memuat data kelas.");
        }
    }

    function start() {
        if (!SiswaCommon.initPage()) return;

        if (idKelas === null) {
            showAccessError("ID kelas tidak valid. Buka kelas dari daftar kelas.");
            return;
        }

        bindTabEvents();
        loadKelasInfo();
    }

    // File tab didaftarkan lebih dulu; start() baru jalan setelah semua script termuat.
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start);
    } else {
        start();
    }

    return {
        getIdKelas: getIdKelas,
        getNamaKelas: getNamaKelas,
        registerTab: registerTab
    };
})();