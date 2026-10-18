<?php
/*
================================================================================
LOKASI FILE : backend/api/siswa/kelas/list.php
GUNA FILE   : Daftar kelas yang diikuti siswa yang login (dari kelas_siswa).
              Method : GET
              Role   : siswa
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';

initApi('GET');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$database = new Database();
$db = $database->getConnection();

try {
    $statement = $db->prepare(
        "SELECT k.id_kelas, k.nama_kelas
         FROM kelas k
         INNER JOIN kelas_siswa ks ON ks.id_kelas = k.id_kelas
         WHERE ks.id_siswa = :id_siswa
         ORDER BY k.nama_kelas ASC"
    );
    $statement->execute([':id_siswa' => $idSiswa]);

    $list = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $list[] = [
            'id_kelas'   => (int) $row['id_kelas'],
            'nama_kelas' => $row['nama_kelas'],
        ];
    }

    sendSuccess('Daftar kelas berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'siswa/kelas/list');
}