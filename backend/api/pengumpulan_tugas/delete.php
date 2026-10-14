
<?php
/**
 * Nama file : delete.php
 * Lokasi    : backend/api/pengumpulan_tugas/delete.php
 *
 * Fungsi:
 * Menghapus file lampiran dari pengumpulan tugas milik siswa
 * tanpa menghapus teks jawaban maupun data pengumpulannya.
 *
 * Metode: POST
 * Input : id_tugas (JSON)
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Metode request tidak diizinkan."
    ]);
    exit;
}

require_once "../../config/database.php";
require_once "../../config/auth.php";

/**
 * Mengirim respons JSON lalu menghentikan proses.
 */
function responseJson(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    // Pastikan hanya siswa yang dapat menggunakan endpoint ini.
    requireRole('siswa');

    $idSiswa = $_SESSION['id'] ?? null;

    if (!$idSiswa) {
        responseJson(401, [
            "success" => false,
            "message" => "Sesi siswa tidak ditemukan. Silakan login kembali."
        ]);
    }

    // Membaca ID tugas dari request JSON.
    $data = json_decode(file_get_contents("php://input"));

    if (
        !isset($data->id_tugas) ||
        !is_numeric($data->id_tugas)
    ) {
        responseJson(400, [
            "success" => false,
            "message" => "ID tugas wajib diisi."
        ]);
    }

    $idTugas = (int) $data->id_tugas;

    if ($idTugas <= 0) {
        responseJson(400, [
            "success" => false,
            "message" => "ID tugas tidak valid."
        ]);
    }

    $database = new Database();
    $db = $database->getConnection();

    $db->beginTransaction();

    // Memeriksa status, waktu mulai, dan deadline tugas.
    $stmtTugas = $db->prepare(
        "SELECT status, deadline, waktu_mulai
         FROM tugas
         WHERE id_tugas = :id_tugas
         LIMIT 1
         FOR UPDATE"
    );

    $stmtTugas->execute([
        ":id_tugas" => $idTugas
    ]);

    $tugas = $stmtTugas->fetch(PDO::FETCH_ASSOC);

    if (!$tugas) {
        $db->rollBack();

        responseJson(404, [
            "success" => false,
            "message" => "Tugas tidak ditemukan."
        ]);
    }

    // Siswa tidak boleh mengubah pengumpulan sebelum tugas dimulai.
    if (
        $tugas['waktu_mulai'] !== null &&
        strtotime($tugas['waktu_mulai']) > time()
    ) {
        $db->rollBack();

        responseJson(403, [
            "success" => false,
            "message" => "Tugas belum dimulai."
        ]);
    }

    // Tugas harus masih terbuka.
    if ($tugas['status'] !== 'terbuka') {
        $db->rollBack();

        responseJson(403, [
            "success" => false,
            "message" => "Tugas sudah ditutup."
        ]);
    }

    // Deadline wajib valid dan belum lewat.
    if (
        empty($tugas['deadline']) ||
        strtotime($tugas['deadline']) < time()
    ) {
        $db->rollBack();

        responseJson(403, [
            "success" => false,
            "message" => "Deadline tugas sudah lewat."
        ]);
    }

    // Mengambil pengumpulan milik siswa yang sedang login.
    // Primary key tabel adalah id_pengumpulan.
    $stmtPengumpulan = $db->prepare(
        "SELECT id_pengumpulan, file
         FROM pengumpulan_tugas
         WHERE id_tugas = :id_tugas
           AND id_siswa = :id_siswa
         LIMIT 1
         FOR UPDATE"
    );

    $stmtPengumpulan->execute([
        ":id_tugas" => $idTugas,
        ":id_siswa" => $idSiswa
    ]);

    $pengumpulan = $stmtPengumpulan->fetch(PDO::FETCH_ASSOC);

    if (!$pengumpulan) {
        $db->rollBack();

        responseJson(404, [
            "success" => false,
            "message" => "Data pengumpulan tugas tidak ditemukan."
        ]);
    }

    if (empty($pengumpulan['file'])) {
        $db->rollBack();

        responseJson(400, [
            "success" => false,
            "message" => "Pengumpulan ini tidak memiliki file lampiran."
        ]);
    }

    $fileLama = $pengumpulan['file'];
    $idPengumpulan = $pengumpulan['id_pengumpulan'];

    // Melepaskan file dari data pengumpulan.
    // Baris pengumpulan dan teks jawaban tetap dipertahankan.
    $stmtUpdate = $db->prepare(
        "UPDATE pengumpulan_tugas
         SET file = NULL
         WHERE id_pengumpulan = :id_pengumpulan
           AND id_siswa = :id_siswa"
    );

    $stmtUpdate->execute([
        ":id_pengumpulan" => $idPengumpulan,
        ":id_siswa" => $idSiswa
    ]);

    // Simpan perubahan database terlebih dahulu.
    $db->commit();

    // Periksa apakah file masih digunakan oleh data pengumpulan lain.
    $stmtPemakaian = $db->prepare(
        "SELECT COUNT(*)
         FROM pengumpulan_tugas
         WHERE file = :file"
    );

    $stmtPemakaian->execute([
        ":file" => $fileLama
    ]);

    $jumlahPemakaian = (int) $stmtPemakaian->fetchColumn();

    if ($jumlahPemakaian === 0) {
        $folderUploads = realpath(__DIR__ . "/../../uploads");

        $folderPengumpulan = realpath(
            __DIR__ . "/../../uploads/pengumpulan_tugas"
        );

        // Pastikan folder pengumpulan berada di dalam folder uploads.
        if (
            $folderUploads !== false &&
            $folderPengumpulan !== false &&
            strpos(
                $folderPengumpulan,
                $folderUploads . DIRECTORY_SEPARATOR
            ) === 0
        ) {
            // Path dalam database diharapkan seperti:
            // pengumpulan_tugas/nama_file.pdf
            $prefix = "pengumpulan_tugas/";

            if (strpos($fileLama, $prefix) === 0) {
                $namaFile = substr($fileLama, strlen($prefix));

                // Tolak path yang mengarah ke folder lain.
                if (
                    $namaFile !== "" &&
                    basename($namaFile) === $namaFile
                ) {
                    $pathFile = $folderPengumpulan
                        . DIRECTORY_SEPARATOR
                        . $namaFile;

                    $realPathFile = realpath($pathFile);

                    // Pastikan file benar-benar berada di folder yang diizinkan.
                    if (
                        $realPathFile !== false &&
                        strpos(
                            $realPathFile,
                            $folderPengumpulan . DIRECTORY_SEPARATOR
                        ) === 0 &&
                        is_file($realPathFile)
                    ) {
                        if (!unlink($realPathFile)) {
                            error_log(
                                "Gagal menghapus file pengumpulan: "
                                . $realPathFile
                            );
                        }
                    }
                }
            }
        }
    }

    responseJson(200, [
        "success" => true,
        "message" => "File lampiran berhasil dihapus. Teks jawaban tetap tersimpan."
    ]);

} catch (Throwable $e) {
    if (
        isset($db) &&
        $db instanceof PDO &&
        $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log(
        "Error delete pengumpulan tugas: " . $e->getMessage()
    );

    responseJson(500, [
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ]);
}
