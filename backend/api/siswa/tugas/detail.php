
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

$session = requireRole("siswa");

// Hanya menerima request GET.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan.",
        "data" => null
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Validasi ID tugas dari URL.
$idTugas = filter_input(
    INPUT_GET,
    "id_tugas",
    FILTER_VALIDATE_INT
);

if (!$idTugas || $idTugas < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID tugas tidak valid.",
        "data" => null
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $idSiswa = (int) $session["id"];

    /*
     * Ambil detail tugas hanya jika siswa terdaftar
     * di kelas tempat tugas tersebut diberikan.
     */
    $query = "
        SELECT
            t.id_tugas,
            t.id_kelas,
            k.nama_kelas,
            t.id_pengajar,
            p.nama AS nama_pengajar,
            t.judul,
            t.deskripsi,
            t.file AS file_tugas,
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

        WHERE t.id_tugas = :id_tugas
          AND ks.id_siswa = :id_siswa

        LIMIT 1
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_tugas" => $idTugas,
        ":id_siswa" => $idSiswa
    ]);

    $tugas = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tugas) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Tugas tidak ditemukan atau kamu tidak memiliki akses.",
            "data" => null
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Detail tugas berhasil diambil.",
        "data" => $tugas
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $error) {
    error_log(
        "Gagal mengambil detail tugas siswa: " .
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
