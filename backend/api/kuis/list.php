<?php

// GET /api/kuis/list.php            -> semua kuis milik pengajar yang login
// GET /api/kuis/list.php?id_kelas=  -> dibatasi pada satu kelas
// Role: pengajar. Hanya kuis miliknya sendiri yang muncul (id_pengajar dari session).
// Memuat jumlah soal, jumlah percobaan, dan jumlah jawaban yang menunggu penilaian.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('GET');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$idKelas = null;

if (isset($_GET['id_kelas'])) {
    $idKelas = parsePositiveInt($_GET['id_kelas']);

    if ($idKelas === null) {
        sendError("ID kelas tidak valid.", 400);
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT k.id_kuis, k.id_kelas, kl.nama_kelas, k.judul, k.deskripsi,
                     k.waktu_mulai, k.waktu_selesai, k.durasi_menit, k.status, k.waktu_dibuat,
                     (SELECT COUNT(*) FROM soal_kuis so WHERE so.id_kuis = k.id_kuis) AS jumlah_soal,
                     (SELECT COUNT(*) FROM percobaan_kuis p WHERE p.id_kuis = k.id_kuis) AS jumlah_percobaan,
                     (SELECT COUNT(*)
                        FROM jawaban_kuis j
                        INNER JOIN percobaan_kuis p ON p.id_percobaan = j.id_percobaan
                       WHERE p.id_kuis = k.id_kuis AND j.status_penilaian = :status_menunggu) AS jumlah_menunggu
              FROM kuis k
              INNER JOIN kelas kl ON kl.id_kelas = k.id_kelas
              WHERE k.id_pengajar = :id_pengajar";

    $params = [
        ":status_menunggu" => PENILAIAN_STATUS_MENUNGGU,
        ":id_pengajar" => $idPengajar,
    ];

    if ($idKelas !== null) {
        $query .= " AND k.id_kelas = :id_kelas";
        $params[":id_kelas"] = $idKelas;
    }

    $query .= " ORDER BY k.waktu_dibuat DESC, k.id_kuis DESC";

    $statement = $db->prepare($query);
    $statement->execute($params);

    $kuisList = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kuisList[] = [
            "id_kuis" => (int) $row['id_kuis'],
            "id_kelas" => (int) $row['id_kelas'],
            "nama_kelas" => $row['nama_kelas'],
            "judul" => $row['judul'],
            "deskripsi" => $row['deskripsi'],
            "waktu_mulai" => $row['waktu_mulai'],
            "waktu_selesai" => $row['waktu_selesai'],
            "durasi_menit" => (int) $row['durasi_menit'],
            "status" => $row['status'],
            "waktu_dibuat" => $row['waktu_dibuat'],
            "jumlah_soal" => (int) $row['jumlah_soal'],
            "jumlah_percobaan" => (int) $row['jumlah_percobaan'],
            "jumlah_menunggu" => (int) $row['jumlah_menunggu'],
        ];
    }

    sendSuccess("Daftar kuis berhasil diambil.", $kuisList);
} catch (Throwable $error) {
    sendServerError($error, 'kuis/list');
}