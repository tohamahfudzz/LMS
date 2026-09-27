<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";
require_once "../../functions/auth.php";

$session = requireRole('siswa');

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id_absensi)) {
    try {
        // ID siswa diambil dari session, bukan dari input pengguna,
        // agar siswa tidak dapat mengisi absensi atas nama siswa lain.
        $idSiswa = $session['id'];

        $queryAbsen = "SELECT id_kelas, status FROM absensi WHERE id_absensi = :id_absensi LIMIT 1";
        $stmtAbsen = $db->prepare($queryAbsen);
        $stmtAbsen->execute([":id_absensi" => $data->id_absensi]);
        $absensi = $stmtAbsen->fetch(PDO::FETCH_ASSOC);

        if (!$absensi) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Sesi absensi tidak ditemukan."]);
            exit;
        }

        if ($absensi['status'] !== 'terbuka') {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Presensi ditolak. Sesi absensi sudah ditutup oleh pengajar."]);
            exit;
        }

        $querySiswa = "SELECT * FROM kelas_siswa WHERE id_kelas = :id_kelas AND id_siswa = :id_siswa LIMIT 1";
        $stmtSiswa = $db->prepare($querySiswa);
        $stmtSiswa->execute([":id_kelas" => $absensi['id_kelas'], ":id_siswa" => $idSiswa]);

        if ($stmtSiswa->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Akses ditolak. Siswa tidak terdaftar di kelas sesi absensi ini."]);
            exit;
        }

        $queryHadir = "INSERT INTO kehadiran (id_absensi, id_siswa, waktu_absen, status) 
                       VALUES (:id_absensi, :id_siswa, NOW(), 'hadir')";
        $stmtHadir = $db->prepare($queryHadir);
        $executed = $stmtHadir->execute([
            ":id_absensi" => $data->id_absensi,
            ":id_siswa" => $idSiswa
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Presensi berhasil dicatat dengan status 'hadir'."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal melakukan presensi."]);
        }

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Presensi ditolak. Anda sudah melakukan absensi pada sesi ini."]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
        }
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID absensi wajib diisi."]);
}