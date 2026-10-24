<?php
/*
================================================================================
LOKASI FILE : backend/api/siswa/absensi/list.php
GUNA FILE   : Daftar sesi absensi pada satu kelas yang diikuti siswa, lengkap dengan
              status kehadiran siswa itu sendiri. Dipakai tab Absensi di halaman kelas
              siswa (frontend/siswa/kelas-detail.html), sehingga siswa tidak perlu
              mengetik ID absensi secara manual.
              Method : GET
              Query  : ?id_kelas=1 (wajib)
              Role   : siswa (hanya kelas yang ia ikuti)
              Respons: data = [ { id_absensi, tanggal, waktu_buka, waktu_tutup, status,
                                  belum_dimulai, status_kehadiran, waktu_absen } ]
              - status           : 'terbuka' atau 'tertutup' (status sesi)
              - belum_dimulai    : true bila waktu_buka masih di masa depan
              - status_kehadiran : status siswa pada sesi itu, null bila belum absen
              Mengisi kehadiran tetap lewat POST kehadiran/hadir.php.
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/absensi.php';

initApi('GET');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$rawIdKelas = $_GET['id_kelas'] ?? '';

if (!is_string($rawIdKelas) || !ctype_digit($rawIdKelas) || (int) $rawIdKelas < 1) {
    sendError('ID kelas tidak valid.', 400);
}

$idKelas = (int) $rawIdKelas;

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan siswa terdaftar di kelas ini.
    $checkStatement = $db->prepare(
        'SELECT id_kelas_siswa FROM kelas_siswa
         WHERE id_kelas = :id_kelas AND id_siswa = :id_siswa
         LIMIT 1'
    );
    $checkStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_siswa' => $idSiswa,
    ]);

    if (!$checkStatement->fetch()) {
        sendError('Anda tidak terdaftar di kelas ini.', 403);
    }

    // Sesi yang sudah lewat waktu_tutup otomatis menjadi 'tertutup'.
    closeExpiredAbsensi($db, $idKelas);

    $statement = $db->prepare(
        'SELECT a.id_absensi, a.tanggal, a.waktu_buka, a.waktu_tutup, a.status,
                (a.waktu_buka > NOW()) AS belum_dimulai,
                kh.status AS status_kehadiran, kh.waktu_absen
           FROM absensi a
           LEFT JOIN kehadiran kh
                  ON kh.id_absensi = a.id_absensi AND kh.id_siswa = :id_siswa
          WHERE a.id_kelas = :id_kelas
          ORDER BY a.tanggal DESC, a.id_absensi DESC'
    );
    $statement->execute([
        ':id_siswa' => $idSiswa,
        ':id_kelas' => $idKelas,
    ]);

    $sessions = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sessions[] = [
            'id_absensi' => (int) $row['id_absensi'],
            'tanggal' => $row['tanggal'],
            'waktu_buka' => $row['waktu_buka'],
            'waktu_tutup' => $row['waktu_tutup'],
            'status' => $row['status'],
            'belum_dimulai' => (int) $row['belum_dimulai'] === 1,
            'status_kehadiran' => $row['status_kehadiran'],
            'waktu_absen' => $row['waktu_absen'],
        ];
    }

    sendSuccess('Daftar sesi absensi berhasil diambil.', $sessions);
} catch (Throwable $error) {
    sendServerError($error, 'siswa/absensi/list');
}