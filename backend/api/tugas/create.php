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

if (!empty($data->id_kelas) && !empty($data->judul) && !empty($data->waktu_mulai) && !empty($data->deadline)) {
    $waktuMulai = strtotime($data->waktu_mulai);
    $deadline = strtotime($data->deadline);

    if ($waktuMulai === false || $deadline === false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Format waktu mulai atau deadline tidak valid."]);
        exit;
    }

    if ($waktuMulai >= $deadline) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Waktu mulai harus lebih awal dari deadline."]);
        exit;
    }

    try {
        // Memastikan pengajar yang login benar-benar mengajar di kelas ini
        $queryCekPengajar = "SELECT id_kelas_pengajar FROM kelas_pengajar 
                              WHERE id_kelas = :id_kelas AND id_pengajar = :id_pengajar LIMIT 1";
        $stmtCekPengajar = $db->prepare($queryCekPengajar);
        $stmtCekPengajar->execute([
            ":id_kelas" => $data->id_kelas,
            ":id_pengajar" => $session['id']
        ]);

        if ($stmtCekPengajar->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Anda tidak mengajar di kelas ini."]);
            exit;
        }

        $query = "INSERT INTO tugas (id_kelas, id_pengajar, judul, deskripsi, file, waktu_mulai, deadline, status, waktu_dibuat) 
                  VALUES (:id_kelas, :id_pengajar, :judul, :deskripsi, :file, :waktu_mulai, :deadline, 'terbuka', NOW())";
        
        $stmt = $db->prepare($query);
        $executed = $stmt->execute([
            ":id_kelas" => $data->id_kelas,
            ":id_pengajar" => $session['id'],
            ":judul" => $data->judul,
            ":deskripsi" => isset($data->deskripsi) ? $data->deskripsi : null,
            ":file" => isset($data->file) ? $data->file : null,
            ":waktu_mulai" => $data->waktu_mulai,
            ":deadline" => $data->deadline
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Tugas berhasil dibuat dan status diset terbuka."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal membuat tugas."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, judul, waktu mulai, dan deadline wajib diisi."]);
}