<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/tahun_ajaran/create.php
GUNA FILE   : Menambah tahun ajaran baru.
              Method : POST
              Role   : admin
              Body   : { nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, status? }
                       (tanggal berformat YYYY-MM-DD; status aktif/nonaktif,
                       bawaan nonaktif). Lebih dari satu tahun ajaran boleh aktif.
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';
require_once __DIR__ . '/../../../functions/tahun_ajaran.php';

initApi('POST');

requireRole('admin');

$fields = readTahunAjaranFields(readJsonBody());

$database = new Database();
$db = $database->getConnection();

try {
    $statement = $db->prepare(
        'INSERT INTO tahun_ajaran (nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, status)
         VALUES (:nama, :mulai, :selesai, :status)'
    );
    $statement->execute([
        ':nama'    => $fields['nama'],
        ':mulai'   => $fields['mulai'],
        ':selesai' => $fields['selesai'],
        ':status'  => $fields['status'],
    ]);

    sendSuccess(
        'Tahun ajaran berhasil ditambahkan.',
        ['id_tahun_ajaran' => (int) $db->lastInsertId()],
        201
    );
} catch (Throwable $error) {
    if (getMysqlErrorCode($error) === 1062) {
        sendError('Nama tahun ajaran sudah dipakai.', 409);
    }

    sendServerError($error, 'admin/tahun_ajaran/create');
}