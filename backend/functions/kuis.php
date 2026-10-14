<?php

// Fungsi bersama untuk fitur kuis: konstanta, validasi input, dan pengecekan kepemilikan.
// Nilai teks di bawah ini adalah kesepakatan tunggal agar tidak ada ejaan ganda.

const KUIS_STATUS_DRAFT = 'draft';
const KUIS_STATUS_TERBIT = 'terbit';
const KUIS_STATUS_TERTUTUP = 'tertutup';

const SOAL_TIPE_PILIHAN_GANDA = 'pilihan_ganda';
const SOAL_TIPE_ISIAN = 'isian';

const PERCOBAAN_STATUS_BERLANGSUNG = 'berlangsung';
const PERCOBAAN_STATUS_SELESAI = 'selesai';

const PENILAIAN_STATUS_MENUNGGU = 'menunggu';
const PENILAIAN_STATUS_SELESAI = 'selesai';

const KUIS_MAX_DURASI_MENIT = 1440;
const KUIS_MAX_SOAL = 200;
const SOAL_MAX_POIN = 9999.99;

// Mengubah input menjadi bilangan bulat positif, atau null jika tidak valid.
function parsePositiveInt($value): ?int
{
    if ($value === null || is_bool($value) || is_array($value)) {
        return null;
    }

    $result = filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

    return $result === false ? null : $result;
}

// Mengambil field string yang sudah di-trim. Null jika tidak ada, bukan string, atau kosong.
function readString(array $data, string $key): ?string
{
    if (!isset($data[$key]) || !is_string($data[$key])) {
        return null;
    }

    $value = trim($data[$key]);

    return $value === '' ? null : $value;
}

// Menormalkan tanggal-waktu ke format MySQL (Y-m-d H:i:s).
// Menerima juga format <input type="datetime-local"> (Y-m-dTH:i).
function normalizeDateTime($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);
        $errors = DateTime::getLastErrors();
        $hasProblem = $errors !== false
            && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

        // Pengecekan error mencegah tanggal mustahil (misalnya 31 Februari) lolos.
        if ($date !== false && !$hasProblem) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

// Membaca field tanggal-waktu opsional. Kosong menghasilkan null,
// sedangkan nilai yang diisi tetapi salah format langsung dijawab error 400.
function readOptionalDateTime(array $data, string $key, string $label): ?string
{
    $raw = $data[$key] ?? null;

    if ($raw === null || $raw === '') {
        return null;
    }

    $value = normalizeDateTime($raw);

    if ($value === null) {
        sendError("Format " . $label . " tidak valid.", 400);
    }

    return $value;
}

// Memvalidasi poin soal: angka > 0, maksimal sesuai DECIMAL(6,2), paling banyak 2 desimal.
function parsePoin($value): ?float
{
    if ($value === null || $value === '' || is_bool($value) || is_array($value) || !is_numeric($value)) {
        return null;
    }

    $poin = (float) $value;

    if ($poin <= 0 || $poin > SOAL_MAX_POIN) {
        return null;
    }

    if (abs($poin * 100 - round($poin * 100)) > 0.0001) {
        return null;
    }

    return round($poin, 2);
}

// Memeriksa apakah pengajar mengajar di kelas tersebut.
function teacherTeachesClass(PDO $db, int $idKelas, int $idPengajar): bool
{
    $statement = $db->prepare(
        "SELECT 1 FROM kelas_pengajar WHERE id_kelas = :id_kelas AND id_pengajar = :id_pengajar LIMIT 1"
    );
    $statement->execute([":id_kelas" => $idKelas, ":id_pengajar" => $idPengajar]);

    return $statement->fetchColumn() !== false;
}

// Mengambil kuis hanya jika milik pengajar yang login.
// Kuis milik pengajar lain dan kuis yang tidak ada sengaja menghasilkan null yang sama,
// supaya keberadaan kuis orang lain tidak bisa ditebak.
function findOwnedKuis(PDO $db, int $idKuis, int $idPengajar, bool $lockRow = false): ?array
{
    $query = "SELECT id_kuis, id_kelas, id_pengajar, judul, durasi_menit, waktu_mulai, waktu_selesai, status
              FROM kuis
              WHERE id_kuis = :id_kuis AND id_pengajar = :id_pengajar
              LIMIT 1";

    // Kunci baris dipakai agar penambahan soal dan penerbitan tidak saling menyalip.
    if ($lockRow) {
        $query .= " FOR UPDATE";
    }

    $statement = $db->prepare($query);
    $statement->execute([":id_kuis" => $idKuis, ":id_pengajar" => $idPengajar]);
    $kuis = $statement->fetch(PDO::FETCH_ASSOC);

    return $kuis === false ? null : $kuis;
}

