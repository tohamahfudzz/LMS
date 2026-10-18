<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/pengajar/list.php
GUNA FILE   : Mengambil daftar semua pengajar (untuk tabel / dropdown admin).
              Password tidak ikut dikirim.
              Hanya role admin.
              Method : GET
              Response : { success, message, data: [ { id_pengajar, kode_pengajar, nama }, ... ] }
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
        'SELECT id_pengajar, kode_pengajar, nama
         FROM pengajar
         ORDER BY nama ASC'
    );

    $list = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $list[] = [
            'id_pengajar'   => (int) $row['id_pengajar'],
            'kode_pengajar' => $row['kode_pengajar'],
            'nama'          => $row['nama'],
        ];
    }

    sendSuccess('Daftar pengajar berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'admin/pengajar/list');
}