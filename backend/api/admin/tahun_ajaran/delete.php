<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/tahun_ajaran/delete.php
GUNA FILE   : Menghapus satu tahun ajaran. Ditolak jika masih ada kelas yang
              memakainya (dijaga ON DELETE RESTRICT di database).
              Method : POST
              Role   : admin
              Body   : { id_tahun_ajaran }
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

if ($idTahunAjaran === null) {
    sendError('ID tahun ajaran tidak valid.', 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $statement = $db->prepare('DELETE FROM tahun_ajaran WHERE id_tahun_ajaran = :id');
    $statement->execute([':id' => $idTahunAjaran]);

    if ($statement->rowCount() === 0) {
        sendError('Tahun ajaran tidak ditemukan.', 404);
    }

    sendSuccess('Tahun ajaran berhasil dihapus.');
} catch (Throwable $error) {
    // 1451 = masih dipakai oleh kelas (foreign key RESTRICT).
    if (getMysqlErrorCode($error) === 1451) {
        sendError('Tahun ajaran masih dipakai oleh kelas, sehingga tidak dapat dihapus.', 409);
    }

    sendServerError($error, 'admin/tahun_ajaran/delete');
}