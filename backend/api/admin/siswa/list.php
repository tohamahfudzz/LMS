<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/siswa/list.php
GUNA FILE   : Mengambil daftar semua siswa (untuk tabel / dropdown admin).
              Password tidak ikut dikirim.
              Hanya role admin.
              Method : GET
              Response : { success, message, data: [ { id_siswa, kode_siswa, nama }, ... ] }
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';

initApi('GET');

$session = requireRole('admin');

$database = new Database();
$db = $database->getConnection();

try {
    $statement = $db->query(
        'SELECT id_siswa, kode_siswa, nama
         FROM siswa
         ORDER BY nama ASC'
    );

    $list = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $list[] = [
            'id_siswa'   => (int) $row['id_siswa'],
            'kode_siswa' => $row['kode_siswa'],
            'nama'       => $row['nama'],
        ];
    }

    sendSuccess('Daftar siswa berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'admin/siswa/list');
}