// Memeriksa apakah sudah ada siswa yang memulai kuis (soal harus dikunci jika sudah ada).
function kuisHasAttempts(PDO $db, int $idKuis): bool
{
    $statement = $db->prepare("SELECT 1 FROM percobaan_kuis WHERE id_kuis = :id_kuis LIMIT 1");
    $statement->execute([":id_kuis" => $idKuis]);

    return $statement->fetchColumn() !== false;
}

// Membatalkan transaksi yang sedang berjalan lalu mengirim respons error.
function rollbackAndSendError(PDO $db, string $message, int $statusCode): void
{
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendError($message, $statusCode);
}

// Mengenali error duplikat unique key MySQL (kode 1062).
function isDuplicateKeyError(PDOException $error): bool
{
    return (int) ($error->errorInfo[1] ?? 0) === 1062;
}

// Mengambil soal untuk siswa. Kolom kunci_jawaban SENGAJA tidak ikut dipilih.
// Urutan: pilihan ganda dulu, lalu isian; di dalam tiap tipe mengikuti kolom urutan.
function fetchSoalForStudent(PDO $db, int $idKuis): array
{
    $statement = $db->prepare(
        "SELECT id_soal, tipe_soal, soal, pilihan_a, pilihan_b, pilihan_c, pilihan_d, poin, urutan
         FROM soal_kuis
         WHERE id_kuis = :id_kuis
         ORDER BY CASE tipe_soal WHEN 'pilihan_ganda' THEN 0 ELSE 1 END, urutan ASC"
    );
    $statement->execute([":id_kuis" => $idKuis]);

    $soalList = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $soal = [
            "id_soal" => (int) $row['id_soal'],
            "tipe_soal" => $row['tipe_soal'],
            "soal" => $row['soal'],
            "poin" => (float) $row['poin'],
            "urutan" => (int) $row['urutan'],
        ];

        if ($row['tipe_soal'] === SOAL_TIPE_PILIHAN_GANDA) {
            $soal["pilihan"] = [
                "A" => $row['pilihan_a'],
                "B" => $row['pilihan_b'],
                "C" => $row['pilihan_c'],
                "D" => $row['pilihan_d'],
            ];
        }

        $soalList[] = $soal;
    }

    return $soalList;
}

// Menghitung batas akhir, sisa waktu, dan status habis waktu sebuah percobaan, seluruhnya memakai jam database
// (NOW()) sehingga konsisten dengan waktu_mulai yang tersimpan dan tidak bergantung jam browser.
// Batas akhir = yang lebih awal antara (waktu mulai percobaan + durasi) dan waktu_selesai kuis.
function getAttemptTiming(PDO $db, int $idPercobaan): ?array
{
    $statement = $db->prepare(
        "SELECT t.batas_akhir,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), t.batas_akhir)) AS sisa_detik,
                (t.batas_akhir <= NOW()) AS sudah_habis
         FROM (
             SELECT LEAST(
                        DATE_ADD(p.waktu_mulai, INTERVAL k.durasi_menit MINUTE),
                        COALESCE(k.waktu_selesai, DATE_ADD(p.waktu_mulai, INTERVAL k.durasi_menit MINUTE))
                    ) AS batas_akhir
             FROM percobaan_kuis p
             INNER JOIN kuis k ON k.id_kuis = p.id_kuis
             WHERE p.id_percobaan = :id_percobaan
         ) AS t"
    );
    $statement->execute([":id_percobaan" => $idPercobaan]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return null;
    }

    return [
        "batas_akhir" => $row['batas_akhir'],
        "sisa_detik" => (int) $row['sisa_detik'],
        "sudah_habis" => (int) $row['sudah_habis'] === 1,
    ];
}

// Mengambil soal beserta kunci dan poin untuk keperluan penilaian di backend.
// JANGAN pernah mengirim hasil fungsi ini ke frontend siswa.
// Hasil berupa array dengan kunci id_soal.
function fetchSoalForGrading(PDO $db, int $idKuis): array
{
    $statement = $db->prepare(
        "SELECT id_soal, tipe_soal, kunci_jawaban, poin
         FROM soal_kuis WHERE id_kuis = :id_kuis ORDER BY urutan ASC"
    );
    $statement->execute([":id_kuis" => $idKuis]);

    $soalMap = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $soalMap[(int) $row['id_soal']] = $row;
    }

    return $soalMap;
}

