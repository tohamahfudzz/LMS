<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";
require_once "../../functions/auth.php";

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->kode_pengajar) && !empty($data->nama) && !empty($data->password)) {
    try {
        $query = "INSERT INTO pengajar (kode_pengajar, nama, password) VALUES (:kode_pengajar, :nama, :password)";
        $stmt = $db->prepare($query);
        
        $hashedPassword = password_hash($data->password, PASSWORD_BCRYPT);

        $executed = $stmt->execute([
            ":kode_pengajar" => $data->kode_pengajar,
            ":nama" => $data->nama,
            ":password" => $hashedPassword
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Data pengajar berhasil ditambahkan."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menambahkan data pengajar."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. Kolom kode_pengajar, nama, dan password wajib diisi."]);
}