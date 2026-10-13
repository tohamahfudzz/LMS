
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
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $idSiswa = (int) $session["id"];

    $query = "
        SELECT
            t.id_tugas,
            t.id_kelas,
            k.nama_kelas,
            t.id_pengajar,
            p.nama AS nama_pengajar,
            t.judul,
            t.deskripsi,
            t.file,
            t.deadline,
            t.waktu_mulai,
            t.status AS status_tugas,
            t.waktu_dibuat,
            pg.id_pengumpulan,
            pg.file AS file_pengumpulan,
            pg.teks AS teks_pengumpulan,
            pg.waktu_upload,
            pg.waktu_pengumpulan,
            CASE
                WHEN pg.id_pengumpulan IS NOT NULL
                    THEN 'sudah_dikumpulkan'
                ELSE 'belum_dikumpulkan'
            END AS status_pengumpulan,
            CASE
                WHEN t.deadline IS NOT NULL
                     AND t.deadline < NOW()
                    THEN 'terlambat'
                ELSE 'belum_deadline'
            END AS status_deadline
        FROM tugas t
        INNER JOIN kelas k
            ON k.id_kelas = t.id_kelas
        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = t.id_kelas
        LEFT JOIN pengajar p
            ON p.id_pengajar = t.id_pengajar
        LEFT JOIN pengumpulan_tugas pg
            ON pg.id_tugas = t.id_tugas
            AND pg.id_siswa = ks.id_siswa
        WHERE ks.id_siswa = :id_siswa
        ORDER BY
            CASE
                WHEN t.deadline IS NULL THEN 1
                ELSE 0
            END ASC,
            t.deadline ASC,
            t.waktu_dibuat DESC
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_siswa" => $idSiswa
    ]);

    $tugas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => count($tugas) > 0
            ? "Daftar tugas berhasil diambil."
            : "Belum ada tugas pada kelas yang kamu ikuti.",
        "data" => $tugas
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $error) {
    error_log(
        "Gagal mengambil daftar tugas siswa: " .
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
