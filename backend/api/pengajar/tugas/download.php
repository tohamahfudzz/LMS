<?php

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya pengajar.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// Validasi ID pengumpulan.
if (
    !isset($_GET['id_pengumpulan']) ||
    !is_numeric($_GET['id_pengumpulan'])
) {

    http_response_code(400);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "ID pengumpulan wajib diisi."
    ]);

    exit;
}

$id_pengumpulan = (int) $_GET['id_pengumpulan'];

if ($id_pengumpulan <= 0) {

    http_response_code(400);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "ID pengumpulan tidak valid."
    ]);

    exit;
}

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Ambil file pengumpulan.
     *
     * JOIN ke tabel tugas digunakan untuk memastikan
     * file tersebut berasal dari tugas milik pengajar
     * yang sedang login.
     */
    $query = "
        SELECT
            pt.id_pengumpulan,
            pt.file,
            pt.id_tugas,
            t.judul,
            s.nama AS nama_siswa

        FROM pengumpulan_tugas pt

        INNER JOIN tugas t
            ON pt.id_tugas = t.id_tugas

        INNER JOIN siswa s
            ON pt.id_siswa = s.id_siswa

        WHERE pt.id_pengumpulan = :id_pengumpulan
          AND t.id_pengajar = :id_pengajar

        LIMIT 1
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_pengumpulan" => $id_pengumpulan,
        ":id_pengajar" => $id_pengajar
    ]);

    $pengumpulan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pengumpulan) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Pengumpulan tidak ditemukan atau Anda tidak memiliki akses."
        ]);

        exit;
    }

    /*
     * Pengumpulan bisa berupa jawaban teks tanpa file.
     */
    if (empty($pengumpulan['file'])) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Pengumpulan ini tidak memiliki file."
        ]);

        exit;
    }

    /*
     * Folder penyimpanan upload.
     */
    $uploadBasePath = dirname(__DIR__, 3) . "/uploads/";

    $relativePath = str_replace(
        "\\",
        "/",
        $pengumpulan['file']
    );

    $relativePath = ltrim($relativePath, "/");

    /*
     * Mencegah path traversal.
     */
    if (
        strpos($relativePath, "..") !== false ||
        strpos($relativePath, ":") !== false
    ) {

        http_response_code(400);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Path file tidak valid."
        ]);

        exit;
    }

    $filePath = $uploadBasePath . $relativePath;

    /*
     * Pastikan file berada di dalam folder uploads.
     */
    $realBasePath = realpath($uploadBasePath);
    $realFilePath = realpath($filePath);

    if (
        $realBasePath === false ||
        $realFilePath === false ||
        strpos(
            $realFilePath,
            $realBasePath . DIRECTORY_SEPARATOR
        ) !== 0
    ) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "File pengumpulan tidak ditemukan."
        ]);

        exit;
    }

    if (
        !is_file($realFilePath) ||
        !is_readable($realFilePath)
    ) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "File pengumpulan tidak dapat diakses."
        ]);

        exit;
    }

    /*
     * Tentukan MIME type.
     */
    $extension = strtolower(
        pathinfo($realFilePath, PATHINFO_EXTENSION)
    );

    $mimeTypes = [
        "pdf"  => "application/pdf",
        "doc"  => "application/msword",
        "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "ppt"  => "application/vnd.ms-powerpoint",
        "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        "xls"  => "application/vnd.ms-excel",
        "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "zip"  => "application/zip",
        "rar"  => "application/vnd.rar",
        "jpg"  => "image/jpeg",
        "jpeg" => "image/jpeg",
        "png"  => "image/png",
        "gif"  => "image/gif",
        "mp4"  => "video/mp4"
    ];

    $mimeType = $mimeTypes[$extension]
        ?? "application/octet-stream";

    /*
     * Nama file ketika didownload.
     */
    $namaSiswa = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        $pengumpulan['nama_siswa']
    );

    $judulTugas = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        $pengumpulan['judul']
    );

    $downloadName =
        $namaSiswa .
        "_" .
        $judulTugas .
        "." .
        $extension;

    /*
     * Kirim file ke browser.
     */
    header("Content-Type: " . $mimeType);
    header("Content-Length: " . filesize($realFilePath));

    header(
        'Content-Disposition: attachment; filename="' .
        $downloadName .
        '"'
    );

    header("X-Content-Type-Options: nosniff");

    readfile($realFilePath);

    exit;

} catch (PDOException $error) {

    error_log(
        "Error download pengumpulan tugas: " .
        $error->getMessage()
    );

    http_response_code(500);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil file pengumpulan."
    ]);

    exit;
}