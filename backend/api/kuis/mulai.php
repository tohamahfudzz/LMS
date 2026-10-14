<?php

// POST /api/kuis/mulai.php
// Role: siswa. Memulai kuis: membuat satu percobaan lalu mengirim soal TANPA kunci jawaban.
// Body JSON: id_kuis.
// Identitas siswa selalu diambil dari session. nomor_percobaan ditentukan backend (selalu 1).
//
// Jaminan satu kali pengerjaan berlapis dua:
// 1. Kode: baris siswa dikunci (SELECT ... FOR UPDATE) di awal transaksi, sehingga permintaan mulai
//    dari siswa yang sama dijalankan satu per satu. Permintaan kedua baru memeriksa percobaan
//    setelah yang pertama selesai, jadi pasti melihat percobaan itu dan ditolak.
// 2. Database: unique key (id_kuis, id_siswa, nomor_percobaan) sebagai pengaman terakhir.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$data = readJsonBody();
$idKuis = parsePositiveInt($data['id_kuis'] ?? null);

if ($idKuis === null) {
    sendError("ID kuis tidak valid.", 400);
}

$pesanSudahMengerjakan = "Kamu sudah pernah mengerjakan kuis ini.";

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Kunci baris siswa ini SEBELUM membaca apa pun. Pembacaan berikutnya baru terjadi setelah
    // kunci didapat, sehingga data yang dilihat sudah mencakup percobaan dari permintaan lain
    // yang lebih dulu selesai. Hanya permintaan siswa yang sama yang saling menunggu.
    $statement = $db->prepare("SELECT id_siswa FROM siswa WHERE id_siswa = :id_siswa FOR UPDATE");
    $statement->execute([":id_siswa" => $idSiswa]);

    if ($statement->fetchColumn() === false) {
        rollbackAndSendError($db, "Akun siswa tidak ditemukan.", 403);
    }

    // JOIN ke kelas_siswa memastikan siswa memang terdaftar di kelas kuis ini.
    // Jadwal dihitung dengan jam database (NOW()), bukan jam browser.
    $statement = $db->prepare(
        "SELECT k.id_kuis, k.judul, k.durasi_menit, k.status,
                (k.waktu_mulai IS NULL OR k.waktu_mulai <= NOW()) AS sudah_dimulai,
                (k.waktu_selesai IS NULL OR k.waktu_selesai > NOW()) AS belum_berakhir
         FROM kuis k
         INNER JOIN kelas_siswa ks ON ks.id_kelas = k.id_kelas AND ks.id_siswa = :id_siswa
         WHERE k.id_kuis = :id_kuis
         LIMIT 1"
    );
    $statement->execute([":id_siswa" => $idSiswa, ":id_kuis" => $idKuis]);
    $kuis = $statement->fetch(PDO::FETCH_ASSOC);

    // Draft sengaja dijawab sama dengan "tidak ada" agar siswa tidak tahu kuis yang belum terbit.
    if ($kuis === false || $kuis['status'] === KUIS_STATUS_DRAFT) {
        rollbackAndSendError($db, "Kuis tidak ditemukan.", 404);
    }

    if ($kuis['status'] !== KUIS_STATUS_TERBIT) {
        rollbackAndSendError($db, "Kuis sudah ditutup.", 403);
    }

    if ((int) $kuis['sudah_dimulai'] !== 1) {
        rollbackAndSendError($db, "Kuis belum dimulai.", 403);
    }

    if ((int) $kuis['belum_berakhir'] !== 1) {
        rollbackAndSendError($db, "Waktu pengerjaan kuis sudah berakhir.", 403);
    }

    // Pemeriksaan percobaan yang sudah ada (aman dari permintaan bersamaan berkat kunci siswa di atas).
    $statement = $db->prepare(
        "SELECT 1 FROM percobaan_kuis WHERE id_kuis = :id_kuis AND id_siswa = :id_siswa LIMIT 1"
    );
    $statement->execute([":id_kuis" => $idKuis, ":id_siswa" => $idSiswa]);

    if ($statement->fetchColumn() !== false) {
        rollbackAndSendError($db, $pesanSudahMengerjakan, 409);
    }

    $soalList = fetchSoalForStudent($db, $idKuis);

    if (count($soalList) === 0) {
        rollbackAndSendError($db, "Kuis ini belum memiliki soal.", 403);
    }

    // Pengaman terakhir: unique key di database bila lapisan kode di atas entah bagaimana terlewati.
    try {
        $statement = $db->prepare(
            "INSERT INTO percobaan_kuis (id_kuis, id_siswa, nomor_percobaan, waktu_mulai, status)
             VALUES (:id_kuis, :id_siswa, 1, NOW(), :status)"
        );
        $statement->execute([
            ":id_kuis" => $idKuis,
            ":id_siswa" => $idSiswa,
            ":status" => PERCOBAAN_STATUS_BERLANGSUNG,
        ]);
    } catch (PDOException $error) {
        if (isDuplicateKeyError($error)) {
            rollbackAndSendError($db, $pesanSudahMengerjakan, 409);
        }

        throw $error;
    }

    $idPercobaan = (int) $db->lastInsertId();
    $db->commit();

    $timing = getAttemptTiming($db, $idPercobaan);

    sendSuccess(
        "Kuis dimulai.",
        [
            "id_percobaan" => $idPercobaan,
            "judul" => $kuis['judul'],
            "durasi_menit" => (int) $kuis['durasi_menit'],
            // Untuk timer tampilan saja; batas sebenarnya tetap ditegakkan backend.
            "sisa_detik" => $timing['sisa_detik'] ?? 0,
            "soal" => $soalList,
        ],
        201
    );
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'kuis/mulai');
}