// Menutup percobaan: menyimpan satu baris jawaban untuk SETIAP soal kuis, menilai pilihan ganda,
// lalu mengisi nilai pada percobaan_kuis. Harus dipanggil di dalam transaksi dengan baris
// percobaan yang sudah dikunci (SELECT ... FOR UPDATE) dan berstatus 'berlangsung'.
//
// Kebijakan nilai (satu aturan: jumlah poin mentah, tidak dikonversi):
// - Pilihan ganda benar = poin soal; salah atau kosong = 0, status penilaian 'selesai'.
// - Isian kosong = 0, status 'selesai' (tidak ada yang perlu dinilai).
// - Isian terisi = status 'menunggu' sampai dinilai pengajar.
// - nilai_total tetap NULL selama masih ada jawaban 'menunggu'; terisi saat semuanya selesai.
//
// $jawabanMap: [id_soal => teks jawaban atau null] yang SUDAH divalidasi pemanggil.
// Hitungan memakai sen (bilangan bulat) agar tidak ada selisih pembulatan desimal.
function finalizeAttempt(PDO $db, int $idPercobaan, array $soalMap, array $jawabanMap): array
{
    $insert = $db->prepare(
        "INSERT INTO jawaban_kuis (id_percobaan, id_soal, jawaban, nilai_diperoleh, status_penilaian)
         VALUES (:id_percobaan, :id_soal, :jawaban, :nilai_diperoleh, :status_penilaian)"
    );

    $totalOtomatisSen = 0;
    $adaMenunggu = false;

    foreach ($soalMap as $idSoal => $soal) {
        $jawaban = $jawabanMap[$idSoal] ?? null;
        $poinSen = (int) round((float) $soal['poin'] * 100);

        if ($soal['tipe_soal'] === SOAL_TIPE_PILIHAN_GANDA) {
            $nilaiSen = ($jawaban !== null && $jawaban === $soal['kunci_jawaban']) ? $poinSen : 0;
            $totalOtomatisSen += $nilaiSen;
            $nilaiDiperoleh = number_format($nilaiSen / 100, 2, '.', '');
            $statusPenilaian = PENILAIAN_STATUS_SELESAI;
        } elseif ($jawaban !== null) {
            $nilaiDiperoleh = null;
            $statusPenilaian = PENILAIAN_STATUS_MENUNGGU;
            $adaMenunggu = true;
        } else {
            $nilaiDiperoleh = '0.00';
            $statusPenilaian = PENILAIAN_STATUS_SELESAI;
        }

        $insert->execute([
            ":id_percobaan" => $idPercobaan,
            ":id_soal" => $idSoal,
            ":jawaban" => $jawaban,
            ":nilai_diperoleh" => $nilaiDiperoleh,
            ":status_penilaian" => $statusPenilaian,
        ]);
    }

    $nilaiOtomatis = number_format($totalOtomatisSen / 100, 2, '.', '');

    $update = $db->prepare(
        "UPDATE percobaan_kuis
         SET status = :status_selesai, waktu_selesai = NOW(),
             nilai_otomatis = :nilai_otomatis, nilai_manual = 0, nilai_total = :nilai_total
         WHERE id_percobaan = :id_percobaan AND status = :status_berlangsung"
    );
    $update->execute([
        ":status_selesai" => PERCOBAAN_STATUS_SELESAI,
        ":nilai_otomatis" => $nilaiOtomatis,
        ":nilai_total" => $adaMenunggu ? null : $nilaiOtomatis,
        ":id_percobaan" => $idPercobaan,
        ":status_berlangsung" => PERCOBAAN_STATUS_BERLANGSUNG,
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException("Percobaan gagal ditutup: id_percobaan " . $idPercobaan);
    }

    return [
        "menunggu_penilaian" => $adaMenunggu,
        "nilai_total" => $adaMenunggu ? null : (float) $nilaiOtomatis,
    ];
}

// Menutup percobaan yang sudah melewati batas waktu (penutupan "malas" tanpa cron):
// dipanggil saat ada permintaan setelah batas waktu. Aman dipanggil berulang;
// jika percobaan sudah ditutup atau belum habis, tidak ada yang diubah.
function closeExpiredAttempt(PDO $db, int $idPercobaan, int $idKuis): void
{
    $db->beginTransaction();

    $statement = $db->prepare("SELECT status FROM percobaan_kuis WHERE id_percobaan = :id_percobaan FOR UPDATE");
    $statement->execute([":id_percobaan" => $idPercobaan]);
    $status = $statement->fetchColumn();

    if ($status === PERCOBAAN_STATUS_BERLANGSUNG) {
        $timing = getAttemptTiming($db, $idPercobaan);

        if ($timing !== null && $timing['sudah_habis']) {
            finalizeAttempt($db, $idPercobaan, fetchSoalForGrading($db, $idKuis), []);
        }
    }

    $db->commit();
}

// Memvalidasi nilai manual: angka 0 sampai SOAL_MAX_POIN, paling banyak 2 desimal.
// Batas atas sesuai poin soal diperiksa terpisah oleh endpoint penilaian.
function parseNilai($value): ?float
{
    if ($value === null || $value === '' || is_bool($value) || is_array($value) || !is_numeric($value)) {
        return null;
    }

    $nilai = (float) $value;

    if ($nilai < 0 || $nilai > SOAL_MAX_POIN) {
        return null;
    }

    if (abs($nilai * 100 - round($nilai * 100)) > 0.0001) {
        return null;
    }

    return round($nilai, 2);
}

// Menghitung ulang nilai_manual dan nilai_total sebuah percobaan yang sudah dikumpulkan.
// Harus dipanggil di dalam transaksi dengan baris percobaan yang sudah dikunci.
// nilai_manual = jumlah nilai semua jawaban isian (isian kosong sudah bernilai 0).
// nilai_total  = nilai_otomatis + nilai_manual, HANYA jika tidak ada lagi jawaban 'menunggu';
//                selain itu tetap NULL sehingga tidak tampil sebagai nilai final.
function recalculateAttemptScore(PDO $db, int $idPercobaan): array
{
    $statement = $db->prepare(
        "SELECT p.nilai_otomatis,
                COALESCE(SUM(CASE WHEN s.tipe_soal = :tipe_isian THEN j.nilai_diperoleh END), 0) AS nilai_manual,
                COALESCE(SUM(CASE WHEN j.status_penilaian = :status_menunggu THEN 1 ELSE 0 END), 0) AS jumlah_menunggu
         FROM percobaan_kuis p
         LEFT JOIN jawaban_kuis j ON j.id_percobaan = p.id_percobaan
         LEFT JOIN soal_kuis s ON s.id_soal = j.id_soal
         WHERE p.id_percobaan = :id_percobaan
         GROUP BY p.id_percobaan, p.nilai_otomatis"
    );
    $statement->execute([
        ":tipe_isian" => SOAL_TIPE_ISIAN,
        ":status_menunggu" => PENILAIAN_STATUS_MENUNGGU,
        ":id_percobaan" => $idPercobaan,
    ]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new RuntimeException("Percobaan tidak ditemukan saat menghitung nilai: " . $idPercobaan);
    }

    $manualSen = (int) round((float) $row['nilai_manual'] * 100);
    $otomatisSen = (int) round((float) $row['nilai_otomatis'] * 100);
    $jumlahMenunggu = (int) $row['jumlah_menunggu'];

    $nilaiManual = number_format($manualSen / 100, 2, '.', '');
    $nilaiTotal = $jumlahMenunggu === 0
        ? number_format(($otomatisSen + $manualSen) / 100, 2, '.', '')
        : null;

    $update = $db->prepare(
        "UPDATE percobaan_kuis
         SET nilai_manual = :nilai_manual, nilai_total = :nilai_total
         WHERE id_percobaan = :id_percobaan AND status = :status_selesai"
    );
    $update->execute([
        ":nilai_manual" => $nilaiManual,
        ":nilai_total" => $nilaiTotal,
        ":id_percobaan" => $idPercobaan,
        ":status_selesai" => PERCOBAAN_STATUS_SELESAI,
    ]);

    return [
        "nilai_manual" => (float) $nilaiManual,
        "nilai_total" => $nilaiTotal === null ? null : (float) $nilaiTotal,
        "jumlah_menunggu" => $jumlahMenunggu,
    ];
}

// Menutup semua percobaan sebuah kuis yang sudah lewat batas waktu tetapi masih 'berlangsung'
// (siswa menutup halaman tanpa mengumpulkan). Dipanggil saat pengajar membuka hasil kuis.
function closeExpiredAttemptsForKuis(PDO $db, int $idKuis): void
{
    $statement = $db->prepare(
        "SELECT p.id_percobaan
         FROM percobaan_kuis p
         INNER JOIN kuis k ON k.id_kuis = p.id_kuis
         WHERE p.id_kuis = :id_kuis
           AND p.status = :status
           AND LEAST(
                   DATE_ADD(p.waktu_mulai, INTERVAL k.durasi_menit MINUTE),
                   COALESCE(k.waktu_selesai, DATE_ADD(p.waktu_mulai, INTERVAL k.durasi_menit MINUTE))
               ) <= NOW()"
    );
    $statement->execute([":id_kuis" => $idKuis, ":status" => PERCOBAAN_STATUS_BERLANGSUNG]);

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $idPercobaan) {
        closeExpiredAttempt($db, (int) $idPercobaan, $idKuis);
    }
}

