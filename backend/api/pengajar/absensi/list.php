<?php
/*
================================================================================
LOKASI FILE : backend/api/pengajar/absensi/list.php
GUNA FILE   : Daftar sesi absensi pada satu kelas milik pengajar yang login.
              Dipakai tab Absensi di halaman kelas (frontend/pengajar/kelas-detail.html)
              agar pengajar tidak perlu mengetik ID absensi secara manual.
              Method : GET
              Query  : ?id_kelas=1 (wajib)
              Role   : pengajar. Hanya kelas yang diajarnya dan hanya sesi yang ia buka.
              Respons: data = {
                           jumlah_siswa : jumlah siswa di kelas,
                           sesi         : [ { id_absensi, tanggal, waktu_buka,
                                              waktu_tutup, status, jumlah_hadir,
                                              belum_dimulai } ]
                       }
              jumlah_hadir menghitung baris kehadiran dengan status 'hadir'.
              belum_dimulai bernilai true bila waktu_buka masih di masa depan
              (sesi dijadwalkan; siswa belum bisa mengisi kehadiran).
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';

initApi('GET');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

// Validasi id_kelas: wajib berupa bilangan bulat positif.
$rawIdKelas = $_GET['id_kelas'] ?? '';

if (!is_string($rawIdKelas) || !ctype_digit($rawIdKelas) || (int) $rawIdKelas < 1) {
    sendError('ID kelas tidak valid.', 400);
}

$idKelas = (int) $rawIdKelas;

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan pengajar memang mengajar di kelas ini.
    $checkStatement = $db->prepare(
        'SELECT id_kelas_pengajar FROM kelas_pengajar
         WHERE id_kelas = :id_kelas AND id_pengajar = :id_pengajar
         LIMIT 1'
    );
    $checkStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_pengajar' => $idPengajar,
    ]);

    if (!$checkStatement->fetch()) {
        sendError('Anda tidak mengajar di kelas ini.', 403);
    }

    $studentStatement = $db->prepare(
        'SELECT COUNT(*) FROM kelas_siswa WHERE id_kelas = :id_kelas'
    );
    $studentStatement->execute([':id_kelas' => $idKelas]);
    $studentCount = (int) $studentStatement->fetchColumn();

    $sessionStatement = $db->prepare(
        "SELECT a.id_absensi, a.tanggal, a.waktu_buka, a.waktu_tutup, a.status,
                (a.waktu_buka > NOW()) AS belum_dimulai,
                (SELECT COUNT(*)
                   FROM kehadiran kh
                  WHERE kh.id_absensi = a.id_absensi
                    AND kh.status = 'hadir') AS jumlah_hadir
           FROM absensi a
          WHERE a.id_kelas = :id_kelas
            AND a.id_pengajar = :id_pengajar
          ORDER BY a.tanggal DESC, a.id_absensi DESC"
    );
    $sessionStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_pengajar' => $idPengajar,
    ]);

    $sessions = [];
    foreach ($sessionStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sessions[] = [
            'id_absensi' => (int) $row['id_absensi'],
            'tanggal' => $row['tanggal'],
            'waktu_buka' => $row['waktu_buka'],
            'waktu_tutup' => $row['waktu_tutup'],
            'status' => $row['status'],
            'jumlah_hadir' => (int) $row['jumlah_hadir'],
            'belum_dimulai' => (int) $row['belum_dimulai'] === 1,
        ];
    }

    sendSuccess('Daftar sesi absensi berhasil diambil.', [
        'jumlah_siswa' => $studentCount,
        'sesi' => $sessions,
    ]);
} catch (Throwable $error) {
    sendServerError($error, 'pengajar/absensi/list');
}