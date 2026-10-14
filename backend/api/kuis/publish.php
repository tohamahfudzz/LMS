<?php

// POST /api/kuis/publish.php
// Role: pengajar. Menerbitkan kuis miliknya (draft -> terbit) setelah seluruh konfigurasi divalidasi.
// Body JSON: id_kuis.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$data = readJsonBody();
$idKuis = parsePositiveInt($data['id_kuis'] ?? null);

if ($idKuis === null) {
    sendError("ID kuis tidak valid.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Kuis tidak ditemukan.", 404);
    }

    if ($kuis['status'] !== KUIS_STATUS_DRAFT) {
        rollbackAndSendError($db, "Hanya kuis berstatus draft yang dapat diterbitkan.", 409);
    }

    $validationError = getPublishValidationError($db, $kuis);

    if ($validationError !== null) {
        rollbackAndSendError($db, $validationError, 400);
    }

    $statement = $db->prepare("UPDATE kuis SET status = :status_baru WHERE id_kuis = :id_kuis AND status = :status_lama");
    $statement->execute([
        ":status_baru" => KUIS_STATUS_TERBIT,
        ":id_kuis" => $idKuis,
        ":status_lama" => KUIS_STATUS_DRAFT,
    ]);

    $db->commit();

    sendSuccess("Kuis berhasil diterbitkan.", ["id_kuis" => $idKuis, "status" => KUIS_STATUS_TERBIT]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'kuis/publish');
}