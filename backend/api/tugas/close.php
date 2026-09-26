<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

// Validasi id_tugas yang akan ditutup
if (!empty($data->id_tugas)) {
    try {
        // Mengubah status tugas menjadi 'tertutup'
        $query = "UPDATE tugas SET status = 'tertutup' WHERE id_tugas = :id_tugas";
        $stmt = $db->prepare($query);
        
        $executed = $stmt->execute([
            ":id_tugas" => $data->id_tugas
        ]);

        if ($executed) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Pengumpulan tugas berhasil ditutup."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menutup pengumpulan tugas."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID tugas wajib diisi."]);
}