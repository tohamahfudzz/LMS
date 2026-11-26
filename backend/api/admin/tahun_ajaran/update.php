<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/tahun_ajaran/update.php
GUNA FILE   : Mengubah nama, tanggal mulai, tanggal selesai, dan status sebuah
              tahun ajaran. (Untuk hanya mengganti status, ada set_status.php.)
              Method : POST
              Role   : admin
              Body   : { id_tahun_ajaran, nama_tahun_ajaran, tanggal_mulai,
                         tanggal_selesai, status }
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

$fields = readTahunAjaranFields($body);

$database = new Database();
$db = $database->getConnection();

try {
    $existsStatement = $db->prepare('SELECT id_tahun_ajaran FROM tahun_ajaran WHERE id_tahun_ajaran = :id LIMIT 1');
    $existsStatement->execute([':id' => $idTahunAjaran]);

    if ($existsStatement->fetch(PDO::FETCH_ASSOC) === false) {
        sendError('Tahun ajaran tidak ditemukan.', 404);
    }

    $statement = $db->prepare(
        'UPDATE tahun_ajaran
         SET nama_tahun_ajaran = :nama, tanggal_mulai = :mulai,
             tanggal_selesai = :selesai, status = :status
         WHERE id_tahun_ajaran = :id'
    );
    $statement->execute([
        ':nama'    => $fields['nama'],
        ':mulai'   => $fields['mulai'],
        ':selesai' => $fields['selesai'],
        ':status'  => $fields['status'],
        ':id'      => $idTahunAjaran,
    ]);

    sendSuccess('Tahun ajaran berhasil diperbarui.');
} catch (Throwable $error) {
    if (getMysqlErrorCode($error) === 1062) {
        sendError('Nama tahun ajaran sudah dipakai.', 409);
    }

    sendServerError($error, 'admin/tahun_ajaran/update');
}