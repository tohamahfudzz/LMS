<?php

// POST /api/kuis/update.php
// Role: pengajar. Mengubah data kuis miliknya. Hanya field yang dikirim yang diubah.
// Body JSON: id_kuis, dan salah satu atau lebih dari:
//   judul, deskripsi (kosong = hapus), waktu_mulai (kosong = hapus), waktu_selesai (kosong = hapus), durasi_menit.
// Kelas kuis tidak dapat diubah (buat kuis baru bila salah kelas).
// Setelah ada siswa yang mengerjakan, hanya judul dan deskripsi yang boleh berubah; jadwal dan durasi
// dikunci karena memengaruhi batas waktu percobaan yang sudah ada.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/response.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/kuis.php';

initApi('POST');

$session = requireRole('pengajar');
$idPengajar = (int) $session['id'];

$data = readJsonBody();

$idKuis = parsePositiveInt($data['id_kuis'] ?? null);

if ($idKuis === null) {
    sendError("ID kuis tidak valid.", 400);
}

// Nama kolom di bawah ditulis tetap di kode (bukan dari input), hanya nilainya yang dari pengguna.
$fields = [];

if (array_key_exists('judul', $data)) {
    $judul = readString($data, 'judul');

    if ($judul === null || mb_strlen($judul) > 200) {
        sendError("Judul wajib diisi dan maksimal 200 karakter.", 400);
    }

    $fields['judul'] = $judul;
}

if (array_key_exists('deskripsi', $data)) {
    if ($data['deskripsi'] !== null && !is_string($data['deskripsi'])) {
        sendError("Deskripsi harus berupa teks.", 400);
    }

    $deskripsi = readString($data, 'deskripsi');

    if ($deskripsi !== null && mb_strlen($deskripsi) > 5000) {
        sendError("Deskripsi maksimal 5000 karakter.", 400);
    }

    $fields['deskripsi'] = $deskripsi;
}

if (array_key_exists('durasi_menit', $data)) {
    $durasiMenit = parsePositiveInt($data['durasi_menit']);

    if ($durasiMenit === null || $durasiMenit > KUIS_MAX_DURASI_MENIT) {
        sendError("Durasi wajib berupa angka 1 sampai " . KUIS_MAX_DURASI_MENIT . " menit.", 400);
    }

    $fields['durasi_menit'] = $durasiMenit;
}

if (array_key_exists('waktu_mulai', $data)) {
    $fields['waktu_mulai'] = readOptionalDateTime($data, 'waktu_mulai', 'waktu mulai');
}

if (array_key_exists('waktu_selesai', $data)) {
    $fields['waktu_selesai'] = readOptionalDateTime($data, 'waktu_selesai', 'waktu selesai');
}

if (count($fields) === 0) {
    sendError("Tidak ada data yang diubah.", 400);
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    $kuis = findOwnedKuis($db, $idKuis, $idPengajar, true);

    if ($kuis === null) {
        rollbackAndSendError($db, "Kuis tidak ditemukan.", 404);
    }

    // Jadwal akhir (gabungan data lama dan baru) harus masuk akal.
    $mulaiAkhir = array_key_exists('waktu_mulai', $fields) ? $fields['waktu_mulai'] : $kuis['waktu_mulai'];
    $selesaiAkhir = array_key_exists('waktu_selesai', $fields) ? $fields['waktu_selesai'] : $kuis['waktu_selesai'];

    if ($mulaiAkhir !== null && $selesaiAkhir !== null && strcmp($selesaiAkhir, $mulaiAkhir) <= 0) {
        rollbackAndSendError($db, "Waktu selesai harus lebih akhir dari waktu mulai.", 400);
    }

    // Penguncian: setelah ada percobaan, jadwal dan durasi tidak boleh berubah nilainya.
    if (kuisHasAttempts($db, $idKuis)) {
        foreach (['durasi_menit', 'waktu_mulai', 'waktu_selesai'] as $kolom) {
            if (array_key_exists($kolom, $fields) && (string) $fields[$kolom] !== (string) $kuis[$kolom]) {
                rollbackAndSendError($db, "Jadwal dan durasi tidak dapat diubah setelah ada siswa yang mengerjakan.", 409);
            }
        }
    }

    $setClauses = [];
    $params = [":id_kuis" => $idKuis, ":id_pengajar" => $idPengajar];

    foreach ($fields as $kolom => $nilai) {
        $setClauses[] = $kolom . " = :" . $kolom;
        $params[":" . $kolom] = $nilai;
    }

    $statement = $db->prepare(
        "UPDATE kuis SET " . implode(", ", $setClauses) . " WHERE id_kuis = :id_kuis AND id_pengajar = :id_pengajar"
    );
    $statement->execute($params);

    // Kuis yang sudah terbit tidak boleh diberi waktu selesai yang sudah lewat.
    if ($kuis['status'] === KUIS_STATUS_TERBIT && array_key_exists('waktu_selesai', $fields) && $selesaiAkhir !== null) {
        $statement = $db->prepare("SELECT (waktu_selesai > NOW()) FROM kuis WHERE id_kuis = :id_kuis");
        $statement->execute([":id_kuis" => $idKuis]);

        if ((int) $statement->fetchColumn() !== 1) {
            rollbackAndSendError($db, "Kuis yang sudah terbit harus memiliki waktu selesai di masa depan.", 400);
        }
    }

    $db->commit();

    sendSuccess("Kuis berhasil diperbarui.", ["id_kuis" => $idKuis, "diubah" => array_keys($fields)]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    sendServerError($error, 'kuis/update');
}