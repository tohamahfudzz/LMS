<?php
// Tampilkan error sementara untuk mendeteksi masalah (Hapus jika sudah produksi)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../functions/response.php';

// 1. Validasi Method dan Header menggunakan Helper Baru
initApi('POST');

// 2. Membaca Request JSON menggunakan Helper Baru (menghasilkan Array)
$requestData = readJsonBody();
$email = trim($requestData['email'] ?? '');

if (empty($email)) {
    sendError("Alamat email wajib diisi.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $checkUserQuery = "SELECT email FROM siswa WHERE email = :email LIMIT 1"; 
    $checkUserStatement = $db->prepare($checkUserQuery);
    $checkUserStatement->execute([":email" => $email]);

    if ($checkUserStatement->rowCount() === 0) {
        sendError("Akun tidak ditemukan. Pastikan email yang Anda masukkan sudah terdaftar di sistem.", 404);
    }

    $resetToken = bin2hex(random_bytes(32)); 

    $insertTokenQuery = "INSERT INTO password_resets (email, token) VALUES (:email, :token)";
    $insertTokenStatement = $db->prepare($insertTokenQuery);
    $insertTokenStatement->execute([
        ":email" => $email,
        ":token" => $resetToken
    ]);

    $resetLink = "http://localhost/LMS/frontend/auth/reset-password.html?token=" . $resetToken;

    // 3. Kirim Balasan Sukses menggunakan Helper Baru
    sendSuccess("Instruksi pemulihan kata sandi telah dikirim ke email terdaftar Anda.", [
        "reset_link" => $resetLink
    ]);

} catch (PDOException $error) {
    // Mode Debug: Menampilkan error langsung ke Thunder Client
    sendError("Database Error: " . $error->getMessage(), 500);
    
    // Jika pengujian sudah berhasil (200 OK), ganti baris di atas dengan standar PM:
    // sendServerError($error, "ForgotPassword");
}