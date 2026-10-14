<?php

// GET /api/percobaan_kuis/list.php?id_kuis=...
// Role: pengajar. Daftar siswa yang mengerjakan sebuah kuis MILIK pengajar yang login,
// lengkap dengan status, nilai, dan jumlah jawaban isian yang masih menunggu penilaian.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('GET');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$idKuis = parsePositiveInt($_GET['id_kuis'] ?? null);

if ($idKuis === null) {
    sendError("ID kuis tidak valid.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $kuis = findOwnedKuis($db, $idKuis, $idPengajar);

    if ($kuis === null) {
        sendError("Kuis tidak ditemukan.", 404);
    }

    // Percobaan yang waktunya habis tetapi belum dikumpulkan ditutup dulu agar daftar akurat.
    closeExpiredAttemptsForKuis($db, $idKuis);

    // Kolom password siswa sengaja tidak pernah dipilih.
    $statement = $db->prepare(
        "SELECT p.id_percobaan, p.id_siswa, s.kode_siswa, s.nama,
                p.waktu_mulai, p.waktu_selesai, p.status,
                p.nilai_otomatis, p.nilai_manual, p.nilai_total,
                (SELECT COUNT(*) FROM jawaban_kuis j
                  WHERE j.id_percobaan = p.id_percobaan AND j.status_penilaian = :status_menunggu) AS jumlah_menunggu
         FROM percobaan_kuis p
         INNER JOIN siswa s ON s.id_siswa = p.id_siswa
         WHERE p.id_kuis = :id_kuis
         ORDER BY s.nama ASC"
    );
    $statement->execute([":status_menunggu" => PENILAIAN_STATUS_MENUNGGU, ":id_kuis" => $idKuis]);

    $percobaanList = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $percobaanList[] = [
            "id_percobaan" => (int) $row['id_percobaan'],
            "id_siswa" => (int) $row['id_siswa'],
            "kode_siswa" => $row['kode_siswa'],
            "nama" => $row['nama'],
            "waktu_mulai" => $row['waktu_mulai'],
            "waktu_selesai" => $row['waktu_selesai'],
            "status" => $row['status'],
            "nilai_otomatis" => (float) $row['nilai_otomatis'],
            "nilai_manual" => (float) $row['nilai_manual'],
            "nilai_total" => $row['nilai_total'] === null ? null : (float) $row['nilai_total'],
            "jumlah_menunggu" => (int) $row['jumlah_menunggu'],
        ];
    }

    sendSuccess("Daftar percobaan berhasil diambil.", [
        "kuis" => [
            "id_kuis" => (int) $kuis['id_kuis'],
            "judul" => $kuis['judul'],
            "status" => $kuis['status'],
        ],
        "percobaan" => $percobaanList,
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'percobaan_kuis/list');
}