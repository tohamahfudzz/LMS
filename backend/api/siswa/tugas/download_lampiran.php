<?php
/*
================================================================================
LOKASI FILE : backend/api/siswa/tugas/download_lampiran.php
GUNA FILE   : Siswa mengunduh file lampiran (soal) yang diunggah pengajar pada tugas
              di kelas yang ia ikuti.
              Method : GET
              Query  : ?id_tugas=1 (wajib)
              Role   : siswa (hanya tugas dari kelas yang terdaftar di kelas_siswa)
              Respons: file sebagai unduhan, atau JSON error bila gagal.
              Nama unduhan: Lampiran_<judul tugas>.<ekstensi>
              Dipakai tombol "Unduh soal" di tab Tugas
              (frontend/assets/js/siswa-kelas-tugas.js).
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

$rawIdTugas = $_GET['id_tugas'] ?? '';

if (!is_string($rawIdTugas) || !ctype_digit($rawIdTugas) || (int) $rawIdTugas < 1) {
    sendError('ID tugas tidak valid.', 400);
}

$idTugas = (int) $rawIdTugas;

$database = new Database();
$db = $database->getConnection();

try {
    // JOIN kelas_siswa memastikan siswa hanya bisa mengunduh tugas kelasnya sendiri.
    $statement = $db->prepare(
        'SELECT t.judul, t.file, t.waktu_mulai
           FROM tugas t
          INNER JOIN kelas_siswa ks
                  ON ks.id_kelas = t.id_kelas AND ks.id_siswa = :id_siswa
          WHERE t.id_tugas = :id_tugas
          LIMIT 1'
    );
    $statement->execute([
        ':id_siswa' => $idSiswa,
        ':id_tugas' => $idTugas,
    ]);
    $tugas = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$tugas) {
        sendError('Tugas tidak ditemukan atau Anda tidak memiliki akses.', 404);
    }

    // Tugas belum dimulai tidak boleh dibuka lebih awal (sama seperti aturan pengumpulan).
    if (!empty($tugas['waktu_mulai']) && strtotime($tugas['waktu_mulai']) > time()) {
        sendError('Tugas belum dimulai.', 403);
    }

    if (empty($tugas['file'])) {
        sendError('Tugas ini tidak memiliki file lampiran.', 404);
    }

    sendUploadedFile($tugas['file'], 'Lampiran_' . $tugas['judul']);
} catch (Throwable $error) {
    sendServerError($error, 'siswa/tugas/download_lampiran');
}