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
$id_kelas = $_POST['id_kelas'] ?? null;
$judul = $_POST['judul'] ?? null;
$deskripsi = $_POST['deskripsi'] ?? null;
$waktu_mulai = $_POST['waktu_mulai'] ?? null;
$deadline = $_POST['deadline'] ?? null;

if (!empty($id_kelas) && !empty($judul) && !empty($waktu_mulai) && !empty($deadline)) {
    $waktuMulaiTs = strtotime($waktu_mulai);
    $deadlineTs = strtotime($deadline);

    if ($waktuMulaiTs === false || $deadlineTs === false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Format waktu mulai atau deadline tidak valid."]);
        exit;
    }

    if ($waktuMulaiTs >= $deadlineTs) {
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
            ":id_kelas" => $id_kelas,
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
            ":judul" => $judul,
            ":deskripsi" => empty($deskripsi) ? null : $deskripsi,
            ":file" => $namaFile,
            ":waktu_mulai" => $waktu_mulai,
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
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID kelas, judul, waktu mulai, dan deadline wajib diisi."]);
}