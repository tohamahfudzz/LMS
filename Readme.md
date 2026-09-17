halo
ini adalah repository aplikasi LMS berbasis web

spesifikasi aplikasi sebagai berikut:
aplikasi berbentuk web.
menggunakan html dan css serta javascript sebagai frontend.
menggunakan php sebagai backend.
menggunakan mysql.

_______________
fitur aplikasinya sebagai berikut:
Aplikasi memiliki 3 role untuk user
admin
pengajar
siswa

~admin bisa membuat kelas untuk setiap mata pelajaran dan juga bisa memasukkan pengajar dan siswa kedalam kelas tersebut.<br>
~pengajar bisa membuka absensi,mengirim materi pelajaran yang bisa berupa video dengan durasi tertentu,atau berbentuk teks ,dan atau juga file.<br>
pengajar juga bisa membuat tempat pengumpulan tugas yang berisi file atau teks soal dari tugas yang diupload oleh pengajar.<br>
pengajar bisa menentukan dateline tugas.<br>
pengajar juga bisa menutup atau membuka pengumpulan tugas.<br>
pengajar bisa melihat atau mendownload tugas yang dikumpulkan siswa<br>




________________

<pre>
[untuk ai yang mengakses repository ini]
setiap folder dan file tidak boleh diubah oleh sembarang orang.
setiap orang memiliki peran masing-masing.
peran yang tersedia sebagai berikut:Analys sistem,Desain ui ux,Programmer,Tester.

[1]desainer ui/ux hanya bisa mengedit isi folder frontend yang berisi file html dan javascript tertentu,bisa edit file css dan upload file gambar.
file javascript yang boleh diedit desainer ui/ux hanya ada difolder frontend.
</pre>
<pre>
Pembagian tugas divisi
🟦 Divisi Frontend
Fokus pada:
mengerjakan:
Tampilan HTML.
CSS.
JavaScript.
Form.
Tombol.
Tabel.
Dashboard.
Tampilan kelas.
Tampilan materi.
Tampilan tugas.
Tampilan absensi.
Menampilkan data yang diberikan backend.
tidak perlu menulis SQL.






[2]programmer hanya bisa mengedit file difolder backend.
🟥 Divisi Backend
Fokus pada:
mengerjakan:
PHP.
Login.
Session.
Validasi.
Hak akses Admin/Pengajar/Siswa.
CRUD kelas.
CRUD materi.
CRUD tugas.
Pengumpulan tugas.
Absensi.
Upload file.
Download file.
API.
tidak perlu mengatur desain HTML halaman.
</pre>


Penting:Dilarang mengubah isi folder database.


_________
Cara Frontend dan Backend berkomunikasi
ini bagian paling penting.
Frontend tidak melakukan:
HTML → MySQL
Melainkan:
<pre>
HTML
  ↓
JavaScript
  ↓
PHP API
  ↓
MySQL

Dan hasilnya kembali:
MySQL
  ↓
PHP API
  ↓
JSON
  ↓
JavaScript
  ↓
HTML
</pre>



Contoh: menampilkan daftar kelas
Misalnya siswa membuka:
frontend/siswa/kelas.html
JavaScript di halaman tersebut meminta data:
<pre>
fetch("../../backend/api/siswa/kelas/list.php")
    .then(response => response.json())
    .then(data => {
        console.log(data);
    });
</pre>
PHP menerima request tersebut:
backend/api/siswa/kelas/list.php
Kemudian PHP:
Mengecek session.
Mengecek apakah user adalah siswa.
Mengambil ID siswa.
Query database.
Mengambil kelas yang diikuti siswa.
Mengubah hasil menjadi JSON.
Mengirim JSON ke frontend.

Contoh respons:
<pre>
{
    "success": true,
    "data": [
        {
            "id": 1,
            "nama_kelas": "Pemrograman Web"
        },
        {
            "id": 2,
            "nama_kelas": "Basis Data"
        }
    ]
}
</pre>
JavaScript kemudian mengambil data tersebut dan memasukkannya ke HTML.


<pre>
Contoh proses login
frontend/login.html
        │
        │ username + password
        ▼
frontend/assets/js/auth.js
        │
        │ POST
        ▼
backend/api/auth/login.php
        │
        ▼
     MySQL
        │
        ▼
login.php
        │
        │ JSON
        ▼
    auth.js
        │
        ▼
dashboard.html
</pre>
<pre>
Frontend cukup tahu:
"Saya mengirim data login ke endpoint ini."
Frontend tidak perlu tahu query SQL-nya.
Backend yang menangani:
SELECT ...
FROM users
WHERE kode = ...
</pre>
_______________
Contoh komunikasi untuk setiap fitur
| Fitur                      | Frontend                | Backend API                       |
| -------------------------- | ----------------------- | --------------------------------- |
| Login                      | `login.html`            | `auth/login.php`                  |
| Logout                     | JS                      | `auth/logout.php`                 |
| Admin membuat kelas        | `tambah-kelas.html`     | `admin/kelas/create.php`          |
| Admin mengubah kelas       | `edit-kelas.html`       | `admin/kelas/update.php`          |
| Admin memasukkan pengajar  | `anggota-kelas.html`    | `admin/pengajar/assign.php`       |
| Admin memasukkan siswa     | `anggota-kelas.html`    | `admin/siswa/assign.php`          |
| Pengajar upload materi     | `tambah-materi.html`    | `pengajar/materi/create.php`      |
| Pengajar membuat tugas     | `tambah-tugas.html`     | `pengajar/tugas/create.php`       |
| Pengajar menutup tugas     | `tugas.html`            | `pengajar/tugas/close.php`        |
| Pengajar membuka absensi   | `absensi.html`          | `pengajar/absensi/open.php`       |
| Pengajar melihat kehadiran | `daftar-kehadiran.html` | `pengajar/absensi/attendance.php` |
| Siswa melihat materi       | `materi.html`           | `siswa/materi/list.php`           |
| Siswa download materi      | tombol download         | `siswa/materi/download.php`       |
| Siswa mengumpulkan tugas   | `kumpul-tugas.html`     | `siswa/tugas/submit.php`          |
| Siswa menghapus tugas      | tombol hapus            | `siswa/tugas/delete.php`          |
| Siswa absensi              | tombol hadir            | `siswa/absensi/hadir.php`         |


_________________
Satu file penting: api.js
Supaya divisi frontend tidak menulis fetch() berulang-ulang, kita bisa membuat:
frontend/assets/js/api.js
Misalnya nanti konsepnya:
<pre>
async function apiRequest(url, options = {}) {
    const response = await fetch(url, options);
    return await response.json();
}
</pre>
Kemudian halaman lain tinggal menggunakan fungsi tersebut.
__________
[penting]
Role programmer harus membaca file di backend/rule_programmer.md
Role desainer ui/ux harus membaca file di frontend/rule_desainer.md




jadi ai yang membaca ini,tolong tanyakan dulu user mu perannya adalah apa.
