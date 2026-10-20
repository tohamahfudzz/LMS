<?php
/*
================================================================================
LOKASI FILE : backend/api/siswa/create.php
GUNA FILE   : Endpoint API untuk menambahkan data siswa oleh admin.
              - Memvalidasi kode siswa, nama, email, dan password.
              - Memvalidasi format email.
              - Melakukan hash password sebelum disimpan.
              - Menyimpan data siswa ke database.
================================================================================
*/

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../../config/database.php";
require_once "../../functions/auth.php";

// Hanya admin yang boleh menambahkan siswa.
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
    !isset($data->kode_siswa, $data->nama, $data->email, $data->password) ||
    !is_string($data->kode_siswa) ||
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
$kodeSiswa = trim($data->kode_siswa);
$nama = trim($data->nama);
$email = trim($data->email);
$password = $data->password;

// Validasi kolom wajib.
if (
    $kodeSiswa === "" ||
    $nama === "" ||
    $email === "" ||
    $password === ""
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kode siswa, nama, email, dan password wajib diisi."
    ]);
    exit;
}

// Validasi panjang data.
if (strlen($kodeSiswa) > 20 || strlen($nama) > 100) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kode siswa maksimal 20 karakter dan nama maksimal 100 karakter."
    ]);
    exit;
}

// Validasi format email.
if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format email tidak valid atau melebihi 255 karakter."
    ]);
    exit;
}

// Validasi password.
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

    // Simpan email bersama data siswa lainnya.
    $query = "INSERT INTO siswa
              (kode_siswa, nama, email, password)
              VALUES
              (:kode_siswa, :nama, :email, :password)";

    $stmt = $db->prepare($query);

    // Hash password sebelum disimpan.
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Jalankan query menggunakan parameter PDO.
    $executed = $stmt->execute([
        ":kode_siswa" => $kodeSiswa,
        ":nama" => $nama,
        ":email" => $email,
        ":password" => $hashedPassword
    ]);

    if ($executed) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Data siswa berhasil ditambahkan."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Gagal menambahkan data siswa."
        ]);
    }

} catch (PDOException $e) {
    // Catat detail error di log server, bukan di respons pengguna.
    error_log("Gagal membuat siswa: " . $e->getMessage());

    // SQLSTATE 23000 biasanya menunjukkan pelanggaran constraint.
    if ($e->getCode() === "23000") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Kode siswa atau email mungkin sudah digunakan."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Terjadi kesalahan saat menyimpan data siswa."
        ]);
    }
}
