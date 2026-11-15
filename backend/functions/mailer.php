<?php

use PHPMailer\PHPMailer\PHPMailer;

// Mengirim email tautan pemulihan kata sandi lewat SMTP (Brevo).
// $resetToken adalah token asli (bukan hash); tautan dibangun di sini.
// Melempar exception jika konfigurasi atau pengiriman gagal.
function sendResetLinkEmail(string $recipientEmail, string $resetToken, int $validMinutes): void
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $mailConfig = require dirname(__DIR__) . '/config/mail_environment.php';

    $resetUrl = $mailConfig['app_url'] . '/login.html?token=' . rawurlencode($resetToken);
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->Port = (int) $mailConfig['port'];
    $mail->SMTPSecure = $mail->Port === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 15;

    // Brevo: pengirim harus email yang sudah diverifikasi, bukan login SMTP.
    $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
    $mail->addAddress($recipientEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Pemulihan Kata Sandi LMS';
    $mail->Body = "Halo,<br><br>"
        . "Kami menerima permintaan untuk mengatur ulang kata sandi akun LMS Anda.<br><br>"
        . "Klik tautan berikut untuk membuat kata sandi baru:<br>"
        . "<p><a href=\"{$safeUrl}\">Atur ulang kata sandi</a></p>"
        . "Jika tombol tidak bisa dibuka, salin alamat ini ke browser:<br>"
        . "{$safeUrl}<br><br>"
        . "Tautan bersifat rahasia dan hanya berlaku selama <b>{$validMinutes} menit</b>.<br><br>"
        . "Jika Anda tidak merasa meminta pemulihan, abaikan pesan ini. Akun Anda tetap aman.<br><br>"
        . "Salam,<br>Tim Administrator LMS";
    $mail->AltBody = "Atur ulang kata sandi LMS Anda lewat tautan ini:\n{$resetUrl}\n"
        . "Berlaku selama {$validMinutes} menit. Jangan bagikan tautan ini kepada siapa pun.";

    $mail->send();
}