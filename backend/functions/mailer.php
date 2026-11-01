<?php

use PHPMailer\PHPMailer\PHPMailer;

// Mengirim email kode pemulihan kata sandi lewat SMTP.
// Melempar exception jika konfigurasi atau pengiriman gagal.
function sendResetCodeEmail(string $recipientEmail, string $resetCode, int $validMinutes): void
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $mailConfig = require dirname(__DIR__) . '/config/mail_environment.php';

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

    // Gmail mewajibkan alamat pengirim sama dengan akun yang login.
    $mail->setFrom($mailConfig['username'], $mailConfig['from_name']);
    $mail->addAddress($recipientEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Kode Reset Kata Sandi LMS';
    $mail->Body = "Halo,<br><br>"
        . "Kami menerima permintaan untuk mengatur ulang kata sandi akun LMS Anda.<br><br>"
        . "Kode pemulihan Anda:<br>"
        . "<h2 style=\"letter-spacing: 4px;\">{$resetCode}</h2>"
        . "Masukkan kode ini pada halaman reset kata sandi. "
        . "Kode bersifat rahasia dan hanya berlaku selama <b>{$validMinutes} menit</b>.<br><br>"
        . "Jika Anda tidak merasa meminta pemulihan, abaikan pesan ini. Akun Anda tetap aman.<br><br>"
        . "Salam,<br>Tim Administrator LMS";
    $mail->AltBody = "Kode pemulihan LMS Anda: {$resetCode}\n"
        . "Berlaku selama {$validMinutes} menit. Jangan bagikan kode ini kepada siapa pun.";

    $mail->send();
}