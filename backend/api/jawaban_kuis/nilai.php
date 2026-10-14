<?php

// POST /api/jawaban_kuis/nilai.php
// Role: pengajar. Memberi (atau memperbaiki) nilai satu jawaban ISIAN pada kuis miliknya.
// Body JSON: id_jawaban, nilai (0 sampai poin soal, paling banyak 2 desimal).
// Identitas penilai selalu dari session. Setelah menilai, nilai_manual dan nilai_total
// percobaan dihitung ulang; nilai_total baru terisi saat tidak ada lagi jawaban 'menunggu'.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$data = readJsonBody();

$idJawaban = parsePositiveInt($data['id_jawaban'] ?? null);
$nilai = parseNilai($data['nilai'] ?? null);

if ($idJawaban === null) {
    sendError("ID jawaban tidak valid.", 400);
}

if ($nilai === null) {
    sendError("Nilai harus angka 0 atau lebih dengan paling banyak 2 desimal.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Kepemilikan: jawaban harus berasal dari kuis milik pengajar yang login.
    $statement = $db->prepare(
        "SELECT j.id_percobaan, j.jawaban, so.tipe_soal, so.poin
         FROM jawaban_kuis j
         INNER JOIN soal_kuis so ON so.id_soal = j.id_soal
         INNER JOIN percobaan_kuis p ON p.id_percobaan = j.id_percobaan
         INNER JOIN kuis k ON k.id_kuis = p.id_kuis
         WHERE j.id_jawaban = :id_jawaban AND k.id_pengajar = :id_pengajar
         LIMIT 1"
    );
    $statement->execute([":id_jawaban" => $idJawaban, ":id_pengajar" => $idPengajar]);
    $jawaban = $statement->fetch(PDO::FETCH_ASSOC);

    if ($jawaban === false) {
        rollbackAndSendError($db, "Jawaban tidak ditemukan.", 404);
    }

    $idPercobaan = (int) $jawaban['id_percobaan'];

    // Kunci baris percobaan agar dua penilaian bersamaan tidak saling menimpa hasil hitungan nilai.
    $statement = $db->prepare("SELECT status FROM percobaan_kuis WHERE id_percobaan = :id_percobaan FOR UPDATE");
    $statement->execute([":id_percobaan" => $idPercobaan]);
    $statusPercobaan = $statement->fetchColumn();

    if ($statusPercobaan !== PERCOBAAN_STATUS_SELESAI) {
        rollbackAndSendError($db, "Percobaan ini belum dikumpulkan, belum bisa dinilai.", 409);
    }

    if ($jawaban['tipe_soal'] !== SOAL_TIPE_ISIAN) {
        rollbackAndSendError($db, "Hanya jawaban isian yang dinilai manual.", 400);
    }

    if ($jawaban['jawaban'] === null) {
        rollbackAndSendError($db, "Siswa tidak menjawab soal ini, nilainya otomatis 0.", 409);
    }

    // Nilai tidak boleh melebihi poin soal (dibandingkan dalam sen agar tepat).
    $nilaiSen = (int) round($nilai * 100);
    $poinSen = (int) round((float) $jawaban['poin'] * 100);

    if ($nilaiSen > $poinSen) {
        rollbackAndSendError($db, "Nilai tidak boleh melebihi poin soal (maksimal " . number_format($poinSen / 100, 2, '.', '') . ").", 400);
    }

    $statement = $db->prepare(
        "UPDATE jawaban_kuis
         SET nilai_diperoleh = :nilai, status_penilaian = :status, id_penilai = :id_penilai, waktu_dinilai = NOW()
         WHERE id_jawaban = :id_jawaban"
    );
    $statement->execute([
        ":nilai" => number_format($nilai, 2, '.', ''),
        ":status" => PENILAIAN_STATUS_SELESAI,
        ":id_penilai" => $idPengajar,
        ":id_jawaban" => $idJawaban,
    ]);

    $hasil = recalculateAttemptScore($db, $idPercobaan);
    $db->commit();

    sendSuccess(
        $hasil['jumlah_menunggu'] === 0
            ? "Nilai tersimpan. Seluruh penilaian selesai, nilai akhir sudah dihitung."
            : "Nilai tersimpan. Masih ada " . $hasil['jumlah_menunggu'] . " jawaban menunggu penilaian.",
        $hasil
    );
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'jawaban_kuis/nilai');
}