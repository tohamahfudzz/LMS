<?php

// Helper kode pemulihan kata sandi, dipakai bersama oleh
// forgot_password.php dan reset_password.php.

const RESET_CODE_LENGTH = 8;
const RESET_CODE_VALID_MINUTES = 15;
const RESET_CODE_RESEND_SECONDS = 60;

// Karakter yang mudah tertukar (0, O, 1, I) sengaja tidak dipakai
// agar kode mudah diketik dari email.
const RESET_CODE_ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";

// Membuat kode acak yang aman secara kriptografis.
function generateResetCode(): string
{
    $maxIndex = strlen(RESET_CODE_ALPHABET) - 1;
    $resetCode = "";

    for ($position = 0; $position < RESET_CODE_LENGTH; $position++) {
        $resetCode .= RESET_CODE_ALPHABET[random_int(0, $maxIndex)];
    }

    return $resetCode;
}

// Merapikan kode input pengguna (huruf besar, tanpa spasi/tanda hubung).
// Mengembalikan string kosong jika formatnya tidak sesuai.
function normalizeResetCode(string $inputCode): string
{
    $normalizedCode = strtoupper(preg_replace('/[\s-]+/', '', $inputCode));
    $pattern = '/^[' . RESET_CODE_ALPHABET . ']{' . RESET_CODE_LENGTH . '}$/';

    return preg_match($pattern, $normalizedCode) === 1 ? $normalizedCode : "";
}