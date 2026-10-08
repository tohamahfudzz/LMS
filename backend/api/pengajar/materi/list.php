<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya pengajar yang boleh mengakses endpoint ini.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// Jika ingin menampilkan materi dari satu kelas saja,
// gunakan: list.php?id_kelas=1
$id_kelas = $_GET['id_kelas'] ?? null;

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Jika id_kelas diberikan, pastikan kelas tersebut
     * memang diajar oleh pengajar yang sedang login.
     */
    if ($id_kelas !== null) {

        if (!is_numeric($id_kelas) || (int) $id_kelas <= 0) {
            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "ID kelas tidak valid."
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
                "message" => "Anda tidak mengajar kelas ini."
            ]);

            exit;
        }
    }

    /*
     * Ambil materi milik pengajar yang sedang login.
     * JOIN kelas digunakan supaya nama kelas ikut dikirim.
     */
    $query = "
        SELECT
            m.id_materi,
            m.id_kelas,
            k.nama_kelas,
            m.id_pengajar,
            m.judul,
            m.tipe,
            m.file,
            m.link,
            m.waktu_upload
        FROM materi m
        INNER JOIN kelas k
            ON m.id_kelas = k.id_kelas
        WHERE m.id_pengajar = :id_pengajar
    ";

    $params = [
        ":id_pengajar" => $id_pengajar
    ];

    if ($id_kelas !== null) {
        $query .= " AND m.id_kelas = :id_kelas";
        $params[":id_kelas"] = $id_kelas;
    }

    $query .= " ORDER BY m.waktu_upload DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $materi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Ubah ID menjadi integer supaya response JSON
     * lebih konsisten.
     */
    foreach ($materi as &$data) {
        $data['id_materi'] = (int) $data['id_materi'];
        $data['id_kelas'] = (int) $data['id_kelas'];
        $data['id_pengajar'] = (int) $data['id_pengajar'];
    }

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "data" => $materi
    ]);

} catch (PDOException $error) {

    // Detail error hanya dicatat di server.
    error_log("Error list materi pengajar: " . $error->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil data materi."
    ]);
}