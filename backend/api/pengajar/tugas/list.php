<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Pastikan hanya pengajar yang dapat mengakses endpoint.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// Parameter opsional.
// Contoh:
// list.php?id_kelas=1
$id_kelas = $_GET['id_kelas'] ?? null;

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Jika id_kelas diberikan, validasi bahwa
     * pengajar memang mengajar kelas tersebut.
     */
    if ($id_kelas !== null) {

        if (!is_numeric($id_kelas) || (int) $id_kelas <= 0) {
            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "ID kelas tidak valid.",
                "data" => null
            ]);

            exit;
        }

        $id_kelas = (int) $id_kelas;

        $queryCekKelas = "
            SELECT id_kelas_pengajar
            FROM kelas_pengajar
            WHERE id_kelas = :id_kelas
              AND id_pengajar = :id_pengajar
            LIMIT 1
        ";

        $stmtCekKelas = $db->prepare($queryCekKelas);

        $stmtCekKelas->execute([
            ":id_kelas" => $id_kelas,
            ":id_pengajar" => $id_pengajar
        ]);

        if (!$stmtCekKelas->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(403);

            echo json_encode([
                "success" => false,
                "message" => "Anda tidak mengajar kelas tersebut.",
                "data" => null
            ]);

            exit;
        }
    }

    /*
     * Ambil semua tugas yang dibuat oleh pengajar
     * yang sedang login.
     */
    $query = "
        SELECT
            t.id_tugas,
            t.id_kelas,
            k.nama_kelas,
            t.id_pengajar,
            t.judul,
            t.deskripsi,
            t.file,
            t.waktu_mulai,
            t.deadline,
            t.status,
            t.waktu_dibuat,

            COUNT(DISTINCT pt.id_pengumpulan) AS jumlah_pengumpulan

        FROM tugas t

        INNER JOIN kelas k
            ON t.id_kelas = k.id_kelas

        LEFT JOIN pengumpulan_tugas pt
            ON t.id_tugas = pt.id_tugas

        WHERE t.id_pengajar = :id_pengajar
    ";

    $params = [
        ":id_pengajar" => $id_pengajar
    ];

    // Filter berdasarkan kelas jika diberikan.
    if ($id_kelas !== null) {
        $query .= " AND t.id_kelas = :id_kelas";
        $params[":id_kelas"] = $id_kelas;
    }

    $query .= "
        GROUP BY
            t.id_tugas,
            t.id_kelas,
            k.nama_kelas,
            t.id_pengajar,
            t.judul,
            t.deskripsi,
            t.file,
            t.waktu_mulai,
            t.deadline,
            t.status,
            t.waktu_dibuat

        ORDER BY t.waktu_dibuat DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $tugas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Konversi nilai numerik dari database
     * menjadi integer pada response JSON.
     */
    foreach ($tugas as &$data) {

        $data['id_tugas'] = (int) $data['id_tugas'];
        $data['id_kelas'] = (int) $data['id_kelas'];
        $data['id_pengajar'] = (int) $data['id_pengajar'];
        $data['jumlah_pengumpulan'] = (int) $data['jumlah_pengumpulan'];
    }

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Data tugas berhasil diambil.",
        "data" => $tugas
    ]);

} catch (PDOException $error) {

    // Detail error database hanya disimpan di log server.
    error_log("Error list tugas pengajar: " . $error->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil data tugas.",
        "data" => null
    ]);
}