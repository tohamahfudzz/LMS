<?php
/*
================================================================================
LOKASI FILE : backend/api/absensi/close.php
GUNA FILE   : Menutup sesi absensi yang dibuka pengajar. Setelah ditutup, siswa
              tidak bisa mengisi kehadiran lagi (dicek di kehadiran/hadir.php).
              Method : POST
              Body   : { "id_absensi": 1 } (application/json)
              Role   : pengajar (hanya sesi miliknya)
              Perubahan dari versi lama:
              - memakai helper initApi/readJsonBody/sendError agar format respons seragam
              - error database tidak lagi dikirim mentah ke pengguna
              - sesi yang sudah tertutup tidak bisa ditutup dua kali
                (waktu_tutup asli tidak tertimpa)
================================================================================
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';

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

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan sesi ada dan milik pengajar yang login.
    $findStatement = $db->prepare(
        'SELECT id_pengajar, status FROM absensi WHERE id_absensi = :id_absensi LIMIT 1'
    );
    $findStatement->execute([':id_absensi' => $idAbsensi]);
    $absensi = $findStatement->fetch(PDO::FETCH_ASSOC);

    if (!$absensi) {
        sendError('Sesi absensi tidak ditemukan.', 404);
    }

    if ((int) $absensi['id_pengajar'] !== $idPengajar) {
        sendError('Anda tidak berhak menutup sesi absensi ini.', 403);
    }

    if ($absensi['status'] !== 'terbuka') {
        sendError('Sesi absensi ini sudah ditutup.', 409);
    }

    // Kondisi status 'terbuka' diulang di UPDATE agar aman bila ada dua permintaan bersamaan.
    $closeStatement = $db->prepare(
        "UPDATE absensi
            SET status = 'tertutup', waktu_tutup = NOW()
          WHERE id_absensi = :id_absensi AND status = 'terbuka'"
    );
    $closeStatement->execute([':id_absensi' => $idAbsensi]);

    sendSuccess('Sesi absensi berhasil ditutup.');
} catch (Throwable $error) {
    sendServerError($error, 'absensi/close');
}
