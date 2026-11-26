<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";
require_once "../../functions/auth.php";

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));
$className = is_object($data) && isset($data->nama_kelas) && is_string($data->nama_kelas)
    ? trim($data->nama_kelas)
    : '';

if ($className !== '') {
       try {
        // Opsional: tahun ajaran dan semester (kosong = diisi nanti oleh admin).
        $idTahunAjaran = (isset($data->id_tahun_ajaran) && $data->id_tahun_ajaran !== '')
            ? (int) $data->id_tahun_ajaran
            : null;
        $semester = (isset($data->semester) && $data->semester !== '')
            ? strtolower(trim((string) $data->semester))
            : null;

        if ($semester !== null && !in_array($semester, ['ganjil', 'genap'], true)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Semester harus ganjil atau genap."]);
            exit;
        }

        if ($idTahunAjaran !== null) {
            $cek = $db->prepare("SELECT 1 FROM tahun_ajaran WHERE id_tahun_ajaran = :id LIMIT 1");
            $cek->execute([":id" => $idTahunAjaran]);

            if ($cek->fetchColumn() === false) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Tahun ajaran tidak ditemukan."]);
                exit;
            }
        }

        $query = "INSERT INTO kelas (nama_kelas, id_tahun_ajaran, semester)
                  VALUES (:nama_kelas, :id_tahun_ajaran, :semester)";
        $stmt = $db->prepare($query);

        $executed = $stmt->execute([
            ":nama_kelas" => $data->nama_kelas,
            ":id_tahun_ajaran" => $idTahunAjaran,
            ":semester" => $semester
        ]); catch (PDOException $e) {
        error_log("Gagal membuat kelas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan pada server."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. Kolom nama_kelas wajib diisi."]);
}