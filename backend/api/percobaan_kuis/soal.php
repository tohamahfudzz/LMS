<?php

// GET /api/percobaan_kuis/soal.php?id_kuis=...
// Role: siswa. Mengambil ulang soal untuk percobaan yang SEDANG berlangsung
// (misalnya setelah halaman dimuat ulang). Tidak pernah membuat percobaan baru.
// Jika batas waktu sudah lewat, percobaan ditutup di sini lalu permintaan ditolak.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('GET');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$idKuis = parsePositiveInt($_GET['id_kuis'] ?? null);

if ($idKuis === null) {
    sendError("ID kuis tidak valid.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    // Percobaan dicari dari id_siswa di session, jadi percobaan siswa lain tidak mungkin terbaca.
    $statement = $db->prepare(
        "SELECT p.id_percobaan, p.status AS status_percobaan,
                k.judul, k.durasi_menit, k.status AS status_kuis
         FROM percobaan_kuis p
         INNER JOIN kuis k ON k.id_kuis = p.id_kuis
         INNER JOIN kelas_siswa ks ON ks.id_kelas = k.id_kelas AND ks.id_siswa = p.id_siswa
         WHERE p.id_kuis = :id_kuis AND p.id_siswa = :id_siswa
         LIMIT 1"
    );
    $statement->execute([":id_kuis" => $idKuis, ":id_siswa" => $idSiswa]);
    $percobaan = $statement->fetch(PDO::FETCH_ASSOC);

    if ($percobaan === false) {
        sendError("Kamu belum memulai kuis ini.", 404);
    }

    $idPercobaan = (int) $percobaan['id_percobaan'];

    if ($percobaan['status_percobaan'] !== PERCOBAAN_STATUS_BERLANGSUNG) {
        sendError("Percobaan kuis ini sudah ditutup.", 409);
    }

    $timing = getAttemptTiming($db, $idPercobaan);

    if ($timing === null || $timing['sudah_habis']) {
        closeExpiredAttempt($db, $idPercobaan, $idKuis);
        sendError("Waktu pengerjaan sudah habis. Percobaan telah ditutup.", 403);
    }

    if ($percobaan['status_kuis'] !== KUIS_STATUS_TERBIT) {
        sendError("Kuis sudah ditutup.", 403);
    }

    sendSuccess("Percobaan sedang berlangsung.", [
        "id_percobaan" => $idPercobaan,
        "judul" => $percobaan['judul'],
        "durasi_menit" => (int) $percobaan['durasi_menit'],
        "sisa_detik" => $timing['sisa_detik'],
        "soal" => fetchSoalForStudent($db, $idKuis),
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'percobaan_kuis/soal');
}