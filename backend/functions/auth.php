<?php

// Memastikan session pengguna aktif sebelum digunakan.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengecek apakah pengguna sudah login.
// Menghentikan proses dan mengirim response 401 jika session tidak ditemukan.
function requireLogin(): array
{
    if (empty($_SESSION['id']) || empty($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Anda harus login terlebih dahulu."]);
        exit;
    }

    return $_SESSION;
}

// Mengecek apakah pengguna yang login memiliki role yang sesuai.
// Menghentikan proses dan mengirim response 403 jika role tidak cocok.
function requireRole(string $allowedRole): array
{
    $session = requireLogin();

    if ($session['role'] !== $allowedRole) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Anda tidak memiliki akses untuk melakukan tindakan ini."]);
        exit;
    }

    return $session;
}