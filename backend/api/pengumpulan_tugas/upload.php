
<?php
/*
 * Nama file : upload.php
 * Lokasi    : backend/api/pengumpulan_tugas/upload.php
 *
 * Fungsi:
 * - Menerima pengumpulan tugas dari siswa yang sudah login.
 * - Memvalidasi kelas, waktu mulai, deadline, dan status tugas.
 * - Membuat pengumpulan baru atau mengganti pengumpulan sebelumnya.
 * - Mempertahankan file lama jika siswa tidak mengirim file pengganti.
 * - Menghapus file lama setelah penggantian berhasil jika tidak lagi digunakan.
 *
 * Format request:
 * multipart/form-data
 *
 * Field:
 * - id_tugas : wajib
 * - teks     : opsional
 * - file     : opsional, input file
 *
 * Identitas siswa diambil dari session, bukan dari request.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
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
require_once "../../functions/auth.php";
require_once "../../functions/upload.php";

/**
 * Mengirim response JSON dan menghentikan proses.
 */
function responseJson(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Menghasilkan path absolut file pengumpulan yang aman.
 * Hanya file di dalam uploads/pengumpulan_tugas yang diterima.
 */
function resolveSubmissionFile(?string $relativePath): ?string
{
    if (
        $relativePath === null ||
        $relativePath === '' ||
        strpos($relativePath, '\\') !== false ||
        strpos($relativePath, 'pengumpulan_tugas/') !== 0
    ) {
        return null;
    }

    $namaFile = substr(
        $relativePath,
        strlen('pengumpulan_tugas/')
    );

    if (
        $namaFile === '' ||
        basename($namaFile) !== $namaFile
    ) {
        return null;
    }

    $folderDasar = realpath(
        UPLOAD_BASE_PATH . 'pengumpulan_tugas'
    );

    $pathFile = realpath(
        UPLOAD_BASE_PATH . $relativePath
    );

    if ($folderDasar === false || $pathFile === false) {
        return null;
    }

    $prefix = rtrim(
        $folderDasar,
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR;

    if (strpos($pathFile, $prefix) !== 0) {
        return null;
    }

    return $pathFile;
}

/**
 * Menghapus file jika tidak lagi digunakan oleh pengumpulan lain.
 */
function deleteUnusedSubmissionFile(PDO $db, ?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM pengumpulan_tugas
        WHERE file = :file
    ");

    $stmt->execute([
        ':file' => $relativePath
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $path = resolveSubmissionFile($relativePath);

    if ($path !== null && is_file($path)) {
        if (!@unlink($path)) {
            error_log(
                "LMS: Gagal menghapus file pengumpulan lama."
            );
        }
    }
}

// Pastikan pengguna sudah login sebagai siswa.
requireRole('siswa');

// Sesuaikan jika login.php menggunakan nama session berbeda.
$idSiswa = $_SESSION['id'] ?? null;

if (!is_numeric($idSiswa) || (int) $idSiswa <= 0) {
    responseJson(401, [
        "success" => false,
        "message" => "Session siswa tidak valid. Silakan login kembali."
    ]);
}

$idSiswa = (int) $idSiswa;

// Validasi ID tugas.
$idTugas = filter_var(
    $_POST['id_tugas'] ?? null,
    FILTER_VALIDATE_INT
);

if ($idTugas === false || $idTugas === null || $idTugas <= 0) {
    responseJson(400, [
        "success" => false,
        "message" => "ID tugas tidak valid."
    ]);
}

// Teks boleh tidak dikirim ketika siswa hanya mengganti file.
// Jika tidak dikirim, teks pengumpulan sebelumnya dipertahankan.
$teksDikirim = array_key_exists('teks', $_POST);

if ($teksDikirim && !is_string($_POST['teks'])) {
    responseJson(400, [
        "success" => false,
        "message" => "Format teks pengumpulan tidak valid."
    ]);
}

$teksBaru = $teksDikirim
    ? trim($_POST['teks'])
    : null;

if ($teksBaru === '') {
    $teksBaru = null;
}

$database = new Database();
$db = $database->getConnection();

$transaksiDimulai = false;
$fileBaru = null;
$fileLama = null;

try {
    $db->beginTransaction();
    $transaksiDimulai = true;

    // Ambil dan kunci data tugas selama proses pengumpulan.
    $stmtTugas = $db->prepare("
        SELECT id_kelas, status, deadline, waktu_mulai
        FROM tugas
        WHERE id_tugas = :id_tugas
        LIMIT 1
        FOR UPDATE
    ");

    $stmtTugas->execute([
        ':id_tugas' => $idTugas
    ]);

    $tugas = $stmtTugas->fetch(PDO::FETCH_ASSOC);

    if (!$tugas) {
        $db->rollBack();
        $transaksiDimulai = false;

        responseJson(404, [
            "success" => false,
            "message" => "Tugas tidak ditemukan."
        ]);
    }

    // Pastikan waktu mulai sudah tercapai.
    if (
        $tugas['waktu_mulai'] !== null &&
        strtotime($tugas['waktu_mulai']) > time()
    ) {
        $db->rollBack();
        $transaksiDimulai = false;

        responseJson(400, [
            "success" => false,
            "message" => "Tugas belum dapat dikerjakan."
        ]);
    }

    // Tugas harus masih terbuka.
    if ($tugas['status'] !== 'terbuka') {
        $db->rollBack();
        $transaksiDimulai = false;

        responseJson(400, [
            "success" => false,
            "message" => "Pengumpulan ditolak. Tugas sudah ditutup."
        ]);
    }

    // Deadline wajib valid dan belum terlewat.
    if (
        empty($tugas['deadline']) ||
        strtotime($tugas['deadline']) === false ||
        strtotime($tugas['deadline']) < time()
    ) {
        $db->rollBack();
        $transaksiDimulai = false;

        responseJson(400, [
            "success" => false,
            "message" => "Deadline tugas sudah terlewat atau tidak valid."
        ]);
    }

    // Pastikan siswa terdaftar di kelas tugas.
    $stmtSiswa = $db->prepare("
        SELECT 1
        FROM kelas_siswa
        WHERE id_kelas = :id_kelas
          AND id_siswa = :id_siswa
        LIMIT 1
    ");

    $stmtSiswa->execute([
        ':id_kelas' => $tugas['id_kelas'],
        ':id_siswa' => $idSiswa
    ]);

    if ($stmtSiswa->fetchColumn() === false) {
        $db->rollBack();
        $transaksiDimulai = false;

        responseJson(403, [
            "success" => false,
            "message" => "Kamu tidak terdaftar di kelas tugas ini."
        ]);
    }

    // Ambil pengumpulan sebelumnya.
    $stmtLama = $db->prepare("
        SELECT file, teks
        FROM pengumpulan_tugas
        WHERE id_tugas = :id_tugas
          AND id_siswa = :id_siswa
        LIMIT 1
        FOR UPDATE
    ");

    $stmtLama->execute([
        ':id_tugas' => $idTugas,
        ':id_siswa' => $idSiswa
    ]);

    $pengumpulanLama = $stmtLama->fetch(PDO::FETCH_ASSOC);

    if ($pengumpulanLama) {
        $fileLama = $pengumpulanLama['file'];

        $teksFinal = $teksDikirim
            ? $teksBaru
            : $pengumpulanLama['teks'];
    } else {
        $teksFinal = $teksDikirim ? $teksBaru : null;
    }

    // Proses file baru jika ada.
    $fileBaru = handleFileUpload(
        'file',
        'pengumpulan_tugas',
        [
            'pdf', 'doc', 'docx',
            'ppt', 'pptx', 'xls', 'xlsx',
            'zip', 'jpg', 'jpeg', 'png', 'gif', 'mp4'
        ]
    );

    // Jika tidak ada file baru, pertahankan file sebelumnya.
    $fileFinal = $fileBaru !== null
        ? $fileBaru
        : ($pengumpulanLama['file'] ?? null);

    // Minimal harus ada teks atau file.
    if ($fileFinal === null && $teksFinal === null) {
        $db->rollBack();
        $transaksiDimulai = false;

        responseJson(400, [
            "success" => false,
            "message" => "Pengumpulan harus berisi teks atau file."
        ]);
    }

    // Ganti data lama jika pengumpulan sudah ada.
    if ($pengumpulanLama) {
        $stmtHapus = $db->prepare("
            DELETE FROM pengumpulan_tugas
            WHERE id_tugas = :id_tugas
              AND id_siswa = :id_siswa
        ");

        $stmtHapus->execute([
            ':id_tugas' => $idTugas,
            ':id_siswa' => $idSiswa
        ]);
    }

    // Simpan pengumpulan terbaru.
    $stmtSimpan = $db->prepare("
        INSERT INTO pengumpulan_tugas
            (id_tugas, id_siswa, file, teks,
             waktu_upload, waktu_pengumpulan)
        VALUES
            (:id_tugas, :id_siswa, :file, :teks, NOW(), NOW())
    ");

    $stmtSimpan->execute([
        ':id_tugas' => $idTugas,
        ':id_siswa' => $idSiswa,
        ':file' => $fileFinal,
        ':teks' => $teksFinal
    ]);

    // Commit harus berhasil sebelum file lama dihapus.
    $db->commit();
    $transaksiDimulai = false;

    // Hapus file lama hanya jika file tersebut diganti.
    if (
        $fileBaru !== null &&
        $fileLama !== null &&
        $fileLama !== $fileBaru
    ) {
        deleteUnusedSubmissionFile($db, $fileLama);
    }

    responseJson(
        $pengumpulanLama ? 200 : 201,
        [
            "success" => true,
            "message" => $pengumpulanLama
                ? "Pengumpulan tugas berhasil diperbarui."
                : "Tugas berhasil dikumpulkan."
        ]
    );

} catch (Throwable $e) {
    if ($transaksiDimulai && $db->inTransaction()) {
        $db->rollBack();
    }

    // Bersihkan file baru yang tidak tersimpan di database.
    if ($fileBaru !== null) {
        $pathBaru = resolveSubmissionFile($fileBaru);

        if ($pathBaru !== null && is_file($pathBaru)) {
            @unlink($pathBaru);
        }
    }

    error_log(
        "LMS pengumpulan tugas: " . $e->getMessage()
    );

    responseJson(500, [
        "success" => false,
        "message" => "Terjadi kesalahan saat menyimpan pengumpulan tugas."
    ]);
}
