<?php
/*
================================================================================
LOKASI FILE : backend/api/absensi/open.php
GUNA FILE   : Membuka sesi absensi untuk kelas yang diajar pengajar.
              Method : POST
              Body   : { "id_kelas": 1, "tanggal": "2026-10-18", "jam": "19:30" }
                       (application/json)
                       - tanggal : YYYY-MM-DD (wajib)
                       - jam     : HH:MM, 24 jam (opsional; jika kosong dipakai
                                   waktu server saat ini)
              waktu_buka disimpan sebagai tanggal + jam. Siswa baru bisa mengisi
              kehadiran setelah waktu_buka tercapai (dicek di kehadiran/hadir.php).
              Respons sukses menyertakan id_absensi.
              Perubahan dari versi lama: memakai helper initApi/readJsonBody dan
              menerima input jam.
================================================================================
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$body = readJsonBody();

$idKelas = filter_var(
    $body['id_kelas'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idKelas === false) {
    sendError('ID kelas tidak valid.', 400);
}

// Tanggal wajib berformat YYYY-MM-DD dan harus tanggal yang benar-benar ada.
$tanggal = $body['tanggal'] ?? '';

if (!is_string($tanggal)) {
    sendError('Format tanggal tidak valid. Gunakan YYYY-MM-DD.', 400);
}

$tanggal = trim($tanggal);
$dateObject = DateTime::createFromFormat('!Y-m-d', $tanggal);

if (!$dateObject || $dateObject->format('Y-m-d') !== $tanggal) {
    sendError('Format tanggal tidak valid. Gunakan YYYY-MM-DD.', 400);
}

// Jam opsional. Jika kosong, waktu_buka memakai waktu server saat ini.
$jam = $body['jam'] ?? '';

if (!is_string($jam)) {
    sendError('Format jam tidak valid. Gunakan HH:MM (24 jam).', 400);
}

$jam = trim($jam);

if ($jam !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam)) {
    sendError('Format jam tidak valid. Gunakan HH:MM (24 jam).', 400);
}

$waktuBuka = $jam === '' ? null : $tanggal . ' ' . $jam . ':00';

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan pengajar mengajar di kelas ini.
    $teacherStatement = $db->prepare(
        'SELECT id_kelas_pengajar FROM kelas_pengajar
         WHERE id_kelas = :id_kelas AND id_pengajar = :id_pengajar
         LIMIT 1'
    );
    $teacherStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_pengajar' => $idPengajar,
    ]);

    if (!$teacherStatement->fetch()) {
        sendError('Anda tidak mengajar di kelas ini.', 403);
    }

    // Cegah sesi ganda yang masih terbuka pada kelas dan tanggal yang sama.
    $duplicateStatement = $db->prepare(
        "SELECT id_absensi FROM absensi
         WHERE id_kelas = :id_kelas
           AND tanggal = :tanggal
           AND status = 'terbuka'
         LIMIT 1"
    );
    $duplicateStatement->execute([
        ':id_kelas' => $idKelas,
        ':tanggal' => $tanggal,
    ]);
    $existing = $duplicateStatement->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        sendError(
            'Sudah ada sesi absensi terbuka untuk kelas dan tanggal ini. Tutup sesi tersebut lebih dulu.',
            409,
            ['id_absensi' => (int) $existing['id_absensi']]
        );
    }

    $insertStatement = $db->prepare(
        "INSERT INTO absensi (id_kelas, id_pengajar, tanggal, waktu_buka, status)
         VALUES (:id_kelas, :id_pengajar, :tanggal, COALESCE(:waktu_buka, NOW()), 'terbuka')"
    );
    $insertStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_pengajar' => $idPengajar,
        ':tanggal' => $tanggal,
        ':waktu_buka' => $waktuBuka,
    ]);

    $idAbsensi = (int) $db->lastInsertId();

    // Ambil waktu_buka yang benar-benar tersimpan (termasuk bila memakai NOW()).
    $savedStatement = $db->prepare('SELECT waktu_buka FROM absensi WHERE id_absensi = :id_absensi');
    $savedStatement->execute([':id_absensi' => $idAbsensi]);

    sendSuccess('Sesi absensi berhasil dibuka.', [
        'id_absensi' => $idAbsensi,
        'id_kelas' => $idKelas,
        'tanggal' => $tanggal,
        'waktu_buka' => $savedStatement->fetchColumn(),
        'status' => 'terbuka',
    ], 201);
} catch (Throwable $error) {
    sendServerError($error, 'absensi/open');
}