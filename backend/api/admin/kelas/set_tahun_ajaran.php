<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/kelas/set_tahun_ajaran.php
GUNA FILE   : Mengatur tahun ajaran dan semester sebuah kelas yang sudah ada
              (termasuk mengisi kelas lama yang masih kosong). Nilai yang
              dikirim menggantikan nilai lama; kosongkan (null) untuk mencabut.
              Method : POST
              Role   : admin
              Body   : { id_kelas, id_tahun_ajaran: angka | null,
                         semester: "ganjil" | "genap" | null }
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/tahun_ajaran.php';

initApi('POST');

requireRole('admin');

$body = readJsonBody();
$idKelas = parseTahunAjaranId($body['id_kelas'] ?? null);

if ($idKelas === null) {
    sendError('ID kelas tidak valid.', 400);
}

// Kosong ("" atau null) berarti dikosongkan; isi yang tidak valid ditolak.
$rawTahun = $body['id_tahun_ajaran'] ?? null;
$idTahunAjaran = null;

if ($rawTahun !== null && $rawTahun !== '') {
    $idTahunAjaran = parseTahunAjaranId($rawTahun);

    if ($idTahunAjaran === null) {
        sendError('ID tahun ajaran tidak valid.', 400);
    }
}

$rawSemester = $body['semester'] ?? null;
$semester = null;

if ($rawSemester !== null && $rawSemester !== '') {
    $semester = normalizeSemester($rawSemester);

    if ($semester === null) {
        sendError('Semester harus ganjil atau genap.', 400);
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    $kelasStatement = $db->prepare('SELECT id_kelas FROM kelas WHERE id_kelas = :id LIMIT 1');
    $kelasStatement->execute([':id' => $idKelas]);

    if ($kelasStatement->fetch(PDO::FETCH_ASSOC) === false) {
        sendError('Kelas tidak ditemukan.', 404);
    }

    if ($idTahunAjaran !== null) {
        $tahunStatement = $db->prepare('SELECT id_tahun_ajaran FROM tahun_ajaran WHERE id_tahun_ajaran = :id LIMIT 1');
        $tahunStatement->execute([':id' => $idTahunAjaran]);

        if ($tahunStatement->fetch(PDO::FETCH_ASSOC) === false) {
            sendError('Tahun ajaran tidak ditemukan.', 404);
        }
    }

    $statement = $db->prepare(
        'UPDATE kelas SET id_tahun_ajaran = :id_tahun_ajaran, semester = :semester WHERE id_kelas = :id_kelas'
    );
    $statement->bindValue(':id_tahun_ajaran', $idTahunAjaran, $idTahunAjaran === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $statement->bindValue(':semester', $semester, $semester === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':id_kelas', $idKelas, PDO::PARAM_INT);
    $statement->execute();

    sendSuccess('Tahun ajaran dan semester kelas berhasil disimpan.');
} catch (Throwable $error) {
    sendServerError($error, 'admin/kelas/set_tahun_ajaran');
}