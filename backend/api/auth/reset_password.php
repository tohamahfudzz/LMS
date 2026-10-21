<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../functions/response.php';

// Validasi Method
initApi('POST');

// Membaca Request
$requestData = readJsonBody();
$token = trim($requestData['token'] ?? '');
$newPassword = $requestData['password_baru'] ?? '';

if (empty($token) || empty($newPassword)) {
    sendError("Token pemulihan dan kata sandi baru wajib diisi.", 400);
}

if (strlen($newPassword) < 8) {
    sendError("Kata sandi baru harus memiliki minimal 8 karakter.", 400);
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
    $verifyTokenStatement->execute([":token" => $token]);

    if ($verifyTokenStatement->rowCount() === 0) {
        sendError("Token pemulihan tidak valid atau sesi telah kedaluwarsa.", 401);
    }

    $userRecord = $verifyTokenStatement->fetch(PDO::FETCH_ASSOC);
    $userEmail = $userRecord['email'];

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $updatePasswordQuery = "UPDATE siswa SET password = :password WHERE email = :email";
    $updatePasswordStatement = $db->prepare($updatePasswordQuery);
    $updatePasswordStatement->execute([
        ":password" => $hashedPassword,
        ":email" => $userEmail
    ]);

    $deleteTokenQuery = "DELETE FROM password_resets WHERE email = :email";
    $deleteTokenStatement = $db->prepare($deleteTokenQuery);
    $deleteTokenStatement->execute([":email" => $userEmail]);

    sendSuccess("Kata sandi berhasil diperbarui. Silakan masuk kembali dengan sandi baru Anda.");

} catch (PDOException $error) {
    // Mode Debug
    sendError("Database Error: " . $error->getMessage(), 500);
    
    // Standar rilis:
    // sendServerError($error, "ResetPassword");
}