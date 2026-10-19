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
$token = trim($requestData->token ?? '');
$newPassword = $requestData->password_baru ?? '';

if (empty($token) || empty($newPassword)) {
    http_response_code(400);
    sendResponse(false, "Token pemulihan dan kata sandi baru wajib diisi.");
    exit;
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    sendResponse(false, "Kata sandi baru harus memiliki minimal 8 karakter.");
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $verifyTokenQuery = "
        SELECT email 
        FROM password_resets 
        WHERE token = :token 
          AND created_at >= NOW() - INTERVAL 15 MINUTE 
        LIMIT 1
    ";
    $verifyTokenStatement = $db->prepare($verifyTokenQuery);
    $verifyTokenStatement->execute([
        ":token" => $token
    ]);

    if ($verifyTokenStatement->rowCount() === 0) {
        http_response_code(401);
        sendResponse(false, "Token pemulihan tidak valid atau sesi telah kedaluwarsa.");
        exit;
    }

    $userRecord = $verifyTokenStatement->fetch(PDO::FETCH_ASSOC);
    $userEmail = $userRecord['email'];

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $updatePasswordQuery = "
        UPDATE users 
        SET password = :password 
        WHERE email = :email
    ";
    $updatePasswordStatement = $db->prepare($updatePasswordQuery);
    $updatePasswordStatement->execute([
        ":password" => $hashedPassword,
        ":email" => $userEmail
    ]);

    $deleteTokenQuery = "
        DELETE FROM password_resets 
        WHERE email = :email
    ";
    $deleteTokenStatement = $db->prepare($deleteTokenQuery);
    $deleteTokenStatement->execute([
        ":email" => $userEmail
    ]);

    sendResponse(true, "Kata sandi berhasil diperbarui. Silakan masuk kembali dengan sandi baru Anda.");

} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    sendResponse(false, "Terjadi kesalahan pada server.");
}