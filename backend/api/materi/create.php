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

if (!empty($data->id_kelas) && !empty($data->judul) && !empty($data->tipe)) {
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

        $query = "INSERT INTO materi (id_kelas, id_pengajar, judul, tipe, file, link, waktu_upload) 
                  VALUES (:id_kelas, :id_pengajar, :judul, :tipe, :file, :link, NOW())";
        
        $stmt = $db->prepare($query);
        $executed = $stmt->execute([
            ":id_kelas" => $data->id_kelas,
            ":id_pengajar" => $session['id'],
            ":judul" => $data->judul,
            ":tipe" => $data->tipe,
            ":file" => isset($data->file) ? $data->file : null,
            ":link" => isset($data->link) ? $data->link : null
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Materi berhasil ditambahkan."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menambahkan materi."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, judul, dan tipe wajib diisi."]);
}