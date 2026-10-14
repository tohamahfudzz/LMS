<?php

// POST /api/kuis/status.php
// Role: pengajar. Mengubah status kuis miliknya.
// Body JSON: id_kuis, status ('tertutup' | 'terbit' | 'draft').
//
// Perpindahan yang diizinkan:
//   terbit   -> tertutup : kapan saja. Siswa yang masih mengerjakan tidak bisa mengumpulkan lagi
//                          (status kuis harus 'terbit' saat pengumpulan, sesuai spesifikasi).
//   tertutup -> terbit   : dibuka kembali, dengan validasi yang sama seperti penerbitan.
//   terbit/tertutup -> draft : hanya jika belum ada siswa yang mengerjakan.
//   draft    -> terbit   : lewat publish.php.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$data = readJsonBody();

$idKuis = parsePositiveInt($data['id_kuis'] ?? null);
$statusBaru = readString($data, 'status');

if ($idKuis === null) {
    sendError("ID kuis tidak valid.", 400);
}

if (!in_array($statusBaru, [KUIS_STATUS_DRAFT, KUIS_STATUS_TERBIT, KUIS_STATUS_TERTUTUP], true)) {
    sendError("Status harus 'draft', 'terbit', atau 'tertutup'.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Kuis tidak ditemukan.", 404);
    }

    $statusLama = $kuis['status'];

    if ($statusLama === $statusBaru) {
        rollbackAndSendError($db, "Kuis sudah berstatus " . $statusBaru . ".", 409);
    }

    if ($statusLama === KUIS_STATUS_DRAFT) {
        $pesan = $statusBaru === KUIS_STATUS_TERBIT
            ? "Gunakan penerbitan (publish) untuk menerbitkan kuis draft."
            : "Kuis draft belum diterbitkan.";
        rollbackAndSendError($db, $pesan, 409);
    }

    if ($statusBaru === KUIS_STATUS_DRAFT && kuisHasAttempts($db, $idKuis)) {
        rollbackAndSendError($db, "Kuis sudah dikerjakan siswa, tidak dapat dikembalikan ke draft.", 409);
    }

    if ($statusBaru === KUIS_STATUS_TERBIT) {
        $validationError = getPublishValidationError($db, $kuis);

        if ($validationError !== null) {
            rollbackAndSendError($db, $validationError, 400);
        }
    }

    $statement = $db->prepare("UPDATE kuis SET status = :status_baru WHERE id_kuis = :id_kuis");
    $statement->execute([":status_baru" => $statusBaru, ":id_kuis" => $idKuis]);

    // Untuk penutupan, beri tahu berapa percobaan yang sedang berjalan agar pengajar sadar dampaknya.
    $berlangsung = 0;

    if ($statusBaru === KUIS_STATUS_TERTUTUP) {
        $statement = $db->prepare(
            "SELECT COUNT(*) FROM percobaan_kuis WHERE id_kuis = :id_kuis AND status = :status"
        );
        $statement->execute([":id_kuis" => $idKuis, ":status" => PERCOBAAN_STATUS_BERLANGSUNG]);
        $berlangsung = (int) $statement->fetchColumn();
    }

    $db->commit();

    $pesan = "Status kuis diubah menjadi " . $statusBaru . ".";

    if ($berlangsung > 0) {
        $pesan .= " Ada " . $berlangsung . " siswa yang masih mengerjakan dan tidak dapat mengumpulkan lagi.";
    }

    sendSuccess($pesan, [
        "id_kuis" => $idKuis,
        "status" => $statusBaru,
        "percobaan_berlangsung" => $berlangsung,
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'kuis/status');
}