// Menutup semua percobaan milik seorang siswa yang sudah lewat batas waktu tetapi masih
// 'berlangsung' (halaman ditutup tanpa mengumpulkan). Dipanggil saat siswa membuka daftar kuis.
function closeExpiredAttemptsForSiswa(PDO $db, int $idSiswa): void
{
    $statement = $db->prepare(
        "SELECT p.id_percobaan, p.id_kuis
         FROM percobaan_kuis p
         INNER JOIN kuis k ON k.id_kuis = p.id_kuis
         WHERE p.id_siswa = :id_siswa
           AND p.status = :status
           AND LEAST(
                   DATE_ADD(p.waktu_mulai, INTERVAL k.durasi_menit MINUTE),
                   COALESCE(k.waktu_selesai, DATE_ADD(p.waktu_mulai, INTERVAL k.durasi_menit MINUTE))
               ) <= NOW()"
    );
    $statement->execute([":id_siswa" => $idSiswa, ":status" => PERCOBAAN_STATUS_BERLANGSUNG]);

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        closeExpiredAttempt($db, (int) $row['id_percobaan'], (int) $row['id_kuis']);
    }
}

// Mengambil kuis yang boleh dilihat siswa (kelas yang diikutinya, status bukan draft),
// lengkap dengan ketersediaan, status pengerjaan, dan nilai.
// $idKuis diisi untuk mengambil satu kuis saja.
//
// ketersediaan: 'dibuka' | 'belum_dimulai' | 'berakhir' | 'ditutup' (dihitung dengan jam database)
// status_pengerjaan: 'belum_dikerjakan' | 'sedang_dikerjakan' | 'menunggu_penilaian' | 'selesai'
// nilai_total hanya berisi angka jika status_pengerjaan = 'selesai'; selain itu selalu null.
// Kunci jawaban, isi soal, dan nilai sementara tidak pernah ikut.
function fetchKuisForStudent(PDO $db, int $idSiswa, ?int $idKuis = null): array
{
    closeExpiredAttemptsForSiswa($db, $idSiswa);

    $query = "SELECT k.id_kuis, k.id_kelas, kl.nama_kelas, k.judul, k.deskripsi,
                     k.waktu_mulai, k.waktu_selesai, k.durasi_menit,
                     CASE
                         WHEN k.status <> :status_terbit THEN 'ditutup'
                         WHEN k.waktu_mulai IS NOT NULL AND k.waktu_mulai > NOW() THEN 'belum_dimulai'
                         WHEN k.waktu_selesai IS NOT NULL AND k.waktu_selesai <= NOW() THEN 'berakhir'
                         ELSE 'dibuka'
                     END AS ketersediaan,
                     (SELECT COUNT(*) FROM soal_kuis so WHERE so.id_kuis = k.id_kuis) AS jumlah_soal,
                     (SELECT COALESCE(SUM(so.poin), 0) FROM soal_kuis so WHERE so.id_kuis = k.id_kuis) AS total_poin,
                     p.id_percobaan, p.status AS status_percobaan, p.nilai_total
              FROM kuis k
              INNER JOIN kelas_siswa ks ON ks.id_kelas = k.id_kelas AND ks.id_siswa = :id_siswa
              INNER JOIN kelas kl ON kl.id_kelas = k.id_kelas
              LEFT JOIN percobaan_kuis p
                     ON p.id_kuis = k.id_kuis AND p.id_siswa = ks.id_siswa AND p.nomor_percobaan = 1
              WHERE k.status <> :status_draft";

    $params = [
        ":status_terbit" => KUIS_STATUS_TERBIT,
        ":id_siswa" => $idSiswa,
        ":status_draft" => KUIS_STATUS_DRAFT,
    ];

    if ($idKuis !== null) {
        $query .= " AND k.id_kuis = :id_kuis";
        $params[":id_kuis"] = $idKuis;
    }

    $query .= " ORDER BY COALESCE(k.waktu_mulai, k.waktu_dibuat) DESC, k.id_kuis DESC";

    $statement = $db->prepare($query);
    $statement->execute($params);

    $kuisList = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['id_percobaan'] === null) {
            $statusPengerjaan = 'belum_dikerjakan';
        } elseif ($row['status_percobaan'] === PERCOBAAN_STATUS_BERLANGSUNG) {
            $statusPengerjaan = 'sedang_dikerjakan';
        } elseif ($row['nilai_total'] === null) {
            $statusPengerjaan = 'menunggu_penilaian';
        } else {
            $statusPengerjaan = 'selesai';
        }

        $kuisList[] = [
            "id_kuis" => (int) $row['id_kuis'],
            "id_kelas" => (int) $row['id_kelas'],
            "nama_kelas" => $row['nama_kelas'],
            "judul" => $row['judul'],
            "deskripsi" => $row['deskripsi'],
            "waktu_mulai" => $row['waktu_mulai'],
            "waktu_selesai" => $row['waktu_selesai'],
            "durasi_menit" => (int) $row['durasi_menit'],
            "jumlah_soal" => (int) $row['jumlah_soal'],
            "total_poin" => (float) $row['total_poin'],
            "ketersediaan" => $row['ketersediaan'],
            "status_pengerjaan" => $statusPengerjaan,
            "nilai_total" => $statusPengerjaan === 'selesai' ? (float) $row['nilai_total'] : null,
        ];
    }

    return $kuisList;
}

