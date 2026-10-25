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
// Field: id_kelas, judul, deskripsi (opsional), waktu_mulai, deadline, file (opsional).
$classId = filter_var(
    $_POST['id_kelas'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$title = is_string($_POST['judul'] ?? null) ? trim($_POST['judul']) : '';
$descriptionInput = $_POST['deskripsi'] ?? null;
$description = $descriptionInput === null || $descriptionInput === ''
    ? null
    : (is_string($descriptionInput) ? trim($descriptionInput) : false);
$startTime = is_string($_POST['waktu_mulai'] ?? null) ? trim($_POST['waktu_mulai']) : '';
$deadline = is_string($_POST['deadline'] ?? null) ? trim($_POST['deadline']) : '';

if (
    $classId !== false
    && $title !== ''
    && $startTime !== ''
    && $deadline !== ''
    && $description !== false
) {
    $startTimestamp = strtotime($startTime);
    $deadlineTimestamp = strtotime($deadline);

    if ($startTimestamp === false || $deadlineTimestamp === false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Format waktu mulai atau deadline tidak valid."]);
        exit;
    }

    if ($startTimestamp >= $deadlineTimestamp) {
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
            ":id_kelas" => $classId,
            ":id_pengajar" => $session['id']
        ]);

        if ($stmtCekPengajar->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Anda tidak mengajar di kelas ini."]);
            exit;
        }

        // Upload lampiran tugas (opsional). Disimpan di backend/uploads/tugas/.
        $namaFile = handleFileUpload(
            'file',
            'tugas',
            ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'jpg', 'jpeg', 'png']
        );

        $query = "INSERT INTO tugas (id_kelas, id_pengajar, judul, deskripsi, file, waktu_mulai, deadline, status, waktu_dibuat) 
                  VALUES (:id_kelas, :id_pengajar, :judul, :deskripsi, :file, :waktu_mulai, :deadline, 'terbuka', NOW())";
        
        $stmt = $db->prepare($query);
        $executed = $stmt->execute([
            ":id_kelas" => $id_kelas,
            ":id_pengajar" => $session['id'],
            ":judul" => $title,
            ":deskripsi" => $description === '' ? null : $description,
            ":file" => $namaFile,
            ":waktu_mulai" => $startTime,
            ":deadline" => $deadline
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Tugas berhasil dibuat dan status diset terbuka."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal membuat tugas."]);
        }
    } catch (PDOException $e) {
        error_log("Gagal membuat tugas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan pada server."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, judul, waktu mulai, dan deadline wajib diisi."]);
}