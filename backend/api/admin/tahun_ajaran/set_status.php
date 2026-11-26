<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/tahun_ajaran/set_status.php
GUNA FILE   : Mengaktifkan atau menonaktifkan satu tahun ajaran. Tidak memengaruhi
              tahun ajaran lain, jadi beberapa tahun ajaran boleh aktif bersamaan.
              Status hanya penanda; kelas dari tahun ajaran nonaktif tetap bisa
              dipilih dan dipakai.
              Method : POST
              Role   : admin
              Body   : { id_tahun_ajaran, status: "aktif" | "nonaktif" }
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/tahun_ajaran.php';

initApi('POST');

requireRole('admin');

$body = readJsonBody();
$idTahunAjaran = parseTahunAjaranId($body['id_tahun_ajaran'] ?? null);
$status = $body['status'] ?? null;

if ($idTahunAjaran === null) {
    sendError('ID tahun ajaran tidak valid.', 400);
}

if (!is_string($status) || !in_array($status, TAHUN_AJARAN_STATUS_VALUES, true)) {
    sendError('Status harus aktif atau nonaktif.', 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $existsStatement = $db->prepare('SELECT id_tahun_ajaran FROM tahun_ajaran WHERE id_tahun_ajaran = :id LIMIT 1');
    $existsStatement->execute([':id' => $idTahunAjaran]);

    if ($existsStatement->fetch(PDO::FETCH_ASSOC) === false) {
        sendError('Tahun ajaran tidak ditemukan.', 404);
    }

    $statement = $db->prepare('UPDATE tahun_ajaran SET status = :status WHERE id_tahun_ajaran = :id');
    $statement->execute([':status' => $status, ':id' => $idTahunAjaran]);

    sendSuccess($status === 'aktif' ? 'Tahun ajaran diaktifkan.' : 'Tahun ajaran dinonaktifkan.');
} catch (Throwable $error) {
    sendServerError($error, 'admin/tahun_ajaran/set_status');
}