
# RULE PROGRAMMER
## Learning Management System (LMS)

Dokumen ini berisi aturan dan standar penulisan kode untuk divisi Programmer pada proyek LMS.

Tujuan utama:
- Menjaga kualitas dan kerapian kode backend.
- Membuat kode mudah dibaca dan dipahami oleh seluruh anggota tim.
- Menjaga keamanan aplikasi dan data pengguna.
- Memudahkan pemeliharaan serta pengembangan fitur LMS.
- Menjaga kesesuaian kode backend dengan struktur database dan frontend.

---

## 1. RUANG LINGKUP PEKERJAAN

Programmer bertanggung jawab atas:

1. Pengembangan backend menggunakan PHP.
2. Pengelolaan komunikasi antara frontend dan database.
3. Pembuatan dan pemeliharaan PHP API.
4. Pengolahan data menggunakan MySQL sesuai struktur yang tersedia.
5. Validasi input dan penerapan keamanan backend.
6. Pengembangan logika bisnis LMS.
7. Penanganan autentikasi dan hak akses pengguna.
8. Pengelolaan upload dan download file sesuai kebutuhan fitur.
9. Pemeliharaan serta pengembangan kode backend.

### Batasan Programmer

Programmer tidak diperbolehkan:

- Mengubah struktur database.
- Menambah, menghapus, atau mengubah tabel dan kolom.
- Mengubah file database yang dilindungi.
- Mengubah desain frontend tanpa koordinasi dengan divisi Desainer UI/UX.
- Mengabaikan aturan yang telah ditetapkan dalam README dan dokumen proyek.

Jika kebutuhan fitur memerlukan perubahan database, programmer wajib mengomunikasikannya kepada pihak yang berwenang sebelum melakukan perubahan.

---

## 2. TEKNOLOGI YANG DIGUNAKAN

Teknologi backend yang digunakan:

- PHP.
- MySQL.
- XAMPP sebagai lingkungan pengembangan lokal.
- HTML, CSS, dan JavaScript sebagai frontend.
- JSON sebagai format pertukaran data antara frontend dan backend.

Programmer wajib mengikuti teknologi dan struktur folder yang telah disepakati dalam proyek.

---

## 3. STANDAR UMUM PENULISAN KODE

### 3.1 Keterbacaan

Kode harus ditulis agar dapat dipahami oleh anggota tim lain.

Ketentuan:
- Gunakan indentasi 4 spasi.
- Gunakan nama variabel dan fungsi yang jelas.
- Hindari kode yang terlalu panjang dan sulit dipahami.
- Hindari penamaan satu huruf jika tidak diperlukan.
- Gunakan struktur kode yang konsisten.
- Jangan menulis kode yang rumit jika solusi sederhana sudah cukup.

Contoh yang benar:

```php
<?php

$studentName = "Mahfudzz";
$classId = 1;
```

Contoh yang tidak dianjurkan:

```php
<?php

$x = "Mahfudzz";
$a = 1;
```

### 3.2 Konsistensi

Seluruh kode backend harus mengikuti pola penulisan yang konsisten.

Ketentuan:
- Gunakan gaya indentasi yang sama.
- Gunakan penamaan file, class, method, dan variabel yang konsisten.
- Gunakan format response API yang seragam.
- Ikuti struktur folder yang telah disepakati.
- Jangan mengubah pola penulisan tanpa alasan yang jelas.

---

## 4. PENGGUNAAN CLASS DAN OOP

### 4.1 Prinsip Umum

Penggunaan class dan OOP dianjurkan untuk kode yang memiliki tanggung jawab jelas dan berpotensi digunakan kembali.

Penggunaan OOP bukan kewajiban mutlak.

Programmer tetap diperbolehkan menggunakan fungsi biasa untuk kebutuhan sederhana apabila penggunaan class justru membuat kode lebih rumit atau sulit dipahami.

### 4.2 Contoh Pembagian Class

Class dapat digunakan untuk mengelompokkan tanggung jawab backend.

Contoh:

```text
Database
Auth
Kelas
Materi
Tugas
Absensi
```

Setiap class harus memiliki tanggung jawab yang jelas.

### 4.3 Aturan Penulisan Class

- Gunakan nama class yang menjelaskan fungsinya.
- Gunakan PascalCase untuk nama class.
- Gunakan camelCase untuk nama method dan variabel.
- Hindari class yang menangani terlalu banyak tanggung jawab.
- Gunakan visibility `public`, `private`, atau `protected` sesuai kebutuhan.
- Hindari membuat class hanya demi memenuhi aturan OOP.
- Gunakan constructor untuk menerima dependency jika diperlukan.

Contoh:

