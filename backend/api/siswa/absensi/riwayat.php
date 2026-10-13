
<?php
header("Content-Type: application/json; charset=UTF-8");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

$session = requireRole("siswa");
$idSiswa = (int) $session["id"];

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Gunakan metode GET."
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $sql = "
        SELECT
            k.id_kehadiran,
            a.id_absensi,
            kl.id_kelas,
            kl.nama_kelas,
            a.tanggal,
            a.waktu_buka,
            a.waktu_tutup,
            k.waktu_absen,
            k.status
        FROM kehadiran k
        INNER JOIN absensi a
            ON k.id_absensi = a.id_absensi
        INNER JOIN kelas kl
            ON a.id_kelas = kl.id_kelas
        WHERE k.id_siswa = :id_siswa
        ORDER BY a.tanggal DESC, k.waktu_absen DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ":id_siswa" => $idSiswa
    ]);

    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Riwayat absensi berhasil diambil.",
        "total" => count($riwayat),
        "data" => $riwayat
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("Error riwayat absensi siswa: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil riwayat absensi."
    ]);
}
