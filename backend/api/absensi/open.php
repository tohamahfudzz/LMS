<?php
/*
================================================================================
LOKASI FILE : backend/api/absensi/open.php
GUNA FILE   : Membuka sesi absensi untuk kelas yang diajar pengajar.
              Method : POST
              Body   : { "id_kelas": 1, "tanggal": "2026-10-25",
                         "jam_buka": "08:00", "jam_tutup": "09:30" }  (application/json)
                       - tanggal   : YYYY-MM-DD (wajib)
                       - jam_buka  : HH:MM, 24 jam (wajib, diisi pengajar)
                       - jam_tutup : HH:MM, 24 jam (wajib, diisi pengajar, harus lebih
                                     akhir dari jam_buka dan belum lewat)
              Disimpan sebagai absensi.waktu_buka dan absensi.waktu_tutup (tanggal + jam).
              Aturan siswa (dicek di kehadiran/hadir.php): siswa baru bisa absen setelah
              waktu_buka dan sebelum waktu_tutup. Setelah waktu_tutup, sesi otomatis
              menjadi 'tertutup' (functions/absensi.php). Pengajar tetap bisa menutup
              lebih awal lewat absensi/close.php.
              Satu kelas tidak boleh punya dua sesi terbuka yang waktunya bertabrakan
              pada tanggal yang sama.
              Respons sukses menyertakan id_absensi.
================================================================================
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/absensi.php';

// Memvalidasi jam berformat HH:MM (00:00 - 23:59). Menghentikan proses bila tidak valid.
function parseJam($value, string $label): string
{
    if (!is_string($value) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim($value))) {
        sendError($label . ' tidak valid. Gunakan format HH:MM (24 jam).', 400);
    }

    return trim($value);
}

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

$jamBuka = parseJam($body['jam_buka'] ?? null, 'Jam buka');
$jamTutup = parseJam($body['jam_tutup'] ?? null, 'Jam tutup');

// Format HH:MM berurutan secara alfabet sama dengan urutan waktunya.
if ($jamTutup <= $jamBuka) {
    sendError('Jam tutup harus lebih akhir dari jam buka.', 400);
}

$waktuBuka = $tanggal . ' ' . $jamBuka . ':00';
$waktuTutup = $tanggal . ' ' . $jamTutup . ':00';

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

    // Sesi yang sudah lewat jam tutupnya harus berstatus tertutup sebelum cek tabrakan.
    closeExpiredAbsensi($db, $idKelas);

    // Jam tutup yang sudah lewat membuat sesi langsung tertutup, jadi ditolak.
    $futureStatement = $db->prepare('SELECT (CAST(:waktu_tutup AS DATETIME) > NOW())');
    $futureStatement->execute([':waktu_tutup' => $waktuTutup]);

    if ((int) $futureStatement->fetchColumn() !== 1) {
        sendError('Jam tutup sudah lewat. Pilih jam tutup yang masih akan datang.', 400);
    }

    // Cegah dua sesi terbuka yang waktunya bertabrakan pada kelas dan tanggal yang sama.
    // Sesi lama tanpa waktu_tutup dianggap terbuka sampai akhir hari tersebut.
    $overlapStatement = $db->prepare(
        "SELECT id_absensi FROM absensi
         WHERE id_kelas = :id_kelas
           AND tanggal = :tanggal
           AND status = 'terbuka'
           AND waktu_buka < :waktu_tutup
           AND (waktu_tutup IS NULL OR waktu_tutup > :waktu_buka)
         LIMIT 1"
    );
    $overlapStatement->execute([
        ':id_kelas' => $idKelas,
        ':tanggal' => $tanggal,
        ':waktu_tutup' => $waktuTutup,
        ':waktu_buka' => $waktuBuka,
    ]);
    $existing = $overlapStatement->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        sendError(
            'Waktu ini bertabrakan dengan sesi absensi lain yang masih terbuka. Tutup sesi itu lebih dulu atau pilih jam lain.',
            409,
            ['id_absensi' => (int) $existing['id_absensi']]
        );
    }

    $insertStatement = $db->prepare(
        "INSERT INTO absensi (id_kelas, id_pengajar, tanggal, waktu_buka, waktu_tutup, status)
         VALUES (:id_kelas, :id_pengajar, :tanggal, :waktu_buka, :waktu_tutup, 'terbuka')"
    );
    $insertStatement->execute([
        ':id_kelas' => $idKelas,
        ':id_pengajar' => $idPengajar,
        ':tanggal' => $tanggal,
        ':waktu_buka' => $waktuBuka,
        ':waktu_tutup' => $waktuTutup,
    ]);

    sendSuccess('Sesi absensi berhasil dibuka.', [
        'id_absensi' => (int) $db->lastInsertId(),
        'id_kelas' => $idKelas,
        'tanggal' => $tanggal,
        'waktu_buka' => $waktuBuka,
        'waktu_tutup' => $waktuTutup,
        'status' => 'terbuka',
    ], 201);
} catch (Throwable $error) {
    sendServerError($error, 'absensi/open');
}