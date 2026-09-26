<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id_kelas) && !empty($data->id_pengajar) && !empty($data->judul) && !empty($data->tipe)) {
    try {
        $query = "INSERT INTO materi (id_kelas, id_pengajar, judul, tipe, file, link, waktu_upload) 
                  VALUES (:id_kelas, :id_pengajar, :judul, :tipe, :file, :link, NOW())";
        
        $stmt = $db->prepare($query);
        $executed = $stmt->execute([
            ":id_kelas" => $data->id_kelas,
            ":id_pengajar" => $data->id_pengajar,
            ":judul" => $data->judul,
            ":tipe" => $data->tipe,
            ":file" => isset($data->file) ? $data->file : null,
            ":link" => isset($data->link) ? $data->link : null
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Materi berhasil ditambahkan."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menambahkan materi."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, ID pengajar, judul, dan tipe wajib diisi."]);
}