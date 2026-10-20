<?php
/*
================================================================================
LOKASI FILE : backend/api/pengajar/create.php
GUNA FILE   : Endpoint API untuk menambahkan data pengajar oleh admin.
              - Memvalidasi kode pengajar, nama, email, dan password.
              - Memvalidasi format email.
              - Melakukan hash password sebelum disimpan.
              - Menyimpan data pengajar ke database.
================================================================================
*/

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../../config/database.php";
require_once "../../functions/auth.php";

// Hanya admin yang boleh menambahkan pengajar.
requireRole('admin');

// Pastikan request menggunakan metode POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Metode request tidak diizinkan."
    ]);
    exit;
}

// Baca data JSON dari request.
$data = json_decode(file_get_contents("php://input"));

if (
    !is_object($data) ||
    !isset($data->kode_pengajar, $data->nama, $data->email, $data->password) ||
    !is_string($data->kode_pengajar) ||
    !is_string($data->nama) ||
    !is_string($data->email) ||
    !is_string($data->password)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Data tidak lengkap atau format data tidak valid."
    ]);
    exit;
}

// Bersihkan spasi pada data teks.
$kodePengajar = trim($data->kode_pengajar);
$nama = trim($data->nama);
$email = trim($data->email);
$password = $data->password;

// Validasi kolom wajib.
if (
    $kodePengajar === "" ||
    $nama === "" ||
    $email === "" ||
    $password === ""
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kode pengajar, nama, email, dan password wajib diisi."
    ]);
    exit;
}

// Validasi panjang data.
if (strlen($kodePengajar) > 20 || strlen($nama) > 100) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kode pengajar maksimal 20 karakter dan nama maksimal 100 karakter."
    ]);
    exit;
}

if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format email tidak valid atau melebihi 255 karakter."
    ]);
    exit;
}

if (strlen($password) < 4 || strlen($password) > 100) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Password harus terdiri dari 4 sampai 100 karakter."
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Simpan email bersama data pengajar lainnya.
    $query = "INSERT INTO pengajar
              (kode_pengajar, nama, email, password)
              VALUES
              (:kode_pengajar, :nama, :email, :password)";

    $stmt = $db->prepare($query);

    // Password tidak disimpan dalam bentuk teks biasa.
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $executed = $stmt->execute([
        ":kode_pengajar" => $kodePengajar,
        ":nama" => $nama,
        ":email" => $email,
        ":password" => $hashedPassword
    ]);

    if ($executed) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Data pengajar berhasil ditambahkan."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal menambahkan data pengajar."
        ]);
    }

} catch (PDOException $e) {
    // SQLSTATE 23000 biasanya menunjukkan pelanggaran constraint,
    // misalnya kode pengajar atau email yang sudah digunakan.
    if ($e->getCode() === "23000") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Kode pengajar atau email mungkin sudah digunakan."
        ]);
    } else {
        error_log("Gagal membuat pengajar: " . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Terjadi kesalahan saat menyimpan data pengajar."
        ]);
    }
}
