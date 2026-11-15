<?php

$smtpHost = getenv('LMS_SMTP_HOST');
$smtpUsername = getenv('LMS_SMTP_USER');
$smtpPassword = getenv('LMS_SMTP_PASS');
$smtpFromEmail = getenv('LMS_SMTP_FROM_EMAIL');
$appUrl = getenv('LMS_APP_URL');
$smtpPortValue = getenv('LMS_SMTP_PORT');
$smtpPort = $smtpPortValue === false
    ? false
    : filter_var($smtpPortValue, FILTER_VALIDATE_INT);

if (
    $smtpHost === false || $smtpHost === ''
    || $smtpUsername === false || $smtpUsername === ''
    || $smtpPassword === false || $smtpPassword === ''
    || $smtpFromEmail === false || !filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)
    || $appUrl === false || !filter_var($appUrl, FILTER_VALIDATE_URL)
    || !in_array($smtpPort, [465, 587], true)
) {
    throw new RuntimeException('SMTP environment variables are not configured.');
}

return [
    'host' => $smtpHost,
    'port' => $smtpPort,
    'username' => $smtpUsername,
    'password' => $smtpPassword,
    // Alamat pengirim harus email yang sudah diverifikasi di Brevo (bukan login SMTP).
    'from_email' => $smtpFromEmail,
    'from_name' => getenv('LMS_SMTP_FROM_NAME') ?: 'LMS',
    // Alamat dasar frontend untuk membuat tautan di email, tanpa garis miring di akhir.
    'app_url' => rtrim($appUrl, '/'),
];