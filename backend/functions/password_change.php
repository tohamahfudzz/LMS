<?php
/*
================================================================================
LOKASI FILE : backend/functions/password_change.php
GUNA FILE   : Konstanta dan helper kecil untuk fitur ganti/reset password
              (halaman profil dan "Lupa password?"). Dipakai oleh
              request_password_change.php, confirm_password_change.php, dan
              forgot_password.php. Tidak mengakses database dan tidak mengirim
              respons.
================================================================================
*/

const PASSWORD_CHANGE_VALID_MINUTES = 15;
const PASSWORD_CHANGE_RESEND_SECONDS = 60;
const PASSWORD_CHANGE_MIN_LENGTH = 8;
const PASSWORD_CHANGE_MAX_BYTES = 72; // batas bcrypt

// Daftar putih role -> tabel. Nama tabel/kolom tidak pernah berasal dari input
// pengguna, jadi aman dipakai langsung di query. Null jika role tidak dikenal.
function getPasswordChangeRoleConfig(string $role): ?array
{
    $roleConfigs = [
        'admin'    => ['table' => 'admin',    'primary' => 'id_admin',    'kode_column' => 'kode_admin'],
        'pengajar' => ['table' => 'pengajar', 'primary' => 'id_pengajar', 'kode_column' => 'kode_pengajar'],
        'siswa'    => ['table' => 'siswa',    'primary' => 'id_siswa',    'kode_column' => 'kode_siswa'],
    ];

    return $roleConfigs[$role] ?? null;
}

// Menyamarkan email untuk ditampilkan: nama@contoh.com -> n***@contoh.com
function maskEmail(string $email): string
{
    $parts = explode('@', $email, 2);

    if (count($parts) !== 2 || $parts[0] === '') {
        return $email;
    }

    return substr($parts[0], 0, 1) . '***@' . $parts[1];
}