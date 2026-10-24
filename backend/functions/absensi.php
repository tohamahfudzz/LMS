<?php
/*
================================================================================
LOKASI FILE : backend/functions/absensi.php
GUNA FILE   : Helper absensi.

              closeExpiredAbsensi($db, $idKelas = null)
                Menutup sesi absensi yang sudah melewati waktu_tutup: status diubah
                dari 'terbuka' menjadi 'tertutup'. Sesi tanpa waktu_tutup (sesi lama)
                tidak disentuh dan baru tertutup bila pengajar menutupnya manual.
                Jika $idKelas diisi, hanya sesi kelas itu yang diperiksa.

              Kenapa dipanggil di banyak endpoint: PHP tidak bisa berjalan sendiri
              pada jam tertentu (tanpa cron). Karena itu setiap endpoint absensi
              memanggil fungsi ini lebih dulu, sehingga status sesi selalu sesuai
              waktu sekarang. Pola yang sama dipakai kuis (functions/kuis.php).
              Dipakai oleh: absensi/open.php, absensi/close.php, kehadiran/hadir.php,
              pengajar/absensi/list.php, pengajar/absensi/report.php,
              siswa/absensi/list.php.
================================================================================
*/

function closeExpiredAbsensi(PDO $db, ?int $idKelas = null): void
{
    $sql = "UPDATE absensi
               SET status = 'tertutup'
             WHERE status = 'terbuka'
               AND waktu_tutup IS NOT NULL
               AND waktu_tutup <= NOW()";
    $params = [];

    if ($idKelas !== null) {
        $sql .= ' AND id_kelas = :id_kelas';
        $params[':id_kelas'] = $idKelas;
    }

    $db->prepare($sql)->execute($params);
}