// Batas nomor urutan soal. Dengan batas ini, urutan sementara (30000+) yang dipakai saat
// menyusun ulang soal dijamin tidak bertabrakan dengan urutan yang sudah ada.
const SOAL_MAX_URUTAN = 1000;

// Mengambil soal hanya jika kuisnya milik pengajar yang login (null jika tidak ada atau milik orang lain).
function findOwnedSoal(PDO $db, int $idSoal, int $idPengajar): ?array
{
    $statement = $db->prepare(
        "SELECT s.id_soal, s.id_kuis
         FROM soal_kuis s
         INNER JOIN kuis k ON k.id_kuis = s.id_kuis
         WHERE s.id_soal = :id_soal AND k.id_pengajar = :id_pengajar
         LIMIT 1"
    );
    $statement->execute([":id_soal" => $idSoal, ":id_pengajar" => $idPengajar]);
    $soal = $statement->fetch(PDO::FETCH_ASSOC);

    return $soal === false ? null : $soal;
}

// Aturan penguncian soal: soal hanya boleh diubah saat kuis 'draft' DAN belum ada siswa yang mulai.
// Mengembalikan pesan alasan jika terkunci, atau null jika boleh diubah.
function getSoalLockError(PDO $db, array $kuis): ?string
{
    if ($kuis['status'] !== KUIS_STATUS_DRAFT) {
        return "Soal hanya dapat diubah saat kuis berstatus draft.";
    }

    if (kuisHasAttempts($db, (int) $kuis['id_kuis'])) {
        return "Kuis sudah dikerjakan siswa, soal tidak dapat diubah.";
    }

    return null;
}

