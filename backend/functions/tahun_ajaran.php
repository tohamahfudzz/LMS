<?php
/*
================================================================================
LOKASI FILE : backend/functions/tahun_ajaran.php
GUNA FILE   : Helper kecil untuk fitur tahun ajaran dan semester: konstanta nilai
              yang diizinkan, pembaca nilai dari input, dan validasi isian tahun
              ajaran. Dipakai oleh api/tahun_ajaran/*, api/admin/tahun_ajaran/*,
              dan endpoint kelas yang menyaring berdasarkan tahun ajaran/semester.
              Fungsi readTahunAjaranFields() memanggil sendError() (dari
              response.php) jika isian tidak valid, jadi pemanggil harus sudah
              memuat response.php.
================================================================================
*/

const TAHUN_AJARAN_NAMA_MAX = 20;
const TAHUN_AJARAN_STATUS_VALUES = ['aktif', 'nonaktif'];
const SEMESTER_VALUES = ['ganjil', 'genap'];

// Semester yang valid (huruf kecil), atau null jika kosong/tidak valid.
function normalizeSemester($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $semester = strtolower(trim($value));

    return in_array($semester, SEMESTER_VALUES, true) ? $semester : null;
}

// Bilangan bulat positif dari angka atau teks angka, atau null jika tidak valid.
function parseTahunAjaranId($value): ?int
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && ctype_digit($value)) {
        $number = (int) $value;
    } else {
        return null;
    }

    return $number > 0 ? $number : null;
}

// Tanggal harus berformat YYYY-MM-DD dan benar-benar ada di kalender.
function isValidDateYmd($value): bool
{
    if (!is_string($value)) {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

// Kode error MySQL dari sebuah exception (1062 = nilai ganda, 1451 = masih dipakai
// tabel lain). 0 jika bukan error database.
function getMysqlErrorCode(Throwable $error): int
{
    if ($error instanceof PDOException && isset($error->errorInfo[1])) {
        return (int) $error->errorInfo[1];
    }

    return 0;
}

// Membaca dan memvalidasi isian tahun ajaran dari body JSON. Jika ada yang tidak
// valid, langsung mengirim error 400 dan berhenti.
// Mengembalikan ['nama', 'mulai', 'selesai', 'status'].
function readTahunAjaranFields(array $body): array
{
    $nama = is_string($body['nama_tahun_ajaran'] ?? null) ? trim($body['nama_tahun_ajaran']) : '';

    if ($nama === '') {
        sendError('Nama tahun ajaran wajib diisi.', 400);
    }

    if (strlen($nama) > TAHUN_AJARAN_NAMA_MAX) {
        sendError('Nama tahun ajaran maksimal ' . TAHUN_AJARAN_NAMA_MAX . ' karakter.', 400);
    }

    $mulai = $body['tanggal_mulai'] ?? null;
    $selesai = $body['tanggal_selesai'] ?? null;

    if (!isValidDateYmd($mulai) || !isValidDateYmd($selesai)) {
        sendError('Tanggal mulai dan tanggal selesai wajib diisi dengan format yang benar.', 400);
    }

    if ($selesai <= $mulai) {
        sendError('Tanggal selesai harus setelah tanggal mulai.', 400);
    }

    $status = $body['status'] ?? 'nonaktif';

    if (!is_string($status) || !in_array($status, TAHUN_AJARAN_STATUS_VALUES, true)) {
        sendError('Status harus aktif atau nonaktif.', 400);
    }

    return [
        'nama' => $nama,
        'mulai' => $mulai,
        'selesai' => $selesai,
        'status' => $status,
    ];
}