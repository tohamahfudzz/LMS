<?php
/*
================================================================================
LOKASI FILE : backend/api/siswa/materi/download.php
GUNA FILE   : Siswa mengunduh file materi dari kelas yang ia ikuti.
              Method : GET
              Query  : ?id_materi=1 (wajib)
              Role   : siswa (hanya materi dari kelas yang terdaftar di kelas_siswa)
              Respons: file sebagai unduhan, atau JSON error bila gagal.
              Dipakai tombol "Unduh file" di tab Materi
              (frontend/assets/js/siswa-kelas-materi.js).
              Bergantung pada: functions/download.php (sendUploadedFile).
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/download.php';

initApi('GET');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$rawIdMateri = $_GET['id_materi'] ?? '';

if (!is_string($rawIdMateri) || !ctype_digit($rawIdMateri) || (int) $rawIdMateri < 1) {
    sendError('ID materi tidak valid.', 400);
}

$idMateri = (int) $rawIdMateri;

$database = new Database();
$db = $database->getConnection();

try {
    // JOIN kelas_siswa memastikan siswa hanya bisa mengunduh materi kelasnya sendiri.
    $statement = $db->prepare(
        'SELECT m.judul, m.file
           FROM materi m
          INNER JOIN kelas_siswa ks
                  ON ks.id_kelas = m.id_kelas AND ks.id_siswa = :id_siswa
          WHERE m.id_materi = :id_materi
          LIMIT 1'
    );
    $statement->execute([
        ':id_siswa' => $idSiswa,
        ':id_materi' => $idMateri,
    ]);
    $materi = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$materi) {
        sendError('Materi tidak ditemukan atau Anda tidak memiliki akses.', 404);
    }

    if (empty($materi['file'])) {
        sendError('Materi ini tidak memiliki file untuk diunduh.', 404);
    }

    sendUploadedFile($materi['file'], $materi['judul']);
} catch (Throwable $error) {
    sendServerError($error, 'siswa/materi/download');
}