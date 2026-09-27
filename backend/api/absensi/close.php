<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";
require_once "../../functions/auth.php";

$session = requireRole('pengajar');

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

// Validasi id_absensi yang akan ditutup
if (!empty($data->id_absensi)) {
    try {
        // Memastikan sesi absensi ini benar-benar milik pengajar yang login
        $queryCekPemilik = "SELECT id_pengajar FROM absensi WHERE id_absensi = :id_absensi LIMIT 1";
        $stmtCekPemilik = $db->prepare($queryCekPemilik);
        $stmtCekPemilik->execute([":id_absensi" => $data->id_absensi]);
        $absensi = $stmtCekPemilik->fetch(PDO::FETCH_ASSOC);

        if (!$absensi) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Sesi absensi tidak ditemukan."]);
            exit;
        }

        if ((int) $absensi['id_pengajar'] !== (int) $session['id']) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Anda tidak berhak menutup sesi absensi ini."]);
            exit;
        }

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