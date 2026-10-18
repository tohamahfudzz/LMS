<?php
/*
================================================================================
LOKASI FILE : backend/api/siswa/materi/list.php
GUNA FILE   : Daftar materi dari kelas yang diikuti siswa.
              Method : GET
              Query  : ?id_kelas=1 (opsional — filter satu kelas)
              Role   : siswa
              Hanya materi kelas yang terdaftar di kelas_siswa.
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
    // Jika filter kelas, pastikan siswa anggota kelas tersebut
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
                m.id_materi,
                m.id_kelas,
                k.nama_kelas,
                m.judul,
                m.tipe,
                m.file,
                m.link,
                m.waktu_upload
            FROM materi m
            INNER JOIN kelas k ON k.id_kelas = m.id_kelas
            INNER JOIN kelas_siswa ks ON ks.id_kelas = m.id_kelas
            WHERE ks.id_siswa = :id_siswa";

    $params = [':id_siswa' => $idSiswa];

    if ($idKelas !== null) {
        $sql .= ' AND m.id_kelas = :id_kelas';
        $params[':id_kelas'] = $idKelas;
    }

    $sql .= ' ORDER BY m.waktu_upload DESC';

    $statement = $db->prepare($sql);
    $statement->execute($params);

    $list = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $list[] = [
            'id_materi'    => (int) $row['id_materi'],
            'id_kelas'     => (int) $row['id_kelas'],
            'nama_kelas'   => $row['nama_kelas'],
            'judul'        => $row['judul'],
            'tipe'         => $row['tipe'],
            'file'         => $row['file'],
            'link'         => $row['link'],
            'waktu_upload' => $row['waktu_upload'],
        ];
    }

    sendSuccess('Daftar materi berhasil diambil.', $list);
} catch (Throwable $error) {
    sendServerError($error, 'siswa/materi/list');
}