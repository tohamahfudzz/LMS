
<?php

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

$session = requireRole("siswa");

// Endpoint ini hanya menerima GET.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ID materi wajib dikirim melalui parameter URL.
$idMateri = filter_input(
    INPUT_GET,
    "id_materi",
    FILTER_VALIDATE_INT
);

if (!$idMateri || $idMateri < 1) {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "ID materi tidak valid."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $idSiswa = (int) $session["id"];

    /*
     * Materi hanya dapat diakses jika siswa terdaftar
     * di kelas tempat materi tersebut diberikan.
     */
    $query = "
        SELECT
            m.id_materi,
            m.judul,
            m.file
        FROM materi m
        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = m.id_kelas
        WHERE m.id_materi = :id_materi
          AND ks.id_siswa = :id_siswa
        LIMIT 1
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_materi" => $idMateri,
        ":id_siswa" => $idSiswa
    ]);

    $materi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$materi) {
        http_response_code(404);
        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Materi tidak ditemukan atau kamu tidak memiliki akses."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if (empty($materi["file"])) {
        http_response_code(404);
        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Materi ini tidak memiliki file untuk diunduh."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /*
     * Lokasi penyimpanan file.
     * Sesuaikan dengan lokasi yang digunakan oleh
     * fitur upload materi pengajar.
     */
    $namaFile = basename(
        str_replace("\\", "/", $materi["file"])
    );

    $direktoriUpload = [
        dirname(__DIR__, 4) . "/uploads/materi",
        dirname(__DIR__, 4) . "/backend/uploads/materi"
    ];

    $lokasiFile = false;

    foreach ($direktoriUpload as $direktori) {
        $direktoriAsli = realpath($direktori);

        if ($direktoriAsli === false) {
            continue;
        }

        $calonFile = realpath(
            $direktoriAsli . DIRECTORY_SEPARATOR . $namaFile
        );

        // Pastikan file benar-benar berada di direktori upload.
        if (
            $calonFile !== false &&
            is_file($calonFile) &&
            strpos(
                $calonFile,
                $direktoriAsli . DIRECTORY_SEPARATOR
            ) === 0 &&
            is_readable($calonFile)
        ) {
            $lokasiFile = $calonFile;
            break;
        }
    }

    if ($lokasiFile === false) {
        error_log(
            "File materi tidak ditemukan untuk ID materi: " .
            $idMateri
        );

        http_response_code(404);
        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "File materi tidak ditemukan di server."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Nama file unduhan dibuat aman untuk header HTTP.
    $namaUnduhan = preg_replace(
        '/[\x00-\x1F\x7F"\\\\\/]/',
        "_",
        basename($materi["file"])
    );

    if ($namaUnduhan === "" || $namaUnduhan === "." || $namaUnduhan === "..") {
        $namaUnduhan = "materi_" . $idMateri;
    }

    header("Content-Type: application/octet-stream");
    header(
        "Content-Disposition: attachment; filename=\"" .
        $namaUnduhan . "\""
    );
    header("Content-Length: " . filesize($lokasiFile));
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store");

    readfile($lokasiFile);
    exit;

} catch (PDOException $error) {
    error_log(
        "Gagal mengunduh materi siswa: " .
        $error->getMessage()
    );

    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}
