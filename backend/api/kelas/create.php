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
$className = is_object($data) && isset($data->nama_kelas) && is_string($data->nama_kelas)
    ? trim($data->nama_kelas)
    : '';

if ($className !== '') {
    try {
        $query = "INSERT INTO kelas (nama_kelas) VALUES (:nama_kelas)";
        $stmt = $db->prepare($query);
        
        $executed = $stmt->execute([
            ":nama_kelas" => $className
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Data kelas berhasil ditambahkan."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menambahkan data kelas."]);
        }
    } catch (PDOException $e) {
        error_log("Gagal membuat kelas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan pada server."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. Kolom nama_kelas wajib diisi."]);
}