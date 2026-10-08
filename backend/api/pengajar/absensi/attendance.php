<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya pengajar yang boleh mengakses endpoint ini.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

// ID absensi wajib diberikan.
// Contoh:
// attendance.php?id_absensi=1
if (
    !isset($_GET['id_absensi']) ||
    !is_numeric($_GET['id_absensi'])
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID absensi wajib diisi.",
        "data" => null
    ]);

    exit;
}

$id_absensi = (int) $_GET['id_absensi'];

if ($id_absensi <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID absensi tidak valid.",
        "data" => null
    ]);

    exit;
}

try {

    $database = new Database();
    $db = $database->getConnection();

    /*
     * Ambil informasi sesi absensi.
     *
     * Sekaligus memastikan sesi tersebut
     * memang milik pengajar yang login.
     */
    $queryAbsensi = "
        SELECT
            a.id_absensi,
            a.id_kelas,
            k.nama_kelas,
            a.id_pengajar,
            a.tanggal,
            a.waktu_buka,
            a.waktu_tutup,
            a.status
        FROM absensi a
        INNER JOIN kelas k
            ON a.id_kelas = k.id_kelas
        WHERE a.id_absensi = :id_absensi
          AND a.id_pengajar = :id_pengajar
        LIMIT 1
    ";

    $stmtAbsensi = $db->prepare($queryAbsensi);

    $stmtAbsensi->execute([
        ":id_absensi" => $id_absensi,
        ":id_pengajar" => $id_pengajar
    ]);

    $absensi = $stmtAbsensi->fetch(PDO::FETCH_ASSOC);

    /*
     * Kalau tidak ditemukan berarti:
     * - ID absensi salah, atau
     * - absensi milik pengajar lain.
     */
    if (!$absensi) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Data absensi tidak ditemukan atau Anda tidak memiliki akses.",
            "data" => null
        ]);

        exit;
    }

    /*
     * Ambil seluruh siswa dari kelas tersebut.
     *
     * LEFT JOIN digunakan agar siswa yang belum
     * melakukan absensi tetap muncul.
     */
    $querySiswa = "
        SELECT
            s.id_siswa,
            s.kode_siswa,
            s.nama,

            kh.id_kehadiran,
            kh.waktu_absen,
            kh.status AS status_kehadiran

        FROM kelas_siswa ks

        INNER JOIN siswa s
            ON ks.id_siswa = s.id_siswa

        LEFT JOIN kehadiran kh
            ON kh.id_siswa = s.id_siswa
            AND kh.id_absensi = :id_absensi

        WHERE ks.id_kelas = :id_kelas

        ORDER BY s.nama ASC
    ";

    $stmtSiswa = $db->prepare($querySiswa);

    $stmtSiswa->execute([
        ":id_absensi" => $id_absensi,
        ":id_kelas" => (int) $absensi['id_kelas']
    ]);

    $siswa = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Statistik kehadiran.
     */
    $jumlahSiswa = count($siswa);

    $hadir = 0;
    $izin = 0;
    $sakit = 0;
    $alpa = 0;
    $belumAbsen = 0;

    foreach ($siswa as &$data) {

        $data['id_siswa'] = (int) $data['id_siswa'];

        if (!empty($data['id_kehadiran'])) {

            $data['id_kehadiran'] = (int) $data['id_kehadiran'];

            $status = strtolower(
                trim($data['status_kehadiran'])
            );

            switch ($status) {

                case 'hadir':
                    $hadir++;
                    break;

                case 'izin':
                    $izin++;
                    break;

                case 'sakit':
                    $sakit++;
                    break;

                case 'alpa':
                    $alpa++;
                    break;
            }

        } else {

            $data['id_kehadiran'] = null;
            $data['status_kehadiran'] = null;

            $belumAbsen++;
        }
    }

    /*
     * Response API.
     */
    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Data kehadiran berhasil diambil.",

        "data" => [

            "absensi" => [
                "id_absensi" => (int) $absensi['id_absensi'],
                "id_kelas" => (int) $absensi['id_kelas'],
                "nama_kelas" => $absensi['nama_kelas'],
                "tanggal" => $absensi['tanggal'],
                "waktu_buka" => $absensi['waktu_buka'],
                "waktu_tutup" => $absensi['waktu_tutup'],
                "status" => $absensi['status']
            ],

            "statistik" => [
                "jumlah_siswa" => $jumlahSiswa,
                "hadir" => $hadir,
                "izin" => $izin,
                "sakit" => $sakit,
                "alpa" => $alpa,
                "belum_absen" => $belumAbsen
            ],

            "siswa" => $siswa
        ]
    ]);

} catch (PDOException $error) {

    /*
     * Detail error database tidak dikirim
     * kepada client.
     */
    error_log(
        "Error attendance pengajar: " .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat mengambil data kehadiran.",
        "data" => null
    ]);

    exit;
}9