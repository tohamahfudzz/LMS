<?php

$smtpHost = getenv('LMS_SMTP_HOST');
$smtpUsername = getenv('LMS_SMTP_USER');
$smtpPassword = getenv('LMS_SMTP_PASS');
$smtpPortValue = getenv('LMS_SMTP_PORT');
$smtpPort = $smtpPortValue === false
    ? false
    : filter_var($smtpPortValue, FILTER_VALIDATE_INT);

if (
    $smtpHost === false || $smtpHost === ''
    || $smtpUsername === false || $smtpUsername === ''
    || $smtpPassword === false
    || !in_array($smtpPort, [465, 587], true)
) {
    throw new RuntimeException('SMTP environment variables are not configured.');
}

return [
    'host' => $smtpHost,
    'port' => $smtpPort,
    'username' => $smtpUsername,
    'password' => $smtpPassword,
    'from_name' => getenv('LMS_SMTP_FROM_NAME') ?: 'LMS',
];
