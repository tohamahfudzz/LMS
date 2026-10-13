
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "../../config/database.php";
require_once "../../functions/auth.php";

// Pastikan pengguna sudah login sebagai siswa.
$session = requireRole("siswa");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan.",
        "data" => null
    ]);

    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $idSiswa = (int) $session["id"];

    // Menghitung jumlah kelas yang diikuti siswa.
    $queryKelas = "
        SELECT COUNT(DISTINCT id_kelas)
        FROM kelas_siswa
        WHERE id_siswa = :id_siswa
    ";

    $stmtKelas = $db->prepare($queryKelas);
    $stmtKelas->execute([
        ":id_siswa" => $idSiswa
    ]);

    $totalKelas = (int) $stmtKelas->fetchColumn();

    // Menghitung jumlah materi pada kelas yang diikuti siswa.
    $queryMateri = "
        SELECT COUNT(*)
        FROM materi m
        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = m.id_kelas
        WHERE ks.id_siswa = :id_siswa
    ";

    $stmtMateri = $db->prepare($queryMateri);
    $stmtMateri->execute([
        ":id_siswa" => $idSiswa
    ]);

    $totalMateri = (int) $stmtMateri->fetchColumn();

    // Menghitung jumlah tugas pada kelas yang diikuti siswa.
    $queryTugas = "
        SELECT COUNT(*)
        FROM tugas t
        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = t.id_kelas
        WHERE ks.id_siswa = :id_siswa
    ";

    $stmtTugas = $db->prepare($queryTugas);
    $stmtTugas->execute([
        ":id_siswa" => $idSiswa
    ]);

    $totalTugas = (int) $stmtTugas->fetchColumn();

    // Menghitung jumlah kehadiran siswa.
    $queryKehadiran = "
        SELECT COUNT(*)
        FROM kehadiran
        WHERE id_siswa = :id_siswa
    ";

    $stmtKehadiran = $db->prepare($queryKehadiran);
    $stmtKehadiran->execute([
        ":id_siswa" => $idSiswa
    ]);

    $totalKehadiran = (int) $stmtKehadiran->fetchColumn();

    // Menghitung jumlah tugas yang sudah dikumpulkan.
    $queryPengumpulan = "
        SELECT COUNT(DISTINCT pt.id_tugas)
        FROM pengumpulan_tugas pt
        INNER JOIN tugas t
            ON t.id_tugas = pt.id_tugas
        INNER JOIN kelas_siswa ks
            ON ks.id_kelas = t.id_kelas
        WHERE pt.id_siswa = :id_siswa
          AND ks.id_siswa = :id_siswa_kelas
    ";

    $stmtPengumpulan = $db->prepare($queryPengumpulan);
    $stmtPengumpulan->execute([
        ":id_siswa" => $idSiswa,
        ":id_siswa_kelas" => $idSiswa
    ]);

    $totalDikumpulkan = (int) $stmtPengumpulan->fetchColumn();

    http_response_code(200);

    echo json_encode([
        "success" => true,
        "message" => "Dashboard siswa berhasil diambil.",
        "data" => [
            "nama_siswa" => $session["nama"] ?? "",
            "total_kelas" => $totalKelas,
            "total_materi" => $totalMateri,
            "total_tugas" => $totalTugas,
            "tugas_dikumpulkan" => $totalDikumpulkan,
            "total_kehadiran" => $totalKehadiran
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $error) {
    error_log(
        "Gagal mengambil dashboard siswa: " .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server.",
        "data" => null
    ], JSON_UNESCAPED_UNICODE);
}
