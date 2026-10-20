/*
================================================================================
LOKASI FILE : frontend/assets/js/siswa-kuis-nav.js
GUNA FILE   : Mengatur tautan "kembali" di halaman mengerjakan kuis
              (frontend/siswa/kuis-kerjakan.html).
              Jika URL membawa id_kelas (dari tab Kuis di kelas-detail.html), tautan
              mengarah ke tab Kuis kelas tersebut. Jika tidak, tetap ke daftar kelas.
================================================================================
*/

(function () {
    "use strict";

    const link = document.getElementById("backToKelasLink");
    if (!link) return;

    const params = new URLSearchParams(window.location.search);
    const idKelas = parseInt(params.get("id_kelas"), 10);

    if (idKelas > 0) {
        link.href = "kelas-detail.html?id_kelas=" + encodeURIComponent(idKelas) + "&tab=kuis";
        link.textContent = "Kembali ke kelas";
    }
})();