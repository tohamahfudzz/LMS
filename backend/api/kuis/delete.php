<?php

// POST /api/kuis/delete.php
// Role: pengajar. Menghapus kuis miliknya sendiri (status apa pun) selama belum dikerjakan siswa.
// Soal kuis ikut terhapus otomatis (ON DELETE CASCADE).
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

    // Kunci baris kuis agar pengecekan percobaan dan penghapusan tidak disela proses lain.
    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Kuis tidak ditemukan.", 404);
    }

    if (kuisHasAttempts($db, $idKuis)) {
        rollbackAndSendError($db, "Kuis sudah dikerjakan siswa sehingga tidak dapat dihapus.", 409);
    }

    $statement = $db->prepare("DELETE FROM kuis WHERE id_kuis = :id_kuis AND id_pengajar = :id_pengajar");
    $statement->execute([
        ":id_kuis" => $idKuis,
        ":id_pengajar" => $idPengajar,
    ]);

    $db->commit();

    sendSuccess("Kuis berhasil dihapus.", ["id_kuis" => $idKuis]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Jaga-jaga: siswa sempat memulai kuis tepat saat dihapus (pelanggaran foreign key).
    if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
        sendError("Kuis sudah dikerjakan siswa sehingga tidak dapat dihapus.", 409);
    }

    sendServerError($error, 'kuis/delete');
}