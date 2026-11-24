<?php

header('Content-Type: text/plain');

try {
    require_once __DIR__ . '/vendor/autoload.php';

    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        echo "PHPMailer berhasil dimuat.";
    } else {
        echo "PHPMailer tidak ditemukan.";
    }

} catch (Throwable $e) {
    error_log('Mailer test: ' . $e->getMessage());
    echo "Terjadi kesalahan saat memuat PHPMailer.";
}