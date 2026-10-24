<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../functions/response.php';
require_once '../../functions/password_reset.php';

initApi('POST');

$requestData = readJsonBody();
$email = is_string($requestData['email'] ?? null) ? trim($requestData['email']) : '';
$resetCode = is_string($requestData['token'] ?? null) ? normalizeResetCode($requestData['token']) : '';
$newPassword = is_string($requestData['password_baru'] ?? null) ? $requestData['password_baru'] : '';

if ($email === '' || $resetCode === '' || $newPassword === '') {
    sendError("Email, kode pemulihan, dan kata sandi baru wajib diisi dengan benar.", 400);
}

if (strlen($newPassword) < 8) {
    sendError("Kata sandi baru harus memiliki minimal 8 karakter.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $findCodeStatement = $db->prepare("
        SELECT id, token
        FROM password_resets
        WHERE email = :email
          AND created_at >= DATE_SUB(NOW(), INTERVAL " . RESET_CODE_VALID_MINUTES . " MINUTE)
        ORDER BY id DESC
        LIMIT 1
    ");
    $findCodeStatement->execute([":email" => $email]);
    $resetRecord = $findCodeStatement->fetch(PDO::FETCH_ASSOC);

    // Pesan sama untuk email salah, kode salah, maupun kode kedaluwarsa.
    if ($resetRecord === false || !password_verify($resetCode, $resetRecord['token'])) {
        sendError("Kode pemulihan tidak valid atau sudah kedaluwarsa.", 401);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Ganti kata sandi dan hapus kode dalam satu transaksi agar kode tidak bisa dipakai ulang.
    $db->beginTransaction();

    $updatePasswordStatement = $db->prepare("UPDATE siswa SET password = :password WHERE email = :email");
    $updatePasswordStatement->execute([
        ":password" => $hashedPassword,
        ":email" => $email
    ]);

    $deleteCodeStatement = $db->prepare("DELETE FROM password_resets WHERE email = :email");
    $deleteCodeStatement->execute([":email" => $email]);

    $db->commit();

    sendSuccess("Kata sandi berhasil diperbarui. Silakan masuk kembali dengan sandi baru Anda.");
} catch (PDOException $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, "ResetPassword");
}