// Memvalidasi input satu soal (dipakai tambah dan ubah soal); langsung menjawab 400 jika ada yang salah.
// $defaultPoin: nilai bila 'poin' tidak dikirim; null berarti 'poin' wajib dikirim.
// Untuk soal isian, pilihan dan kunci dipaksa null.
function parseSoalInput(array $data, ?float $defaultPoin = null): array
{
    $tipeSoal = readString($data, 'tipe_soal');
    $teksSoal = readString($data, 'soal');
    $poin = array_key_exists('poin', $data) ? parsePoin($data['poin']) : $defaultPoin;

    if (!in_array($tipeSoal, [SOAL_TIPE_PILIHAN_GANDA, SOAL_TIPE_ISIAN], true)) {
        sendError("Tipe soal harus 'pilihan_ganda' atau 'isian'.", 400);
    }

    if ($teksSoal === null || mb_strlen($teksSoal) > 5000) {
        sendError("Teks soal wajib diisi dan maksimal 5000 karakter.", 400);
    }

    if ($poin === null) {
        sendError("Poin harus angka lebih dari 0, maksimal " . SOAL_MAX_POIN . ", dengan paling banyak 2 desimal.", 400);
    }

    $pilihan = ["a" => null, "b" => null, "c" => null, "d" => null];
    $kunciJawaban = null;

    if ($tipeSoal === SOAL_TIPE_PILIHAN_GANDA) {
        foreach (array_keys($pilihan) as $label) {
            $teksPilihan = readString($data, 'pilihan_' . $label);

            if ($teksPilihan === null || mb_strlen($teksPilihan) > 1000) {
                sendError("Pilihan " . strtoupper($label) . " wajib diisi dan maksimal 1000 karakter.", 400);
            }

            $pilihan[$label] = $teksPilihan;
        }

        $kunciJawaban = strtoupper(readString($data, 'kunci_jawaban') ?? '');

        if (!in_array($kunciJawaban, ['A', 'B', 'C', 'D'], true)) {
            sendError("Kunci jawaban harus A, B, C, atau D.", 400);
        }
    }

    return [
        "tipe_soal" => $tipeSoal,
        "soal" => $teksSoal,
        "poin" => $poin,
        "pilihan" => $pilihan,
        "kunci_jawaban" => $kunciJawaban,
    ];
}

