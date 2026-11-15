<?php
/*
================================================================================
LOKASI FILE : backend/functions/mail_transport.php
GUNA FILE   : Menyiapkan koneksi SMTP (Brevo) untuk PHPMailer. Hanya mengurus
              "cara mengirim"; isi email ada di file mailer_*.php.
              Pengaturan dibaca dari backend/config/mail_environment.php
              (environment variable LMS_SMTP_*).
              Melempar exception jika konfigurasi atau library belum siap.
================================================================================
*/

use PHPMailer\PHPMailer\PHPMailer;

// Memuat pengaturan email. Melempar RuntimeException jika variabel belum diatur.
function loadMailConfig(): array
{
    return require dirname(__DIR__) . '/config/mail_environment.php';
}

// Membuat objek PHPMailer yang sudah terhubung ke SMTP dan pengirimnya sudah diatur.
function createSmtpMailer(array $mailConfig): PHPMailer
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';

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

    return $mail;
}