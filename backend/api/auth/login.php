<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->kode) && !empty($data->password) && !empty($data->role)) {
    $role = strtolower($data->role);
    $table = "";
    $kode_column = "";
    $id_column = "";

    // Menentukan target tabel dan kolom berdasarkan role
    if ($role === 'admin') {
        $table = "admin";
        $kode_column = "kode_admin";
        $id_column = "id_admin";
    } elseif ($role === 'pengajar') {
        $table = "pengajar";
        $kode_column = "kode_pengajar";
        $id_column = "id_pengajar";
    } elseif ($role === 'siswa') {
        $table = "siswa";
        $kode_column = "kode_siswa";
        $id_column = "id_siswa";
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Role tidak valid. Pilih admin, pengajar, atau siswa."]);
        exit;
    }

    try {
        // Kueri dinamis menyesuaikan tabel role yang dipilih
        $query = "SELECT * FROM {$table} WHERE {$kode_column} = :kode LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([":kode" => $data->kode]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifikasi keberadaan user dan kecocokan password hash
        if ($user && password_verify($data->password, $user['password'])) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Login berhasil.",
                "session" => [
                    "id" => $user[$id_column],
                    "role" => $role,
                    "nama" => $user['nama']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Kode atau password salah."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan server: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. Kirimkan kode, password, dan role."]);
}