<?php
/*
================================================================================
LOKASI FILE : backend/api/admin/siswa/assign.php
GUNA FILE   : Assign siswa ke kelas (isi tabel kelas_siswa).
              Hanya role admin.
              Method : POST
              Body   : { "id_kelas": 1, "id_siswa": 1 }
              Response sukses : { success, message, data }
================================================================================
*/

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../functions/response.php';
require_once __DIR__ . '/../../../functions/auth.php';

initApi('POST');

$session = requireRole('admin');

$data = readJsonBody();

$idKelas = isset($data['id_kelas']) ? (int) $data['id_kelas'] : 0;
$idSiswa = isset($data['id_siswa']) ? (int) $data['id_siswa'] : 0;

if ($idKelas < 1 || $idSiswa < 1) {
    sendError('id_kelas dan id_siswa wajib diisi dengan angka valid.', 400);
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

    // Pastikan siswa ada
    $stmtSiswa = $db->prepare('SELECT id_siswa FROM siswa WHERE id_siswa = :id LIMIT 1');
    $stmtSiswa->execute([':id' => $idSiswa]);
    if (!$stmtSiswa->fetch()) {
        sendError('Siswa tidak ditemukan.', 404);
    }

    // Insert ke tabel junction
    $stmt = $db->prepare(
        'INSERT INTO kelas_siswa (id_kelas, id_siswa) VALUES (:id_kelas, :id_siswa)'
    );
    $stmt->execute([
        ':id_kelas' => $idKelas,
        ':id_siswa' => $idSiswa,
    ]);

    sendSuccess(
        'Siswa berhasil dimasukkan ke kelas.',
        [
            'id_kelas' => $idKelas,
            'id_siswa' => $idSiswa,
        ],
        201
    );
} catch (PDOException $error) {
    // Unique key uq_kelas_siswa → sudah terdaftar
    if ((int) $error->getCode() === 23000) {
        sendError('Siswa sudah terdaftar di kelas ini.', 409);
    }
    sendServerError($error, 'admin/siswa/assign');
} catch (Throwable $error) {
    sendServerError($error, 'admin/siswa/assign');
}