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
$taskId = is_object($data)
    ? filter_var($data->id_tugas ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($taskId !== false) {
    try {
        // Memastikan tugas ini benar-benar milik pengajar yang login
        $queryCekPemilik = "SELECT id_pengajar FROM tugas WHERE id_tugas = :id_tugas LIMIT 1";
        $stmtCekPemilik = $db->prepare($queryCekPemilik);
        $stmtCekPemilik->execute([":id_tugas" => $taskId]);
        $tugas = $stmtCekPemilik->fetch(PDO::FETCH_ASSOC);

        if (!$tugas) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Tugas tidak ditemukan."]);
            exit;
        }

        if ((int) $tugas['id_pengajar'] !== (int) $session['id']) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Anda tidak berhak menutup tugas ini."]);
            exit;
        }

        // Mengubah status tugas menjadi 'tertutup'
        $query = "UPDATE tugas SET status = 'tertutup' WHERE id_tugas = :id_tugas";
        $stmt = $db->prepare($query);
        
        $executed = $stmt->execute([
            ":id_tugas" => $taskId
        ]);

        if ($executed) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Pengumpulan tugas berhasil ditutup."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menutup pengumpulan tugas."]);
        }
    } catch (PDOException $e) {
        error_log("Gagal menutup tugas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan pada server."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID tugas wajib diisi."]);
}