```php
<?php

class Kelas
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $query = "
            SELECT id, nama_kelas
            FROM kelas
            ORDER BY nama_kelas ASC
        ";

        $statement = $this->db->prepare($query);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

Contoh tersebut hanya menunjukkan pola penggunaan class. Struktur final harus mengikuti arsitektur backend yang disepakati.

---

## 5. PEMISAHAN TANGGUNG JAWAB KODE

Kode backend harus dipisahkan berdasarkan tanggung jawabnya.

Secara umum:

```text
backend/
├── api/
│   └── endpoint.php
│
├── config/
│   └── database.php
│
├── models/
│   └── Kelas.php
│
├── services/
│   └── KelasService.php
│
└── helpers/
    └── response.php
```

Struktur di atas merupakan contoh pola. Programmer wajib mengikuti struktur folder aktual yang telah disepakati dalam repository.

### Ketentuan

- File API menangani request dan response.
- Class atau model menangani akses data sesuai tanggung jawabnya.
- Service digunakan jika terdapat logika bisnis yang perlu dipisahkan.
- Config berisi konfigurasi aplikasi.
- Helper berisi fungsi umum yang dapat digunakan kembali.
- Jangan mencampur seluruh logika backend dalam satu file jika dapat dipisahkan dengan jelas.

---

## 6. STANDAR DATABASE

### 6.1 Larangan Perubahan Database

Programmer tidak diperbolehkan mengubah struktur database proyek.

Dilarang:
- `CREATE TABLE`.
- `ALTER TABLE`.
- `DROP TABLE`.
- Menambah kolom.
- Menghapus kolom.
- Mengubah tipe data kolom.
- Mengubah relasi database.

Kode backend harus menyesuaikan diri dengan struktur database yang tersedia.

### 6.2 Penggunaan Query

- Gunakan prepared statements untuk query yang menerima input pengguna.
- Hindari menyusun query SQL menggunakan penggabungan string input pengguna.
- Gunakan nama kolom dan tabel sesuai database.
- Jangan mengambil data yang tidak diperlukan.
- Pastikan operasi database memiliki penanganan error yang sesuai.

Contoh:

```php
<?php

$query = "
    SELECT id, nama_kelas
    FROM kelas
    WHERE id = :id
";

$statement = $db->prepare($query);
$statement->execute([
    ":id" => $classId
]);

$data = $statement->fetch(PDO::FETCH_ASSOC);
```

---

## 7. STANDAR PHP API

### 7.1 Format Response

API harus menggunakan format response JSON yang konsisten.

Contoh response sukses:

```json
{
    "success": true,
    "message": "Data berhasil diambil.",
    "data": []
}
```

Contoh response gagal:

```json
{
    "success": false,
    "message": "Data tidak ditemukan.",
    "data": null
}
```

Format final dapat disesuaikan dengan kesepakatan backend dan frontend.

### 7.2 Ketentuan API

- Gunakan HTTP method sesuai kebutuhan operasi.
- Validasi input sebelum memproses data.
- Jangan mengembalikan data sensitif yang tidak diperlukan.
- Jangan menampilkan error database mentah kepada pengguna.
- Pastikan response memiliki format yang dapat dipahami frontend.
- Gunakan nama endpoint yang jelas dan konsisten.

---

## 8. KEAMANAN BACKEND

Keamanan merupakan tanggung jawab wajib programmer.

### 8.1 Validasi Input

Semua input pengguna harus divalidasi.

Contoh input:
- ID kelas.
- Nama kelas.
- ID tugas.
- Tanggal deadline.
- File upload.
- Data login.

Ketentuan:
- Periksa tipe data.
- Periksa format data.
- Periksa nilai kosong.
- Periksa batas panjang input.
- Jangan mempercayai input dari frontend.

### 8.2 Autentikasi dan Otorisasi

Setiap fitur harus memeriksa apakah pengguna memiliki hak akses yang sesuai.

Contoh:
- Admin mengelola kelas.
- Pengajar mengelola materi dan tugas sesuai kewenangan.
- Siswa mengakses materi dan mengirim tugas sesuai kelasnya.

Programmer wajib memastikan bahwa pemeriksaan hak akses dilakukan di backend, bukan hanya melalui tampilan frontend.

### 8.3 Password dan Session

- Password harus disimpan menggunakan hashing yang aman.
- Jangan menyimpan password dalam bentuk plaintext.
- Gunakan session secara aman.
- Jangan menampilkan informasi autentikasi yang sensitif.
- Pastikan pengguna tidak dapat mengakses data milik pengguna lain tanpa izin.

---

## 9. STANDAR UPLOAD DAN DOWNLOAD FILE

Fitur upload dan download harus memiliki pemeriksaan keamanan.

Ketentuan:
- Validasi ukuran file.
- Validasi tipe file.
- Validasi ekstensi sesuai kebutuhan fitur.
- Jangan mempercayai nama file dari pengguna.
- Gunakan nama file penyimpanan yang aman.
- Pastikan pengguna memiliki hak akses terhadap file.
- Jangan mengizinkan file berbahaya dijalankan sebagai kode backend.
- Pastikan file tidak dapat diakses oleh pengguna yang tidak berwenang.

---

## 10. STANDAR ERROR HANDLING

Setiap operasi yang dapat gagal harus memiliki penanganan error.

Contoh:

```php
<?php

