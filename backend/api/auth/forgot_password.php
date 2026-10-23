<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../functions/response.php';
require_once '../../functions/password_reset.php';
require_once '../../functions/mailer.php';

initApi('POST');

$requestData = readJsonBody();
$email = is_string($requestData['email'] ?? null) ? trim($requestData['email']) : '';

if ($email === '') {
    sendError("Alamat email wajib diisi.", 400);
}

if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError("Format email tidak valid.", 400);
}

// Pesan sama untuk email terdaftar maupun tidak, agar daftar akun tidak bisa ditebak.
$responseMessage = "Jika email terdaftar, kode pemulihan telah dikirim. "
    . "Kode berlaku " . RESET_CODE_VALID_MINUTES . " menit.";

$database = new Database();
$db = $database->getConnection();

try {
    // Membersihkan kode kedaluwarsa agar tabel tidak menumpuk.
    $cleanupStatement = $db->prepare("
        DELETE FROM password_resets
        WHERE created_at < DATE_SUB(NOW(), INTERVAL " . RESET_CODE_VALID_MINUTES . " MINUTE)
    ");
    $cleanupStatement->execute();

    $checkUserStatement = $db->prepare("SELECT email FROM siswa WHERE email = :email LIMIT 1");
    $checkUserStatement->execute([":email" => $email]);

    if ($checkUserStatement->fetch(PDO::FETCH_ASSOC) === false) {
        sendSuccess($responseMessage);
    }

    // Jeda kirim ulang: permintaan beruntun tidak memicu email baru.
    $recentRequestStatement = $db->prepare("
        SELECT id
        FROM password_resets
        WHERE email = :email
          AND created_at >= DATE_SUB(NOW(), INTERVAL " . RESET_CODE_RESEND_SECONDS . " SECOND)
        LIMIT 1
    ");
    $recentRequestStatement->execute([":email" => $email]);

    if ($recentRequestStatement->fetch(PDO::FETCH_ASSOC) !== false) {
        sendSuccess($responseMessage);
    }

    $resetCode = generateResetCode();

    // Yang disimpan adalah hash kode, bukan kode aslinya.
    $hashedCode = password_hash($resetCode, PASSWORD_DEFAULT);

    // Hanya satu kode aktif per email.
    $deleteOldStatement = $db->prepare("DELETE FROM password_resets WHERE email = :email");
    $deleteOldStatement->execute([":email" => $email]);

    $insertCodeStatement = $db->prepare("
        INSERT INTO password_resets (email, token) VALUES (:email, :token)
    ");
    $insertCodeStatement->execute([
        ":email" => $email,
        ":token" => $hashedCode
    ]);
    $resetId = (int) $db->lastInsertId();

    try {
        sendResetCodeEmail($email, $resetCode, RESET_CODE_VALID_MINUTES);
    } catch (Throwable $error) {
        error_log("[LMS][ForgotPassword][Email] " . get_class($error) . ": " . $error->getMessage());

        // Kode dibatalkan agar percobaan berikutnya tidak tertahan jeda kirim ulang.
        $rollbackCodeStatement = $db->prepare("DELETE FROM password_resets WHERE id = :id");
        $rollbackCodeStatement->execute([":id" => $resetId]);

        sendError("Gagal mengirim email.", 500);
    }

    sendSuccess($responseMessage);
} catch (PDOException $error) {
    sendServerError($error, "ForgotPassword");
}