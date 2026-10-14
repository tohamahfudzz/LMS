<?php

// GET /api/kuis/tersedia.php
// Role: siswa. Daftar kuis dari kelas yang diikutinya (kuis draft tidak pernah tampil),
// beserta ketersediaan, status pengerjaan, dan nilai akhir bila sudah final.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('GET');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$database = new Database();
$db = $database->getConnection();

try {
    sendSuccess("Daftar kuis berhasil diambil.", fetchKuisForStudent($db, $idSiswa));
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'kuis/tersedia');
}