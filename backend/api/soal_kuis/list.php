<?php

// GET /api/soal_kuis/list.php?id_kuis=...
// Role: pengajar. Seluruh soal sebuah kuis MILIK pengajar yang login, lengkap dengan kunci jawaban,
// untuk keperluan mengelola soal. 'dapat_diedit' memberi tahu frontend apakah soal sedang terkunci.

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

    $lockError = getSoalLockError($db, $kuis);

    $statement = $db->prepare(
        "SELECT id_soal, tipe_soal, soal, pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin, urutan
         FROM soal_kuis WHERE id_kuis = :id_kuis ORDER BY urutan ASC"
    );
    $statement->execute([":id_kuis" => $idKuis]);

    $soalList = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $soal = [
            "id_soal" => (int) $row['id_soal'],
            "tipe_soal" => $row['tipe_soal'],
            "soal" => $row['soal'],
            "poin" => (float) $row['poin'],
            "urutan" => (int) $row['urutan'],
        ];

        if ($row['tipe_soal'] === SOAL_TIPE_PILIHAN_GANDA) {
            $soal["pilihan"] = [
                "A" => $row['pilihan_a'],
                "B" => $row['pilihan_b'],
                "C" => $row['pilihan_c'],
                "D" => $row['pilihan_d'],
            ];
            $soal["kunci_jawaban"] = $row['kunci_jawaban'];
        }

        $soalList[] = $soal;
    }

    sendSuccess("Daftar soal berhasil diambil.", [
        "kuis" => [
            "id_kuis" => (int) $kuis['id_kuis'],
            "judul" => $kuis['judul'],
            "status" => $kuis['status'],
        ],
        "dapat_diedit" => $lockError === null,
        "alasan_terkunci" => $lockError,
        "soal" => $soalList,
    ]);
} catch (Throwable $error) {
    sendServerError($error, 'soal_kuis/list');
}