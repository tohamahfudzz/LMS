<?php
/*
================================================================================
LOKASI FILE : backend/api/profil/confirm_password_change.php
GUNA FILE   : Menyimpan password baru setelah pengguna membuka tautan dari email.
              Method : POST
              Body   : { "token": "<64 karakter hex dari tautan>", "password_baru": "..." }
              Akses  : tidak perlu login; token dari email adalah kuncinya. Token
                       terikat ke role + id pengguna yang memintanya, jadi hanya
                       password akun itu yang berubah.
              Aturan : token valid, belum kedaluwarsa (15 menit), dan sekali pakai;
                       password baru 8-72 byte.
              Pasangan: request_password_change.php (yang membuat dan mengirim token)
================================================================================
*/

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/password_change.php';

initApi('POST');

$body = readJsonBody();
$token = is_string($body['token'] ?? null) ? trim($body['token']) : '';
$newPassword = is_string($body['password_baru'] ?? null) ? $body['password_baru'] : '';

if (strlen($token) !== 64 || !ctype_xdigit($token) || $newPassword === '') {
    sendError('Tautan atau password baru tidak valid.', 400);
}

if (strlen($newPassword) < PASSWORD_CHANGE_MIN_LENGTH) {
    sendError('Password baru minimal ' . PASSWORD_CHANGE_MIN_LENGTH . ' karakter.', 400);
}

if (strlen($newPassword) > PASSWORD_CHANGE_MAX_BYTES) {
    sendError('Password baru tidak boleh lebih dari ' . PASSWORD_CHANGE_MAX_BYTES . ' byte.', 400);
}

$hashedToken = hash('sha256', $token);

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // FOR UPDATE: dua permintaan serentak dengan token yang sama tidak bisa lolos keduanya.
    $findStatement = $db->prepare("
        SELECT id, role, id_pengguna
        FROM password_change_tokens
        WHERE token = :token
          AND created_at >= DATE_SUB(NOW(), INTERVAL " . PASSWORD_CHANGE_VALID_MINUTES . " MINUTE)
        LIMIT 1
        FOR UPDATE
    ");
    $findStatement->execute([':token' => $hashedToken]);
    $record = $findStatement->fetch(PDO::FETCH_ASSOC);

    if ($record === false) {
        $db->rollBack();
        sendError('Tautan tidak valid atau sudah kedaluwarsa. Minta tautan baru dari halaman profil.', 401);
    }

    $config = getPasswordChangeRoleConfig((string) $record['role']);

    if ($config === null) {
        $db->rollBack();
        sendError('Tautan tidak valid.', 400);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    // Yang diubah hanya satu baris: akun (role + id) pemilik token ini.
    $updateStatement = $db->prepare(
        'UPDATE ' . $config['table'] . ' SET password = :password WHERE ' . $config['primary'] . ' = :id'
    );
    $updateStatement->execute([
        ':password' => $hashedPassword,
        ':id' => (int) $record['id_pengguna'],
    ]);

    if ($updateStatement->rowCount() !== 1) {
        $db->rollBack();
        sendError('Akun untuk tautan ini tidak ditemukan.', 404);
    }

    // Token sekali pakai: hapus semua token milik pengguna ini.
    $deleteStatement = $db->prepare(
        'DELETE FROM password_change_tokens WHERE role = :role AND id_pengguna = :id_pengguna'
    );
    $deleteStatement->execute([
        ':role' => $record['role'],
        ':id_pengguna' => (int) $record['id_pengguna'],
    ]);

    $db->commit();

    sendSuccess('Password berhasil diganti. Silakan masuk dengan password baru.');
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'profil/confirm_password_change');
}