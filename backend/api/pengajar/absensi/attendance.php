<?php
/*
================================================================================
LOKASI FILE : backend/api/pengajar/absensi/attendance.php
GUNA FILE   : Daftar seluruh siswa satu sesi absensi beserta status kehadirannya,
              untuk pengajar. Dipakai bagian "Kehadiran Siswa" di halaman kelas pengajar
              (tampilan ceklis hadir/izin/alpa dan pengisian manual).
              Method : GET
              Query  : ?id_absensi=1 (wajib)
              Role   : pengajar (hanya sesi yang ia buka)
              Respons: data = {
                  absensi  : { id_absensi, id_kelas, nama_kelas, tanggal, waktu_buka,
                               waktu_tutup, status, mode },
                  statistik: { jumlah_siswa, hadir, izin, sakit, alpa, belum_absen },
                  siswa    : [ { id_siswa, kode_siswa, nama, id_kehadiran, waktu_absen,
                                 status_kehadiran, catatan_siswa, catatan_pengajar } ]
              }
              - mode             : 'siswa' atau 'manual' (dikunci saat sesi dibuat)
              - status_kehadiran : null bila belum dicatat ("Belum diisi")
              - catatan_siswa    : alasan izin yang ditulis siswa sendiri (mode siswa)
              - catatan_pengajar : catatan yang ditulis pengajar
              Perubahan: menambah mode, catatan_siswa, dan catatan_pengajar ke respons.
              Menyimpan perubahan status dilakukan lewat set_status.php.
================================================================================
*/

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "../../../config/database.php";
require_once "../../../functions/auth.php";

// Hanya pengajar yang boleh mengakses endpoint ini.
$session = requireRole('pengajar');

$id_pengajar = (int) $session['id'];

if (!isset($_GET['id_absensi'])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "ID absensi wajib diisi.",
        "data" => null
    ]);

    exit;
}

$attendanceId = filter_var(
    $_GET['id_absensi'],
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($attendanceId === false) {
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
            a.status,
            a.mode
        FROM absensi a
        INNER JOIN kelas k
            ON a.id_kelas = k.id_kelas
        WHERE a.id_absensi = :id_absensi
          AND a.id_pengajar = :id_pengajar
        LIMIT 1
    ";

    $stmtAbsensi = $db->prepare($queryAbsensi);

    $stmtAbsensi->execute([
        ":id_absensi" => $attendanceId,
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
            kh.status AS status_kehadiran,
            kh.catatan_siswa,
            kh.catatan_pengajar

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
        ":id_absensi" => $attendanceId,
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
                "status" => $absensi['status'],
                "mode" => $absensi['mode']
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
}