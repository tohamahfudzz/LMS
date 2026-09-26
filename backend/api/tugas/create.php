<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id_kelas) && !empty($data->id_pengajar) && !empty($data->judul) && !empty($data->deadline)) {
    try {
        $query = "INSERT INTO tugas (id_kelas, id_pengajar, judul, deskripsi, file, deadline, status, waktu_dibuat) 
                  VALUES (:id_kelas, :id_pengajar, :judul, :deskripsi, :file, :deadline, 'terbuka', NOW())";
        
        $stmt = $db->prepare($query);
        $executed = $stmt->execute([
            ":id_kelas" => $data->id_kelas,
            ":id_pengajar" => $data->id_pengajar,
            ":judul" => $data->judul,
            ":deskripsi" => isset($data->deskripsi) ? $data->deskripsi : null,
            ":file" => isset($data->file) ? $data->file : null,
            ":deadline" => $data->deadline
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Tugas berhasil dibuat dan status diset terbuka."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal membuat tugas."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, ID pengajar, judul, dan deadline wajib diisi."]);
}