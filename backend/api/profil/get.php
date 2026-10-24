<?php
/*
================================================================================
LOKASI FILE : backend/api/profil/get.php
GUNA FILE   : Mengambil data profil pengguna yang sedang login (admin, pengajar,
              atau siswa) untuk halaman profil.html.
              Method : GET
              Role   : semua peran yang sudah login. Peran dan ID diambil dari
                       session PHP, bukan dari input pengguna, sehingga pengguna
                       hanya bisa melihat profilnya sendiri.
              Respons: data = { nama, kode, role, email }  (email bisa null)
================================================================================
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';

initApi('GET');

$session = requireLogin();
$role = (string) $session['role'];
$idPengguna = (int) $session['id'];

// Daftar putih tabel per peran. Nama tabel/kolom di bawah ini tidak pernah berasal
// dari input pengguna, jadi aman dipakai dalam query. (Sama dengan update_email.php.)
$tableMap = [
    'admin'    => ['table' => 'admin',    'primary' => 'id_admin',    'kode' => 'kode_admin'],
    'pengajar' => ['table' => 'pengajar', 'primary' => 'id_pengajar', 'kode' => 'kode_pengajar'],
    'siswa'    => ['table' => 'siswa',    'primary' => 'id_siswa',    'kode' => 'kode_siswa'],
];

if (!isset($tableMap[$role])) {
    sendError('Peran pengguna tidak dikenal.', 403);
}

$config = $tableMap[$role];

$database = new Database();
$db = $database->getConnection();

try {
    $statement = $db->prepare(
        'SELECT nama, ' . $config['kode'] . ' AS kode, email
           FROM ' . $config['table'] . '
          WHERE ' . $config['primary'] . ' = :id
          LIMIT 1'
    );
    $statement->execute([':id' => $idPengguna]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendError('Data pengguna tidak ditemukan.', 404);
    }

    sendSuccess('Data profil berhasil diambil.', [
        'nama' => $user['nama'],
        'kode' => $user['kode'],
        'role' => $role,
        'email' => $user['email'],
    ]);
} catch (Throwable $error) {
    sendServerError($error, 'profil/get');
}