<?php

// POST /api/percobaan_kuis/submit.php
// Role: siswa. Mengumpulkan seluruh jawaban sekaligus (tombol "Kumpulkan Jawaban").
// Body JSON: id_percobaan, jawaban: [ { "id_soal": 1, "jawaban": "B" }, ... ]
//   - pilihan ganda: "A" | "B" | "C" | "D"
//   - isian: teks bebas (maks. 5000 karakter)
//   - soal yang tidak dikirim atau dikosongkan dihitung tidak dijawab.
// Nilai, status, dan kunci TIDAK diterima dari browser; semuanya dihitung backend.
//
// Pengiriman ganda aman: baris percobaan dikunci (FOR UPDATE), sehingga permintaan kedua
// menunggu lalu melihat status 'selesai' dan ditolak, tanpa menggandakan jawaban.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('siswa');
$idSiswa = (int) $session['id'];

$data = readJsonBody();

$idPercobaan = parsePositiveInt($data['id_percobaan'] ?? null);
$jawabanInput = $data['jawaban'] ?? [];

if ($idPercobaan === null) {
    sendError("ID percobaan tidak valid.", 400);
}

if (!is_array($jawabanInput) || count($jawabanInput) > KUIS_MAX_SOAL) {
    sendError("Format jawaban tidak valid.", 400);
}

// Validasi bentuk data. Kecocokan dengan tipe soal diperiksa nanti setelah soal dimuat.
$jawabanMap = [];

foreach ($jawabanInput as $item) {
    if (!is_array($item)) {
        sendError("Format jawaban tidak valid.", 400);
    }

    $idSoal = parsePositiveInt($item['id_soal'] ?? null);

    if ($idSoal === null) {
        sendError("ID soal tidak valid.", 400);
    }

    if (array_key_exists($idSoal, $jawabanMap)) {
        sendError("Jawaban untuk satu soal dikirim lebih dari sekali.", 400);
    }

    $teks = $item['jawaban'] ?? null;

    if ($teks !== null && !is_string($teks)) {
        sendError("Jawaban harus berupa teks.", 400);
    }

    $teks = $teks === null ? null : trim($teks);

    if ($teks !== null && mb_strlen($teks) > 5000) {
        sendError("Jawaban maksimal 5000 karakter.", 400);
    }

    $jawabanMap[$idSoal] = ($teks === '') ? null : $teks;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Kunci hanya baris percobaan milik siswa yang login (id_siswa dari session).
    $statement = $db->prepare(
        "SELECT id_percobaan, id_kuis, status
         FROM percobaan_kuis
         WHERE id_percobaan = :id_percobaan AND id_siswa = :id_siswa
         LIMIT 1 FOR UPDATE"
    );
    $statement->execute([":id_percobaan" => $idPercobaan, ":id_siswa" => $idSiswa]);
    $percobaan = $statement->fetch(PDO::FETCH_ASSOC);

    // Percobaan milik siswa lain dijawab sama dengan "tidak ada".
    if ($percobaan === false) {
        rollbackAndSendError($db, "Percobaan tidak ditemukan.", 404);
    }

    if ($percobaan['status'] !== PERCOBAAN_STATUS_BERLANGSUNG) {
        rollbackAndSendError($db, "Jawaban untuk percobaan ini sudah dikumpulkan.", 409);
    }

    $idKuis = (int) $percobaan['id_kuis'];

    // Siswa harus masih terdaftar di kelas kuis ini.
    $statement = $db->prepare(
        "SELECT k.status
         FROM kuis k
         INNER JOIN kelas_siswa ks ON ks.id_kelas = k.id_kelas AND ks.id_siswa = :id_siswa
         WHERE k.id_kuis = :id_kuis
         LIMIT 1"
    );
    $statement->execute([":id_siswa" => $idSiswa, ":id_kuis" => $idKuis]);
    $statusKuis = $statement->fetchColumn();

    if ($statusKuis === false) {
        rollbackAndSendError($db, "Kamu tidak berhak mengakses kuis ini.", 403);
    }

    $soalMap = fetchSoalForGrading($db, $idKuis);
    $timing = getAttemptTiming($db, $idPercobaan);

    if ($timing === null) {
        throw new RuntimeException("Batas waktu percobaan tidak dapat dihitung: " . $idPercobaan);
    }

    // Batas waktu ditegakkan di sini dengan jam database. Jawaban yang datang terlambat
    // ditolak dan percobaan ditutup (tidak ada jawaban yang tersimpan, sehingga bernilai 0).
    if ($timing['sudah_habis']) {
        finalizeAttempt($db, $idPercobaan, $soalMap, []);
        $db->commit();
        sendError("Waktu pengerjaan sudah habis. Percobaan ditutup tanpa jawaban yang tersimpan.", 403);
    }

    if ($statusKuis !== KUIS_STATUS_TERBIT) {
        rollbackAndSendError($db, "Kuis sudah ditutup.", 403);
    }

    // Setiap id_soal harus milik kuis ini dan jawaban pilihan ganda harus pilihan yang sah.
    foreach ($jawabanMap as $idSoal => $teks) {
        if (!isset($soalMap[$idSoal])) {
            rollbackAndSendError($db, "Ada soal yang tidak termasuk kuis ini.", 400);
        }

        if ($teks === null || $soalMap[$idSoal]['tipe_soal'] !== SOAL_TIPE_PILIHAN_GANDA) {
            continue;
        }

        $pilihan = strtoupper($teks);

        if (!in_array($pilihan, ['A', 'B', 'C', 'D'], true)) {
            rollbackAndSendError($db, "Jawaban pilihan ganda harus A, B, C, atau D.", 400);
        }

        $jawabanMap[$idSoal] = $pilihan;
    }

    $hasil = finalizeAttempt($db, $idPercobaan, $soalMap, $jawabanMap);
    $db->commit();

    // Nilai hanya ditampilkan jika seluruh penilaian sudah selesai. Kunci jawaban,
    // nilai per soal, dan nilai otomatis sementara tidak dikirim ke siswa.
    sendSuccess(
        $hasil['menunggu_penilaian']
            ? "Jawaban berhasil dikumpulkan. Nilai akan tersedia setelah penilaian selesai."
            : "Jawaban berhasil dikumpulkan.",
        [
            "menunggu_penilaian" => $hasil['menunggu_penilaian'],
            "nilai_total" => $hasil['nilai_total'],
        ]
    );
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'percobaan_kuis/submit');
}