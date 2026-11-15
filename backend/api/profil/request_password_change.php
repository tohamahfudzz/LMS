<?php
/*
================================================================================
LOKASI FILE : backend/api/profil/request_password_change.php
GUNA FILE   : Meminta tautan ganti password untuk pengguna yang sedang login
              (admin, pengajar, atau siswa). Membuat token, menyimpan hash-nya, lalu
              mengirim tautan ke EMAIL AKUN pengguna itu sendiri.
              Method : POST (body JSON boleh kosong: {})
              Role   : semua peran yang sudah login. Role dan ID diambil dari
                       session PHP, bukan dari input pengguna, sehingga tautan
                       hanya bisa diminta untuk akun sendiri.
              Aturan : email akun harus sudah terisi; jeda 60 detik antar permintaan;
                       token berlaku 15 menit; hanya satu token aktif per pengguna.
              Respons: pesan sukses berisi email yang disamarkan.
              Berikutnya: pengguna membuka tautan -> confirm_password_change.php
================================================================================
*/

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/password_change.php';
require_once __DIR__ . '/../../functions/mailer_password_change.php';

initApi('POST');

$session = requireLogin();
$role = (string) $session['role'];
$idPengguna = (int) $session['id'];

$config = getPasswordChangeRoleConfig($role);

if ($config === null) {
    sendError('Peran pengguna tidak dikenal.', 403);
}

$database = new Database();
$db = $database->getConnection();

try {
    // Membersihkan token kedaluwarsa agar tabel tidak menumpuk.
    $cleanupStatement = $db->prepare("
        DELETE FROM password_change_tokens
        WHERE created_at < DATE_SUB(NOW(), INTERVAL " . PASSWORD_CHANGE_VALID_MINUTES . " MINUTE)
    ");
    $cleanupStatement->execute();

    $emailStatement = $db->prepare(
        'SELECT email FROM ' . $config['table'] . ' WHERE ' . $config['primary'] . ' = :id LIMIT 1'
    );
    $emailStatement->execute([':id' => $idPengguna]);
    $user = $emailStatement->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendError('Data pengguna tidak ditemukan.', 404);
    }

    $email = strtolower(trim((string) ($user['email'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Email akun belum diisi atau tidak valid. Isi email di bagian "Ganti Email" terlebih dahulu.', 400);
    }

    // Jeda kirim ulang: mencegah email dikirim berulang dengan cepat.
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
        sendError('Tautan baru saja dikirim. Tunggu 1 menit sebelum meminta lagi.', 429);
    }

    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);

    $db->beginTransaction();

    // Hanya satu token aktif per pengguna.
    $deleteOldStatement = $db->prepare(
        'DELETE FROM password_change_tokens WHERE role = :role AND id_pengguna = :id_pengguna'
    );
    $deleteOldStatement->execute([':role' => $role, ':id_pengguna' => $idPengguna]);

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
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'profil/request_password_change');
}

try {
    sendPasswordChangeEmail($email, $token, PASSWORD_CHANGE_VALID_MINUTES);
} catch (Throwable $error) {
    error_log('[LMS][PasswordChange][Email] ' . get_class($error) . ': ' . $error->getMessage());

    // Token dibatalkan agar pengguna bisa mencoba lagi tanpa menunggu jeda.
    try {
        $rollbackStatement = $db->prepare('DELETE FROM password_change_tokens WHERE id = :id');
        $rollbackStatement->execute([':id' => $tokenId]);
    } catch (Throwable $rollbackError) {
        error_log('[LMS][PasswordChange][Rollback] ' . $rollbackError->getMessage());
    }

    sendError('Gagal mengirim email. Silakan coba lagi nanti.', 500);
}

sendSuccess(
    'Tautan ganti password telah dikirim ke ' . maskEmail($email) .
    '. Tautan berlaku ' . PASSWORD_CHANGE_VALID_MINUTES . ' menit.'
);