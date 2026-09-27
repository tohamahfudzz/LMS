<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id_tugas) && !empty($data->id_siswa)) {
    try {
        $queryTugas = "SELECT id_kelas, status, deadline FROM tugas WHERE id_tugas = :id_tugas LIMIT 1";
        $stmtTugas = $db->prepare($queryTugas);
        $stmtTugas->execute([":id_tugas" => $data->id_tugas]);
        $tugas = $stmtTugas->fetch(PDO::FETCH_ASSOC);

        if (!$tugas) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Tugas tidak ditemukan."]);
            exit;
        }

        if ($tugas['status'] !== 'terbuka') {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pengumpulan ditolak. Tugas sudah ditutup oleh pengajar."]);
            exit;
        }

        if (strtotime($tugas['deadline']) < time()) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pengumpulan ditolak. Deadline tugas sudah terlewat."]);
            exit;
        }

        $querySiswa = "SELECT * FROM kelas_siswa WHERE id_kelas = :id_kelas AND id_siswa = :id_siswa LIMIT 1";
        $stmtSiswa = $db->prepare($querySiswa);
        $stmtSiswa->execute([":id_kelas" => $tugas['id_kelas'], ":id_siswa" => $data->id_siswa]);
        
        if ($stmtSiswa->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Akses ditolak. Siswa tidak terdaftar di kelas tugas ini."]);
            exit;
        }

        $queryUpload = "INSERT INTO pengumpulan_tugas (id_tugas, id_siswa, file, teks, waktu_upload, waktu_pengumpulan) 
                        VALUES (:id_tugas, :id_siswa, :file, :teks, NOW(), NOW())";
        $stmtUpload = $db->prepare($queryUpload);
        $executed = $stmtUpload->execute([
            ":id_tugas" => $data->id_tugas,
            ":id_siswa" => $data->id_siswa,
            ":file" => isset($data->file) ? $data->file : null,
            ":teks" => isset($data->teks) ? $data->teks : null
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Tugas berhasil dikumpulkan."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal mengumpulkan tugas."]);
        }

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Anda sudah mengumpulkan tugas ini sebelumnya."]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
        }
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID tugas dan ID siswa wajib diisi."]);
}