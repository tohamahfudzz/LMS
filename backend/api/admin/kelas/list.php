<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/kelas/list.php
GUNA FILE   : Mengambil daftar semua kelas (untuk tabel admin).
              Hanya role admin.
              Method : GET
              Response : { success, message, data: [ { id_kelas, nama_kelas }, ... ] }
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
    $statement = $db->prepare(
        'SELECT id_kelas, nama_kelas
         FROM kelas
         ORDER BY nama_kelas ASC'
    );
    $statement->execute();

    $list = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $list[] = [
            'id_kelas'   => (int) $row['id_kelas'],
            'nama_kelas' => $row['nama_kelas'],
        ];
    }

    sendSuccess('Daftar kelas berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'admin/kelas/list');
}