// Memeriksa apakah kuis siap diterbitkan (atau dibuka kembali): durasi, jadwal, dan seluruh soal valid.
// Mengembalikan pesan kesalahan, atau null jika siap. Panggil di dalam transaksi.
function getPublishValidationError(PDO $db, array $kuis): ?string
{
    $idKuis = (int) $kuis['id_kuis'];
    $durasiMenit = (int) $kuis['durasi_menit'];

    if ($durasiMenit < 1 || $durasiMenit > KUIS_MAX_DURASI_MENIT) {
        return "Durasi kuis tidak valid.";
    }

    if ($kuis['waktu_mulai'] !== null && $kuis['waktu_selesai'] !== null
        && strcmp($kuis['waktu_selesai'], $kuis['waktu_mulai']) <= 0) {
        return "Waktu selesai harus lebih akhir dari waktu mulai.";
    }

    // Dibandingkan dengan jam database agar konsisten dengan pengecekan saat siswa memulai.
    if ($kuis['waktu_selesai'] !== null) {
        $statement = $db->prepare("SELECT (waktu_selesai > NOW()) AS masih_berlaku FROM kuis WHERE id_kuis = :id_kuis");
        $statement->execute([":id_kuis" => $idKuis]);

        if ((int) $statement->fetchColumn() !== 1) {
            return "Waktu selesai kuis sudah lewat. Ubah jadwal sebelum menerbitkan.";
        }
    }

    $statement = $db->prepare(
        "SELECT tipe_soal, pilihan_a, pilihan_b, pilihan_c, pilihan_d, kunci_jawaban, poin, urutan
         FROM soal_kuis WHERE id_kuis = :id_kuis ORDER BY urutan ASC"
    );
    $statement->execute([":id_kuis" => $idKuis]);
    $soalList = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (count($soalList) === 0) {
        return "Kuis belum memiliki soal.";
    }

    foreach ($soalList as $soal) {
        $nomor = (int) $soal['urutan'];

        if ((float) $soal['poin'] <= 0) {
            return "Soal nomor " . $nomor . " harus memiliki poin lebih dari 0.";
        }

        if ($soal['tipe_soal'] === SOAL_TIPE_ISIAN) {
            continue;
        }

        if ($soal['tipe_soal'] !== SOAL_TIPE_PILIHAN_GANDA) {
            return "Soal nomor " . $nomor . " memiliki tipe yang tidak valid.";
        }

        foreach (['pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d'] as $kolom) {
            if ($soal[$kolom] === null || trim($soal[$kolom]) === '') {
                return "Soal nomor " . $nomor . " belum memiliki empat pilihan jawaban.";
            }
        }

        if (!in_array($soal['kunci_jawaban'], ['A', 'B', 'C', 'D'], true)) {
            return "Soal nomor " . $nomor . " belum memiliki kunci jawaban yang valid.";
        }
    }

    return null;
}