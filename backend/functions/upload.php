<?php

// Konfigurasi dasar upload file.
define('UPLOAD_BASE_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB

// Menangani upload satu file dari <input type="file" name="$fieldName">.
// Mengembalikan nama file (path relatif dari folder uploads) jika berhasil,
// atau null jika memang tidak ada file yang dikirim.
// Jika file terkirim tapi tidak valid, langsung kirim response error JSON dan exit,
// supaya endpoint pemanggil tidak perlu mengulang validasi.
function handleFileUpload(string $fieldName, string $subfolder, array $allowedExtensions, int $maxSize = UPLOAD_MAX_SIZE): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Gagal mengunggah file. Kode error: " . $file['error']]);
        exit;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Upload file tidak valid."]);
        exit;
    }

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Ukuran file melebihi batas maksimal " . round($maxSize / 1024 / 1024) . " MB."]);
        exit;
    }

    // Cocokkan ekstensi nama file dengan whitelist
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Tipe file tidak diizinkan. Ekstensi yang diperbolehkan: " . implode(', ', $allowedExtensions) . "."]);
        exit;
    }

    // Verifikasi isi file yang sebenarnya, bukan hanya percaya nama/ekstensinya.
    // Ini mencegah file .php yang di-rename jadi .pdf.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip'  => 'application/zip',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'mp4'  => 'video/mp4',
    ];

    if (isset($allowedMimeTypes[$extension]) && $realMimeType !== $allowedMimeTypes[$extension]) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Isi file tidak sesuai dengan ekstensinya."]);
        exit;
    }

    // Nama file baru dibuat acak (bukan nama asli) agar tidak bisa ditebak,
    // tidak menimpa file lain, dan menghindari path traversal.
    $newFileName = bin2hex(random_bytes(16)) . '.' . $extension;

    $targetDir = UPLOAD_BASE_PATH . trim($subfolder, '/') . '/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetPath = $targetDir . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Gagal menyimpan file ke server."]);
        exit;
    }

    // Path relatif ini yang disimpan ke kolom `file` di database
    return trim($subfolder, '/') . '/' . $newFileName;
}