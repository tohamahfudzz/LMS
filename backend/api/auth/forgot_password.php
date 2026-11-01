<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../functions/response.php';

const RESET_TOKEN_VALID_MINUTES = 15;
const GENERIC_MESSAGE = "Jika email terdaftar, tautan pemulihan akan tersedia.";

function sendForgotPasswordSuccess(string $message, ?string $resetUrl = null): void
{
    http_response_code(200);

    $response = [
        "success" => true,
        "message" => $message
    ];

    if ($resetUrl !== null) {
        $response["reset_url"] = $resetUrl;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

initApi('POST');

$requestData = readJsonBody();
$email = is_string($requestData['email'] ?? null)
    ? trim($requestData['email'])
    : '';

if ($email === '') {
    sendError("Alamat email wajib diisi.", 400);
}

if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError("Format email tidak valid.", 400);
}

/*
 * MODE DEVELOPER: tautan pemulihan dikembalikan langsung di respons.
 * Ini HANYA aman di localhost. Di server publik, siapa pun yang tahu email
 * seseorang bisa mengambil alih akunnya. Hapus blok ini dan kirim tautan
 * lewat email sebelum aplikasi dipakai di produksi.
 */
$isLocalDev = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

if (!$isLocalDev) {
    sendForgotPasswordSuccess(GENERIC_MESSAGE);
}

$database = new Database();
$db = $database->getConnection();

try {
    $cleanupStatement = $db->prepare("
        DELETE FROM password_resets
        WHERE created_at < DATE_SUB(NOW(), INTERVAL " . RESET_TOKEN_VALID_MINUTES . " MINUTE)
    ");
    $cleanupStatement->execute();

    // Tahan terhadap CR/LF, spasi di tepi, dan perbedaan huruf besar/kecil.
    $checkUserStatement = $db->prepare("
        SELECT id_siswa
        FROM siswa
        WHERE LOWER(TRIM(REPLACE(REPLACE(email, CHAR(13), ''), CHAR(10), ''))) = LOWER(:email)
        LIMIT 1
    ");
    $checkUserStatement->execute([":email" => $email]);

    if ($checkUserStatement->fetch(PDO::FETCH_ASSOC) === false) {
        sendForgotPasswordSuccess(GENERIC_MESSAGE);
    }

    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);

    $db->beginTransaction();

    // Hanya satu token aktif per email.
    $deleteOldStatement = $db->prepare(
        "DELETE FROM password_resets WHERE email = :email"
    );
    $deleteOldStatement->execute([":email" => $email]);

    $insertTokenStatement = $db->prepare("
        INSERT INTO password_resets (email, token)
        VALUES (:email, :token)
    ");
    $insertTokenStatement->execute([
        ":email" => $email,
        ":token" => $hashedToken
    ]);

    $db->commit();

    sendForgotPasswordSuccess(
        "Tautan pemulihan siap digunakan dan berlaku selama "
            . RESET_TOKEN_VALID_MINUTES . " menit.",
        "login.html?token=" . rawurlencode($token)
    );
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, "ForgotPassword");
}