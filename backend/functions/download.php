<?php
/*
================================================================================
LOKASI FILE : backend/functions/download.php
GUNA FILE   : Helper untuk mengirim file yang tersimpan di folder backend/uploads/
              ke browser sebagai unduhan. Dipakai endpoint unduh yang baru, contoh:
              backend/api/pengajar/tugas/download_lampiran.php

              sendUploadedFile($pathTersimpan, $namaDasar)
                - $pathTersimpan : path relatif seperti yang ada di database,
                                   misalnya "tugas/ab12cd.pdf"
                - $namaDasar     : nama file yang dilihat pengguna (tanpa ekstensi);
                                   ekstensi diambil dari file asli
              Keamanan: path traversal ditolak dan file harus berada di dalam folder
              uploads. Jika gagal, fungsi mengirim error JSON (sendError) lalu berhenti.
              Bergantung pada: functions/response.php (sendError).
================================================================================
*/

function sendUploadedFile(string $storedPath, string $downloadBaseName): void
{
    $uploadBasePath = dirname(__DIR__) . '/uploads/';

    $relativePath = ltrim(str_replace('\\', '/', $storedPath), '/');

    // Tolak path yang mencoba keluar dari folder uploads.
    if ($relativePath === '' || strpos($relativePath, '..') !== false || strpos($relativePath, ':') !== false) {
        sendError('Path file tidak valid.', 400);
    }

    $realBasePath = realpath($uploadBasePath);
    $realFilePath = realpath($uploadBasePath . $relativePath);

    if (
        $realBasePath === false ||
        $realFilePath === false ||
        strpos($realFilePath, $realBasePath . DIRECTORY_SEPARATOR) !== 0
    ) {
        sendError('File tidak ditemukan di server.', 404);
    }

    if (!is_file($realFilePath) || !is_readable($realFilePath)) {
        sendError('File tidak dapat diakses.', 404);
    }

    $extension = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));

    $mimeTypes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip'  => 'application/zip',
        'rar'  => 'application/vnd.rar',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'mp4'  => 'video/mp4',
    ];

    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    // Nama unduhan hanya berisi karakter aman; file asli di server tetap bernama acak.
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadBaseName);

    if ($safeName === null || trim($safeName, '_') === '') {
        $safeName = 'file';
    }

    $downloadName = $extension !== '' ? $safeName . '.' . $extension : $safeName;

    // Buang output yang mungkin sudah tertampung agar isi file tidak rusak.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realFilePath));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private');

    readfile($realFilePath);
    exit;
}