try {
    $statement = $db->prepare($query);
    $statement->execute($params);

    echo json_encode([
        "success" => true,
        "data" => $statement->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ]);
}
```

Ketentuan:
- Jangan menampilkan detail error database kepada pengguna.
- Gunakan logging untuk membantu debugging.
- Gunakan HTTP status code yang sesuai.
- Pastikan API tetap mengembalikan response yang dapat diproses frontend.

---

## 11. STANDAR KOMENTAR

Komentar digunakan untuk menjelaskan logika atau alasan yang tidak langsung terlihat.

Tidak dianjurkan:

```php
<?php

// Menambahkan 1 ke i
$i++;
```

Dianjurkan:

```php
<?php

// Menghitung halaman berikutnya setelah data selesai diproses.
$currentPage++;
```

Ketentuan:
- Gunakan komentar yang singkat dan relevan.
- Jangan memberikan komentar pada setiap baris jika tidak diperlukan.
- Hapus komentar yang sudah tidak sesuai.
- Jangan menggunakan komentar untuk menyembunyikan kode yang tidak digunakan.

---

## 12. STANDAR PEMELIHARAAN KODE

Programmer wajib menjaga kode agar mudah dikembangkan.

Ketentuan:
- Hindari kode duplikat.
- Jangan meninggalkan kode percobaan yang tidak diperlukan.
- Jangan menggunakan solusi rumit tanpa alasan.
- Jangan mengubah kode anggota lain tanpa memahami dampaknya.
- Gunakan fungsi atau class yang dapat digunakan kembali jika sesuai.
- Pastikan perubahan tidak merusak fitur yang sudah ada.
- Komunikasikan perubahan besar kepada anggota divisi terkait.

---

## 13. KOMUNIKASI DENGAN DIVISI DESAINER

Programmer wajib menjaga komunikasi dengan divisi Desainer UI/UX.

Ketentuan:
- Dokumentasikan endpoint API yang dibuat.
- Jelaskan method, parameter, dan format response.
- Jangan mengubah format response tanpa koordinasi.
- Jangan mengubah struktur frontend tanpa koordinasi.
- Pastikan API dapat digunakan oleh frontend sesuai kesepakatan.
- Jika terjadi perubahan kebutuhan data, komunikasikan kepada divisi terkait.

---

## 14. CHECKLIST SEBELUM KODE DIANGGAP SELESAI

Sebelum mengirimkan hasil pekerjaan, programmer wajib memeriksa:

- [ ] Kode PHP memiliki struktur yang rapi.
- [ ] Indentasi konsisten.
- [ ] Nama file, class, method, dan variabel jelas.
- [ ] Penggunaan class/OOP dipertimbangkan jika sesuai.
- [ ] Query menggunakan prepared statements jika menerima input pengguna.
- [ ] Tidak ada perubahan struktur database.
- [ ] Input pengguna telah divalidasi.
- [ ] Hak akses pengguna telah diperiksa.
- [ ] Error ditangani dengan aman.
- [ ] Response API memiliki format yang konsisten.
- [ ] Tidak ada password atau data sensitif yang ditulis sembarangan.
- [ ] Upload dan download file memiliki validasi yang sesuai.
- [ ] Tidak ada kode duplikat yang tidak diperlukan.
- [ ] Perubahan tidak merusak fitur yang sudah ada.
- [ ] Dokumentasi API diperbarui jika diperlukan.
- [ ] Kode dapat dipahami oleh anggota tim lain.

---

## 15. ATURAN PENUTUP

Setiap kode yang dibuat oleh divisi Programmer harus mengutamakan:

1. Keamanan.
2. Keterbacaan.
3. Konsistensi.
4. Pemisahan tanggung jawab.
5. Kemudahan pemeliharaan.
6. Kesesuaian dengan struktur database.
7. Komunikasi yang baik dengan frontend.

Penggunaan class dan OOP dianjurkan apabila membantu menghasilkan kode yang lebih rapi, mudah digunakan kembali, dan mudah dikembangkan.

Kode yang berfungsi bukan berarti kode sudah berkualitas. Setiap programmer wajib berusaha menghasilkan kode yang aman, terstruktur, dan dapat dipahami oleh seluruh anggota tim.
