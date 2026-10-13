
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

$session = requireRole("siswa");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan.",
        "data" => null
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $idSiswa = (int) $session["id"];

    $query = "
        SELECT
            a.id_absensi,
            a.id_kelas,
            k.nama_kelas,
            a.id_pengajar,
            p.nama AS nama_pengajar,
            a.tanggal,
            a.waktu_buka,
            a.waktu_tutup,
            a.status AS status_absensi,

            kh.id_kehadiran,
            kh.waktu_absen,
            kh.status AS status_kehadiran,

            CASE
                WHEN kh.id_kehadiran IS NOT NULL
                    THEN 'sudah_absen'
                WHEN a.status = 'dibuka'
                    THEN 'belum_absen'
                ELSE 'tidak_tersedia'
            END AS status_siswa

        FROM absensi a

        INNER JOIN kelas k
            ON k.id_kelas = a.id_kelas

        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = a.id_kelas

        LEFT JOIN pengajar p
            ON p.id_pengajar = a.id_pengajar

        LEFT JOIN kehadiran kh
            ON kh.id_absensi = a.id_absensi
            AND kh.id_siswa = ks.id_siswa

        WHERE ks.id_siswa = :id_siswa

        ORDER BY
            a.tanggal DESC,
            a.waktu_buka DESC
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_siswa" => $idSiswa
    ]);

    $absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => count($absensi) > 0
            ? "Daftar absensi berhasil diambil."
            : "Belum ada data absensi untuk kelas yang kamu ikuti.",
        "data" => $absensi
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $error) {
    error_log(
        "Gagal mengambil daftar absensi siswa: " .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server.",
        "data" => null
    ], JSON_UNESCAPED_UNICODE);

    exit;
}
