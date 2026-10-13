
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

$session = requireRole("siswa");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan.",
        "data" => null
    ]);

    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $idSiswa = (int) $session["id"];

    $query = "
        SELECT
            m.id_materi,
            m.id_kelas,
            k.nama_kelas,
            m.id_pengajar,
            p.nama AS nama_pengajar,
            m.judul,
            m.tipe,
            m.file,
            m.link,
            m.waktu_upload
        FROM materi m
        INNER JOIN kelas k
            ON k.id_kelas = m.id_kelas
        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = m.id_kelas
        LEFT JOIN pengajar p
            ON p.id_pengajar = m.id_pengajar
        WHERE ks.id_siswa = :id_siswa
        ORDER BY m.waktu_upload DESC
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_siswa" => $idSiswa
    ]);

    $materi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => count($materi) > 0
            ? "Daftar materi berhasil diambil."
            : "Belum ada materi pada kelas yang kamu ikuti.",
        "data" => $materi
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $error) {
    error_log(
        "Gagal mengambil materi siswa: " .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server.",
        "data" => null
    ], JSON_UNESCAPED_UNICODE);
}
