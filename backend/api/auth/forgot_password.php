<?php

require_once '../../config/database.php';
require_once '../../functions/response.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, "Metode HTTP tidak diizinkan.");
    exit;
}

$requestData = json_decode(file_get_contents("php://input"));
$email = trim($requestData->email ?? '');

if (empty($email)) {
    http_response_code(400);
    sendResponse(false, "Alamat email wajib diisi.");
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $checkUserQuery = "
        SELECT email 
        FROM users 
        WHERE email = :email 
        LIMIT 1
    "; 
    $checkUserStatement = $db->prepare($checkUserQuery);
    $checkUserStatement->execute([
        ":email" => $email
    ]);

    if ($checkUserStatement->rowCount() === 0) {
        http_response_code(404);
        sendResponse(false, "Akun tidak ditemukan. Pastikan email yang Anda masukkan sudah terdaftar di sistem.");
        exit;
    }

    $resetToken = bin2hex(random_bytes(32)); 

    $insertTokenQuery = "
        INSERT INTO password_resets (email, token) 
        VALUES (:email, :token)
    ";
    $insertTokenStatement = $db->prepare($insertTokenQuery);
    $insertTokenStatement->execute([
        ":email" => $email,
        ":token" => $resetToken
    ]);

    $resetLink = "http://localhost:8000/frontend/auth/reset-password.html?token=" . $resetToken;

    sendResponse(true, "Instruksi pemulihan kata sandi telah dikirim ke email terdaftar Anda.", [
        "reset_link" => $resetLink
    ]);

} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    sendResponse(false, "Terjadi kesalahan pada server.");
}