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

// Pengecekan validasi menggunakan 'kode_siswa'
if (!empty($data->kode_siswa) && !empty($data->nama) && !empty($data->password)) {
    try {
        // Kueri SQL disesuaikan dengan struktur lmsdb.sql
        $query = "INSERT INTO siswa (kode_siswa, nama, password) VALUES (:kode_siswa, :nama, :password)";
        $stmt = $db->prepare($query);
        
        // Enkripsi kata sandi standar keamanan
        $hashedPassword = password_hash($data->password, PASSWORD_BCRYPT);

        // Binding parameter PDO
        $executed = $stmt->execute([
            ":kode_siswa" => $data->kode_siswa,
            ":nama" => $data->nama,
            ":password" => $hashedPassword
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Data siswa berhasil ditambahkan."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menambahkan data siswa."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. Kolom kode_siswa, nama, dan password wajib diisi."]);
}