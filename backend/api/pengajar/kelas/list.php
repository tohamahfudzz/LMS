<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/database.php';
require_once '../../../functions/auth.php';

try {

    // Pastikan user sudah login sebagai pengajar
    $session = requireRole('pengajar');

    // Ambil ID pengajar dari session
    $id_pengajar = (int) $session['id'];

    // Koneksi database
    $database = new Database();
    $db = $database->getConnection();

    // Ambil semua kelas yang diajar oleh pengajar yang sedang login
    $query = "
        SELECT 
            k.id_kelas,
            k.nama_kelas,
            COUNT(DISTINCT ks.id_siswa) AS jumlah_siswa
        FROM kelas k
        INNER JOIN kelas_pengajar kp
            ON k.id_kelas = kp.id_kelas
        LEFT JOIN kelas_siswa ks
            ON k.id_kelas = ks.id_kelas
        WHERE kp.id_pengajar = :id_pengajar
        GROUP BY 
            k.id_kelas,
            k.nama_kelas
        ORDER BY k.nama_kelas ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':id_pengajar', $id_pengajar, PDO::PARAM_INT);
    $stmt->execute();

    $kelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pastikan jumlah_siswa berupa integer
    foreach ($kelas as &$data) {
        $data['id_kelas'] = (int) $data['id_kelas'];
        $data['jumlah_siswa'] = (int) $data['jumlah_siswa'];
    }

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