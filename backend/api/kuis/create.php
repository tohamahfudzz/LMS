<?php

// POST /api/kuis/create.php
// Role: pengajar. Membuat kuis baru berstatus 'draft' untuk kelas yang diajarnya.
// Body JSON: id_kelas, judul, deskripsi (opsional), waktu_mulai (opsional),
//            waktu_selesai (opsional), durasi_menit.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$data = readJsonBody();

$idKelas = parsePositiveInt($data['id_kelas'] ?? null);
$judul = readString($data, 'judul');
$deskripsi = readString($data, 'deskripsi');
$durasiMenit = parsePositiveInt($data['durasi_menit'] ?? null);
$waktuMulai = readOptionalDateTime($data, 'waktu_mulai', 'waktu mulai');
$waktuSelesai = readOptionalDateTime($data, 'waktu_selesai', 'waktu selesai');

if ($idKelas === null) {
    sendError("ID kelas tidak valid.", 400);
}

if ($judul === null || mb_strlen($judul) > 200) {
    sendError("Judul wajib diisi dan maksimal 200 karakter.", 400);
}

if ($deskripsi !== null && mb_strlen($deskripsi) > 5000) {
    sendError("Deskripsi maksimal 5000 karakter.", 400);
}

if ($durasiMenit === null || $durasiMenit > KUIS_MAX_DURASI_MENIT) {
    sendError("Durasi wajib berupa angka 1 sampai " . KUIS_MAX_DURASI_MENIT . " menit.", 400);
}

// Format tanggal-waktu seragam sehingga perbandingan string sama dengan urutan waktu.
if ($waktuMulai !== null && $waktuSelesai !== null && strcmp($waktuSelesai, $waktuMulai) <= 0) {
    sendError("Waktu selesai harus lebih akhir dari waktu mulai.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    // Hak akses diperiksa di backend: pengajar hanya boleh membuat kuis di kelas yang diajarnya.
    if (!teacherTeachesClass($db, $idKelas, $idPengajar)) {
        sendError("Anda tidak mengajar di kelas ini.", 403);
    }

    // id_pengajar diambil dari session, bukan dari input, dan status awal selalu draft.
    $statement = $db->prepare(
        "INSERT INTO kuis (id_kelas, id_pengajar, judul, deskripsi, waktu_mulai, waktu_selesai, durasi_menit, status)
         VALUES (:id_kelas, :id_pengajar, :judul, :deskripsi, :waktu_mulai, :waktu_selesai, :durasi_menit, :status)"
    );
    $statement->execute([
        ":id_kelas" => $idKelas,
        ":id_pengajar" => $idPengajar,
        ":judul" => $judul,
        ":deskripsi" => $deskripsi,
        ":waktu_mulai" => $waktuMulai,
        ":waktu_selesai" => $waktuSelesai,
        ":durasi_menit" => $durasiMenit,
        ":status" => KUIS_STATUS_DRAFT,
    ]);

    sendSuccess(
        "Kuis berhasil dibuat sebagai draft.",
        ["id_kuis" => (int) $db->lastInsertId(), "status" => KUIS_STATUS_DRAFT],
        201
    );
} catch (PDOException $error) {
    sendServerError($error, 'kuis/create');
}