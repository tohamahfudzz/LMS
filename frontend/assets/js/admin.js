/*
================================================================================
LOKASI FILE : frontend/assets/js/admin.js
GUNA FILE   : Logika bersama halaman admin:
              - Proteksi role, logout, tampil nama
              - Buat kelas / pengajar / siswa
              - Mengirim email pengajar dan siswa ke API
              - Assign pengajar & siswa (dropdown)
              - Muat daftar ke tabel & select
================================================================================
*/

(function () {
    "use strict";

    const API = {
        kelasCreate:    "../../backend/api/kelas/create.php",
        kelasList:      "../../backend/api/admin/kelas/list.php",
        pengajarCreate: "../../backend/api/pengajar/create.php",
        pengajarList:   "../../backend/api/admin/pengajar/list.php",
        pengajarAssign: "../../backend/api/admin/pengajar/assign.php",
        siswaCreate:    "../../backend/api/siswa/create.php",
        siswaList:      "../../backend/api/admin/siswa/list.php",
        siswaAssign:    "../../backend/api/admin/siswa/assign.php"
    };

    // ---------- Proteksi ----------
    const user = requireAuth("admin");
    if (!user) return;

    const welcomeEl = document.getElementById("welcomeText");
    if (welcomeEl) {
        welcomeEl.textContent = "Admin: " + (user.nama || "-");
    }

    const logoutBtn = document.getElementById("logoutButton");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", function () {
            logout();
        });
    }

    // ---------- Helper UI ----------
    function showMessage(elementId, text, type) {
        const el = document.getElementById(elementId);
        if (!el) return;

        el.hidden = false;
        el.textContent = text;
        el.dataset.type = type || "error";
    }

    function hideMessage(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;

        el.hidden = true;
        el.textContent = "";
    }

    function setButtonLoading(button, isLoading, labelIdle) {
        if (!button) return;

        button.disabled = isLoading;
        button.textContent = isLoading ? "Memproses..." : labelIdle;
    }

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text == null ? "" : String(text);
        return div.innerHTML;
    }

    /**
     * Mengisi elemen select dengan data dari API.
     */
    function fillSelect(selectId, items, valueKey, labelFn, selectedValue) {
        const select = document.getElementById(selectId);
        if (!select) return;

        if (!items || items.length === 0) {
            select.innerHTML = '<option value="">-- Tidak ada data --</option>';
            return;
        }

        let html = '<option value="">-- Pilih --</option>';

        items.forEach(function (item) {
            const value = item[valueKey];
            const selected =
                selectedValue != null &&
                String(value) === String(selectedValue)
                    ? " selected"
                    : "";

            html +=
                '<option value="' + escapeHtml(value) + '"' + selected + ">" +
                escapeHtml(labelFn(item)) +
                "</option>";
        });

        select.innerHTML = html;
    }

    // ---------- Muat daftar kelas ----------
    async function loadKelasList() {
        const tbody = document.getElementById("kelasTableBody");
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="3">Memuat data...</td></tr>';

        try {
            const result = await apiRequest(API.kelasList, {
                method: "GET"
            });

            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="3">Belum ada data kelas.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + escapeHtml(item.nama_kelas) + "</td>" +
                    "<td>" +
                    '<a href="anggota-kelas.html?id_kelas=' +
                    encodeURIComponent(item.id_kelas) +
                    '">Atur Anggota</a>' +
                    "</td>" +
                    "</tr>"
                );
            }).join("");

        } catch (error) {
            tbody.innerHTML =
                '<tr><td colspan="3">Gagal memuat: ' +
                escapeHtml(error.message) +
                "</td></tr>";
        }
    }

    // ---------- Muat daftar pengajar ----------
    async function loadPengajarList() {
        const tbody = document.getElementById("pengajarTableBody");
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5">Memuat data...</td></tr>';

        try {
            const result = await apiRequest(API.pengajarList, {
                method: "GET"
            });

            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="5">Belum ada data pengajar.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + escapeHtml(item.kode_pengajar) + "</td>" +
                    "<td>" + escapeHtml(item.nama) + "</td>" +
                    "<td>" + escapeHtml(item.email || "-") + "</td>" +
                    "<td>Pengajar</td>" +
                    "</tr>"
                );
            }).join("");

        } catch (error) {
            tbody.innerHTML =
                '<tr><td colspan="5">Gagal memuat: ' +
                escapeHtml(error.message) +
                "</td></tr>";
        }
    }

    // ---------- Muat daftar siswa ----------
    async function loadSiswaList() {
        const tbody = document.getElementById("siswaTableBody");
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5">Memuat data...</td></tr>';

        try {
            const result = await apiRequest(API.siswaList, {
                method: "GET"
            });

            const rows = result.data || [];

            if (rows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="5">Belum ada data siswa.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(function (item, index) {
                return (
                    "<tr>" +
                    "<td>" + (index + 1) + "</td>" +
                    "<td>" + escapeHtml(item.kode_siswa) + "</td>" +
                    "<td>" + escapeHtml(item.nama) + "</td>" +
                    "<td>" + escapeHtml(item.email || "-") + "</td>" +
                    "<td>Siswa</td>" +
                    "</tr>"
                );
            }).join("");

        } catch (error) {
            tbody.innerHTML =
                '<tr><td colspan="5">Gagal memuat: ' +
                escapeHtml(error.message) +
                "</td></tr>";
        }
    }

    // ---------- Muat dropdown anggota kelas ----------
    async function loadAssignDropdowns() {
        const kelasSelect = document.getElementById("idKelas");
        const pengajarSelect = document.getElementById("idPengajar");
        const siswaSelect = document.getElementById("idSiswa");

        if (!kelasSelect && !pengajarSelect && !siswaSelect) return;

        const params = new URLSearchParams(window.location.search);
        const idKelasFromUrl = params.get("id_kelas");

        try {
            if (kelasSelect) {
                const result = await apiRequest(API.kelasList, {
                    method: "GET"
                });

                fillSelect(
                    "idKelas",
                    result.data || [],
                    "id_kelas",
                    function (item) {
                        return item.nama_kelas + " (ID: " + item.id_kelas + ")";
                    },
                    idKelasFromUrl
                );
            }

            if (pengajarSelect) {
                const result = await apiRequest(API.pengajarList, {
                    method: "GET"
                });

                fillSelect(
                    "idPengajar",
                    result.data || [],
                    "id_pengajar",
                    function (item) {
                        return item.nama + " (" + item.kode_pengajar + ")";
                    },
                    null
                );
            }

            if (siswaSelect) {
                const result = await apiRequest(API.siswaList, {
                    method: "GET"
                });

                fillSelect(
                    "idSiswa",
                    result.data || [],
                    "id_siswa",
                    function (item) {
                        return item.nama + " (" + item.kode_siswa + ")";
                    },
                    null
                );
            }

        } catch (error) {
            if (kelasSelect) {
                kelasSelect.innerHTML =
                    '<option value="">Gagal memuat kelas</option>';
            }

            if (pengajarSelect) {
                pengajarSelect.innerHTML =
                    '<option value="">Gagal memuat pengajar</option>';
            }

            if (siswaSelect) {
                siswaSelect.innerHTML =
                    '<option value="">Gagal memuat siswa</option>';
            }
        }
    }

    // ---------- Jalankan loader ----------
    loadKelasList();
    loadPengajarList();
    loadSiswaList();
    loadAssignDropdowns();

    // ---------- Buat kelas ----------
    const createKelasForm = document.getElementById("createKelasForm");

    if (createKelasForm) {
        createKelasForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            hideMessage("kelasMessage");

            const namaKelas = document.getElementById("namaKelas").value.trim();
            const btn = document.getElementById("createKelasButton");

            if (!namaKelas || namaKelas.length < 2) {
                showMessage(
                    "kelasMessage",
                    "Nama kelas wajib diisi (minimal 2 karakter).",
                    "error"
                );
                return;
            }

            setButtonLoading(btn, true, "Simpan Kelas");

            try {
                const result = await apiRequest(API.kelasCreate, {
                    method: "POST",
                    body: {
                        nama_kelas: namaKelas
                    }
                });

                showMessage(
                    "kelasMessage",
                    result.message || "Kelas berhasil ditambahkan.",
                    "success"
                );

                createKelasForm.reset();
                loadKelasList();

            } catch (error) {
                showMessage(
                    "kelasMessage",
                    error.message || "Gagal menambahkan kelas.",
                    "error"
                );
            } finally {
                setButtonLoading(btn, false, "Simpan Kelas");
            }
        });
    }

    // ---------- Buat pengajar ----------
    const createPengajarForm =
        document.getElementById("createPengajarForm");

    if (createPengajarForm) {
        createPengajarForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            hideMessage("pengajarMessage");

            const kode = document.getElementById("kodePengajar").value.trim();
            const nama = document.getElementById("namaPengajar").value.trim();
            const email = document.getElementById("emailPengajar").value.trim();
            const password = document.getElementById("passwordPengajar").value;
            const btn = document.getElementById("createPengajarButton");

            if (!kode || !nama || !email || !password) {
                showMessage(
                    "pengajarMessage",
                    "Semua kolom wajib diisi.",
                    "error"
                );
                return;
            }

            if (email.length > 255 ||
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showMessage(
                    "pengajarMessage",
                    "Format email tidak valid.",
                    "error"
                );
                return;
            }

            if (password.length < 4) {
                showMessage(
                    "pengajarMessage",
                    "Password minimal 4 karakter.",
                    "error"
                );
                return;
            }

            setButtonLoading(btn, true, "Simpan Pengajar");

            try {
                const result = await apiRequest(API.pengajarCreate, {
                    method: "POST",
                    body: {
                        kode_pengajar: kode,
                        nama: nama,
                        email: email,
                        password: password
                    }
                });

                showMessage(
                    "pengajarMessage",
                    result.message || "Pengajar berhasil ditambahkan.",
                    "success"
                );

                createPengajarForm.reset();
                loadPengajarList();

            } catch (error) {
                showMessage(
                    "pengajarMessage",
                    error.message || "Gagal menambahkan pengajar.",
                    "error"
                );
            } finally {
                setButtonLoading(btn, false, "Simpan Pengajar");
                document.getElementById("passwordPengajar").value = "";
            }
        });
    }

    // ---------- Buat siswa ----------
    const createSiswaForm = document.getElementById("createSiswaForm");

    if (createSiswaForm) {
        createSiswaForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            hideMessage("siswaMessage");

            const kode = document.getElementById("kodeSiswa").value.trim();
            const nama = document.getElementById("namaSiswa").value.trim();
            const email = document.getElementById("emailSiswa").value.trim();
            const password = document.getElementById("passwordSiswa").value;
            const btn = document.getElementById("createSiswaButton");

            if (!kode || !nama || !email || !password) {
                showMessage(
                    "siswaMessage",
                    "Semua kolom wajib diisi.",
                    "error"
                );
                return;
            }

            if (email.length > 255 ||
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showMessage(
                    "siswaMessage",
                    "Format email tidak valid.",
                    "error"
                );
                return;
            }

            if (password.length < 4) {
                showMessage(
                    "siswaMessage",
                    "Password minimal 4 karakter.",
                    "error"
                );
                return;
            }

            setButtonLoading(btn, true, "Simpan Siswa");

            try {
                const result = await apiRequest(API.siswaCreate, {
                    method: "POST",
                    body: {
                        kode_siswa: kode,
                        nama: nama,
                        email: email,
                        password: password
                    }
                });

                showMessage(
                    "siswaMessage",
                    result.message || "Siswa berhasil ditambahkan.",
                    "success"
                );

                createSiswaForm.reset();
                loadSiswaList();

            } catch (error) {
                showMessage(
                    "siswaMessage",
                    error.message || "Gagal menambahkan siswa.",
                    "error"
                );
            } finally {
                setButtonLoading(btn, false, "Simpan Siswa");
                document.getElementById("passwordSiswa").value = "";
            }
        });
    }

    // ---------- Assign pengajar ----------
    const assignPengajarForm =
        document.getElementById("assignPengajarForm");

    if (assignPengajarForm) {
        assignPengajarForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            hideMessage("assignPengajarMessage");

            const idKelas = parseInt(
                document.getElementById("idKelas").value,
                10
            );

            const idPengajar = parseInt(
                document.getElementById("idPengajar").value,
                10
            );

            const btn = document.getElementById("assignPengajarButton");

            if (!idKelas || idKelas < 1) {
                showMessage(
                    "assignPengajarMessage",
                    "Silakan pilih kelas.",
                    "error"
                );
                return;
            }

            if (!idPengajar || idPengajar < 1) {
                showMessage(
                    "assignPengajarMessage",
                    "Silakan pilih pengajar.",
                    "error"
                );
                return;
            }

            setButtonLoading(btn, true, "Assign Pengajar");

            try {
                const result = await apiRequest(API.pengajarAssign, {
                    method: "POST",
                    body: {
                        id_kelas: idKelas,
                        id_pengajar: idPengajar
                    }
                });

                showMessage(
                    "assignPengajarMessage",
                    result.message || "Pengajar berhasil dimasukkan ke kelas.",
                    "success"
                );

                document.getElementById("idPengajar").value = "";

            } catch (error) {
                showMessage(
                    "assignPengajarMessage",
                    error.message || "Gagal assign pengajar.",
                    "error"
                );
            } finally {
                setButtonLoading(btn, false, "Assign Pengajar");
            }
        });
    }

    // ---------- Assign siswa ----------
    const assignSiswaForm = document.getElementById("assignSiswaForm");

    if (assignSiswaForm) {
        assignSiswaForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            hideMessage("assignSiswaMessage");

            const idKelas = parseInt(
                document.getElementById("idKelas").value,
                10
            );

            const idSiswa = parseInt(
                document.getElementById("idSiswa").value,
                10
            );

            const btn = document.getElementById("assignSiswaButton");

            if (!idKelas || idKelas < 1) {
                showMessage(
                    "assignSiswaMessage",
                    "Silakan pilih kelas.",
                    "error"
                );
                return;
            }

            if (!idSiswa || idSiswa < 1) {
                showMessage(
                    "assignSiswaMessage",
                    "Silakan pilih siswa.",
                    "error"
                );
                return;
            }

            setButtonLoading(btn, true, "Assign Siswa");

            try {
                const result = await apiRequest(API.siswaAssign, {
                    method: "POST",
                    body: {
                        id_kelas: idKelas,
                        id_siswa: idSiswa
                    }
                });

                showMessage(
                    "assignSiswaMessage",
                    result.message || "Siswa berhasil dimasukkan ke kelas.",
                    "success"
                );

                document.getElementById("idSiswa").value = "";

            } catch (error) {
                showMessage(
                    "assignSiswaMessage",
                    error.message || "Gagal assign siswa.",
                    "error"
                );
            } finally {
                setButtonLoading(btn, false, "Assign Siswa");
            }
        });
    }

})();
