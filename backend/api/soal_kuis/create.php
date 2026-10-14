<?php

// POST /api/soal_kuis/create.php
// Role: pengajar. Menambah satu soal ke kuis miliknya yang masih 'draft' dan belum dikerjakan siswa.
// Body JSON: id_kuis, tipe_soal ('pilihan_ganda' | 'isian'), soal, poin (opsional, default 1),
//            urutan (opsional, default otomatis di akhir),
//            khusus pilihan_ganda: pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban (A-D).

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

$soalInput = parseSoalInput($data, 1.00);

// Urutan boleh dikirim manual; jika kosong, backend menaruhnya di akhir.
$urutan = null;
$urutanInput = $data['urutan'] ?? null;

if ($urutanInput !== null && $urutanInput !== '') {
    $urutan = parsePositiveInt($urutanInput);

    if ($urutan === null || $urutan > SOAL_MAX_URUTAN) {
        sendError("Urutan harus bilangan bulat 1 sampai " . SOAL_MAX_URUTAN . ".", 400);
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Kunci baris kuis agar tidak bertabrakan dengan penerbitan atau perubahan soal lain.
    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Kuis tidak ditemukan.", 404);
    }

    $lockError = getSoalLockError($db, $kuis);

    if ($lockError !== null) {
        rollbackAndSendError($db, $lockError, 409);
    }

    $statement = $db->prepare(
        "SELECT COUNT(*) AS jumlah, COALESCE(MAX(urutan), 0) AS urutan_terakhir
         FROM soal_kuis WHERE id_kuis = :id_kuis"
    );
    $statement->execute([":id_kuis" => $idKuis]);
    $ringkasan = $statement->fetch(PDO::FETCH_ASSOC);

    if ((int) $ringkasan['jumlah'] >= KUIS_MAX_SOAL) {
        rollbackAndSendError($db, "Jumlah soal maksimal " . KUIS_MAX_SOAL . " per kuis.", 400);
    }

    if ($urutan === null) {
        $urutan = (int) $ringkasan['urutan_terakhir'] + 1;

        if ($urutan > SOAL_MAX_URUTAN) {
            rollbackAndSendError($db, "Nomor urutan soal melewati batas. Susun ulang urutan soal terlebih dahulu.", 400);
        }
    }

    $statement = $db->prepare(
        "INSERT INTO soal_kuis (id_kuis, tipe_soal, soal, pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin, urutan)
         VALUES (:id_kuis, :tipe_soal, :soal, :pilihan_a, :pilihan_b, :pilihan_c, :pilihan_d, :kunci_jawaban, :poin, :urutan)"
    );
    $statement->execute([
        ":id_kuis" => $idKuis,
        ":tipe_soal" => $soalInput['tipe_soal'],
        ":soal" => $soalInput['soal'],
        ":pilihan_a" => $soalInput['pilihan']['a'],
        ":pilihan_b" => $soalInput['pilihan']['b'],
        ":pilihan_c" => $soalInput['pilihan']['c'],
        ":pilihan_d" => $soalInput['pilihan']['d'],
        ":kunci_jawaban" => $soalInput['kunci_jawaban'],
        ":poin" => number_format($soalInput['poin'], 2, '.', ''),
        ":urutan" => $urutan,
    ]);

    $idSoal = (int) $db->lastInsertId();
    $db->commit();

    sendSuccess("Soal berhasil ditambahkan.", ["id_soal" => $idSoal, "urutan" => $urutan], 201);
} catch (PDOException $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Unique key (id_kuis, urutan): nomor urutan itu sudah dipakai soal lain.
    if (isDuplicateKeyError($error)) {
        sendError("Nomor urutan tersebut sudah dipakai soal lain.", 409);
    }

    sendServerError($error, 'soal_kuis/create');
}