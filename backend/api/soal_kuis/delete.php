<?php

// POST /api/soal_kuis/delete.php
// Role: pengajar. Menghapus satu soal. Hanya saat kuis 'draft' dan belum dikerjakan siswa.
// Body JSON: id_soal. (Memakai POST karena operasi ini mengubah data.)

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

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    $soal = findOwnedSoal($db, $idSoal, $idPengajar);

    if ($soal === null) {
        rollbackAndSendError($db, "Soal tidak ditemukan.", 404);
    }

    $idKuis = (int) $soal['id_kuis'];
    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Soal tidak ditemukan.", 404);
    }

    $lockError = getSoalLockError($db, $kuis);

    if ($lockError !== null) {
        rollbackAndSendError($db, $lockError, 409);
    }

    $statement = $db->prepare("DELETE FROM soal_kuis WHERE id_soal = :id_soal AND id_kuis = :id_kuis");
    $statement->execute([":id_soal" => $idSoal, ":id_kuis" => $idKuis]);

    $db->commit();

    sendSuccess("Soal berhasil dihapus.", ["id_soal" => $idSoal]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'soal_kuis/delete');
}