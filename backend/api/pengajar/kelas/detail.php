<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Pastikan hanya pengajar yang dapat mengakses endpoint ini.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// Ambil id_kelas dari parameter URL.
// Contoh: detail.php?id_kelas=1
if (!isset($_GET['id_kelas']) || !is_numeric($_GET['id_kelas'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID kelas wajib diisi."
    ]);
    exit;
}

$id_kelas = (int) $_GET['id_kelas'];

if ($id_kelas <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID kelas tidak valid."
    ]);
    exit;
}

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Pastikan kelas tersebut memang diajar
     * oleh pengajar yang sedang login.
     */
    $queryKelas = "
        SELECT 
            k.id_kelas,
            k.nama_kelas
        FROM kelas k
        INNER JOIN kelas_pengajar kp
            ON k.id_kelas = kp.id_kelas
        WHERE k.id_kelas = :id_kelas
          AND kp.id_pengajar = :id_pengajar
        LIMIT 1
    ";

    $stmtKelas = $db->prepare($queryKelas);

    $stmtKelas->execute([
        ":id_kelas" => $id_kelas,
        ":id_pengajar" => $id_pengajar
    ]);

    $kelas = $stmtKelas->fetch(PDO::FETCH_ASSOC);

    // Jika kelas tidak ditemukan atau bukan milik pengajar.
    if (!$kelas) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Kelas tidak ditemukan atau Anda tidak mengajar kelas ini."
        ]);

        exit;
    }

    /*
     * Ambil daftar siswa yang terdaftar
     * pada kelas tersebut.
     */
    $querySiswa = "
        SELECT
            s.id_siswa,
            s.kode_siswa,
            s.nama
        FROM kelas_siswa ks
        INNER JOIN siswa s
            ON ks.id_siswa = s.id_siswa
        WHERE ks.id_kelas = :id_kelas
        ORDER BY s.nama ASC
    ";

    $stmtSiswa = $db->prepare($querySiswa);

    $stmtSiswa->execute([
        ":id_kelas" => $id_kelas
    ]);

    $siswa = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    // Ubah ID menjadi integer agar response JSON lebih rapi.
    foreach ($siswa as &$dataSiswa) {
        $dataSiswa['id_siswa'] = (int) $dataSiswa['id_siswa'];
    }

    /*
     * Response akhir.
     */
    http_response_code(200);

    echo json_encode([
        "success" => true,
        "data" => [
            "id_kelas" => (int) $kelas['id_kelas'],
            "nama_kelas" => $kelas['nama_kelas'],
            "jumlah_siswa" => count($siswa),
            "siswa" => $siswa
        ]
    ]);

} catch (PDOException $e) {

    // Detail error database tidak ditampilkan kepada user.
    error_log("Error detail kelas pengajar: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil detail kelas."
    ]);
}