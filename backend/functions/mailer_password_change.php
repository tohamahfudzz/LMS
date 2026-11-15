<?php
/*
================================================================================
LOKASI FILE : backend/functions/mailer_password_change.php
GUNA FILE   : Menyusun dan mengirim email "tautan ganti password" dari halaman
              profil. Hanya mengurus isi email; koneksi SMTP ada di
              mail_transport.php.
              Tautan: {LMS_APP_URL}/ganti-password.html?token=...
              Melempar exception jika pengiriman gagal.
================================================================================
*/

require_once __DIR__ . '/mail_transport.php';

// $changeToken adalah token asli (bukan hash).
function sendPasswordChangeEmail(string $recipientEmail, string $changeToken, int $validMinutes): void
{
    $mailConfig = loadMailConfig();

    $changeUrl = $mailConfig['app_url'] . '/ganti-password.html?token=' . rawurlencode($changeToken);
    $safeUrl = htmlspecialchars($changeUrl, ENT_QUOTES, 'UTF-8');

    $mail = createSmtpMailer($mailConfig);
    $mail->addAddress($recipientEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Ganti Password Akun LMS';
    $mail->Body = "Halo,<br><br>"
        . "Kami menerima permintaan untuk mengganti password akun LMS Anda dari halaman profil.<br><br>"
        . "Klik tautan berikut untuk membuat password baru:<br>"
        . "<p><a href=\"{$safeUrl}\">Ganti password</a></p>"
        . "Jika tautan tidak bisa dibuka, salin alamat ini ke browser:<br>"
        . "{$safeUrl}<br><br>"
        . "Tautan bersifat rahasia, hanya bisa dipakai satu kali, dan berlaku selama <b>{$validMinutes} menit</b>.<br><br>"
        . "Jika Anda tidak merasa memintanya, abaikan email ini dan password Anda tidak akan berubah. "
        . "Sebaiknya segera periksa keamanan akun Anda.<br><br>"
        . "Salam,<br>Tim Administrator LMS";
    $mail->AltBody = "Ganti password akun LMS Anda lewat tautan ini:\n{$changeUrl}\n"
        . "Berlaku {$validMinutes} menit dan hanya bisa dipakai sekali. "
        . "Jika Anda tidak memintanya, abaikan email ini.";

    $mail->send();
}