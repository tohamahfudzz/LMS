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
require_once __DIR__ . '/../../../functions/tahun_ajaran.php';

initApi('GET');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$database = new Database();
$db = $database->getConnection();

try {
    // Filter opsional dari halaman kelas: ?id_tahun_ajaran=1&semester=ganjil
    // Tanpa parameter (atau nilai tidak valid) = tidak difilter, sama seperti sebelumnya.
    $filterTahun = parseTahunAjaranId($_GET['id_tahun_ajaran'] ?? null);
    $filterSemester = normalizeSemester($_GET['semester'] ?? null);

    $conditions = ['ks.id_siswa = :id_siswa'];
    $params = [':id_siswa' => $idSiswa];

    if ($filterTahun !== null) {
        $conditions[] = 'k.id_tahun_ajaran = :id_tahun_ajaran';
        $params[':id_tahun_ajaran'] = $filterTahun;
    }

    if ($filterSemester !== null) {
        $conditions[] = 'k.semester = :semester';
        $params[':semester'] = $filterSemester;
    }

    $statement = $db->prepare(
        "SELECT k.id_kelas, k.nama_kelas, k.id_tahun_ajaran, ta.nama_tahun_ajaran, k.semester
         FROM kelas k
         INNER JOIN kelas_siswa ks ON ks.id_kelas = k.id_kelas
         LEFT JOIN tahun_ajaran ta ON ta.id_tahun_ajaran = k.id_tahun_ajaran
         WHERE " . implode(' AND ', $conditions) . "
         ORDER BY k.nama_kelas ASC"
    );
    $statement->execute($params);

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
    sendServerError($error, 'siswa/kelas/list');
}