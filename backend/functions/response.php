<?php

// Helper respons JSON dan pembacaan request untuk endpoint API.
// Format respons seragam: { "success": bool, "message": string, "data": mixed|null }

// Menyiapkan header standar dan memastikan metode HTTP sesuai.
// Panggil paling awal di setiap endpoint, sebelum auth dan koneksi database.
function initApi(string $allowedMethod): void
{
    header("Content-Type: application/json; charset=UTF-8");
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: no-store");
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Accept");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== $allowedMethod) {
        header("Allow: " . $allowedMethod);
        sendError("Metode request tidak diizinkan.", 405);
    }
}

// Mengirim respons JSON lalu menghentikan script.
function sendJson(int $statusCode, bool $success, string $message, $data = null): void
{
    http_response_code($statusCode);
    echo json_encode(
        ["success" => $success, "message" => $message, "data" => $data],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function sendSuccess(string $message, $data = null, int $statusCode = 200): void
{
    sendJson($statusCode, true, $message, $data);
}

function sendError(string $message, int $statusCode = 400, $data = null): void
{
    sendJson($statusCode, false, $message, $data);
}

// Mencatat detail error ke log server dan hanya mengirim pesan umum ke pengguna.
function sendServerError(Throwable $error, string $context): void
{
    error_log("[LMS][" . $context . "] " . get_class($error) . ": " . $error->getMessage());
    sendError("Terjadi kesalahan pada server.", 500);
}

// Membaca body JSON dari request.
// Wajib application/json: form lintas situs tidak bisa mengirim tipe ini tanpa
// preflight CORS, sehingga ini sekaligus menjadi lapisan perlindungan CSRF.
function readJsonBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== 0) {
        sendError("Request harus dikirim sebagai application/json.", 415);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        sendError("Format data tidak valid.", 400);
    }

    return $data;
}