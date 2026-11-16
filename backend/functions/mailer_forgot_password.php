<?php
/*
================================================================================
LOKASI FILE : backend/functions/mailer_forgot_password.php
GUNA FILE   : Menyusun dan mengirim email "lupa password" (tautan reset) untuk
              satu akun. Hanya mengurus isi email; koneksi SMTP ada di
              mail_transport.php.
              Tautan: {LMS_APP_URL}/ganti-password.html?token=...
              Melempar exception jika pengiriman gagal.
================================================================================
*/

require_once __DIR__ . '/mail_transport.php';

// $resetToken adalah token asli (bukan hash).
function sendForgotPasswordEmail(
    string $recipientEmail,
    string $namaAkun,
    string $role,
    string $resetToken,
    int $validMinutes
): void {
    $mailConfig = loadMailConfig();

    $resetUrl = $mailConfig['app_url'] . '/ganti-password.html?token=' . rawurlencode($resetToken);
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($namaAkun, ENT_QUOTES, 'UTF-8');
    $safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

    $mail = createSmtpMailer($mailConfig);
    $mail->addAddress($recipientEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Reset Password Akun LMS';
    $mail->Body = "Halo,<br><br>"
        . "Kami menerima permintaan lupa password untuk akun LMS <b>{$safeName}</b> ({$safeRole}).<br><br>"
        . "Klik tautan berikut untuk membuat password baru:<br>"
        . "<p><a href=\"{$safeUrl}\">Atur ulang password</a></p>"
        . "Jika tautan tidak bisa dibuka, salin alamat ini ke browser:<br>"
        . "{$safeUrl}<br><br>"
        . "Tautan bersifat rahasia, hanya bisa dipakai satu kali, dan berlaku selama <b>{$validMinutes} menit</b>.<br><br>"
        . "Jika Anda tidak merasa memintanya, abaikan email ini dan password Anda tidak akan berubah.<br><br>"
        . "Salam,<br>Tim Administrator LMS";
    $mail->AltBody = "Reset password akun LMS {$namaAkun} ({$role}) lewat tautan ini:\n{$resetUrl}\n"
        . "Berlaku {$validMinutes} menit dan hanya bisa dipakai sekali. "
        . "Jika Anda tidak memintanya, abaikan email ini.";

    $mail->send();
}