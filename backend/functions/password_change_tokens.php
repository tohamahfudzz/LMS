<?php
/*
================================================================================
LOKASI FILE : backend/functions/password_change_tokens.php
GUNA FILE   : Operasi database untuk token reset/ganti password: mencari akun
              berdasarkan KODE LOGIN, membuat token baru, dan menghapus token.
              Dipakai oleh api/auth/forgot_password.php (tabel yang sama dipakai
              request_password_change.php dan confirm_password_change.php).
              Tidak mengirim email dan tidak mengirim respons ke browser.
================================================================================
*/

require_once __DIR__ . '/password_change.php';

// Mencari akun dari kode login. Role ditentukan dari 2 karakter pertama kode lewat
// tabel kodetb (sama seperti login), lalu kode lengkap dicari di tabel role itu.
// Mengembalikan ['role', 'id', 'nama', 'email'] atau null jika tidak ditemukan.
// 'email' bisa kosong jika akun belum mengisi email.
function findAccountByKode(PDO $db, string $kode): ?array
{
    $roleStatement = $db->prepare('SELECT role FROM kodetb WHERE kode = :prefix LIMIT 1');
    $roleStatement->execute([':prefix' => substr($kode, 0, 2)]);
    $roleRow = $roleStatement->fetch(PDO::FETCH_ASSOC);

    if ($roleRow === false) {
        return null;
    }

    $role = (string) $roleRow['role'];
    $config = getPasswordChangeRoleConfig($role);

    if ($config === null) {
        return null;
    }

    $statement = $db->prepare(
        'SELECT ' . $config['primary'] . ' AS id, nama, email FROM ' . $config['table'] . '
         WHERE ' . $config['kode_column'] . ' = :kode
         LIMIT 1'
    );
    $statement->execute([':kode' => $kode]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return null;
    }

    return [
        'role' => $role,
        'id' => (int) $row['id'],
        'nama' => (string) $row['nama'],
        'email' => (string) ($row['email'] ?? ''),
    ];
}

// Membuat token baru untuk satu akun. Hanya hash-nya yang disimpan; token asli
// dikembalikan agar bisa dikirim lewat email.
// Mengembalikan ['id' => ..., 'token' => ...], atau null jika akun ini baru saja
// meminta token (masih dalam jeda kirim ulang).
function issuePasswordChangeToken(PDO $db, string $role, int $idPengguna): ?array
{
    $recentStatement = $db->prepare("
        SELECT id
        FROM password_change_tokens
        WHERE role = :role
          AND id_pengguna = :id_pengguna
          AND created_at >= DATE_SUB(NOW(), INTERVAL " . PASSWORD_CHANGE_RESEND_SECONDS . " SECOND)
        LIMIT 1
    ");
    $recentStatement->execute([':role' => $role, ':id_pengguna' => $idPengguna]);

    if ($recentStatement->fetch(PDO::FETCH_ASSOC) !== false) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);

    $db->beginTransaction();

    // Hanya satu token aktif per akun.
    $deleteStatement = $db->prepare(
        'DELETE FROM password_change_tokens WHERE role = :role AND id_pengguna = :id_pengguna'
    );
    $deleteStatement->execute([':role' => $role, ':id_pengguna' => $idPengguna]);

    $insertStatement = $db->prepare("
        INSERT INTO password_change_tokens (role, id_pengguna, token)
        VALUES (:role, :id_pengguna, :token)
    ");
    $insertStatement->execute([
        ':role' => $role,
        ':id_pengguna' => $idPengguna,
        ':token' => $hashedToken,
    ]);
    $tokenId = (int) $db->lastInsertId();

    $db->commit();

    return ['id' => $tokenId, 'token' => $token];
}

// Menghapus satu token (dipakai untuk membatalkan token jika email gagal terkirim).
function deletePasswordChangeToken(PDO $db, int $tokenId): void
{
    $statement = $db->prepare('DELETE FROM password_change_tokens WHERE id = :id');
    $statement->execute([':id' => $tokenId]);
}