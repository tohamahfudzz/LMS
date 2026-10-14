<?php

// GET /api/kelas/saya.php
// Role: pengajar. Daftar kelas yang diajar pengajar yang sedang login (dari tabel kelas_pengajar).
// Dipakai frontend untuk mengisi pilihan kelas pada form Buat Kuis.
// Hanya membaca data; id_pengajar selalu diambil dari session, bukan dari input.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';

initApi('GET');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$database = new Database();
$db = $database->getConnection();

try {
    $statement = $db->prepare(
        "SELECT k.id_kelas, k.nama_kelas
         FROM kelas k
         INNER JOIN kelas_pengajar kp ON kp.id_kelas = k.id_kelas
         WHERE kp.id_pengajar = :id_pengajar
         ORDER BY k.nama_kelas ASC"
    );
    $statement->execute([":id_pengajar" => $idPengajar]);

    $kelasList = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kelasList[] = [
            "id_kelas" => (int) $row['id_kelas'],
            "nama_kelas" => $row['nama_kelas'],
        ];
    }

    sendSuccess("Daftar kelas berhasil diambil.", $kelasList);
} catch (Throwable $error) {
    sendServerError($error, 'kelas/saya');
}