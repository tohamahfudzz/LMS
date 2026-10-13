
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya siswa yang sudah login yang boleh mengakses endpoint ini.
$session = requireRole("siswa");

// Endpoint ini hanya menerima request GET.
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

    // ID siswa diambil dari session, bukan dari input request.
    $idSiswa = (int) $session["id"];

    $query = "
        SELECT
            k.id_kelas,
            k.nama_kelas,
            (
                SELECT COUNT(*)
                FROM materi m
                WHERE m.id_kelas = k.id_kelas
            ) AS total_materi,
            (
                SELECT COUNT(*)
                FROM tugas t
                WHERE t.id_kelas = k.id_kelas
            ) AS total_tugas
        FROM kelas k
        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = k.id_kelas
        WHERE ks.id_siswa = :id_siswa
        ORDER BY k.nama_kelas ASC
    ";

    $statement = $db->prepare($query);

    $statement->execute([
        ":id_siswa" => $idSiswa
    ]);

    $daftarKelas = $statement->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => count($daftarKelas) > 0
            ? "Daftar kelas berhasil diambil."
            : "Siswa belum terdaftar di kelas mana pun.",
        "data" => $daftarKelas
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $error) {
    error_log("Gagal mengambil daftar kelas siswa: " . $error->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server.",
        "data" => null
    ], JSON_UNESCAPED_UNICODE);
}
