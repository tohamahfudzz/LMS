<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya pengajar.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// Validasi ID tugas.
if (!isset($_GET['id_tugas']) || !is_numeric($_GET['id_tugas'])) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID tugas wajib diisi.",
        "data" => null
    ]);

    exit;
}

$id_tugas = (int) $_GET['id_tugas'];

if ($id_tugas <= 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID tugas tidak valid.",
        "data" => null
    ]);

    exit;
}

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Ambil detail tugas sekaligus memastikan
     * tugas tersebut milik pengajar yang login.
     */
    $query = "
        SELECT
            t.id_tugas,
            t.id_kelas,
            k.nama_kelas,
            t.id_pengajar,
            t.judul,
            t.deskripsi,
            t.file,
            t.waktu_mulai,
            t.deadline,
            t.status,
            t.waktu_dibuat,
            COUNT(DISTINCT pt.id_pengumpulan) AS jumlah_pengumpulan

        FROM tugas t

        INNER JOIN kelas k
            ON t.id_kelas = k.id_kelas

        LEFT JOIN pengumpulan_tugas pt
            ON t.id_tugas = pt.id_tugas

        WHERE t.id_tugas = :id_tugas
          AND t.id_pengajar = :id_pengajar

        GROUP BY
            t.id_tugas,
            t.id_kelas,
            k.nama_kelas,
            t.id_pengajar,
            t.judul,
            t.deskripsi,
            t.file,
            t.waktu_mulai,
            t.deadline,
            t.status,
            t.waktu_dibuat

        LIMIT 1
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_tugas" => $id_tugas,
        ":id_pengajar" => $id_pengajar
    ]);

    $tugas = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tugas) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Tugas tidak ditemukan atau Anda tidak memiliki akses.",
            "data" => null
        ]);

        exit;
    }

    $tugas['id_tugas'] = (int) $tugas['id_tugas'];
    $tugas['id_kelas'] = (int) $tugas['id_kelas'];
    $tugas['id_pengajar'] = (int) $tugas['id_pengajar'];
    $tugas['jumlah_pengumpulan'] = (int) $tugas['jumlah_pengumpulan'];

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Detail tugas berhasil diambil.",
        "data" => $tugas
    ]);

} catch (PDOException $error) {

    error_log("Error detail tugas pengajar: " . $error->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil detail tugas.",
        "data" => null
    ]);
}