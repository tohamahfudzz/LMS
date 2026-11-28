<?php
/*
================================================================================
LOKASI FILE : backend/api/pengajar/absensi/report.php
GUNA FILE   : Data report absensi satu kelas untuk pengajar. Dipakai halaman
              frontend/pengajar/absensi-report.html.
              Method : GET
              Query  : ?id_kelas=1 (wajib)
              Role   : pengajar (hanya kelas yang diajarnya, hanya sesi yang ia buka)
              Respons: data = {
                  kelas : { id_kelas, nama_kelas },
                  jumlah_sesi_dihitung : jumlah sesi yang sudah dimulai (penyebut persentase),
                  sesi  : [ { id_absensi, tanggal, waktu_buka, waktu_tutup, status, mode,
                              belum_dimulai } ]            (urut dari yang paling lama),
                  siswa : [ { id_siswa, kode_siswa, nama,
                              kehadiran : { "<id_absensi>": { status, waktu_absen,
                                            catatan_siswa, catatan_pengajar } },
                              jumlah_hadir, jumlah_izin, jumlah_alpa,
                              jumlah_belum_diisi, persentase } ]  (urut nama)
              }
              Semua siswa kelas ditampilkan. Siswa yang tidak punya catatan pada sebuah
              sesi berstatus "Belum diisi" (bukan otomatis tidak hadir); frontend
              menampilkannya demikian. Sesi terjadwal (belum dimulai) tidak dihitung.
              Hadir, izin, dan alpa dihitung terpisah (hanya pada sesi yang sudah dimulai).
              jumlah_belum_diisi = jumlah_sesi_dihitung dikurangi sesi yang sudah dicatat.
              persentase hanya dari hadir: jumlah_hadir / jumlah_sesi_dihitung * 100
              (null bila belum ada sesi yang dimulai); izin dan alpa tidak mengubah
              penyebut.
              Perubahan: menambah mode sesi, catatan, serta hitungan izin, alpa, dan
                         belum diisi per siswa.
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/absensi.php';

initApi('GET');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$rawIdKelas = $_GET['id_kelas'] ?? '';

if (!is_string($rawIdKelas) || !ctype_digit($rawIdKelas) || (int) $rawIdKelas < 1) {
    sendError('ID kelas tidak valid.', 400);
}

$idKelas = (int) $rawIdKelas;

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan pengajar mengajar di kelas ini sekaligus ambil nama kelasnya.
    $kelasStatement = $db->prepare(
        'SELECT k.nama_kelas
           FROM kelas k
          INNER JOIN kelas_pengajar kp
                  ON kp.id_kelas = k.id_kelas AND kp.id_pengajar = :id_pengajar
          WHERE k.id_kelas = :id_kelas
          LIMIT 1'
    );
    $kelasStatement->execute([
        ':id_pengajar' => $idPengajar,
        ':id_kelas' => $idKelas,
    ]);
    $kelas = $kelasStatement->fetch(PDO::FETCH_ASSOC);

    if (!$kelas) {
        sendError('Anda tidak mengajar di kelas ini.', 403);
    }

    // Sesi yang sudah lewat waktu_tutup otomatis menjadi 'tertutup'.
    closeExpiredAbsensi($db, $idKelas);

    $sessionStatement = $db->prepare(
        'SELECT a.id_absensi, a.tanggal, a.waktu_buka, a.waktu_tutup, a.status, a.mode,
                (a.waktu_buka > NOW()) AS belum_dimulai
           FROM absensi a
          WHERE a.id_kelas = :id_kelas AND a.id_pengajar = :id_pengajar
          ORDER BY a.tanggal ASC, a.waktu_buka ASC, a.id_absensi ASC'
    );
    $sessionStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_pengajar' => $idPengajar,
    ]);

    $sessions = [];
    $countedSessionIds = [];

    foreach ($sessionStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idAbsensi = (int) $row['id_absensi'];
        $notStarted = (int) $row['belum_dimulai'] === 1;

        $sessions[] = [
            'id_absensi' => $idAbsensi,
            'tanggal' => $row['tanggal'],
            'waktu_buka' => $row['waktu_buka'],
            'waktu_tutup' => $row['waktu_tutup'],
            'status' => $row['status'],
            'mode' => $row['mode'],
            'belum_dimulai' => $notStarted,
        ];

        if (!$notStarted) {
            $countedSessionIds[$idAbsensi] = true;
        }
    }

    $studentStatement = $db->prepare(
        'SELECT s.id_siswa, s.kode_siswa, s.nama
           FROM kelas_siswa ks
          INNER JOIN siswa s ON s.id_siswa = ks.id_siswa
          WHERE ks.id_kelas = :id_kelas
          ORDER BY s.nama ASC, s.id_siswa ASC'
    );
    $studentStatement->execute([':id_kelas' => $idKelas]);
    $students = $studentStatement->fetchAll(PDO::FETCH_ASSOC);

    $attendanceStatement = $db->prepare(
        'SELECT kh.id_absensi, kh.id_siswa, kh.status, kh.waktu_absen,
                kh.catatan_siswa, kh.catatan_pengajar
           FROM kehadiran kh
          INNER JOIN absensi a ON a.id_absensi = kh.id_absensi
          WHERE a.id_kelas = :id_kelas AND a.id_pengajar = :id_pengajar'
    );
    $attendanceStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_pengajar' => $idPengajar,
    ]);

    // Peta kehadiran: [id_siswa][id_absensi] => { status, waktu_absen }
    $attendanceMap = [];
    foreach ($attendanceStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attendanceMap[(int) $row['id_siswa']][(int) $row['id_absensi']] = [
            'status' => $row['status'],
            'waktu_absen' => $row['waktu_absen'],
            'catatan_siswa' => $row['catatan_siswa'],
            'catatan_pengajar' => $row['catatan_pengajar'],
        ];
    }

    $countedSessions = count($countedSessionIds);
    $studentRows = [];

    foreach ($students as $student) {
        $idSiswa = (int) $student['id_siswa'];
        $records = $attendanceMap[$idSiswa] ?? [];
        $presentCount = 0;
        $excusedCount = 0;
        $absentCount = 0;
        $recordedCount = 0;
        $attendance = [];

        foreach ($records as $idAbsensi => $record) {
            $attendance[(string) $idAbsensi] = $record;

            // Hanya sesi yang sudah dimulai yang ikut dihitung.
            if (!isset($countedSessionIds[$idAbsensi])) {
                continue;
            }

            $recordedCount++;

            if ($record['status'] === 'hadir') {
                $presentCount++;
            } elseif ($record['status'] === 'izin') {
                $excusedCount++;
            } elseif ($record['status'] === 'alpa') {
                $absentCount++;
            }
        }

        $studentRows[] = [
            'id_siswa' => $idSiswa,
            'kode_siswa' => $student['kode_siswa'],
            'nama' => $student['nama'],
            'kehadiran' => (object) $attendance,
            'jumlah_hadir' => $presentCount,
            'jumlah_izin' => $excusedCount,
            'jumlah_alpa' => $absentCount,
            'jumlah_belum_diisi' => max(0, $countedSessions - $recordedCount),
            'persentase' => $countedSessions > 0
                ? round($presentCount / $countedSessions * 100, 1)
                : null,
        ];
    }

    sendSuccess('Report absensi berhasil diambil.', [
        'kelas' => [
            'id_kelas' => $idKelas,
            'nama_kelas' => $kelas['nama_kelas'],
        ],
        'jumlah_sesi_dihitung' => $countedSessions,
        'sesi' => $sessions,
        'siswa' => $studentRows,
    ]);
} catch (Throwable $error) {
    sendServerError($error, 'pengajar/absensi/report');
}