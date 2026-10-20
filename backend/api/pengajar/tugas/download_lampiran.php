<?php
/*
================================================================================
LOKASI FILE : backend/api/pengajar/tugas/download_lampiran.php
GUNA FILE   : Pengajar mengunduh file lampiran (soal) yang ia unggah saat membuat
              tugas. Berbeda dengan pengajar/tugas/download.php yang mengunduh file
              JAWABAN siswa.
              Method : GET
              Query  : ?id_tugas=1 (wajib)
              Role   : pengajar (hanya tugas miliknya)
              Respons: file sebagai unduhan, atau JSON error bila gagal.
              Nama unduhan: Lampiran_<judul tugas>.<ekstensi>
              Dipakai tombol "Unduh lampiran" di tab Tugas
              (frontend/assets/js/pengajar-kelas-tugas.js).
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/download.php';

initApi('GET');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$rawIdTugas = $_GET['id_tugas'] ?? '';

if (!is_string($rawIdTugas) || !ctype_digit($rawIdTugas) || (int) $rawIdTugas < 1) {
    sendError('ID tugas tidak valid.', 400);
}

$idTugas = (int) $rawIdTugas;

$database = new Database();
$db = $database->getConnection();

try {
    // Kondisi id_pengajar memastikan pengajar tidak bisa mengunduh tugas milik pengajar lain.
    $statement = $db->prepare(
        'SELECT judul, file FROM tugas
         WHERE id_tugas = :id_tugas AND id_pengajar = :id_pengajar
         LIMIT 1'
    );
    $statement->execute([
        ':id_tugas' => $idTugas,
        ':id_pengajar' => $idPengajar,
    ]);
    $tugas = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$tugas) {
        sendError('Tugas tidak ditemukan atau Anda tidak memiliki akses.', 404);
    }

    if (empty($tugas['file'])) {
        sendError('Tugas ini tidak memiliki file lampiran.', 404);
    }

    sendUploadedFile($tugas['file'], 'Lampiran_' . $tugas['judul']);
} catch (Throwable $error) {
    sendServerError($error, 'pengajar/tugas/download_lampiran');
}
