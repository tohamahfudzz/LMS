<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->kode) && !empty($data->password)) {
    $database = new Database();
    $db = $database->getConnection();

    try {
        $query = "SELECT * FROM admin WHERE kode_admin = :kode LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([":kode" => $data->kode]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($data->password, $admin['password'])) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Login berhasil.",
                "role" => "admin",
                "data" => ["id" => $admin['id_admin'], "nama" => $admin['nama']]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Kode atau password salah."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Kesalahan pada server."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data yang dikirimkan tidak lengkap."]);
}