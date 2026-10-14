<?php

// POST /api/soal_kuis/update.php
// Role: pengajar. Mengubah satu soal (seluruh isi diganti). Hanya saat kuis 'draft' dan belum dikerjakan.
// Body JSON: id_soal, tipe_soal, soal, poin (wajib), khusus pilihan_ganda: pilihan_a..pilihan_d, kunci_jawaban.
// Nomor urutan diubah lewat reorder.php, bukan di sini.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$data = readJsonBody();

$idSoal = parsePositiveInt($data['id_soal'] ?? null);

if ($idSoal === null) {
    sendError("ID soal tidak valid.", 400);
}

$soalInput = parseSoalInput($data);

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    $soal = findOwnedSoal($db, $idSoal, $idPengajar);

    if ($soal === null) {
        rollbackAndSendError($db, "Soal tidak ditemukan.", 404);
    }

    $idKuis = (int) $soal['id_kuis'];

    // Kunci baris kuis lalu periksa penguncian dengan data terbaru.
    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Soal tidak ditemukan.", 404);
    }

    $lockError = getSoalLockError($db, $kuis);

    if ($lockError !== null) {
        rollbackAndSendError($db, $lockError, 409);
    }

    $statement = $db->prepare(
        "UPDATE soal_kuis
         SET tipe_soal = :tipe_soal, soal = :soal,
             pilihan_a = :pilihan_a, pilihan_b = :pilihan_b, pilihan_c = :pilihan_c, pilihan_d = :pilihan_d,
             kunci_jawaban = :kunci_jawaban, poin = :poin
         WHERE id_soal = :id_soal AND id_kuis = :id_kuis"
    );
    $statement->execute([
        ":tipe_soal" => $soalInput['tipe_soal'],
        ":soal" => $soalInput['soal'],
        ":pilihan_a" => $soalInput['pilihan']['a'],
        ":pilihan_b" => $soalInput['pilihan']['b'],
        ":pilihan_c" => $soalInput['pilihan']['c'],
        ":pilihan_d" => $soalInput['pilihan']['d'],
        ":kunci_jawaban" => $soalInput['kunci_jawaban'],
        ":poin" => number_format($soalInput['poin'], 2, '.', ''),
        ":id_soal" => $idSoal,
        ":id_kuis" => $idKuis,
    ]);

    $db->commit();

    sendSuccess("Soal berhasil diperbarui.", ["id_soal" => $idSoal]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'soal_kuis/update');
}