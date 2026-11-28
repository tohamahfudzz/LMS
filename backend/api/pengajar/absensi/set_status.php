<?php
/*
================================================================================
LOKASI FILE : backend/api/pengajar/absensi/set_status.php
GUNA FILE   : Pengajar mencatat atau menimpa status kehadiran siswa pada satu sesi
              absensi, sekaligus untuk banyak siswa (ceklis hadir/izin/alpa per siswa).
              Method : POST
              Body   : { "id_absensi": 1,
                         "data": [ { "id_siswa": 3, "status": "izin", "catatan": "sakit" },
                                   { "id_siswa": 4, "status": "hadir", "catatan": "" } ] }
                       - status  : 'hadir', 'izin', atau 'alpa'
                       - catatan : opsional, maks 255 karakter. Disimpan di
                                   kehadiran.catatan_pengajar. Catatan kosong menghapus
                                   catatan pengajar sebelumnya, jadi frontend harus
                                   mengirim ulang catatan yang sudah ada (dari
                                   pengajar/absensi/attendance.php) bila tidak diubah.
              Role   : pengajar (hanya sesi yang ia buka)
              Aturan : - berlaku di kedua mode sesi (siswa dan manual), pada sesi
                         terbuka maupun tertutup
                       - pengajar boleh menimpa status yang diisi siswa sendiri
                       - saat menimpa, waktu_absen dan catatan_siswa tidak diubah; hanya
                         status dan catatan_pengajar
                       - semua id_siswa harus terdaftar di kelas sesi tersebut; bila ada
                         satu saja yang tidak, seluruh permintaan ditolak
                       - disimpan dalam satu transaksi: semua tersimpan atau tidak sama sekali
              Respons: data = { id_absensi, jumlah_disimpan }
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/absensi.php';

// Batas jumlah siswa per permintaan, untuk mencegah payload berlebihan.
const SET_STATUS_MAKS_SISWA = 500;

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$body = readJsonBody();

$idAbsensi = filter_var(
    $body['id_absensi'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idAbsensi === false) {
    sendError('ID absensi tidak valid.', 400);
}

$entries = $body['data'] ?? null;

if (!is_array($entries) || count($entries) === 0) {
    sendError('Data kehadiran siswa wajib diisi.', 400);
}

if (count($entries) > SET_STATUS_MAKS_SISWA) {
    sendError('Terlalu banyak data dalam satu permintaan.', 400);
}

// Validasi seluruh baris sebelum menyentuh database.
$rows = [];
$seen = [];

foreach ($entries as $entry) {
    if (!is_array($entry)) {
        sendError('Format data siswa tidak valid.', 400);
    }

    $idSiswa = filter_var(
        $entry['id_siswa'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idSiswa === false) {
        sendError('ID siswa tidak valid.', 400);
    }

    if (isset($seen[$idSiswa])) {
        sendError('Siswa yang sama dikirim lebih dari sekali.', 400);
    }
    $seen[$idSiswa] = true;

    $statusInput = $entry['status'] ?? null;

    if (!is_string($statusInput)) {
        sendError('Status kehadiran wajib diisi.', 400);
    }

    $status = strtolower(trim($statusInput));

    if (!in_array($status, ABSENSI_STATUS_PENGAJAR, true)) {
        sendError('Status kehadiran tidak valid. Pilih hadir, izin, atau alpa.', 400);
    }

    $catatanInput = $entry['catatan'] ?? null;

    if ($catatanInput !== null && !is_string($catatanInput)) {
        sendError('Catatan tidak valid.', 400);
    }

    $catatan = trim((string) $catatanInput);

    if (mb_strlen($catatan) > ABSENSI_CATATAN_MAKS) {
        sendError('Catatan terlalu panjang. Maksimal ' . ABSENSI_CATATAN_MAKS . ' karakter.', 400);
    }

    $rows[] = [
        'id_siswa' => $idSiswa,
        'status' => $status,
        'catatan' => $catatan === '' ? null : $catatan,
    ];
}

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan sesi ini ada dan dibuka oleh pengajar yang login.
    $sessionStatement = $db->prepare(
        'SELECT id_kelas FROM absensi
          WHERE id_absensi = :id_absensi AND id_pengajar = :id_pengajar
          LIMIT 1'
    );
    $sessionStatement->execute([
        ':id_absensi' => $idAbsensi,
        ':id_pengajar' => $idPengajar,
    ]);
    $absensi = $sessionStatement->fetch(PDO::FETCH_ASSOC);

    if (!$absensi) {
        sendError('Sesi absensi tidak ditemukan atau Anda tidak memiliki akses.', 404);
    }

    $idKelas = (int) $absensi['id_kelas'];

    // Semua siswa yang dikirim harus terdaftar di kelas sesi ini.
    $idList = array_column($rows, 'id_siswa');
    $placeholders = implode(',', array_fill(0, count($idList), '?'));

    $memberStatement = $db->prepare(
        'SELECT COUNT(DISTINCT id_siswa) FROM kelas_siswa
          WHERE id_kelas = ? AND id_siswa IN (' . $placeholders . ')'
    );
    $memberStatement->execute(array_merge([$idKelas], $idList));

    if ((int) $memberStatement->fetchColumn() !== count($idList)) {
        sendError('Ada siswa yang tidak terdaftar di kelas ini.', 422);
    }

    // Prepared statement native tidak boleh memakai nama parameter yang sama dua kali,
    // jadi bagian UPDATE memakai nama parameter sendiri.
    $upsertStatement = $db->prepare(
        'INSERT INTO kehadiran (id_absensi, id_siswa, waktu_absen, status, catatan_pengajar)
         VALUES (:id_absensi, :id_siswa, NOW(), :status, :catatan)
         ON DUPLICATE KEY UPDATE
             status = :status_baru,
             catatan_pengajar = :catatan_baru'
    );

    $db->beginTransaction();

    foreach ($rows as $row) {
        $upsertStatement->execute([
            ':id_absensi' => $idAbsensi,
            ':id_siswa' => $row['id_siswa'],
            ':status' => $row['status'],
            ':catatan' => $row['catatan'],
            ':status_baru' => $row['status'],
            ':catatan_baru' => $row['catatan'],
        ]);
    }

    $db->commit();

    sendSuccess('Kehadiran siswa berhasil disimpan.', [
        'id_absensi' => $idAbsensi,
        'jumlah_disimpan' => count($rows),
    ]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'pengajar/absensi/set_status');
}