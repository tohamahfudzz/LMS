<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya pengajar yang dapat mengakses endpoint ini.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// Validasi ID tugas.
if (!isset($_GET['id_tugas']) || !is_numeric($_GET['id_tugas'])) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID tugas wajib diisi.",
        "data" => null
    ]);

    exit;
}

$id_tugas = (int) $_GET['id_tugas'];

if ($id_tugas <= 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID tugas tidak valid.",
        "data" => null
    ]);

    exit;
}

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Pastikan tugas memang milik pengajar
     * yang sedang login.
     */
    $queryTugas = "
        SELECT
            t.id_tugas,
            t.id_kelas,
            k.nama_kelas,
            t.judul,
            t.deadline,
            t.status
        FROM tugas t
        INNER JOIN kelas k
            ON t.id_kelas = k.id_kelas
        WHERE t.id_tugas = :id_tugas
          AND t.id_pengajar = :id_pengajar
        LIMIT 1
    ";

    $stmtTugas = $db->prepare($queryTugas);

    $stmtTugas->execute([
        ":id_tugas" => $id_tugas,
        ":id_pengajar" => $id_pengajar
    ]);

    $tugas = $stmtTugas->fetch(PDO::FETCH_ASSOC);

    if (!$tugas) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Tugas tidak ditemukan atau Anda tidak memiliki akses.",
            "data" => null
        ]);

        exit;
    }

    /*
     * Ambil seluruh siswa dari kelas tugas.
     *
     * LEFT JOIN digunakan supaya siswa yang BELUM
     * mengumpulkan tugas tetap muncul.
     */
    $querySiswa = "
        SELECT
            s.id_siswa,
            s.kode_siswa,
            s.nama,

            pt.id_pengumpulan,
            pt.file,
            pt.teks,
            pt.waktu_upload,
            pt.waktu_pengumpulan

        FROM kelas_siswa ks

        INNER JOIN siswa s
            ON ks.id_siswa = s.id_siswa

        LEFT JOIN pengumpulan_tugas pt
            ON pt.id_siswa = s.id_siswa
            AND pt.id_tugas = :id_tugas

        WHERE ks.id_kelas = :id_kelas

        ORDER BY s.nama ASC
    ";

    $stmtSiswa = $db->prepare($querySiswa);

    $stmtSiswa->execute([
        ":id_tugas" => $id_tugas,
        ":id_kelas" => (int) $tugas['id_kelas']
    ]);

    $siswa = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    $jumlahSiswa = count($siswa);
    $jumlahSudahMengumpulkan = 0;
    $jumlahBelumMengumpulkan = 0;

    foreach ($siswa as &$data) {

        $data['id_siswa'] = (int) $data['id_siswa'];

        if (!empty($data['id_pengumpulan'])) {

            $data['id_pengumpulan'] = (int) $data['id_pengumpulan'];

            $data['status_pengumpulan'] = "sudah";

            $jumlahSudahMengumpulkan++;

        } else {

            $data['id_pengumpulan'] = null;

            $data['status_pengumpulan'] = "belum";

            $jumlahBelumMengumpulkan++;
        }
    }

    /*
     * Response.
     */
    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Data pengumpulan tugas berhasil diambil.",

        "data" => [
            "tugas" => [
                "id_tugas" => (int) $tugas['id_tugas'],
                "id_kelas" => (int) $tugas['id_kelas'],
                "nama_kelas" => $tugas['nama_kelas'],
                "judul" => $tugas['judul'],
                "deadline" => $tugas['deadline'],
                "status" => $tugas['status']
            ],

            "statistik" => [
                "jumlah_siswa" => $jumlahSiswa,
                "sudah_mengumpulkan" => $jumlahSudahMengumpulkan,
                "belum_mengumpulkan" => $jumlahBelumMengumpulkan
            ],

            "siswa" => $siswa
        ]
    ]);

} catch (PDOException $error) {

    error_log(
        "Error submissions tugas pengajar: " .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil data pengumpulan tugas.",
        "data" => null
    ]);

    exit;
}