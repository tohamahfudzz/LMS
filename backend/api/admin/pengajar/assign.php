<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/pengajar/assign.php
GUNA FILE   : Assign pengajar ke kelas (isi tabel kelas_pengajar).
              Hanya role admin.
              Method : POST
              Body   : { "id_kelas": 1, "id_pengajar": 1 }
              Response sukses : { success, message, data }
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';

initApi('POST');

$session = requireRole('admin');

$data = readJsonBody();

$idKelas    = isset($data['id_kelas'])    ? (int) $data['id_kelas']    : 0;
$idPengajar = isset($data['id_pengajar']) ? (int) $data['id_pengajar'] : 0;

if ($idKelas < 1 || $idPengajar < 1) {
    sendError('id_kelas dan id_pengajar wajib diisi dengan angka valid.', 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan kelas ada
    $stmtKelas = $db->prepare('SELECT id_kelas FROM kelas WHERE id_kelas = :id LIMIT 1');
    $stmtKelas->execute([':id' => $idKelas]);
    if (!$stmtKelas->fetch()) {
        sendError('Kelas tidak ditemukan.', 404);
    }

    // Pastikan pengajar ada
    $stmtPengajar = $db->prepare('SELECT id_pengajar FROM pengajar WHERE id_pengajar = :id LIMIT 1');
    $stmtPengajar->execute([':id' => $idPengajar]);
    if (!$stmtPengajar->fetch()) {
        sendError('Pengajar tidak ditemukan.', 404);
    }

    // Insert ke tabel junction
    $stmt = $db->prepare(
        'INSERT INTO kelas_pengajar (id_kelas, id_pengajar) VALUES (:id_kelas, :id_pengajar)'
    );
    $stmt->execute([
        ':id_kelas'    => $idKelas,
        ':id_pengajar' => $idPengajar,
    ]);

    sendSuccess(
        'Pengajar berhasil dimasukkan ke kelas.',
        [
            'id_kelas'    => $idKelas,
            'id_pengajar' => $idPengajar,
        ],
        201
    );
} catch (PDOException $error) {
    // Unique key uq_kelas_pengajar → sudah terdaftar
    if ((int) $error->getCode() === 23000) {
        sendError('Pengajar sudah terdaftar di kelas ini.', 409);
    }
    sendServerError($error, 'admin/pengajar/assign');
} catch (Throwable $error) {
    sendServerError($error, 'admin/pengajar/assign');
}