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
        'SELECT k.id_kelas, k.nama_kelas, k.id_tahun_ajaran, ta.nama_tahun_ajaran, k.semester
         FROM kelas k
         LEFT JOIN tahun_ajaran ta ON ta.id_tahun_ajaran = k.id_tahun_ajaran
         ORDER BY k.nama_kelas ASC'
    );
    $statement->execute();

    $list = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $list[] = [
            'id_kelas'          => (int) $row['id_kelas'],
            'nama_kelas'        => $row['nama_kelas'],
            'id_tahun_ajaran'   => $row['id_tahun_ajaran'] === null ? null : (int) $row['id_tahun_ajaran'],
            'nama_tahun_ajaran' => $row['nama_tahun_ajaran'],
            'semester'          => $row['semester'],
        ];
    }

    sendSuccess('Daftar kelas berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'admin/kelas/list');
}