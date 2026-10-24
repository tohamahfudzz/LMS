<?php
/*
================================================================================
LOKASI FILE : backend/api/profil/update_email.php
GUNA FILE   : Mengganti email pengguna yang sedang login (admin, pengajar, atau siswa).
              Email baru langsung menimpa email lama di kolom `email`.
              Method : POST
              Body   : { "email": "nama@contoh.com" }  (application/json)
              Role   : semua peran yang sudah login. Tabel dan ID diambil dari session
                       PHP, bukan dari input pengguna, sehingga pengguna hanya bisa
                       mengubah emailnya sendiri.
              Aturan : wajib diisi, format email valid, maksimal 100 karakter,
                       disimpan dalam huruf kecil. Email tidak harus unik.
              Respons: data = { email } berisi email yang tersimpan.
================================================================================
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';

const EMAIL_MAX_LENGTH = 100;

initApi('POST');

$session = requireLogin();
$role = (string) $session['role'];
$idPengguna = (int) $session['id'];

// Daftar putih tabel per peran. Nama tabel/kolom di bawah ini tidak pernah berasal
// dari input pengguna, jadi aman dipakai dalam query. (Sama dengan get.php.)
$tableMap = [
    'admin'    => ['table' => 'admin',    'primary' => 'id_admin'],
    'pengajar' => ['table' => 'pengajar', 'primary' => 'id_pengajar'],
    'siswa'    => ['table' => 'siswa',    'primary' => 'id_siswa'],
];

if (!isset($tableMap[$role])) {
    sendError('Peran pengguna tidak dikenal.', 403);
}

$config = $tableMap[$role];

$body = readJsonBody();
$email = $body['email'] ?? '';

if (!is_string($email)) {
    sendError('Format email tidak valid.', 400);
}

$email = trim($email);

if ($email === '') {
    sendError('Email baru wajib diisi.', 400);
}

if (strlen($email) > EMAIL_MAX_LENGTH) {
    sendError('Email maksimal ' . EMAIL_MAX_LENGTH . ' karakter.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError('Format email tidak valid.', 400);
}

// Email dibuat huruf kecil agar konsisten (setelah lolos validasi, isinya hanya ASCII).
$email = strtolower($email);

$database = new Database();
$db = $database->getConnection();

try {
    $findStatement = $db->prepare(
        'SELECT ' . $config['primary'] . ' FROM ' . $config['table'] .
        ' WHERE ' . $config['primary'] . ' = :id LIMIT 1'
    );
    $findStatement->execute([':id' => $idPengguna]);

    if (!$findStatement->fetch()) {
        sendError('Data pengguna tidak ditemukan.', 404);
    }

    $updateStatement = $db->prepare(
        'UPDATE ' . $config['table'] . ' SET email = :email WHERE ' . $config['primary'] . ' = :id'
    );
    $updateStatement->execute([
        ':email' => $email,
        ':id' => $idPengguna,
    ]);

    sendSuccess('Email berhasil diperbarui.', ['email' => $email]);
} catch (Throwable $error) {
    sendServerError($error, 'profil/update_email');
}