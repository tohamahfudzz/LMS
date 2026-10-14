<?php

// GET /api/kuis/detail_siswa.php?id_kuis=...
// Role: siswa. Detail satu kuis (judul, deskripsi, jadwal, durasi, ketersediaan, status, nilai final)
// TANPA isi soal dan tanpa kunci jawaban. Soal baru dikirim lewat mulai.php atau soal.php.

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
    $kuisList = fetchKuisForStudent($db, $idSiswa, $idKuis);

    // Kuis di kelas lain dan kuis draft sama-sama dijawab "tidak ditemukan".
    if (count($kuisList) === 0) {
        sendError("Kuis tidak ditemukan.", 404);
    }

    sendSuccess("Detail kuis berhasil diambil.", $kuisList[0]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'kuis/detail_siswa');
}