<?php
/*
================================================================================
LOKASI FILE : backend/api/siswa/tugas/list.php
GUNA FILE   : Daftar tugas di kelas yang diikuti siswa.
              Method : GET
              Query  : ?id_kelas=1 (opsional)
              Role   : siswa
              Menyertakan status pengumpulan siswa (jika sudah mengumpulkan).
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';

initApi('GET');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$idKelas = null;
if (isset($_GET['id_kelas']) && $_GET['id_kelas'] !== '') {
    if (!is_numeric($_GET['id_kelas']) || (int) $_GET['id_kelas'] < 1) {
        sendError('ID kelas tidak valid.', 400);
    }
    $idKelas = (int) $_GET['id_kelas'];
}

$database = new Database();
$db = $database->getConnection();

try {
    if ($idKelas !== null) {
        $cek = $db->prepare(
            'SELECT id_kelas_siswa FROM kelas_siswa
             WHERE id_kelas = :id_kelas AND id_siswa = :id_siswa LIMIT 1'
        );
        $cek->execute([
            ':id_kelas' => $idKelas,
            ':id_siswa' => $idSiswa,
        ]);
        if (!$cek->fetch()) {
            sendError('Anda tidak terdaftar di kelas ini.', 403);
        }
    }

    $sql = "SELECT
                t.id_tugas,
                t.id_kelas,
                k.nama_kelas,
                t.judul,
                t.deskripsi,
                t.file,
                t.waktu_mulai,
                t.deadline,
                t.status AS status_tugas,
                t.waktu_dibuat,
                pt.id_pengumpulan,
                pt.file AS file_pengumpulan,
                pt.teks AS teks_pengumpulan,
                pt.waktu_pengumpulan
            FROM tugas t
            INNER JOIN kelas k ON k.id_kelas = t.id_kelas
            INNER JOIN kelas_siswa ks ON ks.id_kelas = t.id_kelas AND ks.id_siswa = :id_siswa
            LEFT JOIN pengumpulan_tugas pt
                ON pt.id_tugas = t.id_tugas AND pt.id_siswa = :id_siswa2
            WHERE 1 = 1";

    $params = [
        ':id_siswa'  => $idSiswa,
        ':id_siswa2' => $idSiswa,
    ];

    if ($idKelas !== null) {
        $sql .= ' AND t.id_kelas = :id_kelas';
        $params[':id_kelas'] = $idKelas;
    }

    $sql .= ' ORDER BY t.deadline ASC';

    $statement = $db->prepare($sql);
    $statement->execute($params);

    $list = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sudahKumpul = !empty($row['id_pengumpulan']);

        $list[] = [
            'id_tugas'          => (int) $row['id_tugas'],
            'id_kelas'          => (int) $row['id_kelas'],
            'nama_kelas'        => $row['nama_kelas'],
            'judul'             => $row['judul'],
            'deskripsi'         => $row['deskripsi'],
            'file'              => $row['file'],
            'waktu_mulai'       => $row['waktu_mulai'],
            'deadline'          => $row['deadline'],
            'status_tugas'      => $row['status_tugas'],
            'waktu_dibuat'      => $row['waktu_dibuat'],
            'sudah_mengumpulkan'=> $sudahKumpul,
            'pengumpulan'       => $sudahKumpul ? [
                'id_pengumpulan'    => (int) $row['id_pengumpulan'],
                'file'              => $row['file_pengumpulan'],
                'teks'              => $row['teks_pengumpulan'],
                'waktu_pengumpulan' => $row['waktu_pengumpulan'],
            ] : null,
        ];
    }

    sendSuccess('Daftar tugas berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'siswa/tugas/list');
}