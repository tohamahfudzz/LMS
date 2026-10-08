<?php

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya pengajar yang boleh mengakses endpoint ini.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// Ambil ID materi dari URL.
// Contoh: download.php?id_materi=1
if (!isset($_GET['id_materi']) || !is_numeric($_GET['id_materi'])) {

    http_response_code(400);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "ID materi wajib diisi."
    ]);

    exit;
}

$id_materi = (int) $_GET['id_materi'];

if ($id_materi <= 0) {

    http_response_code(400);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "ID materi tidak valid."
    ]);

    exit;
}

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Cari materi berdasarkan:
     * 1. id_materi
     * 2. id_pengajar yang sedang login
     *
     * Dengan begitu pengajar tidak bisa mendownload
     * file materi milik pengajar lain.
     */
    $query = "
        SELECT
            id_materi,
            judul,
            file
        FROM materi
        WHERE id_materi = :id_materi
          AND id_pengajar = :id_pengajar
        LIMIT 1
    ";

    $stmt = $db->prepare($query);

    $stmt->execute([
        ":id_materi" => $id_materi,
        ":id_pengajar" => $id_pengajar
    ]);

    $materi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$materi) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Materi tidak ditemukan atau Anda tidak memiliki akses."
        ]);

        exit;
    }

    // Materi bisa berupa link tanpa file.
    if (empty($materi['file'])) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Materi ini tidak memiliki file untuk didownload."
        ]);

        exit;
    }

    /*
     * UPLOAD_BASE_PATH berasal dari upload.php.
     * Karena download.php tidak membutuhkan fungsi upload,
     * path dibuat berdasarkan struktur folder project.
     */
    $uploadBasePath = dirname(__DIR__, 3) . "/uploads/";

    $relativePath = $materi['file'];

    /*
     * Normalisasi path.
     * Menghilangkan kemungkinan path traversal seperti:
     * ../../config/database.php
     */
    $relativePath = str_replace("\\", "/", $relativePath);
    $relativePath = ltrim($relativePath, "/");

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
     * Pastikan file benar-benar berada di dalam folder uploads.
     */
    $realBasePath = realpath($uploadBasePath);
    $realFilePath = realpath($filePath);

    if (
        $realBasePath === false ||
        $realFilePath === false ||
        strpos($realFilePath, $realBasePath . DIRECTORY_SEPARATOR) !== 0
    ) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "File materi tidak ditemukan."
        ]);

        exit;
    }

    if (!is_file($realFilePath) || !is_readable($realFilePath)) {

        http_response_code(404);

        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "File materi tidak dapat diakses."
        ]);

        exit;
    }

    /*
     * Ambil ekstensi file untuk menentukan MIME type.
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
        "jpg"  => "image/jpeg",
        "jpeg" => "image/jpeg",
        "png"  => "image/png",
        "gif"  => "image/gif",
        "mp4"  => "video/mp4"
    ];

    $mimeType = $mimeTypes[$extension] ?? "application/octet-stream";

    /*
     * Nama file yang ditampilkan kepada user.
     * File asli di server tetap menggunakan nama random.
     */
    $downloadName = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        $materi['judul']
    );

    if ($downloadName === '') {
        $downloadName = "materi";
    }

    $downloadName .= "." . $extension;

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

    error_log("Error download materi pengajar: " . $error->getMessage());

    http_response_code(500);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil file materi."
    ]);

    exit;
}