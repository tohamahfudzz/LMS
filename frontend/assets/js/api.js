/*
================================================================================
LOKASI FILE : frontend/assets/js/api.js
GUNA FILE   : Helper generik untuk memanggil API backend.
              - Menyeragamkan fetch (JSON + credentials)
              - Menangani error jaringan & response non-JSON
              - Digunakan oleh auth.js dan halaman lain
================================================================================
*/

const API_BASE_URL = "http://localhost/LMS";

/**
 * Melakukan request ke API backend.
 * @param {string} url        Path relatif ke endpoint, contoh: "../../backend/api/auth/login.php"
 * @param {object} options    Opsi fetch standar + body object (akan di-JSON.stringify)
 * @returns {Promise<object>} Response JSON dari server
 */
async function apiRequest(url, options = {}) {
    const apiPathIndex = url.indexOf("/backend/api/");
    const apiUrl = apiPathIndex !== -1
        ? `${API_BASE_URL}${url.substring(apiPathIndex)}`
        : url;
    const config = {
        method: options.method || "GET",
        credentials: "include", // penting: kirim cookie session
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            ...(options.headers || {})
        }
    };

    // Body hanya untuk method yang mengirim data
    if (options.body && typeof options.body === "object") {
        config.body = JSON.stringify(options.body);
    }

    let response;
    try {
        response = await fetch(apiUrl, config);
    } catch (networkError) {
        throw new Error("Tidak dapat terhubung ke server. Periksa koneksi Anda.");
    }

    let data;
    try {
        data = await response.json();
    } catch (parseError) {
        throw new Error("Respons server tidak valid.");
    }

    // Jika status HTTP tidak sukses, lempar error dengan pesan dari backend
    if (!response.ok) {
        const message = (data && data.message) ? data.message : "Terjadi kesalahan pada server.";
        const error = new Error(message);
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
}
