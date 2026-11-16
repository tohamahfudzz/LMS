<?php
/*
================================================================================
LOKASI FILE : backend/api/auth/forgot_password.php
GUNA FILE   : Meminta tautan reset password dari halaman "Lupa password?" (belum
              login). Pengguna memasukkan KODE LOGIN-nya; role ditentukan dari 2
              karakter pertama kode (tabel kodetb), akun dicari di tabel role itu,
              lalu tautan dikirim ke email yang tersimpan di akun tersebut.
              Method : POST
              Body   : { "kode": "..." }
              Akses  : publik (tanpa login).
              Keamanan:
                - Respons SELALU sama, baik kode terdaftar atau tidak, akun punya
                  email atau tidak, sehingga daftar akun tidak bisa ditebak.
                - Email dikirim SETELAH respons dikembalikan, jadi lamanya proses
                  dan error pengiriman tidak terlihat oleh pemanggil.
                - Jeda 60 detik per akun; token berlaku 15 menit, sekali pakai.
              Berikutnya: pengguna membuka tautan -> ganti-password.html
                          -> api/profil/confirm_password_change.php
================================================================================
*/

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/password_change_tokens.php';
require_once __DIR__ . '/../../functions/mailer_forgot_password.php';

const FORGOT_KODE_MIN_LENGTH = 2;
const FORGOT_KODE_MAX_LENGTH = 20;
const FORGOT_GENERIC_MESSAGE = "Jika kode terdaftar dan akun memiliki email, tautan reset password telah dikirim ke email akun tersebut. Tautan berlaku "
    . PASSWORD_CHANGE_VALID_MINUTES . " menit.";

// Mengirim respons yang selalu sama. Dengan $continueAfter = true, script TIDAK
// berhenti sehingga email bisa dikirim setelah klien menerima respons.
function sendForgotGenericResponse(bool $continueAfter = false): void
{
    ignore_user_abort(true);
    set_time_limit(60);

    $body = json_encode(
        ["success" => true, "message" => FORGOT_GENERIC_MESSAGE],
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
$kode = is_string($requestData['kode'] ?? null) ? trim($requestData['kode']) : '';

if ($kode === '') {
    sendError("Kode wajib diisi.", 400);
}

if (strlen($kode) < FORGOT_KODE_MIN_LENGTH || strlen($kode) > FORGOT_KODE_MAX_LENGTH) {
    sendError(
        "Kode harus " . FORGOT_KODE_MIN_LENGTH . " sampai " . FORGOT_KODE_MAX_LENGTH . " karakter.",
        400
    );
}

$database = new Database();
$db = $database->getConnection();

$issued = null;
$account = null;
$email = '';

try {
    // Membersihkan token kedaluwarsa agar tabel tidak menumpuk.
    $cleanupStatement = $db->prepare("
        DELETE FROM password_change_tokens
        WHERE created_at < DATE_SUB(NOW(), INTERVAL " . PASSWORD_CHANGE_VALID_MINUTES . " MINUTE)
    ");
    $cleanupStatement->execute();

    $account = findAccountByKode($db, $kode);

    if ($account !== null) {
        // Buang CR/LF dan spasi di tepi (data lama bisa menyimpannya).
        $email = strtolower(trim(preg_replace('/[\r\n]+/', '', $account['email'])));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // null = akun ini baru saja meminta (masih dalam jeda kirim ulang).
            $issued = issuePasswordChangeToken($db, $account['role'], $account['id']);
        }
    }
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'ForgotPassword');
}

// Kode tidak terdaftar, akun tanpa email, atau masih dalam jeda: jawaban tetap
// sama, tanpa email.
if ($issued === null) {
    sendForgotGenericResponse();
}

// Respons dikirim lebih dulu, baru email dikirim lewat SMTP.
sendForgotGenericResponse(true);

try {
    sendForgotPasswordEmail(
        $email,
        $account['nama'],
        $account['role'],
        $issued['token'],
        PASSWORD_CHANGE_VALID_MINUTES
    );
} catch (Throwable $error) {
    error_log("[LMS][ForgotPassword][Email] " . get_class($error) . ": " . $error->getMessage());

    // Token dibatalkan agar percobaan berikutnya tidak tertahan jeda kirim ulang.
    try {
        deletePasswordChangeToken($db, $issued['id']);
    } catch (Throwable $rollbackError) {
        error_log("[LMS][ForgotPassword][Rollback] " . $rollbackError->getMessage());
    }
}