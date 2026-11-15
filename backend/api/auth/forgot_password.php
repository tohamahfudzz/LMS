<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../functions/response.php';
require_once '../../functions/mailer.php';

const RESET_TOKEN_VALID_MINUTES = 15;
const RESET_RESEND_SECONDS = 60;
const GENERIC_MESSAGE = "Jika email terdaftar, tautan pemulihan telah dikirim ke email tersebut. Tautan berlaku 15 menit.";

// Mengirim respons yang selalu sama (email terdaftar maupun tidak).
// Dengan $continueAfter = true, script TIDAK berhenti sehingga email bisa dikirim
// setelah klien menerima respons; lamanya SMTP tidak terlihat oleh klien.
function sendGenericResponse(bool $continueAfter = false): void
{
    ignore_user_abort(true);
    set_time_limit(60);

    $body = json_encode(
        ["success" => true, "message" => GENERIC_MESSAGE],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    http_response_code(200);

    if (!$continueAfter) {
        echo $body;
        exit;
    }

    if (function_exists('fastcgi_finish_request')) {
        // PHP-FPM
        echo $body;
        fastcgi_finish_request();
        return;
    }

    // Apache mod_php (XAMPP): tutup koneksi setelah isi respons terkirim.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header("Content-Encoding: none");
    header("Content-Length: " . strlen($body));
    header("Connection: close");

    echo $body;
    flush();
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
        sendGenericResponse();
    }

    // Jeda kirim ulang: permintaan beruntun tidak memicu email baru.
    $recentRequestStatement = $db->prepare("
        SELECT id
        FROM password_resets
        WHERE email = :email
          AND created_at >= DATE_SUB(NOW(), INTERVAL " . RESET_RESEND_SECONDS . " SECOND)
        LIMIT 1
    ");
    $recentRequestStatement->execute([":email" => $email]);

    if ($recentRequestStatement->fetch(PDO::FETCH_ASSOC) !== false) {
        sendGenericResponse();
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
    $resetId = (int) $db->lastInsertId();

    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, "ForgotPassword");
}

// Respons dikirim lebih dulu, baru email dikirim lewat SMTP.
sendGenericResponse(true);

try {
    sendResetLinkEmail($email, $token, RESET_TOKEN_VALID_MINUTES);
} catch (Throwable $error) {
    error_log("[LMS][ForgotPassword][Email] " . get_class($error) . ": " . $error->getMessage());

    // Token dibatalkan agar percobaan berikutnya tidak tertahan jeda kirim ulang.
    try {
        $rollbackTokenStatement = $db->prepare("DELETE FROM password_resets WHERE id = :id");
        $rollbackTokenStatement->execute([":id" => $resetId]);
    } catch (Throwable $rollbackError) {
        error_log("[LMS][ForgotPassword][Rollback] " . $rollbackError->getMessage());
    }
}