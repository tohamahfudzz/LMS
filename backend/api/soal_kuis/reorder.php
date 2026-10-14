<?php

// POST /api/soal_kuis/reorder.php
// Role: pengajar. Menyusun ulang urutan seluruh soal sebuah kuis (hasilnya bernomor 1..n tanpa celah).
// Body JSON: id_kuis, urutan: [id_soal, id_soal, ...] -> HARUS memuat semua soal kuis tepat sekali.
// Catatan: siswa tetap melihat pilihan ganda lebih dulu, baru isian; urutan ini berlaku di dalam tiap tipe.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$data = readJsonBody();

$idKuis = parsePositiveInt($data['id_kuis'] ?? null);
$urutanInput = $data['urutan'] ?? null;

if ($idKuis === null) {
    sendError("ID kuis tidak valid.", 400);
}

if (!is_array($urutanInput) || count($urutanInput) === 0 || count($urutanInput) > KUIS_MAX_SOAL) {
    sendError("Daftar urutan soal tidak valid.", 400);
}

$daftarIdSoal = [];

foreach ($urutanInput as $item) {
    $idSoal = parsePositiveInt($item);

    if ($idSoal === null || in_array($idSoal, $daftarIdSoal, true)) {
        sendError("Daftar urutan soal tidak valid atau berisi soal ganda.", 400);
    }

    $daftarIdSoal[] = $idSoal;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Kuis tidak ditemukan.", 404);
    }

    $lockError = getSoalLockError($db, $kuis);

    if ($lockError !== null) {
        rollbackAndSendError($db, $lockError, 409);
    }

    // Daftar yang dikirim harus persis sama dengan himpunan soal milik kuis ini.
    $statement = $db->prepare("SELECT id_soal FROM soal_kuis WHERE id_kuis = :id_kuis");
    $statement->execute([":id_kuis" => $idKuis]);
    $idSoalKuis = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

    $sama = count($idSoalKuis) === count($daftarIdSoal)
        && count(array_diff($idSoalKuis, $daftarIdSoal)) === 0;

    if (!$sama) {
        rollbackAndSendError($db, "Daftar urutan harus memuat semua soal kuis ini, tepat satu kali.", 400);
    }

    $statement = $db->prepare("UPDATE soal_kuis SET urutan = :urutan WHERE id_soal = :id_soal AND id_kuis = :id_kuis");

    // Dua tahap agar unique key (id_kuis, urutan) tidak bentrok di tengah jalan.
    // Tahap 1: pindahkan ke nomor sementara di atas SOAL_MAX_URUTAN. Tahap 2: beri nomor final.
    foreach ($daftarIdSoal as $indeks => $idSoal) {
        $statement->execute([":urutan" => 30000 + $indeks + 1, ":id_soal" => $idSoal, ":id_kuis" => $idKuis]);
    }

    foreach ($daftarIdSoal as $indeks => $idSoal) {
        $statement->execute([":urutan" => $indeks + 1, ":id_soal" => $idSoal, ":id_kuis" => $idKuis]);
    }

    $db->commit();

    sendSuccess("Urutan soal berhasil diperbarui.", ["jumlah_soal" => count($daftarIdSoal)]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'soal_kuis/reorder');
}