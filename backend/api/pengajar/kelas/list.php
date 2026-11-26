<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/database.php';
require_once '../../../functions/auth.php';
require_once '../../../functions/tahun_ajaran.php';

try {

    // Pastikan user sudah login sebagai pengajar
    $session = requireRole('pengajar');

    // Ambil ID pengajar dari session
    $id_pengajar = (int) $session['id'];

    // Koneksi database
    $database = new Database();
    $db = $database->getConnection();

    // Filter opsional dari dashboard: ?id_tahun_ajaran=1&semester=ganjil
    // Tanpa parameter (atau nilai tidak valid) = tidak difilter, sama seperti sebelumnya.
    $filterTahun = parseTahunAjaranId($_GET['id_tahun_ajaran'] ?? null);
    $filterSemester = normalizeSemester($_GET['semester'] ?? null);

    $conditions = ['kp.id_pengajar = :id_pengajar'];
    $params = [':id_pengajar' => $id_pengajar];

    if ($filterTahun !== null) {
        $conditions[] = 'k.id_tahun_ajaran = :id_tahun_ajaran';
        $params[':id_tahun_ajaran'] = $filterTahun;
    }

    if ($filterSemester !== null) {
        $conditions[] = 'k.semester = :semester';
        $params[':semester'] = $filterSemester;
    }

    // Ambil kelas yang diajar oleh pengajar yang sedang login
    $query = "
        SELECT 
            k.id_kelas,
            k.nama_kelas,
            k.id_tahun_ajaran,
            ta.nama_tahun_ajaran,
            k.semester,
            COUNT(DISTINCT ks.id_siswa) AS jumlah_siswa
        FROM kelas k
        INNER JOIN kelas_pengajar kp
            ON k.id_kelas = kp.id_kelas
        LEFT JOIN kelas_siswa ks
            ON k.id_kelas = ks.id_kelas
        LEFT JOIN tahun_ajaran ta
            ON ta.id_tahun_ajaran = k.id_tahun_ajaran
        WHERE " . implode(' AND ', $conditions) . "
        GROUP BY 
            k.id_kelas,
            k.nama_kelas,
            k.id_tahun_ajaran,
            ta.nama_tahun_ajaran,
            k.semester
        ORDER BY k.nama_kelas ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $kelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pastikan angka bertipe integer
    foreach ($kelas as &$data) {
        $data['id_kelas'] = (int) $data['id_kelas'];
        $data['jumlah_siswa'] = (int) $data['jumlah_siswa'];
        $data['id_tahun_ajaran'] = $data['id_tahun_ajaran'] === null ? null : (int) $data['id_tahun_ajaran'];
    }
    unset($data);
    echo json_encode([
        'success' => true,
        'data' => $kelas
    ]);

} catch (PDOException $e) {

    error_log('Error kelas pengajar: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengambil data kelas.'
    ]);
}