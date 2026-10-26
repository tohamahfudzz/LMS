<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../functions/response.php';

const RESET_TOKEN_VALID_MINUTES = 15;

initApi('POST');

$requestData = readJsonBody();
$token = is_string($requestData['token'] ?? null)
    ? trim($requestData['token'])
    : '';
$newPassword = is_string($requestData['password_baru'] ?? null)
    ? $requestData['password_baru']
    : '';

if (
    strlen($token) !== 64 ||
    !ctype_xdigit($token) ||
    $newPassword === ''
) {
    sendError("Tautan pemulihan atau kata sandi baru tidak valid.", 400);
}

if (strlen($newPassword) < 8) {
    sendError("Kata sandi baru harus memiliki minimal 8 karakter.", 400);
}

if (strlen($newPassword) > 72) {
    sendError("Kata sandi baru tidak boleh lebih dari 72 byte.", 400);
}

$database = new Database();
$db = $database->getConnection();
$hashedToken = hash('sha256', $token);

try {
    $db->beginTransaction();

    $findTokenStatement = $db->prepare("
        SELECT id, email
        FROM password_resets
        WHERE token = :token
          AND created_at >= DATE_SUB(NOW(), INTERVAL " . RESET_TOKEN_VALID_MINUTES . " MINUTE)
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $findTokenStatement->execute([":token" => $hashedToken]);
    $resetRecord = $findTokenStatement->fetch(PDO::FETCH_ASSOC);

    if ($resetRecord === false) {
        $db->rollBack();
        sendError("Tautan pemulihan tidak valid atau sudah kedaluwarsa.", 401);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    $updatePasswordStatement = $db->prepare(
        "UPDATE siswa SET password = :password WHERE email = :email"
    );
    $updatePasswordStatement->execute([
        ":password" => $hashedPassword,
        ":email" => $resetRecord['email']
    ]);

    if ($updatePasswordStatement->rowCount() !== 1) {
        $db->rollBack();
        sendError("Akun untuk tautan pemulihan tidak ditemukan.", 404);
    }

    $deleteTokenStatement = $db->prepare(
        "DELETE FROM password_resets WHERE id = :id"
    );
    $deleteTokenStatement->execute([":id" => $resetRecord['id']]);

    $db->commit();

    sendSuccess(
        "Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru."
    );
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, "ResetPassword");
}
