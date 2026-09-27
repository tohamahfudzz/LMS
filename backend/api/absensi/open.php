=<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

// Validasi data wajib sesuai alur absensi pengajar
if (!empty($data->id_kelas) && !empty($data->id_pengajar) && !empty($data->tanggal)) {
    try {
        // Menyimpan sesi absensi dengan status "terbuka" dan waktu buka otomatis
        $query = "INSERT INTO absensi (id_kelas, id_pengajar, tanggal, waktu_buka, status) 
                  VALUES (:id_kelas, :id_pengajar, :tanggal, NOW(), 'terbuka')";
        
        $stmt = $db->prepare($query);
        $executed = $stmt->execute([
            ":id_kelas" => $data->id_kelas,
            ":id_pengajar" => $data->id_pengajar,
            ":tanggal" => $data->tanggal
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Sesi absensi berhasil dibuka."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal membuka sesi absensi."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, ID pengajar, dan tanggal wajib diisi."]);
}