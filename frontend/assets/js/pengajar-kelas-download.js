/*
================================================================================
LOKASI FILE : frontend/assets/js/pengajar-kelas-download.js
GUNA FILE   : Helper unduh file untuk halaman kelas pengajar
              (frontend/pengajar/kelas-detail.html).
              KelasDownload.saveFromUrl(url, namaCadangan)
                - Mengambil file dari endpoint unduh backend (session login ikut terkirim)
                - Jika berhasil, file disimpan lewat dialog unduhan browser dengan
                  nama dari header Content-Disposition (atau namaCadangan)
                - Jika gagal (file hilang, tidak punya akses, dll.), melempar Error
                  berisi pesan dari server sehingga bisa ditampilkan di halaman,
                  bukan membuka tab kosong berisi JSON
              Dipakai oleh pengajar-kelas-materi.js dan pengajar-kelas-tugas.js.
              Harus dimuat SEBELUM kedua file tersebut.
================================================================================
*/

const KelasDownload = (function () {
    "use strict";

    // Mengambil nama file dari header: Content-Disposition: attachment; filename="abc.pdf"
    function readFileName(headerValue) {
        if (!headerValue) return "";
        const match = /filename="?([^";]+)"?/i.exec(headerValue);
        return match ? match[1] : "";
    }

    async function readErrorMessage(response) {
        try {
            const data = await response.json();
            if (data && data.message) return data.message;
        } catch (error) {
            // Respons bukan JSON; pakai pesan umum di bawah.
        }
        return "Gagal mengunduh file (HTTP " + response.status + ").";
    }

    async function saveFromUrl(url, fallbackName) {
        let response;

        try {
            response = await fetch(url, { method: "GET", credentials: "include" });
        } catch (error) {
            throw new Error("Tidak dapat terhubung ke server.");
        }

        if (!response.ok) {
            throw new Error(await readErrorMessage(response));
        }

        // Endpoint unduh mengirim file; JSON berarti server mengirim pesan error.
        const contentType = response.headers.get("Content-Type") || "";
        if (contentType.indexOf("application/json") !== -1) {
            throw new Error(await readErrorMessage(response));
        }

        const blob = await response.blob();
        const fileName = readFileName(response.headers.get("Content-Disposition")) ||
            fallbackName || "file";

        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = objectUrl;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();

        // Beri waktu browser memulai unduhan sebelum alamat sementara dilepas.
        setTimeout(function () {
            URL.revokeObjectURL(objectUrl);
        }, 1000);
    }

    return { saveFromUrl: saveFromUrl };
})();