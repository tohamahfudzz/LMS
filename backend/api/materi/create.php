<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";
require_once "../../functions/auth.php";
require_once "../../functions/upload.php";

$session = requireRole('pengajar');

$database = new Database();
$db = $database->getConnection();

// Catatan untuk frontend: endpoint ini menerima file, jadi data dikirim
// sebagai multipart/form-data (FormData), BUKAN JSON.
// Field: id_kelas, judul, tipe, link (opsional), file (opsional, input file).
$classId = filter_var(
    $_POST['id_kelas'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$title = is_string($_POST['judul'] ?? null) ? trim($_POST['judul']) : '';
$type = is_string($_POST['tipe'] ?? null) ? trim($_POST['tipe']) : '';
$linkInput = $_POST['link'] ?? null;
$link = $linkInput === null || $linkInput === ''
    ? null
    : (is_string($linkInput) ? trim($linkInput) : false);

if ($classId !== false && $title !== '' && $type !== '' && $link !== false) {
    try {
        // Memastikan pengajar yang login benar-benar mengajar di kelas ini
        $queryCekPengajar = "SELECT id_kelas_pengajar FROM kelas_pengajar 
                              WHERE id_kelas = :id_kelas AND id_pengajar = :id_pengajar LIMIT 1";
        $stmtCekPengajar = $db->prepare($queryCekPengajar);
        $stmtCekPengajar->execute([
            ":id_kelas" => $classId,
            ":id_pengajar" => $session['id']
        ]);

        if ($stmtCekPengajar->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Anda tidak mengajar di kelas ini."]);
            exit;
        }

        // Upload file materi (opsional). Disimpan di backend/uploads/materi/.
        $namaFile = handleFileUpload(
            'file',
            'materi',
            ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'mp4']
        );

        if (empty($namaFile) && empty($link)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Materi harus memiliki file atau link."]);
            exit;
        }

        $query = "INSERT INTO materi (id_kelas, id_pengajar, judul, tipe, file, link, waktu_upload) 
                  VALUES (:id_kelas, :id_pengajar, :judul, :tipe, :file, :link, NOW())";
        
        $stmt = $db->prepare($query);
        $executed = $stmt->execute([
            ":id_kelas" => $id_kelas,
            ":id_pengajar" => $session['id'],
            ":judul" => $title,
            ":tipe" => $type,
            ":file" => $namaFile,
            ":link" => $link === '' ? null : $link
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Materi berhasil ditambahkan."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal menambahkan materi."]);
        }
    } catch (PDOException $e) {
        error_log("Gagal membuat materi: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan pada server."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, judul, dan tipe wajib diisi."]);
}