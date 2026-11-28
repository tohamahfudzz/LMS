<?php
/*
================================================================================
LOKASI FILE : backend/api/kehadiran/hadir.php
GUNA FILE   : Siswa mengisi kehadirannya sendiri pada sesi absensi yang dibuka pengajar.
              Method : POST
              Body   : { "id_absensi": 1, "status": "hadir", "catatan": "..." }
                       - id_absensi : wajib
                       - status     : 'hadir' atau 'izin'. Opsional, default 'hadir'
                                      (kompatibel dengan halaman lama yang hanya mengirim
                                      id_absensi). 'alpa' hanya bisa dicatat pengajar.
                       - catatan    : alasan izin. Wajib bila status 'izin' (maks 255
                                      karakter), diabaikan bila status 'hadir'.
                                      Disimpan di kehadiran.catatan_siswa.
              Role   : siswa (id siswa diambil dari session, bukan dari input)
              Syarat : sesi berstatus 'terbuka', bermode 'siswa', waktu_buka sudah tercapai,
                       waktu_tutup belum lewat (sesi yang lewat waktu_tutup otomatis tertutup
                       lewat functions/absensi.php), dan siswa terdaftar di kelas sesi tersebut.
                       Satu siswa hanya bisa mengisi satu kali per sesi; koreksi sesudahnya
                       dilakukan pengajar lewat pengajar/absensi/set_status.php.
              Perubahan: menolak sesi mode 'manual', menerima status hadir/izin beserta
                         catatan alasan izin.
================================================================================
*/
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../../config/database.php";
require_once "../../functions/auth.php";
require_once "../../functions/absensi.php";

$session = requireRole('siswa');

$database = new Database();
$db = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id_absensi)) {
    try {
        // ID siswa diambil dari session, bukan dari input pengguna,
        // agar siswa tidak dapat mengisi absensi atas nama siswa lain.
        $idSiswa = $session['id'];

        // Validasi status dan catatan lebih dulu, sebelum menyentuh database.
        $statusInput = $data->status ?? 'hadir';

        if (!is_string($statusInput)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Status kehadiran tidak valid."]);
            exit;
        }

        $status = strtolower(trim($statusInput));

        if (!in_array($status, ABSENSI_STATUS_SISWA, true)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Status kehadiran tidak valid. Pilih hadir atau izin."]);
            exit;
        }

        $catatan = null;

        if ($status === 'izin') {
            $catatanInput = $data->catatan ?? '';

            if (!is_string($catatanInput)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Catatan tidak valid."]);
                exit;
            }

            $catatan = trim($catatanInput);

            if ($catatan === '') {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Alasan izin wajib diisi."]);
                exit;
            }

            if (mb_strlen($catatan) > ABSENSI_CATATAN_MAKS) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Alasan izin terlalu panjang. Maksimal " . ABSENSI_CATATAN_MAKS . " karakter."
                ]);
                exit;
            }
        }

        // Tutup dulu sesi yang sudah lewat waktu_tutup agar siswa tidak bisa absen terlambat.
        closeExpiredAbsensi($db);

        $queryAbsen = "SELECT id_kelas, status, mode, waktu_buka, (waktu_buka > NOW()) AS belum_dibuka
                       FROM absensi WHERE id_absensi = :id_absensi LIMIT 1";
        $stmtAbsen = $db->prepare($queryAbsen);
        $stmtAbsen->execute([":id_absensi" => $data->id_absensi]);
        $absensi = $stmtAbsen->fetch(PDO::FETCH_ASSOC);

        if (!$absensi) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Sesi absensi tidak ditemukan."]);
            exit;
        }

        // Sesi mode manual dicatat sepenuhnya oleh pengajar.
        if ($absensi['mode'] !== 'siswa') {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Presensi ditolak. Sesi ini diisi oleh pengajar, bukan oleh siswa."]);
            exit;
        }

        if ($absensi['status'] !== 'terbuka') {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Presensi ditolak. Sesi absensi sudah ditutup oleh pengajar."]);
            exit;
        }

        // Sesi yang dijadwalkan untuk jam tertentu belum bisa diisi sebelum waktunya.
        if ((int) $absensi['belum_dibuka'] === 1) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Presensi belum dibuka. Sesi dimulai pukul " . date('H:i', strtotime($absensi['waktu_buka'])) . "."
            ]);
            exit;
        }

        $querySiswa = "SELECT * FROM kelas_siswa WHERE id_kelas = :id_kelas AND id_siswa = :id_siswa LIMIT 1";
        $stmtSiswa = $db->prepare($querySiswa);
        $stmtSiswa->execute([":id_kelas" => $absensi['id_kelas'], ":id_siswa" => $idSiswa]);

        if ($stmtSiswa->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Akses ditolak. Siswa tidak terdaftar di kelas sesi absensi ini."]);
            exit;
        }

        $queryHadir = "INSERT INTO kehadiran (id_absensi, id_siswa, waktu_absen, status, catatan_siswa)
                       VALUES (:id_absensi, :id_siswa, NOW(), :status, :catatan_siswa)";
        $stmtHadir = $db->prepare($queryHadir);
        $executed = $stmtHadir->execute([
            ":id_absensi" => $data->id_absensi,
            ":id_siswa" => $idSiswa,
            ":status" => $status,
            ":catatan_siswa" => $catatan
        ]);

        if ($executed) {
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Presensi berhasil dicatat dengan status '" . $status . "'."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "message" => "Gagal melakukan presensi."]);
        }

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Presensi ditolak. Kehadiran Anda pada sesi ini sudah tercatat."]);
        } else {
            error_log("[LMS][kehadiran/hadir] " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Terjadi kesalahan pada server."]);
        }
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID absensi wajib diisi."]);
}