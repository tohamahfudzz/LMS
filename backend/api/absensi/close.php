<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

// Validasi id_absensi yang akan ditutup
if (!empty($data->id_absensi)) {
    try {
        // Mengubah status absensi menjadi 'tertutup' dan mencatat waktu tutup
        $query = "UPDATE absensi SET status = 'tertutup', waktu_tutup = NOW() WHERE id_absensi = :id_absensi";
        $stmt = $db->prepare($query);
        
        $executed = $stmt->execute([
            ":id_absensi" => $data->id_absensi
        ]);

        if ($executed) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Sesi absensi berhasil ditutup."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menutup sesi absensi."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID absensi wajib diisi."]);
}