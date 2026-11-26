<?php
/*
================================================================================
LOKASI FILE : backend/api/tahun_ajaran/list.php
GUNA FILE   : Daftar semua tahun ajaran (aktif dan nonaktif), terbaru di atas.
              Dipakai untuk mengisi pilihan tahun ajaran di dashboard pengajar,
              halaman kelas siswa, dan halaman admin.
              Method : GET
              Role   : semua peran yang sudah login
              Response : { success, message, data: [ { id_tahun_ajaran,
                           nama_tahun_ajaran, tanggal_mulai, tanggal_selesai,
                           status, jumlah_kelas (hanya untuk admin) }, ... ] }
================================================================================
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';

initApi('GET');

$session = requireLogin();
$isAdmin = ($session['role'] ?? '') === 'admin';

$database = new Database();
$db = $database->getConnection();

try {
    $statement = $db->query(
        'SELECT ta.id_tahun_ajaran, ta.nama_tahun_ajaran, ta.tanggal_mulai,
                ta.tanggal_selesai, ta.status, COUNT(k.id_kelas) AS jumlah_kelas
         FROM tahun_ajaran ta
         LEFT JOIN kelas k ON k.id_tahun_ajaran = ta.id_tahun_ajaran
         GROUP BY ta.id_tahun_ajaran, ta.nama_tahun_ajaran, ta.tanggal_mulai,
                  ta.tanggal_selesai, ta.status
         ORDER BY ta.tanggal_mulai DESC, ta.id_tahun_ajaran DESC'
    );

    $list = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $item = [
            'id_tahun_ajaran'   => (int) $row['id_tahun_ajaran'],
            'nama_tahun_ajaran' => $row['nama_tahun_ajaran'],
            'tanggal_mulai'     => $row['tanggal_mulai'],
            'tanggal_selesai'   => $row['tanggal_selesai'],
            'status'            => $row['status'],
        ];

        if ($isAdmin) {
            $item['jumlah_kelas'] = (int) $row['jumlah_kelas'];
        }

        $list[] = $item;
    }

    sendSuccess('Daftar tahun ajaran berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'tahun_ajaran/list');
}