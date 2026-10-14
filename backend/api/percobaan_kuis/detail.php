<?php

// GET /api/percobaan_kuis/detail.php?id_percobaan=...
// Role: pengajar. Rincian jawaban satu siswa pada kuis MILIK pengajar yang login.
// Berisi soal, kunci jawaban, jawaban siswa, nilai, dan status penilaian per soal.
// Kunci jawaban hanya dikirim ke pengajar pemilik kuis, tidak pernah ke siswa.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('GET');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$idPercobaan = parsePositiveInt($_GET['id_percobaan'] ?? null);

if ($idPercobaan === null) {
    sendError("ID percobaan tidak valid.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    // Kepemilikan: percobaan harus berasal dari kuis milik pengajar ini.
    $statement = $db->prepare(
        "SELECT p.id_kuis
         FROM percobaan_kuis p
         INNER JOIN kuis k ON k.id_kuis = p.id_kuis
         WHERE p.id_percobaan = :id_percobaan AND k.id_pengajar = :id_pengajar
         LIMIT 1"
    );
    $statement->execute([":id_percobaan" => $idPercobaan, ":id_pengajar" => $idPengajar]);
    $idKuis = $statement->fetchColumn();

    if ($idKuis === false) {
        sendError("Percobaan tidak ditemukan.", 404);
    }

    // Jika waktunya sudah habis tetapi belum dikumpulkan, tutup dulu agar datanya final.
    closeExpiredAttempt($db, $idPercobaan, (int) $idKuis);

    $statement = $db->prepare(
        "SELECT p.id_percobaan, p.status, p.waktu_mulai, p.waktu_selesai,
                p.nilai_otomatis, p.nilai_manual, p.nilai_total,
                s.id_siswa, s.kode_siswa, s.nama, k.id_kuis, k.judul
         FROM percobaan_kuis p
         INNER JOIN kuis k ON k.id_kuis = p.id_kuis
         INNER JOIN siswa s ON s.id_siswa = p.id_siswa
         WHERE p.id_percobaan = :id_percobaan
         LIMIT 1"
    );
    $statement->execute([":id_percobaan" => $idPercobaan]);
    $percobaan = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT j.id_jawaban, so.id_soal, so.urutan, so.tipe_soal, so.soal,
                so.pilihan_a, so.pilihan_b, so.pilihan_c, so.pilihan_d,
                so.kunci_jawaban, so.poin,
                j.jawaban, j.nilai_diperoleh, j.status_penilaian, j.id_penilai, j.waktu_dinilai
         FROM jawaban_kuis j
         INNER JOIN soal_kuis so ON so.id_soal = j.id_soal
         WHERE j.id_percobaan = :id_percobaan
         ORDER BY CASE so.tipe_soal WHEN 'pilihan_ganda' THEN 0 ELSE 1 END, so.urutan ASC"
    );
    $statement->execute([":id_percobaan" => $idPercobaan]);

    $jawabanList = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $jawaban = [
            "id_jawaban" => (int) $row['id_jawaban'],
            "id_soal" => (int) $row['id_soal'],
            "urutan" => (int) $row['urutan'],
            "tipe_soal" => $row['tipe_soal'],
            "soal" => $row['soal'],
            "poin" => (float) $row['poin'],
            "jawaban" => $row['jawaban'],
            "nilai_diperoleh" => $row['nilai_diperoleh'] === null ? null : (float) $row['nilai_diperoleh'],
            "status_penilaian" => $row['status_penilaian'],
            "id_penilai" => $row['id_penilai'] === null ? null : (int) $row['id_penilai'],
            "waktu_dinilai" => $row['waktu_dinilai'],
        ];

        if ($row['tipe_soal'] === SOAL_TIPE_PILIHAN_GANDA) {
            $jawaban["pilihan"] = [
                "A" => $row['pilihan_a'],
                "B" => $row['pilihan_b'],
                "C" => $row['pilihan_c'],
                "D" => $row['pilihan_d'],
            ];
            $jawaban["kunci_jawaban"] = $row['kunci_jawaban'];
            $jawaban["benar"] = $row['jawaban'] !== null && $row['jawaban'] === $row['kunci_jawaban'];
        }

        $jawabanList[] = $jawaban;
    }

    sendSuccess("Detail percobaan berhasil diambil.", [
        "percobaan" => [
            "id_percobaan" => (int) $percobaan['id_percobaan'],
            "status" => $percobaan['status'],
            "waktu_mulai" => $percobaan['waktu_mulai'],
            "waktu_selesai" => $percobaan['waktu_selesai'],
            "nilai_otomatis" => (float) $percobaan['nilai_otomatis'],
            "nilai_manual" => (float) $percobaan['nilai_manual'],
            "nilai_total" => $percobaan['nilai_total'] === null ? null : (float) $percobaan['nilai_total'],
        ],
        "siswa" => [
            "id_siswa" => (int) $percobaan['id_siswa'],
            "kode_siswa" => $percobaan['kode_siswa'],
            "nama" => $percobaan['nama'],
        ],
        "kuis" => [
            "id_kuis" => (int) $percobaan['id_kuis'],
            "judul" => $percobaan['judul'],
        ],
        "jawaban" => $jawabanList,
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'percobaan_kuis/detail');
}